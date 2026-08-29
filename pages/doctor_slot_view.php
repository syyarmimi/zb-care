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
   SAFE OUTPUT
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GET PARAMETERS
========================================================= */

$doctorId =
    (int)(
        $_GET['doctor']
        ?? 0
    );


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
========================================================= */

if (
    $from ===
    'admin_appointment'
) {

    $backUrl =
        'admin_appointment.php';

} else {

    $backUrl =
        'doctor_availability_admin.php';
}


/* =========================================================
   GET DOCTOR
========================================================= */

$stmt =
    $conn->prepare("

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

    ':id' =>
        $doctorId

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

$slotStmt =
    $conn->prepare("

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
            TRUNC(
                DS.SLOT_DATE
            )
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


/* =========================================================
   STATISTICS
========================================================= */

$totalSlots =
    count(
        $slotList
    );


$availableSlots = 0;

$bookedSlots = 0;

$unavailableSlots = 0;


foreach (
    $slotList
    as
    $slot
) {

    $status =
        strtolower(
            trim(
                $slot['STATUS']
                ?? ''
            )
        );


    if (
        $status ===
        'booked'
    ) {

        $bookedSlots++;

    }
    elseif (
        $status ===
        'available'
    ) {

        $availableSlots++;

    }
    else {

        $unavailableSlots++;
    }
}


/* =========================================================
   DISPLAY DATE
========================================================= */

$displayDate =
    $selectedDate;


try {

    $dateObject =
        new DateTime(
            $selectedDate
        );


    $displayDate =
        $dateObject->format(
            'd F Y'
        );

}
catch (Throwable $e) {

    $displayDate =
        $selectedDate;
}

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


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:#f5f7fa;

    color:#1f2937;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;
}


.page-wrapper{

    min-height:100vh;

    padding:28px 32px 45px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-btn{

    display:inline-flex;

    align-items:center;

    gap:7px;

    margin-bottom:20px;

    padding:8px 13px;

    background:#ffffff;

    border:1px solid #e2e8f0;

    border-radius:8px;

    color:#475569;

    font-size:12px;

    font-weight:600;

    text-decoration:none;

    transition:.2s;
}


.back-btn:hover{

    background:#f8fafc;

    border-color:#cbd5e1;

    color:#1e293b;

    transform:translateX(-2px);
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header{

    margin-bottom:22px;
}


.page-title{

    display:flex;

    align-items:center;

    gap:11px;

    margin:0;

    color:#111827;

    font-size:27px;

    font-weight:750;

    letter-spacing:-.4px;
}


.title-icon{

    width:40px;

    height:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:10px;

    color:#2563eb;

    font-size:18px;
}


.page-subtitle{

    margin-top:7px;

    color:#94a3b8;

    font-size:13px;
}


/* =========================================================
   DOCTOR CARD
========================================================= */

.doctor-card{

    position:relative;

    overflow:hidden;

    margin-bottom:18px;

    padding:20px 22px;

    background:#ffffff;

    border:1px solid #e5e9ef;

    border-radius:13px;
}


.doctor-card::before{

    content:"";

    position:absolute;

    top:0;

    left:0;

    bottom:0;

    width:4px;

    background:#2563eb;
}


.doctor-content{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;
}


.doctor-left{

    display:flex;

    align-items:center;

    gap:15px;
}


.doctor-avatar{

    width:48px;

    height:48px;

    min-width:48px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:12px;

    color:#2563eb;

    font-size:21px;
}


.doctor-name{

    margin:0;

    color:#111827;

    font-size:17px;

    font-weight:700;
}


.doctor-department{

    display:flex;

    align-items:center;

    gap:6px;

    margin-top:5px;

    color:#64748b;

    font-size:12px;
}


.date-display{

    padding:9px 13px;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:8px;

    color:#475569;

    font-size:12px;

    font-weight:600;

    white-space:nowrap;
}


.date-display i{

    margin-right:5px;

    color:#2563eb;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats-row{

    margin-bottom:18px;
}


.stat-card{

    height:100%;

    padding:16px 17px;

    background:#ffffff;

    border:1px solid #e5e9ef;

    border-radius:11px;
}


.stat-inner{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;
}


.stat-label{

    color:#94a3b8;

    font-size:11px;

    font-weight:600;
}


.stat-value{

    margin-top:3px;

    color:#111827;

    font-size:25px;

    font-weight:700;

    line-height:1.1;
}


.stat-icon{

    width:38px;

    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:16px;
}


.stat-total{

    background:#eff6ff;

    color:#2563eb;
}


.stat-available{

    background:#ecfdf5;

    color:#16a34a;
}


.stat-booked{

    background:#fef2f2;

    color:#dc2626;
}


.stat-unavailable{

    background:#f8fafc;

    color:#64748b;
}


/* =========================================================
   MAIN CARD
========================================================= */

.content-card{

    background:#ffffff;

    border:1px solid #e5e9ef;

    border-radius:13px;

    overflow:hidden;
}


.card-header-custom{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    padding:18px 20px;

    border-bottom:1px solid #edf0f3;
}


.card-title-custom{

    display:flex;

    align-items:center;

    gap:9px;

    margin:0;

    color:#1f2937;

    font-size:15px;

    font-weight:700;
}


.card-title-icon{

    width:32px;

    height:32px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:8px;

    color:#475569;

    font-size:14px;
}


.card-description{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   DATE FILTER
========================================================= */

.date-filter{

    width:210px;
}


.date-filter label{

    display:block;

    margin-bottom:5px;

    color:#64748b;

    font-size:10px;

    font-weight:650;

    text-transform:uppercase;

    letter-spacing:.3px;
}


.form-control{

    min-height:39px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:12px;
}


.form-control:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


/* =========================================================
   TABLE
========================================================= */

.table-container{

    padding:0 20px 20px;
}


.table-responsive{

    border:1px solid #edf0f3;

    border-radius:9px;

    overflow:hidden;
}


.table{

    width:100%;

    margin:0;

    vertical-align:middle;
}


.table thead th{

    padding:11px 14px;

    background:#f8fafc;

    border:0;

    border-bottom:1px solid #e5e7eb;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    letter-spacing:.4px;

    text-transform:uppercase;
}


.table tbody td{

    padding:14px;

    border-color:#eef1f4;

    color:#374151;

    font-size:12px;
}


.table tbody tr:last-child td{

    border-bottom:0;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


/* =========================================================
   SLOT TIME
========================================================= */

.slot-time{

    display:flex;

    align-items:center;

    gap:9px;

    color:#334155;

    font-weight:650;
}


.slot-time-icon{

    width:30px;

    height:30px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f8fafc;

    border-radius:7px;

    color:#64748b;

    font-size:12px;
}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:6px 9px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;
}


.status-available{

    background:#ecfdf5;

    color:#15803d;
}


.status-booked{

    background:#fef2f2;

    color:#dc2626;
}


.status-lunch{

    background:#fff7ed;

    color:#c2410c;
}


.status-unavailable{

    background:#f1f5f9;

    color:#64748b;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-box{

    display:flex;

    align-items:center;

    gap:8px;
}


.patient-icon{

    width:29px;

    height:29px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f8fafc;

    border-radius:50%;

    color:#64748b;

    font-size:12px;
}


.patient-name{

    color:#334155;

    font-weight:600;
}


.no-patient{

    color:#cbd5e1;

    font-size:12px;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{

    padding:48px 20px;

    text-align:center;
}


.empty-icon{

    width:54px;

    height:54px;

    margin:0 auto 13px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f8fafc;

    border:1px solid #e2e8f0;

    border-radius:14px;

    color:#94a3b8;

    font-size:22px;
}


.empty-title{

    margin-bottom:5px;

    color:#475569;

    font-size:13px;

    font-weight:650;
}


.empty-text{

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:768px){

    .page-wrapper{

        padding:20px 16px 35px;
    }


    .page-title{

        font-size:23px;
    }


    .doctor-content{

        align-items:flex-start;

        flex-direction:column;
    }


    .date-display{

        width:100%;
    }


    .card-header-custom{

        align-items:flex-start;

        flex-direction:column;
    }


    .date-filter{

        width:100%;
    }


    .table-container{

        padding:0 14px 14px;
    }

}

</style>

</head>


<body>


<div class="page-wrapper">


<!-- =====================================================
     BACK
===================================================== -->

<a
    href="<?= h($backUrl) ?>"
    class="back-btn"
>

<i class="bi bi-arrow-left"></i>

Back

</a>


<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="page-header">


<h1 class="page-title">

<span class="title-icon">

<i class="bi bi-clock-history"></i>

</span>

Doctor Time Slots

</h1>


<div class="page-subtitle">

View the doctor's availability, booked appointments and daily consultation schedule.

</div>


</div>


<!-- =====================================================
     DOCTOR INFORMATION
===================================================== -->

<div class="doctor-card">


<div class="doctor-content">


<div class="doctor-left">


<div class="doctor-avatar">

<i class="bi bi-person-badge"></i>

</div>


<div>


<h2 class="doctor-name">

Dr.
<?= h(
    $doctor['USERNAME']
) ?>

</h2>


<div class="doctor-department">

<i class="bi bi-building"></i>

<?= h(
    $doctor['DEPARTMENT']
) ?>

Department

</div>


</div>


</div>


<div class="date-display">

<i class="bi bi-calendar3"></i>

<?= h(
    $displayDate
) ?>

</div>


</div>


</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="row g-3 stats-row">


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-inner">


<div>

<div class="stat-label">

Total Slots

</div>

<div class="stat-value">

<?= $totalSlots ?>

</div>

</div>


<div class="stat-icon stat-total">

<i class="bi bi-calendar2-week"></i>

</div>


</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-inner">


<div>

<div class="stat-label">

Available

</div>

<div class="stat-value">

<?= $availableSlots ?>

</div>

</div>


<div class="stat-icon stat-available">

<i class="bi bi-check-circle"></i>

</div>


</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-inner">


<div>

<div class="stat-label">

Booked

</div>

<div class="stat-value">

<?= $bookedSlots ?>

</div>

</div>


<div class="stat-icon stat-booked">

<i class="bi bi-calendar-check"></i>

</div>


</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-inner">


<div>

<div class="stat-label">

Unavailable

</div>

<div class="stat-value">

<?= $unavailableSlots ?>

</div>

</div>


<div class="stat-icon stat-unavailable">

<i class="bi bi-slash-circle"></i>

</div>


</div>


</div>


</div>


</div>


<!-- =====================================================
     SLOT CONTENT
===================================================== -->

<div class="content-card">


<!-- =====================================================
     HEADER + DATE FILTER
===================================================== -->

<div class="card-header-custom">


<div>


<div class="card-title-custom">


<span class="card-title-icon">

<i class="bi bi-list-ul"></i>

</span>


<div>

Daily Schedule

<div class="card-description">

Consultation slots for <?= h($displayDate) ?>

</div>

</div>


</div>


</div>


<div class="date-filter">


<form method="GET">


<input
    type="hidden"
    name="doctor"
    value="<?= h($doctorId) ?>"
>


<input
    type="hidden"
    name="from"
    value="<?= h($from) ?>"
>


<label>

Select Date

</label>


<input
    type="date"
    name="date"
    class="form-control"
    value="<?= h($selectedDate) ?>"
    min="<?= date('Y-m-d') ?>"
    onchange="this.form.submit()"
>


</form>


</div>


</div>


<!-- =====================================================
     TABLE
===================================================== -->

<div class="table-container">


<?php if (
    count($slotList) > 0
): ?>


<div class="table-responsive">


<table class="table align-middle">


<thead>


<tr>


<th width="35%">

Time Slot

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


<?php foreach (
    $slotList
    as
    $slot
): ?>


<?php


$start =
    trim(
        $slot['SLOT_TIME']
        ?? ''
    );


$end =
    '';


if ($start !== '') {

    $timestamp =
        strtotime(
            $start
            .
            ' +1 hour'
        );


    if (
        $timestamp !== false
    ) {

        $end =
            date(
                'H:i',
                $timestamp
            );
    }
}


$status =
    strtolower(
        trim(
            $slot['STATUS']
            ?? ''
        )
    );


?>


<tr>


<!-- =================================================
     TIME
================================================= -->

<td>


<div class="slot-time">


<span class="slot-time-icon">

<i class="bi bi-clock"></i>

</span>


<span>

<?= h($start) ?>

<?php if ($end !== ''): ?>

<span class="text-muted mx-1">

–

</span>

<?= h($end) ?>

<?php endif; ?>

</span>


</div>


</td>


<!-- =================================================
     STATUS
================================================= -->

<td>


<?php if (
    $status ===
    'booked'
): ?>


<span class="status-badge status-booked">

<i class="bi bi-calendar-check"></i>

Booked

</span>


<?php elseif (
    $status ===
    'lunch break'
): ?>


<span class="status-badge status-lunch">

<i class="bi bi-cup-hot"></i>

Lunch Break

</span>


<?php elseif (
    $status ===
    'unavailable'
): ?>


<span class="status-badge status-unavailable">

<i class="bi bi-slash-circle"></i>

Unavailable

</span>


<?php else: ?>


<span class="status-badge status-available">

<i class="bi bi-check-circle"></i>

Available

</span>


<?php endif; ?>


</td>


<!-- =================================================
     PATIENT
================================================= -->

<td>


<?php if (
    !empty(
        $slot['PATIENT_NAME']
    )
): ?>


<div class="patient-box">


<span class="patient-icon">

<i class="bi bi-person"></i>

</span>


<span class="patient-name">

<?= h(
    $slot['PATIENT_NAME']
) ?>

</span>


</div>


<?php else: ?>


<span class="no-patient">

—

</span>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<!-- =================================================
     EMPTY STATE
================================================= -->

<div class="empty-state">


<div class="empty-icon">

<i class="bi bi-calendar-x"></i>

</div>


<div class="empty-title">

No Time Slots Available

</div>


<div class="empty-text">

No consultation slots were found for Dr. <?= h($doctor['USERNAME']) ?>
on <?= h($displayDate) ?>.

</div>


</div>


<?php endif; ?>


</div>


</div>


</div>


</body>

</html>