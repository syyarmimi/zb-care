<?php
session_start();
include("../config/config.php");

/* ================= ROLE ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$staff_id = $_SESSION['user_id'] ?? 0;

/* ================= HANDLE DELIVERY ================= */
if(isset($_GET['deliver'])){

    $id = $_GET['deliver'];

    // Prevent duplicate delivery
    $check = $conn->prepare("
        SELECT COUNT(*) FROM SYARMIMI.MEDICATION_DELIVERY
        WHERE MEDORDER_ID = :id
    ");
    $check->execute([':id'=>$id]);

    if($check->fetchColumn() == 0){

        $conn->prepare("
            INSERT INTO SYARMIMI.MEDICATION_DELIVERY
            (MEDDELIVERY_ID, MEDORDER_ID, DELIVERY_TIME, STATUS, STAFF_ID)
            VALUES (SYARMIMI.MED_DELIVERY_SEQ.NEXTVAL, :id, SYSDATE, 'Delivered', :staff)
        ")->execute([
            ':id'=>$id,
            ':staff'=>$staff_id
        ]);

        echo "<script>alert('Medication Delivered Successfully'); window.location='med.php';</script>";
    } else {
        echo "<script>alert('Already Delivered'); window.location='med.php';</script>";
    }
}

/* ================= FETCH ================= */
$sql = "
SELECT 
mo.MEDORDER_ID,
p.NAME,
m.MEDICATION_NAME,
mo.DOSAGE,
mo.FREQUENCY,

CASE 
    WHEN md.MEDORDER_ID IS NOT NULL THEN 'Delivered'
    ELSE 'Pending'
END AS STATUS,

md.DELIVERY_TIME

FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.MEDICATION_DELIVERY md 
ON mo.MEDORDER_ID = md.MEDORDER_ID

ORDER BY mo.MEDORDER_ID DESC
";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Delivery</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }

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

<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-3">💊 Medication Delivery</h4>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Status</th>
<th>Delivered Time</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>

<tr>

<td><?= $row['MEDORDER_ID'] ?></td>
<td><?= $row['NAME'] ?></td>
<td><?= $row['MEDICATION_NAME'] ?></td>
<td><?= $row['DOSAGE'] ?></td>
<td><?= $row['FREQUENCY'] ?></td>

<td>
<?php if($row['STATUS']=='Delivered'): ?>
<span class="badge bg-success">Delivered</span>
<?php else: ?>
<span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td><?= $row['DELIVERY_TIME'] ?? '-' ?></td>

<td>
<?php if($row['STATUS']=='Pending'): ?>
<a href="?deliver=<?= $row['MEDORDER_ID'] ?>" 
class="btn btn-success btn-sm"
onclick="return confirm('Mark as delivered?')">
Deliver
</a>
<?php else: ?>
<button class="btn btn-secondary btn-sm" disabled>Done</button>
<?php endif; ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

</div>
</div>

</body>
</html>