<?php
session_start();
include("../config/config.php");

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   SUMMARY
========================= */

$availableDoctors = $conn->query("
SELECT COUNT(DISTINCT ACCOUNT_ID)
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE STATUS='Available'
")->fetchColumn();

$unavailableDoctors = $conn->query("
SELECT COUNT(DISTINCT ACCOUNT_ID)
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE STATUS='Unavailable'
")->fetchColumn();

/* =========================
   AVAILABILITY LIST
========================= */

$doctorList = $conn->query("
SELECT
    ACCOUNT_ID,
    USERNAME
FROM SYARMIMI.HOSPITAL_STAFF
WHERE ROLE='doctor'
ORDER BY USERNAME
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   TODAY AVAILABILITY CARDS
========================= */

$todayDoctors = $conn->query("
SELECT
    H.ACCOUNT_ID,
    H.USERNAME,
    D.STATUS,
    D.START_TIME,
    D.END_TIME
FROM SYARMIMI.DOCTOR_AVAILABILITY D
JOIN SYARMIMI.HOSPITAL_STAFF H
ON D.ACCOUNT_ID = H.ACCOUNT_ID
WHERE D.AVAILABLE_DATE = TRUNC(SYSDATE)
ORDER BY H.USERNAME
")->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>

<title>Doctor Availability</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI',sans-serif;
}

.box{
    background:white;
    padding:25px;
    border-radius:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.05);
}

.stat-card{
    color:white;
    border-radius:20px;
    padding:25px;
    text-align:center;
    height:100%;
}

.available-card{
    background:linear-gradient(135deg,#16a34a,#22c55e);
}

.unavailable-card{
    background:linear-gradient(135deg,#dc2626,#ef4444);
}

.stat-card h1{
    font-size:48px;
    font-weight:700;
    margin-bottom:10px;
}

.table th{
    background:#2563eb;
    color:white;
    border:none;
}

.badge-status{
    padding:8px 16px;
    border-radius:20px;
    font-size:14px;
}

.doctor-card{
    background:white;
    border-radius:20px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.05);
    text-align:center;
    height:100%;
    transition:.3s;
}

.doctor-card:hover{
    transform:translateY(-5px);
}

.available{
    color:#16a34a;
    font-weight:700;
}

.unavailable{
    color:#dc2626;
    font-weight:700;
}

.sidebar{
width:260px !important;
min-width:260px !important;
max-width:260px !important;
height:100vh;
flex-shrink:0;
}

.dataTables_filter{
    display:none;
}

.dataTables_info{
    margin-top:15px;
}

.dataTables_paginate{
    margin-top:15px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current{
    background:#2563eb !important;
    color:white !important;
    border:none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover{
    background:#2563eb !important;
    color:white !important;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h2 class="mb-4">
📅 Doctor Availability Management
</h2>

<!-- SUMMARY -->

<div class="row mb-4">

<div class="col-md-6">

<div class="stat-card available-card">

<h1><?= $availableDoctors ?></h1>

<h5>Available Doctors</h5>

</div>

</div>

<div class="col-md-6">

<div class="stat-card unavailable-card">

<h1><?= $unavailableDoctors ?></h1>

<h5>Unavailable Doctors</h5>

</div>

</div>

</div>

<div class="box mb-4">

<h5 class="mb-4">
👨‍⚕️ Today's Doctor Availability
</h5>

<div class="row g-3">

<?php foreach($todayDoctors as $doc): ?>

<div class="col-md-3">

<div class="doctor-card">

<h6>
Dr. <?= htmlspecialchars($doc['USERNAME']) ?>
</h6>

<?php if($doc['STATUS']=='Available'): ?>

<div class="available">
🟢 Available
</div>

<?php

$availableSlotStmt = $conn->prepare("
SELECT COUNT(*)
FROM SYARMIMI.DOCTOR_SLOT
WHERE ACCOUNT_ID = :doctor
AND SLOT_DATE = TRUNC(SYSDATE)
AND STATUS='Available'
");

$availableSlotStmt->execute([
    ':doctor'=>$doc['ACCOUNT_ID']
]);

$availableSlots =
$availableSlotStmt->fetchColumn();


$bookedSlotStmt = $conn->prepare("
SELECT COUNT(*)
FROM SYARMIMI.DOCTOR_SLOT
WHERE ACCOUNT_ID = :doctor
AND SLOT_DATE = TRUNC(SYSDATE)
AND STATUS='Booked'
");

$bookedSlotStmt->execute([
    ':doctor'=>$doc['ACCOUNT_ID']
]);

$bookedSlots =
$bookedSlotStmt->fetchColumn();

?>

<p class="mt-2">

Available Slots:
<b><?= $availableSlots ?></b>

<br>

Booked Slots:
<b><?= $bookedSlots ?></b>

</p>

<?php else: ?>

<div class="unavailable">
🔴 Unavailable
</div>

<p class="mt-2">
-
</p>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- TABLE -->

<div class="box">

<div class="row mb-3">

<div class="col-md-6">

<input
type="text"
id="doctorSearch"
class="form-control"
placeholder="🔍 Search Doctor...">

</div>

<div class="col-md-3">

<select
id="sortDoctor"
class="form-select">

<option value="az">
Doctor A-Z
</option>

<option value="za">
Doctor Z-A
</option>

</select>

</div>

<div class="col-md-3">

<select
id="sortDate"
class="form-select">

<option value="latest">
Latest Date
</option>

<option value="oldest">
Oldest Date
</option>

</select>

</div>

</div>

<form method="GET">

<div class="row mb-3">

<div class="col-md-4">

<input
type="date"
name="date"
class="form-control"
value="<?= $_GET['date'] ?? '' ?>">

</div>

<div class="col-md-2">

<button class="btn btn-primary">
Search
</button>

</div>

</div>

</form>

<h5 class="mb-4">
📜 Availability History
</h5>

<div class="table-responsive">

<table
id="availabilityTable"
class="table table-hover table-bordered align-middle">

<thead>

<tr>
<th>Doctor</th>
<th>Today Status</th>
<th>Upcoming Dates</th>
<th>Available Slots</th>
<th>Booked Slots</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php foreach($doctorList as $doctor): ?>

<?php

$dateStmt = $conn->prepare("
SELECT
TO_CHAR(AVAILABLE_DATE,'DD Mon YYYY') AS AVAIL_DATE
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE ACCOUNT_ID = :doctor
AND AVAILABLE_DATE >= TRUNC(SYSDATE)
ORDER BY AVAILABLE_DATE
");

$dateStmt->execute([
':doctor'=>$doctor['ACCOUNT_ID']
]);

$dates =
$dateStmt->fetchAll(PDO::FETCH_COLUMN);



$availableStmt = $conn->prepare("
SELECT COUNT(*)
FROM SYARMIMI.DOCTOR_SLOT
WHERE ACCOUNT_ID = :doctor
AND STATUS='Available'
");

$availableStmt->execute([
':doctor'=>$doctor['ACCOUNT_ID']
]);

$availableCount =
$availableStmt->fetchColumn();



$bookedStmt = $conn->prepare("
SELECT COUNT(*)
FROM SYARMIMI.DOCTOR_SLOT
WHERE ACCOUNT_ID = :doctor
AND STATUS='Booked'
");

$bookedStmt->execute([
':doctor'=>$doctor['ACCOUNT_ID']
]);

$bookedCount =
$bookedStmt->fetchColumn();



$todayStmt = $conn->prepare("
SELECT STATUS
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE ACCOUNT_ID = :doctor
AND AVAILABLE_DATE = TRUNC(SYSDATE)
");

$todayStmt->execute([
':doctor'=>$doctor['ACCOUNT_ID']
]);

$todayStatus =
$todayStmt->fetchColumn();

?>

<tr>

<td>
<b>Dr. <?= $doctor['USERNAME'] ?></b>
</td>

<td>

<?php if($todayStatus=='Available'): ?>

<span class="badge bg-success">
Available
</span>

<?php else: ?>

<span class="badge bg-danger">
Unavailable
</span>

<?php endif; ?>

</td>

<td>

<?= implode(
'<br>',
array_slice($dates,0,5)
) ?>

</td>

<td>

<span class="badge bg-success">

<?= $availableCount ?>

</span>

</td>

<td>

<span class="badge bg-danger">

<?= $bookedCount ?>

</span>

</td>

<td>

<a
href="doctor_slot_view.php?doctor=<?= $doctor['ACCOUNT_ID'] ?>"
class="btn btn-primary btn-sm">

View Schedule

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script>

<script>

$(document).ready(function(){

    var table = $('#availabilityTable').DataTable();

    $('#doctorSearch').on('keyup', function(){

        table.search(this.value).draw();

    });

});

</script>

</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

    var table = $('#availabilityTable').DataTable({

        pageLength: 10,

        order: [[0,'asc']],

        lengthMenu: [
            [10,25,50,100],
            [10,25,50,100]
        ]

    });

});

</script>

</body>
</html>