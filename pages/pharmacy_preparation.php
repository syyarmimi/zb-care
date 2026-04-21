<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   HANDLE PREPARE ACTION
========================= */
if (isset($_GET['prepare'])) {

    $medOrderId = $_GET['prepare'];
    $staffId = $_SESSION['user_id'];

    try {

        $check = $conn->prepare("
            SELECT COUNT(*) FROM SYARMIMI.PHARMACY_PREPARATION
            WHERE MEDORDER_ID = :id
        ");
        $check->execute([':id' => $medOrderId]);

        if ($check->fetchColumn() > 0) {
            echo "<script>alert('Already Prepared'); window.location='pharmacy_preparation.php';</script>";
            exit();
        }

        $sql = "INSERT INTO SYARMIMI.PHARMACY_PREPARATION
                (PREP_ID, STATUS, PREPARED_TIME, MEDORDER_ID, STAFF_ID)
                VALUES (SYARMIMI.PREPARATION_SEQ.NEXTVAL, 'Prepared', SYSDATE, :orderId, :staff)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':orderId' => $medOrderId,
            ':staff'   => $staffId
        ]);

        echo "<script>alert('Medication Prepared Successfully'); window.location='pharmacy_preparation.php';</script>";

    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}

/* =========================
   COUNT PENDING
========================= */
$pendingCount = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEDICATION_ORDER mo
LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp 
ON mo.MEDORDER_ID = pp.MEDORDER_ID
WHERE pp.MEDORDER_ID IS NULL
")->fetchColumn();

/* =========================
   FETCH MEDICATION ORDERS (UPDATED)
========================= */
$sql = "SELECT 
        mo.MEDORDER_ID,
        p.NAME,
        m.MEDICATION_NAME,
        mo.DOSAGE,
        mo.FREQUENCY,
        w.WARD_NAME,
        b.BED_NUMBER,

        CASE 
            WHEN pp.MEDORDER_ID IS NOT NULL THEN 'Prepared'
            ELSE 'Pending'
        END AS STATUS

FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID
JOIN SYARMIMI.BED b ON a.BED_ID = b.BED_ID
JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID

LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp 
ON mo.MEDORDER_ID = pp.MEDORDER_ID

ORDER BY mo.MEDORDER_ID DESC";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Prepare Medication</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }
.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_pharma.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-3">💊 Prepare Medication</h4>

<!-- 🔔 NOTIFICATION -->
<?php if($pendingCount > 0): ?>
<div class="alert alert-warning">
🔔 <?= $pendingCount ?> medication(s) pending preparation
</div>
<?php endif; ?>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Location</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>
<td><?= $row['MEDORDER_ID'] ?></td>

<td><?= $row['NAME'] ?></td>

<td>
<?= $row['WARD_NAME'] ?><br>
<small class="text-muted">Bed <?= $row['BED_NUMBER'] ?></small>
</td>

<td><?= $row['MEDICATION_NAME'] ?></td>
<td><?= $row['DOSAGE'] ?></td>
<td><?= $row['FREQUENCY'] ?></td>

<td>
<?php if($row['STATUS'] == 'Prepared'): ?>
<span class="badge bg-success">Prepared</span>
<?php else: ?>
<span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td>
<?php if($row['STATUS'] == 'Pending'): ?>
<a href="?prepare=<?= $row['MEDORDER_ID'] ?>" 
class="btn btn-success btn-sm"
onclick="return confirm('Prepare this medication?')">
Prepare
</a>
<?php else: ?>
<button class="btn btn-secondary btn-sm" disabled>Done</button>
<?php endif; ?>
</td>

</tr>

<?php } ?>

</tbody>
</table>

</div>

</div>
</div>

</body>
</html>