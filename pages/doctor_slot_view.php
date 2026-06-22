<?php
session_start();
include("../config/config.php");

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    die("Access Denied");
}

$doctorName = $_GET['doctor'] ?? '';
$selectedDate = $_GET['date'] ?? '';

/* GET DOCTOR */

$stmt = $conn->prepare("
SELECT ACCOUNT_ID
FROM SYARMIMI.HOSPITAL_STAFF
WHERE USERNAME = :doctor
");

$stmt->execute([
    ':doctor'=>$doctorName
]);

$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

$doctorId = $doctor['ACCOUNT_ID'] ?? 0;

/* GET SLOT */

$slotStmt = $conn->prepare("
SELECT
    DS.SLOT_TIME,
    DS.STATUS,
    A.PATIENT_NAME
FROM SYARMIMI.DOCTOR_SLOT DS

LEFT JOIN SYARMIMI.APPOINTMENT A
ON DS.APPOINTMENT_ID = A.APPOINTMENT_ID

WHERE DS.ACCOUNT_ID = :doctor
AND DS.SLOT_DATE = TO_DATE(:date,'YYYY-MM-DD')

ORDER BY DS.SLOT_TIME
");

$slotStmt->execute([
    ':doctor'=>$doctorId,
    ':date'=>$selectedDate
]);

$slotList = $slotStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>

<title>Doctor Slot Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI';
}

.box{
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.05);
}

.badge{
    padding:8px 12px;
}

</style>

</head>

<body>

<div class="container mt-4">

<a href="doctor_availability_admin.php"
class="btn btn-secondary mb-3">

← Back

</a>

<div class="box">

<h3>
👨‍⚕️ Dr. <?= htmlspecialchars($doctorName) ?>
</h3>

<p>
📅 <?= $selectedDate ?>
</p>

<hr>

<table class="table table-bordered align-middle">

<tr>
<th>Time Slot</th>
<th>Status</th>
<th>Patient</th>
</tr>

<?php foreach($slotList as $slot): ?>

<tr>

<td>

<?php

$start = $slot['SLOT_TIME'];

$end = date(
'H:i',
strtotime($slot['SLOT_TIME'].' +1 hour')
);

echo $start . ' - ' . $end;

?>

</td>

<td>

<?php

if($slot['STATUS'] == 'Booked')
{
    echo "<span class='badge bg-danger'>🔴 Booked</span>";
}
elseif($slot['STATUS'] == 'Lunch Break')
{
    echo "<span class='badge bg-warning text-dark'>🍽 Lunch Break</span>";
}
elseif($slot['STATUS'] == 'Unavailable')
{
    echo "<span class='badge bg-secondary'>Unavailable</span>";
}
else
{
    echo "<span class='badge bg-success'>🟢 Available</span>";
}

?>

</td>

<td>

<?= $slot['PATIENT_NAME'] ?? '-' ?>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php if(count($slotList) == 0): ?>

<div class="alert alert-warning">

No slots found for this doctor/date.

</div>

<?php endif; ?>

</div>

</div>

</body>
</html>