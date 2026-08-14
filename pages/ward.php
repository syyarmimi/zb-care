<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

$swal = '';

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

            echo "
<script>
Swal.fire({
    icon: 'success',
    title: 'Transferred',
    text: 'Patient transferred successfully',
    confirmButtonColor: '#0d6efd'
}).then(() => {
    window.location='ward.php';
});
</script>
";
exit();
        }

    } catch(PDOException $e){
        die("Transfer Error: ".$e->getMessage());
    }
}

/* ================= ADD BED ================= */
if(isset($_POST['add_bed'])){
    $ward_id = $_POST['ward_id'];
    $bed_no  = trim($_POST['bed_number']);

    try{
        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.BED
            WHERE BED_NUMBER = ?
            AND WARD_ID = ?
        ");
        $check->execute([$bed_no, $ward_id]);

        if($check->fetchColumn() > 0){
            echo "
<script>
Swal.fire({
    icon: 'warning',
    title: 'Duplicate Bed',
    text: 'This bed number already exists in the selected ward'
});
</script>
";
        }else{
            $stmt = $conn->query("
                SELECT NVL(MAX(BED_ID),0)+1 NEW_ID
                FROM SYARMIMI.BED
            ");
            $newId = $stmt->fetch(PDO::FETCH_ASSOC)['NEW_ID'];

            $insert = $conn->prepare("
                INSERT INTO SYARMIMI.BED
                (BED_ID, BED_NUMBER, STATUS, WARD_ID)
                VALUES (:id, :bed, 'Available', :ward)
            ");
            $insert->execute([
                ':id'   => $newId,
                ':bed'  => $bed_no,
                ':ward' => $ward_id
            ]);

            $swal = "
Swal.fire({
    icon:'success',
    title:'Success',
    text:'Bed Added Successfully',
    confirmButtonColor:'#198754'
}).then(() => {
    window.location='ward.php';
});
";
        }
    }catch(PDOException $e){
        die($e->getMessage());
    }
}

if(isset($_GET['delete_bed'])){
    $bed = $_GET['delete_bed'];

    $check = $conn->prepare("
        SELECT STATUS
        FROM SYARMIMI.BED
        WHERE BED_ID = ?
    ");
    $check->execute([$bed]);
    $status = $check->fetchColumn();

    if($status == 'Occupied'){
        echo "
<script>
Swal.fire({
    icon: 'error',
    title: 'Cannot Delete',
    text: 'This bed is currently occupied'
}).then(() => {
    window.location='ward.php';
});
</script>
";
exit();
    }else{
        $conn->prepare("
            DELETE FROM SYARMIMI.BED
            WHERE BED_ID=?
        ")->execute([$bed]);

        echo "
<script>
Swal.fire({
    icon: 'success',
    title: 'Deleted',
    text: 'Bed deleted successfully',
    confirmButtonColor: '#dc3545'
}).then(() => {
    window.location='ward.php';
});
</script>
";
exit();
    }
}

/* ================= WARD SUMMARY ================= */
$wardSummary = $conn->query("
SELECT 
    w.WARD_ID,
    w.WARD_NAME,
    COUNT(b.BED_ID) AS TOTAL_BED,
    SUM(CASE WHEN b.STATUS='Occupied' THEN 1 ELSE 0 END) AS OCCUPIED,
    SUM(CASE WHEN b.STATUS='Available' THEN 1 ELSE 0 END) AS AVAILABLE_BEDS
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
    transition: all 0.3s ease;
    position: relative;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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

.low-stock-alert {
    position: relative;
    overflow: hidden;
}

.low-stock-alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(45deg, #ff6b6b22, #ffd93d22);
    border-radius: 14px;
    animation: pulse-alert 2s ease-in-out infinite;
}

@keyframes pulse-alert {
    0%, 100% { opacity: 0.3; }
    50% { opacity: 0.8; }
}

.alert-badge {
    position: absolute;
    top: -8px;
    right: -8px;
    animation: bounce 1s ease infinite;
}

@keyframes bounce {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.add-bed-btn {
    transition: all 0.3s ease;
}

.add-bed-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
}
</style>

</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<?php if(isset($_GET['success']) && $_GET['success']=='bed_added'): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
Swal.fire({
    icon:'success',
    title:'Success',
    text:'Bed Added Successfully',
    confirmButtonColor:'#198754'
});
});
</script>
<?php endif; ?>

<h3>🏥 Ward Management (Admin)</h3>

<div class="card shadow-sm mb-4">
<div class="card-header bg-primary text-white">
➕ Add New Bed
</div>
<div class="card-body">
<form method="POST">
<div class="row">
<div class="col-md-5">
<label>Ward</label>
<select name="ward_id" class="form-control" required>
<option value="">Select Ward</option>
<?php foreach($wards as $w): ?>
<option value="<?= $w['WARD_ID'] ?>"><?= $w['WARD_NAME'] ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-5">
<label>Bed Number</label>
<input type="text" name="bed_number" class="form-control" placeholder="Example: B101" required>
</div>
<div class="col-md-2 d-flex align-items-end">
<button type="submit" name="add_bed" class="btn btn-success w-100">Add Bed</button>
</div>
</div>
</form>
</div>
</div>

<!-- ================= WARD SUMMARY WITH ALERT ================= -->
<div class="row mb-4">
<?php foreach($wardSummary as $w): 
$available = $w['AVAILABLE_BEDS'] ?? 0;
$total = $w['TOTAL_BED'] ?? 0;
$occupied = $w['OCCUPIED'] ?? 0;
$percentage = $total > 0 ? round(($occupied / $total) * 100) : 0;

// Determine alert level
$alertLevel = '';
$alertMessage = '';
if ($available <= 1 && $total > 0) {
    $alertLevel = 'danger';
    $alertMessage = "⚠️ CRITICAL: Only $available bed left!";
} elseif ($available <= 2 && $total > 0) {
    $alertLevel = 'warning';
    $alertMessage = "⚠️ WARNING: Only $available beds left!";
} elseif ($percentage >= 80 && $total > 0) {
    $alertLevel = 'info';
    $alertMessage = "ℹ️ Ward is $percentage% full";
}
?>
<div class="col-md-3">
<div class="card summary-card p-3 text-center shadow-sm <?= ($alertLevel == 'danger' || $alertLevel == 'warning') ? 'low-stock-alert' : '' ?>" 
     style="border: <?= $alertLevel == 'danger' ? '3px solid #dc3545' : ($alertLevel == 'warning' ? '3px solid #ffc107' : 'none') ?>;">
    
    <?php if($alertLevel == 'danger' || $alertLevel == 'warning'): ?>
    <div class="alert-badge">
        <span class="badge bg-<?= $alertLevel ?> rounded-pill" style="font-size: 0.8rem;">
            <?= $alertLevel == 'danger' ? '🚨' : '⚠️' ?>
        </span>
    </div>
    <?php endif; ?>

    <h6><?= $w['WARD_NAME'] ?></h6>

    <p>
        Total: <?= $total ?><br>
        Occupied: <?= $occupied ?><br>
        Available: <strong class="text-<?= $available <= 1 ? 'danger' : ($available <= 2 ? 'warning' : 'success') ?>">
            <?= $available ?>
        </strong>
    </p>

    <!-- Progress Bar -->
    <div class="progress mb-2" style="height: 8px;">
        <div class="progress-bar bg-<?= $percentage >= 90 ? 'danger' : ($percentage >= 70 ? 'warning' : 'success') ?>" 
             role="progressbar" 
             style="width: <?= $percentage ?>%;" 
             aria-valuenow="<?= $percentage ?>" 
             aria-valuemin="0" 
             aria-valuemax="100">
        </div>
    </div>

    <?php if($available == 0): ?>
        <span class="badge bg-danger">FULL</span>
    <?php else: ?>
        <span class="badge bg-success">AVAILABLE</span>
    <?php endif; ?>

    <!-- ALERT MESSAGE WITH ADD BED BUTTON -->
    <?php if($alertLevel == 'danger' || $alertLevel == 'warning'): ?>
        <div class="mt-2 p-2 bg-<?= $alertLevel ?> bg-opacity-10 rounded">
            <small class="text-<?= $alertLevel ?> d-block mb-2">
                <strong><?= $alertMessage ?></strong>
            </small>
            
            <!-- Trigger Add Bed Modal -->
            <button class="btn btn-<?= $alertLevel ?> btn-sm w-100 add-bed-btn" 
                    onclick="openAddBedModal('<?= $w['WARD_ID'] ?>', '<?= $w['WARD_NAME'] ?>')">
                ➕ Add Bed Now
            </button>
        </div>
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

<br><br>

<a
href="ward.php?delete_bed=<?= $row['BED_ID'] ?>"
class="btn btn-danger btn-sm"
onclick="return confirmDelete(event,this.href)">

Delete Bed

</a>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>
</div>

<!-- ================= ADD BED MODAL ================= -->
<div class="modal fade" id="addBedModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h5 class="modal-title">➕ Add New Bed</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addBedForm">
                    <div class="mb-3">
                        <label class="form-label">Ward</label>
                        <input type="text" id="modalWardName" class="form-control" readonly style="background-color: #f8f9fa;">
                        <input type="hidden" name="ward_id" id="modalWardId">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Bed Number</label>
                        <input type="text" name="bed_number" class="form-control" placeholder="Enter bed number" required>
                    </div>
                    <button type="submit" name="add_bed" class="btn btn-success w-100">Add Bed</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Function to open Add Bed Modal with ward pre-selected
function openAddBedModal(wardId, wardName) {
    document.getElementById('modalWardId').value = wardId;
    document.getElementById('modalWardName').value = wardName;
    
    var modal = new bootstrap.Modal(document.getElementById('addBedModal'));
    modal.show();
}

// Confirm Delete function
function confirmDelete(e, url) {
    e.preventDefault();
    Swal.fire({
        title: 'Delete Bed?',
        text: 'This action cannot be undone',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location = url;
        }
    });
    return false;
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    // Check for low stock alerts and show notification
    <?php foreach($wardSummary as $w): 
        $available = $w['AVAILABLE_BEDS'] ?? 0;
        if ($available <= 1 && $w['TOTAL_BED'] > 0): ?>
            setTimeout(function() {
                Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Low Bed Stock Alert!',
                    html: 'Ward <strong><?= $w['WARD_NAME'] ?></strong> has only <strong><?= $available ?></strong> bed left!<br><br>Click "Add Bed" to add more beds.',
                    confirmButtonText: 'Add Bed Now',
                    confirmButtonColor: '#dc3545',
                    showCancelButton: true,
                    cancelButtonText: 'Dismiss'
                }).then((result) => {
                    if (result.isConfirmed) {
                        openAddBedModal('<?= $w['WARD_ID'] ?>', '<?= $w['WARD_NAME'] ?>');
                    }
                });
            }, 1000);
        <?php endif; ?>
    <?php endforeach; ?>
});
</script>

<?php if(!empty($swal)): ?>
<script>
<?= $swal ?>
</script>
<?php endif; ?>

</body>
</html>