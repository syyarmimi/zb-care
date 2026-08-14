<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

$admission_id = $_GET['admission_id'] ?? 0;

if(!$admission_id){
    die("Admission ID not found");
}

/* ================= PATIENT INFO ================= */

$stmt = $conn->prepare("
SELECT
p.*,
a.ADMISSION_ID,
a.ADMISSION_DATE,
a.DISCHARGE_DATE,
b.BED_NUMBER,
w.WARD_NAME

FROM SYARMIMI.ADMISSION a

JOIN SYARMIMI.PATIENT p
ON a.PATIENT_ID = p.PATIENT_ID

LEFT JOIN SYARMIMI.BED b
ON a.BED_ID = b.BED_ID

LEFT JOIN SYARMIMI.WARD w
ON b.WARD_ID = w.WARD_ID

WHERE a.ADMISSION_ID = :id
");

$stmt->execute([
    ':id' => $admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$patient){
    die("Patient not found");
}

$patient_id = $patient['PATIENT_ID'];

/* ================= DIAGNOSIS ================= */

$diagnosis = $conn->prepare("
SELECT *
FROM SYARMIMI.DIAGNOSIS
WHERE PATIENT_ID = :pid
ORDER BY DATE_RECORDED DESC
");

$diagnosis->execute([
    ':pid' => $patient_id
]);

/* ================= MEDICATION ================= */

$medication = $conn->prepare("
SELECT
m.MEDICATION_NAME,
mo.DOSAGE,
mo.FREQUENCY,

CASE
WHEN ma.MEDORDER_ID IS NOT NULL
THEN 'Administered'
ELSE 'Pending'
END AS STATUS,

ma.ADMIN_TIME

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.MEDICATION m
ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
ON mo.MEDORDER_ID = ma.MEDORDER_ID

WHERE mo.PATIENT_ID = :pid

ORDER BY mo.MEDORDER_ID DESC
");

$medication->execute([
    ':pid' => $patient_id
]);

?>
<!DOCTYPE html>
<html>
<head>

<title>Patient Record</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
background:#eef2f7;
}

.card-box{
background:white;
border-radius:20px;
padding:25px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
margin-bottom:20px;
}

.timeline{
border-left:4px solid #0d6efd;
padding-left:20px;
margin-left:10px;
}

.timeline-item{
margin-bottom:20px;
}

.profile-icon{
width:70px;
height:70px;
background:#dbeafe;
border-radius:50%;
display:flex;
align-items:center;
justify-content:center;
font-size:30px;
margin:auto;
}

.nav-btn{
border-radius:12px;
font-weight:600;
}

.section-content{
transition:.3s;
}

.table{
margin-bottom:0;
}

</style>

</head>

<body>

<div class="container-fluid p-4">

<a href="patient_list.php"
class="btn btn-secondary mb-3">
← Back
</a>

<div class="card-box py-3">

<div class="d-flex flex-wrap gap-2">

<button class="btn btn-primary nav-btn"
onclick="showSection('profile')">
👤 Patient Profile
</button>

<button class="btn btn-outline-primary nav-btn"
onclick="showSection('admission')">
🏥 Admission Details
</button>

<button class="btn btn-outline-primary nav-btn"
onclick="showSection('diagnosis')">
🩺 Admission Diagnosis
</button>

<button class="btn btn-outline-primary nav-btn"
onclick="showSection('medication')">
💊 Admission Medication
</button>

<button class="btn btn-outline-primary nav-btn"
onclick="showSection('timeline')">
📈 Journey Timeline
</button>

</div>

</div>

<!-- PROFILE -->

<div class="card-box section-content" id="profile">

<div class="text-center">

<div class="profile-icon">
👤
</div>

<h3 class="mt-3 fw-bold">
<?= $patient['NAME'] ?>
</h3>

<span class="badge bg-primary">
Admission #<?= $patient['ADMISSION_ID'] ?>
</span>

</div>

<hr>

<div class="row g-4">

    <div class="col-md-4">
        <strong>IC Number</strong><br>
        <?= $patient['IC_NUMBER'] ?>
    </div>

    <div class="col-md-4">
        <strong>Age</strong><br>
        <?= $patient['AGE'] ?>
    </div>

    <div class="col-md-4">
        <strong>Gender</strong><br>
        <?= $patient['GENDER'] ?>
    </div>

    <div class="col-md-4">
        <strong>Phone</strong><br>
        <?= $patient['PHONE'] ?>
    </div>

    <div class="col-md-8">
        <strong>Address</strong><br>
        <?= $patient['ADDRESS'] ?>
    </div>

</div>
</div>

<!-- ADMISSION -->

<div class="card-box section-content d-none" id="admission">

<h4>
🏥 Admission Information
</h4>

<hr>

<div class="row">

<div class="col-md-3">
<strong>Ward</strong><br>
<?= $patient['WARD_NAME'] ?? '-' ?>
</div>

<div class="col-md-3">
<strong>Bed</strong><br>
<?= $patient['BED_NUMBER'] ?? '-' ?>
</div>

<div class="col-md-3">
<strong>Admission Date</strong><br>
<?= $patient['ADMISSION_DATE'] ?>
</div>

<div class="col-md-3">
<strong>Status</strong><br>

<?php if(empty($patient['DISCHARGE_DATE'])): ?>

<span class="badge bg-success">
Admitted
</span>

<?php else: ?>

<span class="badge bg-secondary">
Discharged
</span>

<?php endif; ?>

</div>

</div>

</div>

<!-- DIAGNOSIS -->

<div class="card-box section-content d-none" id="diagnosis">

<h4>
🩺 Diagnosis History
</h4>

<table class="table table-bordered">

<thead>

<tr>
<th>Date</th>
<th>Diagnosis</th>
<th>Allergies</th>
</tr>

</thead>

<tbody>

<?php while($d = $diagnosis->fetch(PDO::FETCH_ASSOC)): ?>

<tr>

<td><?= $d['DATE_RECORDED'] ?></td>

<td><?= $d['DIAGNOSIS_DETAILS'] ?></td>

<td><?= $d['ALLERGIES'] ?: '-' ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- MEDICATION -->

<div class="card-box section-content d-none" id="medication">

<h4>
💊 Medication History
</h4>

<table class="table table-striped">

<thead>

<tr>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Status</th>
<th>Admin Time</th>
</tr>

</thead>

<tbody>

<?php while($m = $medication->fetch(PDO::FETCH_ASSOC)): ?>

<tr>

<td><?= $m['MEDICATION_NAME'] ?></td>

<td><?= $m['DOSAGE'] ?></td>

<td><?= $m['FREQUENCY'] ?></td>

<td>

<?php if($m['STATUS']=='Administered'): ?>

<span class="badge bg-success">
Administered
</span>

<?php else: ?>

<span class="badge bg-warning text-dark">
Pending
</span>

<?php endif; ?>

</td>

<td>
<?= $m['ADMIN_TIME'] ?? '-' ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div>

<!-- TIMELINE -->

<div class="card-box section-content d-none" id="timeline">

<h4>
📈 Patient Journey Timeline
</h4>

<hr>

<div class="timeline">

<div class="timeline-item">
✅ Patient Registered
</div>

<div class="timeline-item">
🏥 Admitted
<br>
<small><?= $patient['ADMISSION_DATE'] ?></small>
</div>

<?php if(!empty($patient['DISCHARGE_DATE'])): ?>

<div class="timeline-item">
🏠 Discharged
<br>
<small><?= $patient['DISCHARGE_DATE'] ?></small>
</div>

<?php endif; ?>

</div>

</div>

</div>

<script>

function showSection(sectionId){

document.querySelectorAll('.section-content')
.forEach(function(section){

section.classList.add('d-none');

});

document.getElementById(sectionId)
.classList.remove('d-none');

document.querySelectorAll('.nav-btn')
.forEach(function(btn){

btn.classList.remove('btn-primary');
btn.classList.add('btn-outline-primary');

});

event.target.classList.remove('btn-outline-primary');
event.target.classList.add('btn-primary');

}

</script>

</body>
</html>