<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= FETCH MEDICATION TASK ================= */
$sql = "
SELECT mo.MEDORDER_ID,
       a.ADMISSION_ID,
       p.NAME,
       m.MEDICATION_NAME,
       mo.DOSAGE,
       mo.FREQUENCY,
       w.WARD_NAME,
       b.BED_NUMBER

FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID
JOIN SYARMIMI.BED b ON a.BED_ID = b.BED_ID
JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID

-- MUST be prepared first
JOIN SYARMIMI.PHARMACY_PREPARATION pp 
ON mo.MEDORDER_ID = pp.MEDORDER_ID

-- NOT yet given
LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
ON mo.MEDORDER_ID = ma.MEDORDER_ID

WHERE ma.MEDORDER_ID IS NULL
ORDER BY mo.MEDORDER_ID DESC
";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Medication</title>
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
<?php include("../includes/sidebar_nurse.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-4">💊 Medication Tasks</h4>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>Patient</th>
<th>Location</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>
<td><?= $row['NAME'] ?></td>

<td>
<?= $row['WARD_NAME'] ?><br>
<small class="text-muted">Bed <?= $row['BED_NUMBER'] ?></small>
</td>

<td><?= $row['MEDICATION_NAME'] ?></td>
<td><?= $row['DOSAGE'] ?></td>
<td><?= $row['FREQUENCY'] ?></td>

<td>
<a href="nurse_action.php?give_med=<?= $row['ADMISSION_ID'] ?>"
class="btn btn-success btn-sm"
onclick="return confirm('Give this medication?')">
💊 Give
</a>
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