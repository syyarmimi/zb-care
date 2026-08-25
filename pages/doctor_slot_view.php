<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] != 'admin'
) {
    die("Access Denied");
}


/* =========================================================
   GET PARAMETERS
========================================================= */

$doctorId = $_GET['doctor'] ?? 0;

$selectedDate =
    $_GET['date']
    ??
    date('Y-m-d');

$from =
    $_GET['from']
    ??
    '';


/* =========================================================
   BACK URL

   If user comes from Admin Appointment:
   -> back to admin_appointment.php

   Otherwise:
   -> back to doctor_availability_admin.php
========================================================= */

if ($from === 'admin_appointment') {

    $backUrl =
        'admin_appointment.php';

} else {

    $backUrl =
        'doctor_availability_admin.php';

}


/* =========================================================
   GET DOCTOR
========================================================= */

$stmt = $conn->prepare("

    SELECT

        ACCOUNT_ID,
        USERNAME,
        DEPARTMENT

    FROM
        SYARMIMI.HOSPITAL_STAFF

    WHERE
        ACCOUNT_ID = :id

");


$stmt->execute([

    ':id' => $doctorId

]);


$doctor =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$doctor) {

    die("Doctor not found");

}


/* =========================================================
   GET SLOTS
========================================================= */

$slotStmt = $conn->prepare("

    SELECT

        DS.SLOT_TIME,
        DS.STATUS,
        A.PATIENT_NAME

    FROM
        SYARMIMI.DOCTOR_SLOT DS

    LEFT JOIN
        SYARMIMI.APPOINTMENT A

        ON
        DS.APPOINTMENT_ID =
        A.APPOINTMENT_ID

    WHERE
        DS.ACCOUNT_ID = :doctor

    AND
        TRUNC(DS.SLOT_DATE)
        =
        TO_DATE(
            :date,
            'YYYY-MM-DD'
        )

    ORDER BY
        DS.SLOT_TIME

");


$slotStmt->execute([

    ':doctor' =>
        $doctorId,

    ':date' =>
        $selectedDate

]);


$slotList =
    $slotStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Doctor Slot Details
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<style>

body {

    background:#f1f5f9;

    font-family:
        'Segoe UI',
        sans-serif;

}


.slot-card {

    background:white;

    border-radius:16px;

    padding:25px;

    box-shadow:
        0 5px 15px
        rgba(0,0,0,.08);

}


.slot-title {

    font-size:32px;

    font-weight:600;

    margin-bottom:20px;

}


.badge {

    padding:8px 12px;

}


</style>

</head>


<body>


<div class="container-fluid p-4">


<!-- =====================================================
     BACK BUTTON
===================================================== -->

<a
    href="<?= htmlspecialchars($backUrl) ?>"
    class="btn btn-secondary mb-3"
>

← Back

</a>


<!-- =====================================================
     SLOT CARD
===================================================== -->

<div class="slot-card">


<h2 class="slot-title">

🕒 Doctor Time Slots

</h2>


<h5>

👨‍⚕️ Dr.
<?= htmlspecialchars(
    $doctor['USERNAME']
) ?>

</h5>


<p class="text-muted">

Department:

<?= htmlspecialchars(
    $doctor['DEPARTMENT']
) ?>

</p>


<hr>


<!-- =====================================================
     DATE FILTER
===================================================== -->

<div class="row mb-4">


<div class="col-md-4">


<form method="GET">


<!-- KEEP DOCTOR ID -->

<input
    type="hidden"
    name="doctor"
    value="<?= htmlspecialchars($doctorId) ?>"
>


<!-- =================================================
     IMPORTANT FIX

     Preserve where this page came from.

     Example:
     from=admin_appointment

     Without this hidden input, selecting another
     date removes the "from" parameter.
================================================= -->

<input
    type="hidden"
    name="from"
    value="<?= htmlspecialchars($from) ?>"
>


<label class="form-label">

Select Date

</label>


<input
    type="date"
    name="date"
    class="form-control"
    value="<?= htmlspecialchars($selectedDate) ?>"
    min="<?= date('Y-m-d') ?>"
    onchange="this.form.submit()"
>


</form>


</div>


</div>


<!-- =====================================================
     SLOT TABLE
===================================================== -->

<div class="table-responsive">


<table
    class="
        table
        table-bordered
        align-middle
    "
>


<thead>


<tr>


<th width="35%">

Slot

</th>


<th width="25%">

Status

</th>


<th>

Patient

</th>


</tr>


</thead>


<tbody>


<?php if (count($slotList) > 0): ?>


<?php foreach ($slotList as $slot): ?>


<tr>


<!-- SLOT TIME -->

<td>


<?php

$start =
    $slot['SLOT_TIME'];


$end =
    date(
        'H:i',
        strtotime(
            $slot['SLOT_TIME']
            .
            ' +1 hour'
        )
    );


echo
    htmlspecialchars($start)
    .
    " - "
    .
    htmlspecialchars($end);

?>


</td>


<!-- STATUS -->

<td>


<?php


if (
    $slot['STATUS']
    ==
    'Booked'
) {

    echo "
        <span class='badge bg-danger'>
            Booked
        </span>
    ";

}


elseif (
    $slot['STATUS']
    ==
    'Lunch Break'
) {

    echo "
        <span class='badge bg-warning text-dark'>
            Lunch Break
        </span>
    ";

}


elseif (
    $slot['STATUS']
    ==
    'Unavailable'
) {

    echo "
        <span class='badge bg-secondary'>
            Unavailable
        </span>
    ";

}


else {

    echo "
        <span class='badge bg-success'>
            Available
        </span>
    ";

}


?>


</td>


<!-- PATIENT -->

<td>


<?= htmlspecialchars(
    $slot['PATIENT_NAME']
    ??
    '-'
) ?>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td colspan="3">


<div
    class="
        alert
        alert-warning
        mb-0
    "
>


No slots found for this doctor
on selected date.


</div>


</td>


</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


</body>

</html>