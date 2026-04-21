<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

$ward_id = $_GET['ward'] ?? 'All';

/* ================= TRANSFER ================= */
if(isset($_POST['transfer'])){
    $admission_id = $_POST['admission_id'];
    $new_bed      = $_POST['new_bed'];

    try {
        // Get current (old) bed
        $stmt = $conn->prepare("
            SELECT BED_ID 
            FROM SYARMIMI.ADMISSION 
            WHERE ADMISSION_ID=:id AND DISCHARGE_DATE IS NULL
        ");
        $stmt->execute([':id'=>$admission_id]);
        $old = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($old) {
            // Move to new bed
            $conn->prepare("
                UPDATE SYARMIMI.ADMISSION
                SET BED_ID = :new_bed
                WHERE ADMISSION_ID = :id
            ")->execute([
                ':new_bed'=>$new_bed,
                ':id'=>$admission_id
            ]);

            // Free old bed
            $conn->prepare("
                UPDATE SYARMIMI.BED 
                SET STATUS='Available' 
                WHERE BED_ID=:id
            ")->execute([':id'=>$old['BED_ID']]);

            // Occupy new bed
            $conn->prepare("
                UPDATE SYARMIMI.BED 
                SET STATUS='Occupied' 
                WHERE BED_ID=:id
            ")->execute([':id'=>$new_bed]);

            echo "<script>alert('Patient Transferred'); window.location='ward.php';</script>";
        }

    } catch(PDOException $e){
        die("Transfer Error: ".$e->getMessage());
    }
}

/* ================= WARD SUMMARY ================= */
$wardSummary = $conn->query("
SELECT 
    w.WARD_ID,
    w.WARD_NAME,
    COUNT(b.BED_ID) AS TOTAL_BED,
    SUM(CASE WHEN b.STATUS='Occupied' THEN 1 ELSE 0 END) AS OCCUPIED
FROM SYARMIMI.WARD w
LEFT JOIN SYARMIMI.BED b ON w.WARD_ID = b.WARD_ID
GROUP BY w.WARD_ID, w.WARD_NAME
ORDER BY w.WARD_ID
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= BED + PATIENT ================= */
$sql = "
SELECT 
    b.BED_ID,
    b.BED_NUMBER,
    b.STATUS,
    w.WARD_NAME,
    a.ADMISSION_ID,
    p.NAME,
    p.AGE,
    p.GENDER,

    (SELECT COUNT(*) 
     FROM SYARMIMI.ADMISSION a2 
     WHERE a2.BED_ID = b.BED_ID) AS TOTAL_HISTORY

FROM SYARMIMI.BED b
JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID

LEFT JOIN SYARMIMI.ADMISSION a 
ON b.BED_ID = a.BED_ID 
AND a.DISCHARGE_DATE IS NULL

LEFT JOIN SYARMIMI.PATIENT p 
ON a.PATIENT_ID = p.PATIENT_ID

WHERE 1=1
";

if($ward_id != 'All'){
    $sql .= " AND b.WARD_ID = '$ward_id'";
}

$sql .= " ORDER BY b.BED_ID";

$result = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ================= WARD LIST ================= */
$wards = $conn->query("SELECT * FROM SYARMIMI.WARD ORDER BY WARD_ID")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Ward Layout (Admin)</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }

.ward-box {
    background:white;
    padding:25px;
    border-radius:18px;
    box-shadow:0 6px 16px rgba(0,0,0,0.08);
}

.summary-card {
    border-radius:14px;
}

.bed-grid {
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:20px;
}

.bed-card {
    min-height:170px;
    border-radius:16px;
    padding:15px;
    text-align:center;
    transition:0.2s;
}

.bed-card:hover {
    transform:scale(1.02);
}

.available { background:#d1fae5; }
.occupied { background:#fee2e2; }

.details {
    margin-top:10px;
    font-size:14px;
}
</style>

</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<h3>🏥 Ward Management (Admin)</h3>

<!-- ================= WARD SUMMARY ================= -->
<div class="row mb-4">
<?php foreach($wardSummary as $w): 
$available = $w['TOTAL_BED'] - $w['OCCUPIED'];
?>
<div class="col-md-3">
<div class="card summary-card p-3 text-center shadow-sm">

<h6><?= $w['WARD_NAME'] ?></h6>

<p>
Total: <?= $w['TOTAL_BED'] ?><br>
Occupied: <?= $w['OCCUPIED'] ?><br>
Available: <?= $available ?>
</p>

<?php if($available == 0): ?>
<span class="badge bg-danger">FULL</span>
<?php else: ?>
<span class="badge bg-success">AVAILABLE</span>
<?php endif; ?>

</div>
</div>
<?php endforeach; ?>
</div>

<!-- ================= FILTER ================= -->
<form method="GET" class="mb-3">
<select name="ward" onchange="this.form.submit()" class="form-control w-25">
<option value="All">All Ward</option>
<?php foreach($wards as $w): ?>
<option value="<?= $w['WARD_ID'] ?>" <?= ($ward_id == $w['WARD_ID']) ? 'selected' : '' ?>>
<?= $w['WARD_NAME'] ?>
</option>
<?php endforeach; ?>
</select>
</form>

<!-- ================= BED GRID ================= -->
<div class="ward-box">

<div class="bed-grid">

<?php foreach($result as $row):

$statusClass = ($row['STATUS'] == 'Available') ? 'available' : 'occupied';
?>

<div class="bed-card <?= $statusClass ?>">

<strong><?= $row['BED_NUMBER'] ?></strong><br>
<small><?= $row['STATUS'] ?></small>

<div class="details">

<?php if($row['STATUS'] == 'Available'): ?>
No patient
<?php else: ?>

<strong><?= $row['NAME'] ?></strong><br>
Age: <?= $row['AGE'] ?><br>
Gender: <?= $row['GENDER'] ?>

<hr>

<!-- TRANSFER FORM -->
<form method="POST">
<input type="hidden" name="admission_id" value="<?= $row['ADMISSION_ID'] ?>">

<select name="new_bed" class="form-control form-control-sm mb-1" required>
<option value="">Move Bed</option>

<?php
$beds = $conn->query("
SELECT BED_ID, BED_NUMBER 
FROM SYARMIMI.BED 
WHERE STATUS='Available'
");
while($b = $beds->fetch(PDO::FETCH_ASSOC)){
echo "<option value='{$b['BED_ID']}'>{$b['BED_NUMBER']}</option>";
}
?>
</select>

<button class="btn btn-warning btn-sm w-100" name="transfer">
Transfer
</button>

</form>

<?php endif; ?>

<hr>
🛏 History: <?= $row['TOTAL_HISTORY'] ?>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>
</div>

</body>
</html>