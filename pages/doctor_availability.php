<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   SECURITY
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    header("Location: ../auth/login.php");
    exit();
}


$username = $_SESSION['user'] ?? '';


/* =========================================================
   GET DOCTOR
========================================================= */

$stmtDoctor = $conn->prepare("
    SELECT
        ACCOUNT_ID,
        USERNAME,
        DEPARTMENT
    FROM SYARMIMI.HOSPITAL_STAFF
    WHERE LOWER(USERNAME) = LOWER(:username)
");

$stmtDoctor->execute([
    ':username' => $username
]);

$doctor = $stmtDoctor->fetch(PDO::FETCH_ASSOC);

if (!$doctor) {
    die("Doctor account not found.");
}

$doctorId       = (int)$doctor['ACCOUNT_ID'];
$doctorUsername = $doctor['USERNAME'] ?? $username;
$department     = $doctor['DEPARTMENT'] ?? '-';


/* =========================================================
   SELECTED DATE
========================================================= */

$selectedDate = trim($_GET['date'] ?? '');

$appointmentsOnDate = [];
$slotList = [];


/* =========================================================
   VALIDATE SELECTED DATE

   Important:
   Only allow YYYY-MM-DD before putting date into SQL.
========================================================= */

if (
    !empty($selectedDate) &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
) {
    $selectedDate = '';
}


/* =========================================================
   GET APPOINTMENTS FOR SELECTED DATE
========================================================= */

if (!empty($selectedDate)) {

    /*
       selectedDate is safe here because it has already
       passed the strict YYYY-MM-DD regex above.
    */

    $safeSelectedDate = $selectedDate;

    try {

        $stmt = $conn->prepare("

            SELECT
                A.APPOINTMENT_ID,
                A.PATIENT_NAME,
                DS.SLOT_TIME AS APPOINTMENT_TIME,
                A.STATUS

            FROM SYARMIMI.DOCTOR_SLOT DS

            JOIN SYARMIMI.APPOINTMENT A
                ON DS.APPOINTMENT_ID = A.APPOINTMENT_ID

            WHERE DS.ACCOUNT_ID = :doctor

            AND TRUNC(DS.SLOT_DATE)
                = TO_DATE('$safeSelectedDate', 'YYYY-MM-DD')

            AND UPPER(TRIM(A.STATUS)) = 'APPROVED'

            ORDER BY
                DS.SLOT_TIME ASC,
                A.APPOINTMENT_ID DESC
        ");

        $stmt->execute([
            ':doctor' => $doctorId
        ]);

        $appointmentsOnDate =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {

        $appointmentsOnDate = [];
    }
}


/* =========================================================
   GET SLOTS FOR SELECTED DATE
========================================================= */

if (!empty($selectedDate)) {

    $safeSelectedDate = $selectedDate;

    try {

        $slotStmt = $conn->prepare("

            SELECT
                DS.SLOT_ID,
                DS.SLOT_TIME,
                DS.STATUS,
                DS.CURRENT_PATIENT,
                DS.MAX_PATIENT,
                DS.APPOINTMENT_ID,
                A.PATIENT_NAME

            FROM SYARMIMI.DOCTOR_SLOT DS

            LEFT JOIN SYARMIMI.APPOINTMENT A
                ON DS.APPOINTMENT_ID = A.APPOINTMENT_ID

            WHERE DS.ACCOUNT_ID = :doctor

            AND TRUNC(DS.SLOT_DATE)
                = TO_DATE('$safeSelectedDate', 'YYYY-MM-DD')

            ORDER BY DS.SLOT_TIME ASC
        ");

        $slotStmt->execute([
            ':doctor' => $doctorId
        ]);

        $slotList =
            $slotStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {

        $slotList = [];
    }
}


/* =========================================================
   AJAX SAVE AVAILABILITY
========================================================= */

if (isset($_POST['save'])) {

    header('Content-Type: text/plain; charset=UTF-8');


    /* =====================================================
       GET POST VALUES
    ===================================================== */

    $date = substr(
        trim($_POST['date'] ?? ''),
        0,
        10
    );

    $status =
        trim($_POST['status'] ?? '');


    /* =====================================================
       VALIDATE DATE
    ===================================================== */

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {

        echo "Invalid date.";
        exit();
    }


    /*
       Additional real-date validation.

       Example:
       2026-99-99 will fail.
    */

    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $date
    ) {

        echo "Invalid date.";
        exit();
    }


    /* =====================================================
       VALIDATE STATUS
    ===================================================== */

    if (
        !in_array(
            $status,
            [
                'Available',
                'Unavailable'
            ],
            true
        )
    ) {

        echo "Invalid availability status.";
        exit();
    }


    /*
       IMPORTANT FIX

       The date has passed strict validation, therefore we
       can safely place it directly inside Oracle TO_DATE().

       This follows the approach used by your older working
       doctor_availability.php.

       Avoid:

       TO_DATE(:available_date,'YYYY-MM-DD')

       with PDO ODBC here.
    */

    $safeDate = $date;


    try {

        $conn->beginTransaction();


        /* =================================================
           CHECK EXISTING AVAILABILITY
        ================================================= */

        $checkStmt = $conn->prepare("

            SELECT COUNT(*)

            FROM SYARMIMI.DOCTOR_AVAILABILITY

            WHERE ACCOUNT_ID = :doctor

            AND TRUNC(AVAILABLE_DATE)
                = TO_DATE('$safeDate', 'YYYY-MM-DD')
        ");

        $checkStmt->execute([
            ':doctor' => $doctorId
        ]);

        $exists =
            (int)$checkStmt->fetchColumn();


        /* =================================================
           UPDATE EXISTING AVAILABILITY
        ================================================= */

        if ($exists > 0) {

            $updateStmt = $conn->prepare("

                UPDATE SYARMIMI.DOCTOR_AVAILABILITY

                SET STATUS = :availability_status

                WHERE ACCOUNT_ID = :doctor

                AND TRUNC(AVAILABLE_DATE)
                    = TO_DATE('$safeDate', 'YYYY-MM-DD')
            ");

            $updateStmt->execute([

                ':availability_status' => $status,

                ':doctor' => $doctorId

            ]);

        }


        /* =================================================
           INSERT NEW AVAILABILITY
        ================================================= */

        else {

            $insertStmt = $conn->prepare("

                INSERT INTO SYARMIMI.DOCTOR_AVAILABILITY
                (
                    AVAILABILITY_ID,
                    ACCOUNT_ID,
                    AVAILABLE_DATE,
                    STATUS,
                    START_TIME,
                    END_TIME
                )

                VALUES
                (
                    SYARMIMI.DOCTOR_AVAIL_SEQ.NEXTVAL,
                    :doctor,
                    TO_DATE('$safeDate', 'YYYY-MM-DD'),
                    :availability_status,
                    '08:00',
                    '17:00'
                )
            ");

            $insertStmt->execute([

                ':doctor' => $doctorId,

                ':availability_status' => $status

            ]);
        }


        /* =================================================
           IF AVAILABLE
           CREATE / UPDATE DOCTOR SLOTS
        ================================================= */

        if ($status === 'Available') {


            /*
               Working hours:

               08:00
               09:00
               10:00
               11:00
               12:00

               13:00 = Lunch Break

               14:00
               15:00
               16:00
            */

            for ($hour = 8; $hour <= 16; $hour++) {


                /* =========================================
                   SLOT TIME
                ========================================= */

                $slotTime =
                    sprintf(
                        '%02d:00',
                        $hour
                    );


                /* =========================================
                   DEFAULT STATUS
                ========================================= */

                if ($hour === 13) {

                    $defaultSlotStatus =
                        'Lunch Break';

                } else {

                    $defaultSlotStatus =
                        'Available';
                }


                /* =========================================
                   CHECK EXISTING SLOT
                ========================================= */

                $checkSlotStmt = $conn->prepare("

                    SELECT
                        SLOT_ID,
                        STATUS,
                        APPOINTMENT_ID,
                        CURRENT_PATIENT,
                        MAX_PATIENT

                    FROM SYARMIMI.DOCTOR_SLOT

                    WHERE ACCOUNT_ID = :doctor

                    AND TRUNC(SLOT_DATE)
                        = TO_DATE('$safeDate', 'YYYY-MM-DD')

                    AND SLOT_TIME = :slot_time
                ");

                $checkSlotStmt->execute([

                    ':doctor' =>
                        $doctorId,

                    ':slot_time' =>
                        $slotTime

                ]);


                $existingSlot =
                    $checkSlotStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                /* =========================================
                   SLOT DOES NOT EXIST
                   CREATE IT
                ========================================= */

                if (!$existingSlot) {

                    $insertSlotStmt =
                        $conn->prepare("

                            INSERT INTO SYARMIMI.DOCTOR_SLOT
                            (
                                SLOT_ID,
                                ACCOUNT_ID,
                                SLOT_DATE,
                                SLOT_TIME,
                                MAX_PATIENT,
                                CURRENT_PATIENT,
                                STATUS
                            )

                            VALUES
                            (
                                SYARMIMI.DOCTOR_SLOT_SEQ.NEXTVAL,
                                :doctor,
                                TO_DATE('$safeDate', 'YYYY-MM-DD'),
                                :slot_time,
                                1,
                                0,
                                :slot_status
                            )
                        ");


                    $insertSlotStmt->execute([

                        ':doctor' =>
                            $doctorId,

                        ':slot_time' =>
                            $slotTime,

                        ':slot_status' =>
                            $defaultSlotStatus

                    ]);

                }


                /* =========================================
                   SLOT ALREADY EXISTS
                ========================================= */

                else {


                    $appointmentId =
                        $existingSlot['APPOINTMENT_ID']
                        ?? null;


                    /*
                       IMPORTANT

                       If appointment exists:
                       DO NOT change Booked slot back
                       to Available.
                    */

                    if (
                        $appointmentId === null ||
                        $appointmentId === ''
                    ) {

                        $updateSlotStmt =
                            $conn->prepare("

                                UPDATE SYARMIMI.DOCTOR_SLOT

                                SET STATUS = :slot_status

                                WHERE SLOT_ID = :slot_id
                            ");


                        $updateSlotStmt->execute([

                            ':slot_status' =>
                                $defaultSlotStatus,

                            ':slot_id' =>
                                $existingSlot['SLOT_ID']

                        ]);

                    }

                }

            }

        }


        /* =================================================
           IF UNAVAILABLE
        ================================================= */

        else {


            /*
               IMPORTANT:

               Do NOT remove/cancel existing appointments.

               Only slots without appointment become
               Unavailable.
            */

            $disableStmt = $conn->prepare("

                UPDATE SYARMIMI.DOCTOR_SLOT

                SET STATUS = 'Unavailable'

                WHERE ACCOUNT_ID = :doctor

                AND TRUNC(SLOT_DATE)
                    = TO_DATE('$safeDate', 'YYYY-MM-DD')

                AND APPOINTMENT_ID IS NULL
            ");


            $disableStmt->execute([

                ':doctor' =>
                    $doctorId

            ]);

        }


        /* =================================================
           COMMIT
        ================================================= */

        $conn->commit();


        echo "success";
        exit();


    } catch (Exception $e) {


        /* =================================================
           ROLLBACK
        ================================================= */

        if ($conn->inTransaction()) {

            $conn->rollBack();

        }


        echo "Database Error: " .
            $e->getMessage();

        exit();

    }

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
Doctor Availability
</title>


<!-- =====================================================
     BOOTSTRAP
===================================================== -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- =====================================================
     BOOTSTRAP ICONS
===================================================== -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<!-- =====================================================
     FULLCALENDAR
===================================================== -->

<link
    href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js">
</script>


<!-- =====================================================
     SWEETALERT
===================================================== -->

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>



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


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar{

    width:260px !important;

    min-width:260px !important;

    max-width:260px !important;

    height:100vh;

    flex-shrink:0;

}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:28px 30px 45px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

    margin-bottom:24px;

}


.page-title{

    margin:0;

    color:#111827;

    font-size:27px;

    font-weight:700;

}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;

}


.doctor-chip{

    display:flex;

    align-items:center;

    gap:10px;

    padding:9px 13px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:10px;

    color:#475569;

    font-size:12px;

}


.doctor-chip-icon{

    width:30px;

    height:30px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    color:#2563eb;

    border-radius:8px;

}


/* =========================================================
   CALENDAR CARD
========================================================= */

.calendar-card{

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

}


.calendar-card-header{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:20px;

}


.card-title{

    margin:0;

    color:#1f2937;

    font-size:16px;

    font-weight:650;

}


.card-description{

    margin-top:4px;

    color:#94a3b8;

    font-size:12px;

}


/* =========================================================
   LEGEND
========================================================= */

.legend{

    display:flex;

    flex-wrap:wrap;

    align-items:center;

    gap:16px;

    font-size:12px;

    color:#64748b;

}


.legend-item{

    display:flex;

    align-items:center;

    gap:6px;

}


.legend-dot{

    width:8px;

    height:8px;

    border-radius:50%;

}


.dot-available{

    background:#22c55e;

}


.dot-unavailable{

    background:#ef4444;

}


.dot-booked{

    background:#2563eb;

}


/* =========================================================
   FULL CALENDAR
========================================================= */

#calendar{

    width:100%;

}


/* =========================================================
   TOOLBAR
========================================================= */

.fc .fc-toolbar{

    margin-bottom:22px !important;

}


.fc .fc-toolbar-title{

    color:#111827;

    font-size:20px !important;

    font-weight:650 !important;

}


/* =========================================================
   CALENDAR BUTTONS
========================================================= */

.fc .fc-button{

    padding:7px 12px !important;

    background:#fff !important;

    border:1px solid #dfe3e8 !important;

    border-radius:7px !important;

    box-shadow:none !important;

    color:#475569 !important;

    font-size:12px !important;

    font-weight:600 !important;

    text-transform:capitalize !important;

}


.fc .fc-button:hover{

    background:#f8fafc !important;

    border-color:#cbd5e1 !important;

    color:#111827 !important;

}


.fc .fc-button-active{

    background:#2563eb !important;

    border-color:#2563eb !important;

    color:#fff !important;

}


.fc .fc-today-button{

    background:#f8fafc !important;

    color:#475569 !important;

}


/* =========================================================
   CALENDAR TABLE
========================================================= */

.fc-theme-standard td,
.fc-theme-standard th{

    border-color:#edf0f3 !important;

}


.fc .fc-col-header-cell{

    background:#f8fafc;

}


.fc .fc-col-header-cell-cushion{

    padding:11px 4px;

    color:#64748b;

    font-size:11px;

    font-weight:650;

    text-decoration:none;

    text-transform:uppercase;

}


/* =========================================================
   CALENDAR DAYS
========================================================= */

.fc .fc-daygrid-day{

    cursor:pointer;

    transition:.15s;

}


.fc .fc-daygrid-day:hover{

    background:#f8fafc;

}


.fc .fc-daygrid-day-number{

    padding:9px;

    color:#475569;

    font-size:12px;

    text-decoration:none;

}


/* =========================================================
   TODAY
========================================================= */

.fc .fc-day-today{

    background:#f0f7ff !important;

}


.fc .fc-day-today
.fc-daygrid-day-number{

    width:28px;

    height:28px;

    margin:5px;

    padding:5px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#2563eb;

    border-radius:50%;

    color:#fff !important;

}


/* =========================================================
   EVENTS
========================================================= */

.fc-event{

    margin:2px 4px !important;

    padding:3px 5px !important;

    border:none !important;

    border-radius:5px !important;

    cursor:pointer;

    font-size:10px !important;

    font-weight:600;

}


/* =========================================================
   DETAILS CARD
========================================================= */

.details-card{

    margin-top:20px;

    padding:20px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

}


.details-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:16px;

}


.selected-date{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:6px 9px;

    background:#f1f5f9;

    border-radius:6px;

    color:#475569;

    font-size:11px;

    font-weight:600;

}


/* =========================================================
   TABLE
========================================================= */

.table{

    margin-bottom:0;

}


.table thead th{

    padding:11px 12px;

    background:#f8fafc;

    border-bottom:1px solid #e5e7eb;

    color:#64748b;

    font-size:11px;

    font-weight:650;

    text-transform:uppercase;

}


.table tbody td{

    padding:12px;

    border-color:#eef1f4;

    color:#374151;

    font-size:13px;

    vertical-align:middle;

}


.table tbody tr:hover td{

    background:#fafbfc;

}


.patient-name{

    color:#1f2937;

    font-weight:650;

}


/* =========================================================
   STATUS BADGES
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;

}


.status-available{

    background:#ecfdf5;

    color:#15803d;

}


.status-booked{

    background:#eff6ff;

    color:#2563eb;

}


.status-lunch{

    background:#fff7ed;

    color:#c2410c;

}


.status-unavailable{

    background:#f3f4f6;

    color:#64748b;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{

    padding:32px 20px;

    text-align:center;

    color:#94a3b8;

}


.empty-state i{

    display:block;

    margin-bottom:7px;

    color:#cbd5e1;

    font-size:25px;

}


/* =========================================================
   MODAL
========================================================= */

.modal-content{

    border:0;

    border-radius:14px;

    box-shadow:
        0 20px 50px
        rgba(15,23,42,.15);

}


.modal-header{

    padding:20px 22px;

    border-bottom:1px solid #eef1f4;

}


.modal-title{

    color:#111827;

    font-size:16px;

    font-weight:650;

}


.modal-body{

    padding:22px;

}


.modal-footer{

    padding:16px 22px;

    border-top:1px solid #eef1f4;

}


.form-label{

    color:#475569;

    font-size:12px;

    font-weight:600;

}


.form-control,
.form-select{

    min-height:43px;

    border-color:#dfe3e8;

    border-radius:8px;

    font-size:13px;

}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.08);

}


.save-btn{

    padding:9px 15px;

    background:#2563eb;

    border:0;

    border-radius:8px;

    color:#fff;

    font-size:12px;

    font-weight:600;

}


.save-btn:hover{

    background:#1d4ed8;

    color:#fff;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .main-content{

        padding:20px;

    }


    .page-header{

        flex-direction:column;

    }


    .calendar-card-header{

        flex-direction:column;

        align-items:flex-start;

    }


    .fc .fc-toolbar{

        gap:12px;

        flex-direction:column;

    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php

include("../includes/sidebar_doctor.php");

?>


<main class="main-content">


<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Availability & Schedule

</h1>


<div class="page-subtitle">

Manage your working days and view appointment slots.

</div>


</div>


<div class="doctor-chip">


<div class="doctor-chip-icon">

<i class="bi bi-person"></i>

</div>


<div>


<div class="fw-semibold">

Dr. <?= htmlspecialchars($doctorUsername) ?>

</div>


<div class="text-muted">

<?= htmlspecialchars($department) ?>

</div>


</div>


</div>


</div>



<!-- =====================================================
     CALENDAR
===================================================== -->

<div class="calendar-card">


<div class="calendar-card-header">


<div>


<h5 class="card-title">

Availability Calendar

</h5>


<div class="card-description">

Select a date to set your availability. Click an existing status to view its schedule.

</div>


</div>


<div class="legend">


<div class="legend-item">

<span class="legend-dot dot-available"></span>

Available

</div>


<div class="legend-item">

<span class="legend-dot dot-unavailable"></span>

Unavailable

</div>


<div class="legend-item">

<span class="legend-dot dot-booked"></span>

Booked Slot

</div>


</div>


</div>


<div id="calendar"></div>


</div>



<!-- =====================================================
     SELECTED DATE
===================================================== -->

<?php if (!empty($selectedDate)): ?>


<!-- =====================================================
     APPOINTMENTS
===================================================== -->

<div class="details-card">


<div class="details-header">


<div>


<h5 class="card-title">

Appointments

</h5>


<div class="card-description">

Approved appointments scheduled for the selected date.

</div>


</div>


<div class="selected-date">

<i class="bi bi-calendar3"></i>


<?php

$displaySelectedDate =
    DateTime::createFromFormat(
        'Y-m-d',
        $selectedDate
    );

?>


<?=

$displaySelectedDate
    ? htmlspecialchars(
        $displaySelectedDate->format('d M Y')
    )
    : htmlspecialchars($selectedDate)

?>


</div>


</div>



<?php if (
    count($appointmentsOnDate) > 0
): ?>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>No.</th>

<th>Time</th>

<th>Patient</th>

<th>Status</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $appointmentsOnDate
    as
    $index => $row
): ?>


<tr>


<td>

<?= $index + 1 ?>

</td>


<td>

<?= htmlspecialchars(
    $row['APPOINTMENT_TIME']
    ?? '-'
) ?>

</td>


<td>


<span class="patient-name">

<?= htmlspecialchars(
    $row['PATIENT_NAME']
    ?? '-'
) ?>

</span>


</td>


<td>


<span class="status-badge status-booked">

<i class="bi bi-check-circle"></i>

Approved

</span>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-state">


<i class="bi bi-calendar2-check"></i>


<div class="fw-semibold">

No appointments

</div>


<div class="small mt-1">

There are no approved appointments booked for this date.

</div>


</div>


<?php endif; ?>


</div>



<!-- =====================================================
     TIME SLOTS
===================================================== -->

<div class="details-card">


<div class="details-header">


<div>


<h5 class="card-title">

Time Slots

</h5>


<div class="card-description">

Availability and booking status for each consultation hour.

</div>


</div>


<div class="selected-date">

<?= count($slotList) ?>

slot(s)

</div>


</div>



<?php if (
    count($slotList) > 0
): ?>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>No.</th>

<th>Time</th>

<th>Status</th>

<th>Patient</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $slotList
    as
    $index => $slot
): ?>


<?php


$start =
    substr(
        trim($slot['SLOT_TIME'] ?? ''),
        0,
        5
    );


$end = '-';


if (
    preg_match(
        '/^\d{2}:\d{2}$/',
        $start
    )
) {

    $slotDateTime =
        DateTime::createFromFormat(
            'H:i',
            $start
        );


    if ($slotDateTime) {

        $slotDateTime->modify(
            '+1 hour'
        );

        $end =
            $slotDateTime->format(
                'H:i'
            );
    }
}


$slotStatus =
    trim(
        $slot['STATUS']
        ?? ''
    );


?>


<tr>


<td>

<?= $index + 1 ?>

</td>


<td>


<strong>

<?= htmlspecialchars(
    $start ?: '-'
) ?>

</strong>


<span class="text-muted">

-

</span>


<?= htmlspecialchars($end) ?>


</td>


<td>


<?php if (
    strcasecmp(
        $slotStatus,
        'Booked'
    ) === 0
): ?>


<span class="status-badge status-booked">

<i class="bi bi-person-check"></i>

Booked

</span>


<?php elseif (
    strcasecmp(
        $slotStatus,
        'Lunch Break'
    ) === 0
): ?>


<span class="status-badge status-lunch">

<i class="bi bi-cup-hot"></i>

Lunch Break

</span>


<?php elseif (
    strcasecmp(
        $slotStatus,
        'Unavailable'
    ) === 0
): ?>


<span class="status-badge status-unavailable">

<i class="bi bi-x-circle"></i>

Unavailable

</span>


<?php else: ?>


<span class="status-badge status-available">

<i class="bi bi-check-circle"></i>

Available

</span>


<?php endif; ?>


</td>


<td>


<?php if (
    !empty(
        $slot['PATIENT_NAME']
    )
): ?>


<span class="patient-name">

<?= htmlspecialchars(
    $slot['PATIENT_NAME']
) ?>

</span>


<?php else: ?>


<span class="text-muted">

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


<div class="empty-state">


<i class="bi bi-clock"></i>


<div class="fw-semibold">

No time slots

</div>


<div class="small mt-1">

Set this date as available to generate consultation slots.

</div>


</div>


<?php endif; ?>


</div>


<?php endif; ?>


</main>


</div>



<!-- =====================================================
     AVAILABILITY MODAL
===================================================== -->

<div
    class="modal fade"
    id="availabilityModal"
    tabindex="-1"
>


<div class="modal-dialog modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">


<div>


<h5 class="modal-title">

Set Availability

</h5>


<div class="text-muted small mt-1">

Choose your availability for this date.

</div>


</div>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div class="modal-body">


<input
    type="hidden"
    id="availabilityDate"
>


<div class="mb-3">


<label class="form-label">

Selected Date

</label>


<div class="input-group">


<span class="input-group-text bg-light">

<i class="bi bi-calendar3"></i>

</span>


<input
    type="text"
    id="displayDate"
    class="form-control"
    readonly
>


</div>


</div>


<div>


<label class="form-label">

Availability Status

</label>


<select
    id="availabilityStatus"
    class="form-select"
>


<option value="Available">

Available

</option>


<option value="Unavailable">

Unavailable

</option>


</select>


</div>


</div>


<div class="modal-footer">


<button
    type="button"
    class="btn btn-light"
    data-bs-dismiss="modal"
>

Cancel

</button>


<button
    type="button"
    id="saveAvailability"
    class="save-btn"
>

<i class="bi bi-check-lg me-1"></i>

Save Availability

</button>


</div>


</div>


</div>


</div>



<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>



<script>

document.addEventListener(
    'DOMContentLoaded',
    function()
    {


        /* =================================================
           CALENDAR
        ================================================= */

        const calendarEl =
            document.getElementById(
                'calendar'
            );


        const calendar =
            new FullCalendar.Calendar(
                calendarEl,
                {


                    /* =====================================
                       DEFAULT VIEW
                    ===================================== */

                    initialView:
                        'dayGridMonth',


                    /* =====================================
                       TOOLBAR
                    ===================================== */

                    headerToolbar:
                    {

                        left:
                            'prev,next today',

                        center:
                            'title',

                        right:
                            'dayGridMonth,timeGridWeek,listMonth'

                    },


                    /* =====================================
                       BUTTON TEXT
                    ===================================== */

                    buttonText:
                    {

                        today:
                            'Today',

                        month:
                            'Month',

                        week:
                            'Week',

                        list:
                            'List'

                    },


                    height:
                        'auto',


                    contentHeight:
                        'auto',


                    dayMaxEvents:
                        3,


                    navLinks:
                        true,


                    nowIndicator:
                        true,


                    /* =====================================
                       LOAD AVAILABILITY
                    ===================================== */

                    events:
                        'load_availability.php',


                    /* =====================================
                       CLICK EMPTY DATE
                    ===================================== */

                    dateClick:
                    function(info)
                    {

                        const clickedDate =
                            info.dateStr
                                .substring(
                                    0,
                                    10
                                );


                        document
                            .getElementById(
                                'availabilityDate'
                            )
                            .value =
                            clickedDate;


                        const dateObject =
                            new Date(
                                clickedDate +
                                'T00:00:00'
                            );


                        document
                            .getElementById(
                                'displayDate'
                            )
                            .value =
                            dateObject
                                .toLocaleDateString(
                                    'en-GB',
                                    {

                                        day:
                                            '2-digit',

                                        month:
                                            'long',

                                        year:
                                            'numeric'

                                    }
                                );


                        const modal =
                            new bootstrap.Modal(
                                document
                                    .getElementById(
                                        'availabilityModal'
                                    )
                            );


                        modal.show();

                    },


                    /* =====================================
                       CLICK EXISTING AVAILABILITY EVENT
                    ===================================== */

                    eventClick:
                    function(info)
                    {

                        info.jsEvent
                            .preventDefault();


                        let clickedDate =
                            info.event.startStr;


                        if (clickedDate) {

                            clickedDate =
                                clickedDate.substring(
                                    0,
                                    10
                                );

                        }


                        window.location.href =
                            'doctor_availability.php?date='
                            +
                            encodeURIComponent(
                                clickedDate
                            );

                    },


                    /* =====================================
                       CLICK DATE NUMBER
                    ===================================== */

                    navLinkDayClick:
                    function(date)
                    {

                        const year =
                            date.getFullYear();


                        const month =
                            String(
                                date.getMonth() + 1
                            )
                            .padStart(
                                2,
                                '0'
                            );


                        const day =
                            String(
                                date.getDate()
                            )
                            .padStart(
                                2,
                                '0'
                            );


                        const selected =
                            year
                            +
                            '-'
                            +
                            month
                            +
                            '-'
                            +
                            day;


                        window.location.href =
                            'doctor_availability.php?date='
                            +
                            encodeURIComponent(
                                selected
                            );

                    }

                }
            );


        calendar.render();



        /* =================================================
           SAVE AVAILABILITY
        ================================================= */

        const saveButton =
            document.getElementById(
                'saveAvailability'
            );


        saveButton.addEventListener(
            'click',
            function()
            {


                const date =
                    document
                        .getElementById(
                            'availabilityDate'
                        )
                        .value;


                const status =
                    document
                        .getElementById(
                            'availabilityStatus'
                        )
                        .value;


                /* =========================================
                   VALIDATE
                ========================================= */

                if (!date) {


                    Swal.fire({

                        icon:
                            'warning',

                        title:
                            'Select a date',

                        text:
                            'Please select a date first.'

                    });


                    return;

                }


                const button =
                    this;


                button.disabled =
                    true;


                button.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';



                /* =========================================
                   POST
                ========================================= */

                fetch(
                    'doctor_availability.php',
                    {


                        method:
                            'POST',


                        headers:
                        {

                            'Content-Type':
                                'application/x-www-form-urlencoded'

                        },


                        body:

                            'date='
                            +
                            encodeURIComponent(
                                date
                            )

                            +

                            '&status='
                            +
                            encodeURIComponent(
                                status
                            )

                            +

                            '&save=1'

                    }
                )


                .then(
                    response =>
                        response.text()
                )


                .then(
                    data =>
                    {


                        /* =================================
                           SUCCESS
                        ================================= */

                        if (
                            data.trim()
                            ===
                            'success'
                        ) {


                            const modalElement =
                                document
                                    .getElementById(
                                        'availabilityModal'
                                    );


                            const modalInstance =
                                bootstrap.Modal
                                    .getInstance(
                                        modalElement
                                    );


                            if (modalInstance) {

                                modalInstance.hide();

                            }


                            /* =============================
                               REFRESH CALENDAR EVENTS
                            ============================= */

                            calendar
                                .refetchEvents();


                            /* =============================
                               SUCCESS MESSAGE
                            ============================= */

                            Swal.fire({

                                toast:
                                    true,

                                position:
                                    'top-end',

                                icon:
                                    'success',

                                title:
                                    status
                                    +
                                    ' successfully saved',

                                showConfirmButton:
                                    false,

                                timer:
                                    2200,

                                timerProgressBar:
                                    true

                            });


                            /* =============================
                               IF CURRENTLY VIEWING SAME DATE
                            ============================= */

                            const params =
                                new URLSearchParams(
                                    window.location.search
                                );


                            if (
                                params.get('date')
                                ===
                                date
                            ) {


                                setTimeout(
                                    function()
                                    {

                                        window.location.reload();

                                    },

                                    700
                                );

                            }

                        }


                        /* =================================
                           DATABASE ERROR
                        ================================= */

                        else {


                            Swal.fire({

                                icon:
                                    'error',

                                title:
                                    'Unable to save',

                                text:
                                    data

                            });

                        }

                    }
                )


                /* =========================================
                   CONNECTION ERROR
                ========================================= */

                .catch(
                    error =>
                    {


                        Swal.fire({

                            icon:
                                'error',

                            title:
                                'Connection Error',

                            text:
                                error.toString()

                        });

                    }
                )


                /* =========================================
                   RESET BUTTON
                ========================================= */

                .finally(
                    () =>
                    {


                        button.disabled =
                            false;


                        button.innerHTML =
                            '<i class="bi bi-check-lg me-1"></i> Save Availability';

                    }
                );

            }
        );

    }
);

</script>


</body>

</html>