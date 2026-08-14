<?php
session_start();
include("../config/config.php");

// ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

// TOTAL PATIENTS
$stmt1 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.PATIENT");
$row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

// TOTAL ADMISSION
$stmt2 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.ADMISSION");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

// OCCUPIED BEDS
$stmt3 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.BED WHERE STATUS='Occupied'");
$row3 = $stmt3->fetch(PDO::FETCH_ASSOC);

// TOTAL BEDS
$totalBeds = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.BED
")->fetchColumn();


// ACTIVE WARDS
$activeWards = $conn->query("
SELECT COUNT(DISTINCT WARD_ID)
FROM SYARMIMI.BED
")->fetchColumn();


// RECENT ADMISSIONS
$recentAdmissions = $conn->query("
SELECT
p.NAME,
b.BED_NUMBER,
w.WARD_NAME,
a.ADMISSION_ID

FROM SYARMIMI.ADMISSION a
JOIN SYARMIMI.PATIENT p
ON a.PATIENT_ID = p.PATIENT_ID

JOIN SYARMIMI.BED b
ON a.BED_ID = b.BED_ID

JOIN SYARMIMI.WARD w
ON b.WARD_ID = w.WARD_ID

ORDER BY a.ADMISSION_ID DESC

FETCH FIRST 5 ROWS ONLY
");

// ✅ NEW: PENDING MEDICATION
$pendingMed = $conn->query("

SELECT COUNT(*)

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.ADMISSION a
ON mo.ADMISSION_ID = a.ADMISSION_ID

JOIN SYARMIMI.PHARMACY_PREPARATION pp
ON mo.MEDORDER_ID = pp.MEDORDER_ID

LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
ON mo.MEDORDER_ID = ma.MEDORDER_ID

WHERE ma.MEDORDER_ID IS NULL

")->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background:#f4f6f9;
    font-family:'Segoe UI';
}

.card-box {
    height:160px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    border-radius:15px;
}

.icon {
    width:50px;
    height:50px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    margin-bottom:10px;
    font-size:22px;
}

.blue { background:#3b82f6; }
.green { background:#10b981; }
.red { background:#ef4444; }

.card:hover {
    transform: translateY(-3px);
    transition: 0.2s;
}

.dashboard-card{
    background:white;
    border:none;
    border-radius:20px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
    transition:.3s;
}

.dashboard-card:hover{
    transform:translateY(-4px);
}

.stat-number{
    font-size:38px;
    font-weight:700;
}

.realtime-card{
    background:white;
    border-radius:20px;
    padding:25px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.section-card{
    background:white;
    border-radius:20px;
    padding:25px;
    box-shadow:0 10px 25px rgba(0,0,0,.08);
}

.sidebar .nav-link{
color:#dbeafe;
padding:12px 18px;
margin:4px 12px;
border-radius:14px;
display:flex;
align-items:center;
gap:10px;
font-weight:500;
transition:.25s;
}

.main-content{
    margin-left:270px;
}


</style>
</head>

<body>

<div>

<?php include("../includes/sidebar_nurse.php"); ?>

<div class="main-content p-4">

<h3 class="mb-4">🩺 Nurse Dashboard</h3>

<!-- 🔔 NEW NOTIFICATION -->
<?php if($pendingMed > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">

<div>
<strong>🚨 Patient Care Reminder</strong><br>

<?php if($pendingMed > 0): ?>
💊 <?= $pendingMed ?> medication(s) waiting for delivery<br>
<?php endif; ?>

</div>

<div class="d-flex gap-2">

<?php if($pendingMed > 0): ?>
<a href="nurse_medication.php" class="btn btn-success btn-sm">
Go Medication
</a>
<?php endif; ?>

</div>

</div>
<?php endif; ?>


<div class="row g-3 mb-4">

<!-- REALTIME -->

<div class="col-md-3">

<div class="realtime-card">

<div id="currentDate" class="text-muted"></div>

<h2 id="currentTime"></h2>

<hr>

<h5 class="fw-bold">
Registered Hospital Patients
</h5>

<small class="text-muted">
Real-time patient statistics
</small>

</div>

</div>

<!-- PATIENTS -->

<div class="col-md-3">

<div class="dashboard-card p-4 text-center">

<i class="bi bi-people-fill text-primary fs-1"></i>

<h6 class="mt-3">
Active Patients
</h6>

<div class="stat-number">
<?= $row1['TOTAL']; ?>
</div>

</div>

</div>

<!-- BEDS -->

<div class="col-md-3">

<div class="dashboard-card p-4 text-center">

<i class="bi bi-hospital-fill text-success fs-1"></i>

<h6 class="mt-3">
Occupied Beds
</h6>

<div class="stat-number">
<?= $row3['TOTAL']; ?>/<?= $totalBeds ?>
</div>

</div>

</div>

<!-- MEDICATION -->

<div class="col-md-3">

<div class="dashboard-card p-4 text-center">

<i class="bi bi-capsule-pill text-danger fs-1"></i>

<h6 class="mt-3">
Pending Medication
</h6>

<div class="stat-number text-danger">
<?= $pendingMed ?>
</div>

</div>

</div>

</div>

<div class="section-card mb-4">

<h4 class="mb-4">
🩺 Patient Care Summary
</h4>

<div class="row">

<div class="col-md-4">

<div class="alert alert-danger">

<h5>
💊 Pending Medication
</h5>

<h2>
<?= $pendingMed ?>
</h2>

</div>

</div>

<div class="col-md-4">

<div class="alert alert-success">

<h5>
🛏 Occupied Beds
</h5>

<h2>
<?= $row3['TOTAL']; ?>
</h2>

</div>

</div>

<div class="col-md-4">

<div class="alert alert-primary">

<h5>
🏥 Active Wards
</h5>

<h2>
<?= $activeWards ?>
</h2>

</div>

</div>

</div>

<div class="mt-3">

<a href="nurse_medication.php"
class="btn btn-success">

💊 Medication Tasks

</a>

<a href="patient_record_details.php"
class="btn btn-primary">

👤 Patient Records

</a>

</div>

</div>

<div class="section-card mb-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<h4 class="mb-0">
🏥 Recent Admissions
</h4>

<span class="badge bg-primary fs-6">
Latest 5 Admissions
</span>

</div>

<div class="row">

<?php while($r = $recentAdmissions->fetch(PDO::FETCH_ASSOC)): ?>

<div class="col-md-6 mb-3">

<div class="card border-0 shadow-sm">

<div class="card-body">

<div class="d-flex justify-content-between">

<div>

<h5 class="fw-bold mb-1">
<?= $r['NAME'] ?>
</h5>

<small class="text-muted">
Admission #<?= $r['ADMISSION_ID'] ?>
</small>

</div>

<span class="badge bg-success">
New
</span>

</div>

<hr>

<p class="mb-1">
🏥 <?= $r['WARD_NAME'] ?>
</p>

<p class="mb-0">
🛏 Bed <?= $r['BED_NUMBER'] ?>
</p>

</div>

</div>

</div>

<?php endwhile; ?>

</div>

</div>
<!-- TABLE -->
<div class="section-card">

<div class="d-flex justify-content-between align-items-center mb-4">

<h4>
👤 Patient Records
</h4>

<a href="patient_list.php"
class="btn btn-primary">

View All Patients

</a>

</div>

<div class="row mb-4">

<div class="col-md-5">

<input
type="text"
class="form-control"
placeholder="🔍 Search patient">

</div>

<div class="col-md-3">

<select class="form-select">

<option>All Gender</option>
<option>Male</option>
<option>Female</option>

</select>

</div>

<div class="col-md-4">

<select class="form-select">

<option>Newest First</option>
<option>Name A-Z</option>

</select>

</div>

</div>

<!-- PATIENT CARDS -->

<div class="row">

<?php

$stmtPatient = $conn->query("

SELECT
p.NAME,
p.GENDER,
a.ADMISSION_ID

FROM SYARMIMI.PATIENT p

JOIN SYARMIMI.ADMISSION a
ON p.PATIENT_ID = a.PATIENT_ID

ORDER BY a.ADMISSION_ID DESC

FETCH FIRST 6 ROWS ONLY

");

while($p = $stmtPatient->fetch(PDO::FETCH_ASSOC)):

?>

<div class="col-md-4 mb-3">

<div class="card border-0 shadow-sm h-100">

<div class="card-body">

<div class="text-center">

<div style="
width:70px;
height:70px;
background:#dbeafe;
border-radius:50%;
margin:auto;
display:flex;
align-items:center;
justify-content:center;
font-size:30px;
">

👤

</div>

<h5 class="mt-3 fw-bold">

<?= $p['NAME'] ?>

</h5>

<span class="badge bg-secondary">

<?= $p['GENDER'] ?>

</span>

</div>

<hr>

<p class="mb-3">

Admission ID:
<b>#<?= $p['ADMISSION_ID'] ?></b>

</p>

<a href="patient_record_details.php?admission_id=<?= $p['ADMISSION_ID'] ?>"
class="btn btn-outline-primary w-100">

Open Patient Record

</a>
</div>

</div>

</div>

<?php endwhile; ?>

</div>

</div>

<script>

function updateClock(){

const now = new Date();

document.getElementById("currentTime").innerHTML =
now.toLocaleTimeString();

document.getElementById("currentDate").innerHTML =
now.toDateString();

}

setInterval(updateClock,1000);

updateClock();

</script>

</body>
</html>