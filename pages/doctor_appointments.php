<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   CURRENT DOCTOR
========================= */

$doctorName = $_SESSION['user'];

/* =========================
   GET APPROVED APPOINTMENTS
========================= */

$sql = "
SELECT *
FROM SYARMIMI.APPOINTMENT
WHERE LOWER(STATUS) = 'approved'
AND LOWER(DOCTOR_NAME) LIKE LOWER(:doctor_name)
ORDER BY APPOINTMENT_ID DESC
";

$stmt = $conn->prepare($sql);

$searchDoctor = "%" . $doctorName . "%";

$stmt->bindParam(':doctor_name', $searchDoctor);

$stmt->execute();

$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TOTAL APPOINTMENT
========================= */

$totalAppointments = count($appointments);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Doctor Appointments</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI', sans-serif;
}

/* MAIN CONTENT */

.content{
    padding:30px;
}

/* TITLE */

.page-title{
    font-size:55px;
    font-weight:800;
    color:#0f172a;
}

/* BOX */

.box{
    background:white;
    padding:30px;
    border-radius:30px;
    box-shadow:0 5px 20px rgba(0,0,0,0.05);
}

/* TABLE */

.table th{
    background:#2563eb;
    color:white;
    border:none;
    padding:18px;
}

.table td{
    padding:18px;
    vertical-align:middle;
}

/* STATUS */

.badge-approved{
    background:#22c55e;
    color:white;
    padding:10px 20px;
    border-radius:50px;
    font-size:14px;
}

/* EMPTY */

.empty-box{
    padding:80px 20px;
    text-align:center;
}

.empty-box h3{
    color:#64748b;
}

.empty-box p{
    color:#94a3b8;
}

</style>

</head>

<body>

<div class="d-flex">

<!-- SIDEBAR -->
<?php include("../includes/sidebar_doctor.php"); ?>

<!-- CONTENT -->
<div class="flex-grow-1 content">

<h1 class="page-title mb-4">
📋 My Appointments
</h1>

<div class="box">

<?php if($totalAppointments > 0): ?>

<table class="table align-middle">

<tr>

<th>ID</th>
<th>Patient</th>
<th>IC Number</th>
<th>Gender</th>
<th>Phone</th>
<th>Department</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>

</tr>

<?php foreach($appointments as $a): ?>

<tr>

<td>
<b>#<?= $a['APPOINTMENT_ID'] ?></b>
</td>

<td>

<b>
<?= $a['PATIENT_NAME'] ?>
</b>

<br>

<small class="text-muted">
<?= $a['EMAIL'] ?>
</small>

</td>

<td>
<?= $a['IC_NUMBER'] ?>
</td>

<td>
<?= $a['GENDER'] ?>
</td>

<td>
<?= $a['PHONE'] ?>
</td>

<td>
<?= $a['DEPARTMENT'] ?>
</td>

<td>
<?= $a['APPOINTMENT_DATE'] ?>
</td>

<td>
<?= $a['APPOINTMENT_TIME'] ?>
</td>

<td>

<span class="badge-approved">
Approved
</span>

</td>

<td>

<button
class="btn btn-primary btn-sm"
data-bs-toggle="modal"
data-bs-target="#patientModal<?= $a['APPOINTMENT_ID'] ?>">

View Details

</button>

</td>

</tr>

<div
class="modal fade"
id="patientModal<?= $a['APPOINTMENT_ID'] ?>">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">

Patient Details

</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<p>
<b>Patient Name:</b>
<?= $a['PATIENT_NAME'] ?>
</p>

<p>
<b>IC Number:</b>
<?= $a['IC_NUMBER'] ?>
</p>

<p>
<b>Gender:</b>
<?= $a['GENDER'] ?>
</p>

<p>
<b>Phone:</b>
<?= $a['PHONE'] ?>
</p>

<p>
<b>Email:</b>
<?= $a['EMAIL'] ?>
</p>

<p>
<b>Address:</b>
<?= $a['ADDRESS'] ?>
</p>

<p>
<b>Symptoms / Notes:</b><br>
<?= $a['NOTES'] ?>
</p>

<p>
<b>Appointment Date:</b>
<?= $a['APPOINTMENT_DATE'] ?>
</p>

<p>
<b>Appointment Time:</b>
<?= $a['APPOINTMENT_TIME'] ?>
</p>

</div>

</div>

</div>

</div>

<?php endforeach; ?>

</table>

<?php else: ?>

<div class="empty-box">

<h3>
📭 No Approved Appointments
</h3>

<p>
There are currently no approved appointments assigned to you.
</p>

</div>

<?php endif; ?>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>