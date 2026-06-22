<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$limit = 10;

$offset = ($page - 1) * $limit;

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
OFFSET $offset ROWS
FETCH NEXT $limit ROWS ONLY
";

$stmt = $conn->query($sql);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalPatients = count($rows);

$pendingCount = $conn->query("

SELECT COUNT(DISTINCT a.ADMISSION_ID)

FROM MEDICATION_ORDER mo

JOIN ADMISSION a
ON mo.ADMISSION_ID = a.ADMISSION_ID

JOIN PHARMACY_PREPARATION pp
ON mo.MEDORDER_ID = pp.MEDORDER_ID

LEFT JOIN MEDICATION_ADMIN ma
ON mo.MEDORDER_ID = ma.MEDORDER_ID

WHERE ma.MEDORDER_ID IS NULL

")->fetchColumn();

$wardCount = $conn->query("

SELECT COUNT(DISTINCT w.WARD_ID)

FROM MEDICATION_ORDER mo
JOIN ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
JOIN BED b ON a.BED_ID = b.BED_ID
JOIN WARD w ON b.WARD_ID = w.WARD_ID
JOIN PHARMACY_PREPARATION pp ON mo.MEDORDER_ID = pp.MEDORDER_ID
LEFT JOIN MEDICATION_ADMIN ma ON mo.MEDORDER_ID = ma.MEDORDER_ID

WHERE ma.MEDORDER_ID IS NULL

")->fetchColumn();

$deliveredToday = $conn->query("

SELECT COUNT(*)

FROM SYARMIMI.MEDICATION_ADMIN

WHERE TRUNC(ADMIN_TIME)=TRUNC(SYSDATE)

")->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Medication</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background:#eef2f7;
}

.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.table tbody tr{
    transition:.2s;
}

.table tbody tr:hover{
    background:#f8fafc;
}

.card{
    border-radius:18px;
}

.form-control,
.form-select{
    border-radius:12px;
}

.btn-success{
    border-radius:10px;
}

</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_nurse.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-4">💊 Medication Tasks</h4>

<div class="row mb-3">

    <div class="col-md-5">
        <input type="text"
               id="searchInput"
               class="form-control"
               placeholder="🔍 Search patient or medication">
    </div>

    <div class="col-md-3">
        <select id="wardFilter" class="form-select">
            <option value="">All Wards</option>
            <option value="Orthopaedic">Orthopaedic</option>
            <option value="Paediatric">Paediatric</option>
            <option value="Nutrition">Nutrition</option>
        </select>
    </div>

    <div class="col-md-4">
        <select id="sortSelect" class="form-select">
            <option value="newest">Newest First</option>
            <option value="patient">Patient Name</option>
            <option value="ward">Ward Name</option>
        </select>
    </div>

</div>

<div class="row mb-4">

<div class="col-md-4">
<div class="card border-0 shadow-sm">
<div class="card-body text-center">
<h6>Pending Medication</h6>
<h2 class="text-danger"><?= $pendingCount ?></h2>
</div>
</div>
</div>

<div class="col-md-4">
<div class="card border-0 shadow-sm">
<div class="card-body text-center">
<h6>Delivered Today</h6>
<h2 class="text-success">
<?= $deliveredToday ?>
</h2>
</div>
</div>
</div>

<div class="col-md-4">
<div class="card border-0 shadow-sm">
<div class="card-body text-center">
<h6>Active Wards</h6>
<h2 class="text-primary">
<?= $wardCount ?>
</h2>
</div>
</div>
</div>

</div>

<div class="box">

<table class="table table-bordered table-hover">

<thead style="background:#0f172a;color:white;">
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

<?php foreach($rows as $row) { ?>

<tr>
<td>

<div class="fw-bold">
<?= $row['NAME'] ?>
</div>

<small class="text-muted">
Admission #<?= $row['ADMISSION_ID'] ?>
</small>

</td>

<td>
<?= $row['WARD_NAME'] ?><br>
<small class="text-muted">Bed <?= $row['BED_NUMBER'] ?></small>
</td>

<td>
<span class="badge bg-primary fs-6">
<?= $row['MEDICATION_NAME'] ?>
</span>
</td>
<td>
<span class="badge bg-info text-dark">
<?= $row['DOSAGE'] ?>
</span>
</td>
<td>
<span class="badge bg-secondary">
<?= is_numeric($row['FREQUENCY'])
      ? $row['FREQUENCY'].' Times Daily'
      : $row['FREQUENCY']; ?>
</span>
</td>

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

<nav class="mt-4">

<ul class="pagination">

<li class="page-item">
<a class="page-link"
href="?page=<?= max(1,$page-1) ?>">
Previous
</a>
</li>

<li class="page-item active">
<a class="page-link">
<?= $page ?>
</a>
</li>

<li class="page-item">
<a class="page-link"
href="?page=<?= $page+1 ?>">
Next
</a>
</li>

</ul>

</nav>

</div>

</div>
</div>

</body>
</html>