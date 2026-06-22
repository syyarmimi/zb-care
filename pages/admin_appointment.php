<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");
include("../includes/send_mail.php");

/* =========================
   APPROVE APPOINTMENT
========================= */

if(isset($_GET['approve'])){

    $id = $_GET['approve'];

    /* GET APPOINTMENT INFO */

  $stmt = $conn->prepare("
SELECT PATIENT_NAME,
       EMAIL,
       DOCTOR_NAME,
       ACCOUNT_ID,
       APPOINTMENT_DATE,
       APPOINTMENT_TIME
FROM SYARMIMI.APPOINTMENT
WHERE APPOINTMENT_ID = :id
");

$stmt->execute([
    ':id'=>$id
]);

$appointment = $stmt->fetch(PDO::FETCH_ASSOC);

$doctorId = $appointment['ACCOUNT_ID'];

/* CHECK APPROVED SLOT */

$check = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.APPOINTMENT
    WHERE DOCTOR_NAME = :doctor
    AND APPOINTMENT_DATE = :date
    AND APPOINTMENT_TIME = :time
    AND STATUS = 'Approved'
    AND APPOINTMENT_ID <> :id
    ");

    $check->execute([
        ':doctor'=>$appointment['DOCTOR_NAME'],
        ':date'=>$appointment['APPOINTMENT_DATE'],
        ':time'=>$appointment['APPOINTMENT_TIME'],
        ':id'=>$id
    ]);

    $slotTaken = $check->fetchColumn();

    if($slotTaken > 0){

        echo "
        <script>
        alert('This slot is already approved for another patient.');
        window.location='admin_appointment.php';
        </script>
        ";
        exit();
    }

 /* APPROVE */

$stmt = $conn->prepare("
UPDATE SYARMIMI.APPOINTMENT
SET STATUS='Approved'
WHERE APPOINTMENT_ID=:id
");

$stmt->execute([
    ':id'=>$id
]);

/* =========================
   UPDATE SLOT TO BOOKED
========================= */

$slotTime = substr(
    $appointment['APPOINTMENT_TIME'],
    0,
    5
);

$updateSlot = $conn->prepare("
UPDATE SYARMIMI.DOCTOR_SLOT
SET
    STATUS = 'Booked',
    CURRENT_PATIENT = 1,
    APPOINTMENT_ID = :appointment
WHERE ACCOUNT_ID = :doctor
AND TO_CHAR(SLOT_DATE,'DD-MON-RR') = :date
AND SLOT_TIME = :time
");

$updateSlot->execute([
    ':appointment' => $id,
    ':doctor'      => $doctorId,
    ':date'        => $appointment['APPOINTMENT_DATE'],
    ':time'        => $slotTime
]);

$notify = $conn->prepare("
INSERT INTO SYARMIMI.APPOINTMENT_NOTIFICATION
(
    NOTIFICATION_ID,
    ACCOUNT_ID,
    MESSAGE,
    IS_READ,
    CREATED_AT
)
VALUES
(
    SYARMIMI.NOTIF_SEQ.NEXTVAL,
    :doctor,
    :message,
    0,
    SYSDATE
)
");

$notify->execute([
':doctor'=>$doctorId,
':message'=>'New appointment approved for '.$appointment['PATIENT_NAME']
]);

$emailSent = sendAppointmentApprovalEmail(
    $appointment['EMAIL'],
    $appointment['PATIENT_NAME'],
    $appointment['DOCTOR_NAME'],
    $appointment['APPOINTMENT_DATE'],
    $appointment['APPOINTMENT_TIME']
);

if($emailSent){

    $_SESSION['success'] =
    "Appointment approved and email sent successfully.";

}else{

    $_SESSION['error'] =
    "Appointment approved but email failed to send.";

}

header("Location: admin_appointment.php");
exit();

}

/* =========================
   REJECT APPOINTMENT
========================= */

if(isset($_GET['reject'])){

    $id = $_GET['reject'];

    $stmtPatient = $conn->prepare("
SELECT PATIENT_NAME, EMAIL
FROM SYARMIMI.APPOINTMENT
WHERE APPOINTMENT_ID = :id
");

$stmtPatient->execute([
    ':id'=>$id
]);

$patient = $stmtPatient->fetch(PDO::FETCH_ASSOC);

    $sql = "
    UPDATE SYARMIMI.APPOINTMENT
    SET STATUS='Rejected'
    WHERE APPOINTMENT_ID=:id
    ";

    $stmt = $conn->prepare($sql);

   $stmt->execute([
    ':id' => $id
]);

$emailSent = sendAppointmentRejectedEmail(
    $patient['EMAIL'],
    $patient['PATIENT_NAME']
);

if($emailSent){

    $_SESSION['success'] =
    "Appointment rejected and email sent successfully.";

}else{

    $_SESSION['error'] =
    "Appointment rejected but email failed to send.";

}

header("Location: admin_appointment.php");
exit();
}

/* =========================
   COUNTS
========================= */

$pendingCount = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Pending'
")->fetchColumn();

$approvedCount = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Approved'
")->fetchColumn();

$rejectedCount = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Rejected'
")->fetchColumn();

$doctorAvailability = $conn->query("
SELECT
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

$scheduleDoctors = $conn->query("
SELECT
ACCOUNT_ID,
USERNAME
FROM SYARMIMI.HOSPITAL_STAFF
WHERE LOWER(ROLE)='doctor'
ORDER BY USERNAME
")->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   FETCH APPOINTMENTS
========================= */

$sql = "
SELECT *
FROM SYARMIMI.APPOINTMENT
ORDER BY APPOINTMENT_ID DESC
";

$stmt = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<title>Appointment Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI', sans-serif;
}

.page-title{
    font-size:42px;
    font-weight:800;
    color:#0f172a;
}

.stats-card{
    border-radius:20px;
    padding:25px;
    color:white;
    height:100%;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

.pending-bg{
    background:linear-gradient(135deg,#f59e0b,#facc15);
}

.approved-bg{
    background:linear-gradient(135deg,#16a34a,#22c55e);
}

.rejected-bg{
    background:linear-gradient(135deg,#dc2626,#ef4444);
}

.stats-card h2{
    font-size:40px;
    font-weight:700;
}

.stats-card p{
    margin:0;
    font-size:18px;
}

.table-box{
    background:white;
    border-radius:25px;
    padding:25px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
}

.table th{
    background:#2563eb;
    color:white;
    border:none;
    padding:16px;
}

.table td{
    vertical-align:middle;
    padding:16px;
}

.badge-pending{
    background:#facc15;
    color:black;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
}

.badge-approved{
    background:#22c55e;
    color:white;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
}

.badge-rejected{
    background:#ef4444;
    color:white;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
}

.patient-box strong{
    font-size:16px;
}

.patient-box small{
    color:#64748b;
}

.action-btn{
    border-radius:12px;
    padding:8px 16px;
    font-weight:600;
}

.table tbody tr:hover{
    background:#f8fafc;
    transition:0.2s;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<h2 class="page-title mb-4">
📅 Appointment Management
</h2>

<?php if(isset($_SESSION['success'])): ?>

<div class="alert alert-success alert-dismissible fade show shadow-sm">

<i class="bi bi-check-circle-fill"></i>
<?= $_SESSION['success']; ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php unset($_SESSION['success']); endif; ?>

<?php if(isset($_SESSION['error'])): ?>

<div class="alert alert-danger alert-dismissible fade show shadow-sm">

<i class="bi bi-exclamation-triangle-fill"></i>
<?= $_SESSION['error']; ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php unset($_SESSION['error']); endif; ?>

<!-- =========================
     STATISTICS
========================= -->

<div class="row g-4 mb-4">

<div class="col-md-4">

<div class="stats-card pending-bg">

<h2><?= $pendingCount ?></h2>

<p>
Pending Appointments
</p>

</div>

</div>

<div class="col-md-4">

<div class="stats-card approved-bg">

<h2><?= $approvedCount ?></h2>

<p>
Approved Appointments
</p>

</div>

</div>

<div class="col-md-4">

<div class="stats-card rejected-bg">

<h2><?= $rejectedCount ?></h2>

<p>
Rejected Appointments
</p>

</div>

</div>

</div>

<div class="table-box mb-4">

<h5 class="mb-3">
📅 Today's Doctor Availability
</h5>

<div class="table-responsive">

<table class="table align-middle">

<thead>

<tr>
<th>Doctor</th>
<th>Status</th>
<th>Time Slot</th>
</tr>

</thead>

<tbody>

<?php foreach($doctorAvailability as $doctor): ?>

<tr>

<td>
<b>Dr. <?= htmlspecialchars($doctor['USERNAME']) ?></b>
</td>

<td>

<?php if($doctor['STATUS']=="Available"): ?>

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

<?php if($doctor['STATUS']=="Available"): ?>

<?= $doctor['START_TIME'] ?>
-
<?= $doctor['END_TIME'] ?>

<?php else: ?>

-
<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<div class="table-box mb-4">

<h5 class="mb-3">
📅 Doctor Future Schedule Overview
</h5>

<div class="table-responsive">

<table class="table align-middle">

<thead>

<tr>
<th>Doctor</th>
<th>Upcoming Dates</th>
<th>Available Slots</th>
<th>Booked Slots</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php foreach($scheduleDoctors as $doctor): ?>

<?php

$dateStmt = $conn->prepare("
SELECT
TO_CHAR(
AVAILABLE_DATE,
'DD Mon YYYY'
) AS AVAIL_DATE
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE ACCOUNT_ID=:doctor
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
WHERE ACCOUNT_ID=:doctor
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
WHERE ACCOUNT_ID=:doctor
AND STATUS='Booked'
");

$bookedStmt->execute([
':doctor'=>$doctor['ACCOUNT_ID']
]);

$bookedCount =
$bookedStmt->fetchColumn();

?>

<tr>

<td>

<b>
Dr. <?= htmlspecialchars($doctor['USERNAME']) ?>
</b>

</td>

<td>

<?php

foreach(array_slice($dates,0,5) as $d)
{
echo "
<span class='badge bg-info me-1 mb-1'>
$d
</span>
";
}

?>

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

<!-- =========================
     TABLE
========================= -->

<div class="table-box">

<div class="table-responsive">

<table class="table align-middle">

<thead>

<tr>

<th>ID</th>
<th>Patient</th>
<th>Gender</th>
<th>Phone</th>
<th>Department</th>
<th>Doctor</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Actions</th>


</tr>

</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>

<td>
<b>#<?= $row['APPOINTMENT_ID'] ?></b>
</td>

<td class="patient-box">

<strong><?= $row['PATIENT_NAME'] ?></strong><br>

<small><?= $row['EMAIL'] ?></small>

</td>

<td>

<?= $row['GENDER'] ?>

</td>

<td>

<?= $row['PHONE'] ?>

</td>

<td>

<?= $row['DEPARTMENT'] ?>

</td>


<td>

<?= $row['DOCTOR_NAME'] ?>

</td>


<td>

<?= $row['APPOINTMENT_DATE'] ?>

</td>

<td>

<?= $row['APPOINTMENT_TIME'] ?>

</td>

<td>

<?php

$status = $row['STATUS'];

if($status == 'Pending'){

    echo "<span class='badge-pending'>Pending</span>";

}
elseif($status == 'Approved'){

    echo "<span class='badge-approved'>Approved</span>";

}
else{

    echo "<span class='badge-rejected'>Rejected</span>";

}

?>

</td>

<td>

<?php if($status == 'Pending'): ?>

<a href="?approve=<?= $row['APPOINTMENT_ID'] ?>"
class="btn btn-success btn-sm action-btn">

<i class="bi bi-check-circle"></i>
Approve

</a>

<a href="?reject=<?= $row['APPOINTMENT_ID'] ?>"
class="btn btn-danger btn-sm action-btn">

<i class="bi bi-x-circle"></i>
Reject

</a>

<?php else: ?>

<span class="text-muted fw-semibold">
Completed
</span>

<?php endif; ?>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div
class="modal fade"
id="doctorAvailabilityModal">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
📅 Doctor Availability Today
</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<table class="table">

<tr>
<th>Doctor</th>
<th>Status</th>
<th>Time Slot</th>
</tr>

<?php foreach($doctorAvailability as $doctor): ?>

<tr>

<td>
Dr. <?= htmlspecialchars($doctor['USERNAME']) ?>
</td>

<td>

<?php if($doctor['STATUS']=="Available"): ?>

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

<?php if($doctor['STATUS']=="Available"): ?>

<?= $doctor['START_TIME'] ?>
-
<?= $doctor['END_TIME'] ?>

<?php else: ?>

-

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

</div>

</div>



</body>
</html>