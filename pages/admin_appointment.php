<?php

session_start();
include("../config/config.php");

/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   STATISTICS
========================================================= */

$pendingCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.APPOINTMENT
    WHERE UPPER(TRIM(STATUS)) = 'PENDING'
")->fetchColumn();


$approvedCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.APPOINTMENT
    WHERE UPPER(TRIM(STATUS)) = 'APPROVED'
")->fetchColumn();


$rejectedCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.APPOINTMENT
    WHERE UPPER(TRIM(STATUS)) = 'REJECTED'
")->fetchColumn();


$totalCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.APPOINTMENT
")->fetchColumn();


/* =========================================================
   TODAY DOCTOR AVAILABILITY
========================================================= */

$doctorAvailabilitySql = "

    SELECT
        H.ACCOUNT_ID,
        H.USERNAME,
        H.DEPARTMENT,
        D.STATUS,
        D.START_TIME,
        D.END_TIME,

        TO_CHAR(
            D.AVAILABLE_DATE,
            'DD-MON-YYYY'
        ) AS AVAILABLE_DATE

    FROM SYARMIMI.DOCTOR_AVAILABILITY D

    JOIN SYARMIMI.HOSPITAL_STAFF H
        ON TO_CHAR(D.ACCOUNT_ID)
        =
        TO_CHAR(H.ACCOUNT_ID)

    WHERE TRUNC(D.AVAILABLE_DATE)
          =
          TRUNC(SYSDATE)

    ORDER BY
        H.DEPARTMENT,
        H.USERNAME
";


$doctorAvailabilityStmt =
    $conn->query($doctorAvailabilitySql);

$doctorAvailability =
    $doctorAvailabilityStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   DOCTOR LIST
========================================================= */

$doctorListSql = "

    SELECT
        ACCOUNT_ID,
        USERNAME,
        DEPARTMENT

    FROM SYARMIMI.HOSPITAL_STAFF

    WHERE UPPER(TRIM(ROLE)) = 'DOCTOR'

    ORDER BY
        DEPARTMENT,
        USERNAME
";


$doctorListStmt =
    $conn->query($doctorListSql);

$scheduleDoctors =
    $doctorListStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PREPARE DOCTOR SCHEDULE DATA
========================================================= */

$doctorScheduleData = [];


foreach ($scheduleDoctors as $doctor) {

    $doctorId = $doctor['ACCOUNT_ID'];


    /* =====================================================
       UPCOMING AVAILABLE DATES
    ===================================================== */

    $dateStmt = $conn->prepare("

        SELECT

            TO_CHAR(
                AVAILABLE_DATE,
                'DD-MON-YYYY'
            ) AS AVAILABLE_DATE

        FROM SYARMIMI.DOCTOR_AVAILABILITY

        WHERE TO_CHAR(ACCOUNT_ID)
              =
              TO_CHAR(:doctor_id)

        AND TRUNC(AVAILABLE_DATE)
            >=
            TRUNC(SYSDATE)

        AND UPPER(TRIM(STATUS))
            =
            'AVAILABLE'

        ORDER BY
            AVAILABLE_DATE
    ");


    $dateStmt->execute([
        ':doctor_id' => (string)$doctorId
    ]);


    $upcomingDates =
        $dateStmt->fetchAll(PDO::FETCH_COLUMN);


    /* =====================================================
       AVAILABLE SLOT COUNT
    ===================================================== */

    $availableStmt = $conn->prepare("

        SELECT COUNT(*)

        FROM SYARMIMI.DOCTOR_SLOT

        WHERE TO_CHAR(ACCOUNT_ID)
              =
              TO_CHAR(:doctor_id)

        AND TRUNC(SLOT_DATE)
            >=
            TRUNC(SYSDATE)

        AND UPPER(TRIM(STATUS))
            =
            'AVAILABLE'

        AND NVL(CURRENT_PATIENT, 0)
            <
            NVL(MAX_PATIENT, 1)
    ");


    $availableStmt->execute([
        ':doctor_id' => (string)$doctorId
    ]);


    $availableSlots =
        (int)$availableStmt->fetchColumn();


    /* =====================================================
       BOOKED SLOT COUNT
    ===================================================== */

    $bookedStmt = $conn->prepare("

        SELECT COUNT(*)

        FROM SYARMIMI.DOCTOR_SLOT

        WHERE TO_CHAR(ACCOUNT_ID)
              =
              TO_CHAR(:doctor_id)

        AND TRUNC(SLOT_DATE)
            >=
            TRUNC(SYSDATE)

        AND
        (
            UPPER(TRIM(STATUS)) = 'BOOKED'

            OR

            NVL(CURRENT_PATIENT, 0)
            >=
            NVL(MAX_PATIENT, 1)
        )
    ");


    $bookedStmt->execute([
        ':doctor_id' => (string)$doctorId
    ]);


    $bookedSlots =
        (int)$bookedStmt->fetchColumn();


    /* =====================================================
       STORE
    ===================================================== */

    $doctorScheduleData[] = [

        'ACCOUNT_ID' =>
            $doctorId,

        'USERNAME' =>
            $doctor['USERNAME'],

        'DEPARTMENT' =>
            $doctor['DEPARTMENT'],

        'DATES' =>
            $upcomingDates,

        'AVAILABLE_SLOTS' =>
            $availableSlots,

        'BOOKED_SLOTS' =>
            $bookedSlots

    ];
}


/* =========================================================
   FETCH APPOINTMENTS

   Highest APPOINTMENT_ID = newest record
========================================================= */

$appointmentSql = "

    SELECT

        APPOINTMENT_ID,
        PATIENT_NAME,
        EMAIL,
        GENDER,
        PHONE,
        DEPARTMENT,
        DOCTOR_NAME,
        APPOINTMENT_DATE,
        APPOINTMENT_TIME,
        STATUS,
        ACCOUNT_ID

    FROM SYARMIMI.APPOINTMENT

    ORDER BY
        APPOINTMENT_ID DESC
";


$appointmentStmt =
    $conn->query($appointmentSql);

$appointments =
    $appointmentStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   NORMALIZE APPOINTMENT DATE
========================================================= */

function normalizeAppointmentDate($date)
{
    if ($date === null || trim($date) === '') {
        return '';
    }

    $date = trim($date);


    /* YYYY-MM-DD */

    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        return $date;
    }


    /* DD-MON-RR */

    if (
        preg_match(
            '/^\d{2}-[A-Za-z]{3}-\d{2}$/',
            $date
        )
    ) {

        $object =
            DateTime::createFromFormat(
                'd-M-y',
                strtoupper($date)
            );

        if ($object) {

            return $object->format('Y-m-d');

        }
    }


    /* DD-MON-YYYY */

    if (
        preg_match(
            '/^\d{2}-[A-Za-z]{3}-\d{4}$/',
            $date
        )
    ) {

        $object =
            DateTime::createFromFormat(
                'd-M-Y',
                strtoupper($date)
            );

        if ($object) {

            return $object->format('Y-m-d');

        }
    }


    $timestamp = strtotime($date);


    if ($timestamp !== false) {

        return date(
            'Y-m-d',
            $timestamp
        );
    }


    return '';
}


/* =========================================================
   DISPLAY APPOINTMENT DATE
========================================================= */

function displayAppointmentDate($date)
{
    $normalized =
        normalizeAppointmentDate($date);


    if ($normalized === '') {

        return $date ?: '-';

    }


    $timestamp =
        strtotime($normalized);


    return strtoupper(
        date(
            'd-M-y',
            $timestamp
        )
    );
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
    Appointment Management
</title>


<!-- BOOTSTRAP -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- BOOTSTRAP ICONS -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<!-- DATATABLES -->

<link
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   GENERAL
========================================================= */

body {

    background:#f4f6f9;
    font-family:'Segoe UI', sans-serif;
    color:#1f2937;

}

.main-content {

    padding:30px;
    width:100%;
}


/* =========================================================
   HEADER
========================================================= */

.page-title {

    font-size:28px;
    font-weight:700;
    color:#172033;
    margin-bottom:4px;

}

.page-subtitle {

    font-size:14px;
    color:#6b7280;

}


/* =========================================================
   AUTOMATION BANNER
========================================================= */

.automation-banner {

    background:#eff6ff;
    border:1px solid #bfdbfe;
    border-radius:14px;
    padding:18px 20px;
    margin-bottom:24px;

}

.automation-icon {

    width:45px;
    height:45px;

    border-radius:12px;

    background:#dbeafe;
    color:#2563eb;

    display:flex;
    justify-content:center;
    align-items:center;

    font-size:21px;

    flex-shrink:0;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stat-card {

    background:white;

    border:1px solid #e5e7eb;

    border-radius:15px;

    padding:20px;

    height:100%;

    box-shadow:
        0 3px 12px
        rgba(0,0,0,0.04);

}

.stat-label {

    color:#6b7280;
    font-size:13px;
    margin-bottom:7px;

}

.stat-number {

    font-size:30px;
    font-weight:700;

}

.stat-icon {

    width:42px;
    height:42px;

    border-radius:12px;

    display:flex;
    justify-content:center;
    align-items:center;

    font-size:19px;

}

.icon-total {

    background:#f3f4f6;
    color:#374151;

}

.icon-pending {

    background:#fef3c7;
    color:#d97706;

}

.icon-approved {

    background:#dcfce7;
    color:#16a34a;

}

.icon-rejected {

    background:#fee2e2;
    color:#dc2626;

}


/* =========================================================
   CONTENT BOX
========================================================= */

.content-box {

    background:#ffffff;

    border:1px solid #e5e7eb;

    border-radius:16px;

    padding:24px;

    margin-bottom:24px;

    box-shadow:
        0 3px 14px
        rgba(0,0,0,0.04);

}


/* =========================================================
   TABLE
========================================================= */

.table {

    vertical-align:middle;

}

.table thead th {

    background:#1f2937;
    color:white;

    border:none;

    padding:13px 12px;

    font-size:13px;

    white-space:nowrap;

}

.table tbody td {

    padding:14px 12px;

    border-color:#edf0f3;

}

.table tbody tr:hover {

    background:#f8fafc;

}


/* =========================================================
   NO COLUMN
========================================================= */

.row-number {

    width:45px;

    text-align:center;

    font-weight:600;

    color:#64748b;

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display:inline-flex;

    align-items:center;

    gap:5px;

    border-radius:20px;

    padding:6px 10px;

    font-size:12px;

    font-weight:600;

    white-space:nowrap;

}

.status-approved {

    color:#166534;
    background:#dcfce7;

}

.status-rejected {

    color:#991b1b;
    background:#fee2e2;

}

.status-pending {

    color:#92400e;
    background:#fef3c7;

}

.status-other {

    color:#374151;
    background:#f3f4f6;

}


/* =========================================================
   PROCESS
========================================================= */

.processing {

    font-size:12px;

    font-weight:600;

    white-space:nowrap;

}

.processing.approved {

    color:#15803d;

}

.processing.rejected {

    color:#dc2626;

}

.processing.pending {

    color:#d97706;

}


/* =========================================================
   FORM
========================================================= */

.form-control,
.form-select {

    border-radius:10px;

    min-height:44px;

}


/* Hide DataTables default search because we have custom search */

.dataTables_filter {

    display:none;

}


/* =========================================================
   DOCTOR DATE
========================================================= */

.date-badge {

    display:inline-block;

    padding:5px 8px;

    margin:2px;

    border-radius:7px;

    background:#eff6ff;

    color:#1d4ed8;

    font-size:11px;

}

</style>

</head>


<body>


<div class="d-flex">


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include("../includes/sidebar_admin.php"); ?>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="mb-4">

    <div class="page-title">

        📅 Appointment Management

    </div>

    <div class="page-subtitle">

        Monitor automatically processed appointments and doctor availability.

    </div>

</div>


<!-- =====================================================
     AUTOMATION BANNER
===================================================== -->

<div class="automation-banner">

    <div class="d-flex align-items-center gap-3">

        <div class="automation-icon">

            <i class="bi bi-lightning-charge-fill"></i>

        </div>

        <div>

            <div class="fw-bold mb-1">

                Automatic Appointment Processing

            </div>

            <div class="small text-muted">

                Appointment requests are automatically checked
                against doctor availability and available slot capacity.
                Admin approval is no longer required.

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="row g-3 mb-4">


<div class="col-md-3">

    <div class="stat-card">

        <div class="d-flex justify-content-between">

            <div>

                <div class="stat-label">
                    Total Appointments
                </div>

                <div class="stat-number">
                    <?= $totalCount ?>
                </div>

            </div>

            <div class="stat-icon icon-total">

                <i class="bi bi-calendar3"></i>

            </div>

        </div>

    </div>

</div>


<div class="col-md-3">

    <div class="stat-card">

        <div class="d-flex justify-content-between">

            <div>

                <div class="stat-label">
                    Pending
                </div>

                <div class="stat-number text-warning">
                    <?= $pendingCount ?>
                </div>

            </div>

            <div class="stat-icon icon-pending">

                <i class="bi bi-hourglass-split"></i>

            </div>

        </div>

    </div>

</div>


<div class="col-md-3">

    <div class="stat-card">

        <div class="d-flex justify-content-between">

            <div>

                <div class="stat-label">
                    Approved
                </div>

                <div class="stat-number text-success">
                    <?= $approvedCount ?>
                </div>

            </div>

            <div class="stat-icon icon-approved">

                <i class="bi bi-check-circle"></i>

            </div>

        </div>

    </div>

</div>


<div class="col-md-3">

    <div class="stat-card">

        <div class="d-flex justify-content-between">

            <div>

                <div class="stat-label">
                    Rejected
                </div>

                <div class="stat-number text-danger">
                    <?= $rejectedCount ?>
                </div>

            </div>

            <div class="stat-icon icon-rejected">

                <i class="bi bi-x-circle"></i>

            </div>

        </div>

    </div>

</div>


</div>


<!-- =====================================================
     TODAY DOCTOR AVAILABILITY
===================================================== -->

<div class="content-box">

<div class="mb-3">

    <h5 class="fw-bold mb-1">

        Today's Doctor Availability

    </h5>

    <small class="text-muted">

        Doctors who have availability records for today.

    </small>

</div>


<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

    <th>Doctor</th>
    <th>Department</th>
    <th>Status</th>
    <th>Available Time</th>

</tr>

</thead>


<tbody>

<?php if (count($doctorAvailability) > 0): ?>

<?php foreach ($doctorAvailability as $doctor): ?>

<?php

$status =
    strtoupper(
        trim(
            $doctor['STATUS'] ?? ''
        )
    );

?>

<tr>

<td>

    <strong>

        Dr. <?= htmlspecialchars(
            $doctor['USERNAME'] ?? ''
        ) ?>

    </strong>

</td>


<td>

    <?= htmlspecialchars(
        $doctor['DEPARTMENT'] ?? '-'
    ) ?>

</td>


<td>

<?php if ($status === 'AVAILABLE'): ?>

    <span class="status status-approved">

        <i class="bi bi-check-circle"></i>

        Available

    </span>

<?php else: ?>

    <span class="status status-rejected">

        <i class="bi bi-x-circle"></i>

        <?= htmlspecialchars(
            $doctor['STATUS'] ?? 'Unavailable'
        ) ?>

    </span>

<?php endif; ?>

</td>


<td>

<?php if ($status === 'AVAILABLE'): ?>

    <?= htmlspecialchars(
        $doctor['START_TIME'] ?? '-'
    ) ?>

    -

    <?= htmlspecialchars(
        $doctor['END_TIME'] ?? '-'
    ) ?>

<?php else: ?>

    <span class="text-muted">
        -
    </span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="4"
    class="text-center text-muted py-4"
>

    No doctor availability recorded for today.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<!-- =====================================================
     DOCTOR FUTURE SCHEDULE
===================================================== -->

<div class="content-box">

<div class="mb-3">

    <h5 class="fw-bold mb-1">

        Doctor Schedule Overview

    </h5>

    <small class="text-muted">

        Upcoming doctor availability and slot status.

    </small>

</div>


<div class="table-responsive">

<table class="table table-hover">

<thead>

<tr>

    <th>Doctor</th>
    <th>Department</th>
    <th>Upcoming Availability</th>
    <th>Available Slots</th>
    <th>Booked Slots</th>
    <th>Action</th>

</tr>

</thead>


<tbody>

<?php if (count($doctorScheduleData) > 0): ?>

<?php foreach ($doctorScheduleData as $doctor): ?>

<tr>

<td>

    <strong>

        Dr. <?= htmlspecialchars(
            $doctor['USERNAME']
        ) ?>

    </strong>

</td>


<td>

    <?= htmlspecialchars(
        $doctor['DEPARTMENT'] ?? '-'
    ) ?>

</td>


<td>

<?php if (count($doctor['DATES']) > 0): ?>

<?php

$shownDates =
    array_slice(
        $doctor['DATES'],
        0,
        4
    );

?>

<?php foreach ($shownDates as $date): ?>

    <span class="date-badge">

        <?= htmlspecialchars($date) ?>

    </span>

<?php endforeach; ?>


<?php if (count($doctor['DATES']) > 4): ?>

    <small class="text-muted">

        +<?= count($doctor['DATES']) - 4 ?> more

    </small>

<?php endif; ?>


<?php else: ?>

    <span class="text-muted">

        No upcoming availability

    </span>

<?php endif; ?>

</td>


<td>

    <span class="badge bg-success">

        <?= (int)$doctor['AVAILABLE_SLOTS'] ?>

    </span>

</td>


<td>

    <span class="badge bg-danger">

        <?= (int)$doctor['BOOKED_SLOTS'] ?>

    </span>

</td>


<td>

    <a
        href="doctor_slot_view.php?doctor=<?= urlencode(
            $doctor['ACCOUNT_ID']
        ) ?>&from=admin_appointment"
        class="btn btn-primary btn-sm"
    >

        <i class="bi bi-calendar3"></i>

        Schedule

    </a>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="6"
    class="text-center text-muted py-4"
>

    No doctors found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<!-- =====================================================
     APPOINTMENT RECORDS
===================================================== -->

<div class="content-box">

<div class="mb-3">

    <h5 class="fw-bold mb-1">

        Appointment Records

    </h5>

    <small class="text-muted">

        View appointments processed by the automatic booking system.

    </small>

</div>


<!-- FILTERS -->

<div class="row g-2 mb-4">

<div class="col-md-5">

    <input
        type="text"
        id="appointmentSearch"
        class="form-control"
        placeholder="🔍 Search patient, doctor or department"
    >

</div>


<div class="col-md-3">

    <select
        id="statusFilter"
        class="form-select"
    >

        <option value="">
            All Status
        </option>

        <option value="Approved">
            Approved
        </option>

        <option value="Rejected">
            Rejected
        </option>

        <option value="Pending">
            Pending
        </option>

    </select>

</div>


<div class="col-md-4">

    <select
        id="sortFilter"
        class="form-select"
    >

        <option value="desc">
            Newest First
        </option>

        <option value="asc">
            Oldest First
        </option>

    </select>

</div>

</div>


<div class="table-responsive">

<table
    id="appointmentTable"
    class="table table-hover"
>

<thead>

<tr>

    <th>No.</th>
    <th>ID</th>
    <th>Patient</th>
    <th>Gender</th>
    <th>Phone</th>
    <th>Department</th>
    <th>Doctor</th>
    <th>Date</th>
    <th>Time</th>
    <th>Status</th>
    <th>Processing</th>

</tr>

</thead>


<tbody>

<?php foreach ($appointments as $row): ?>

<?php

$status =
    trim(
        $row['STATUS'] ?? ''
    );


$normalizedDate =
    normalizeAppointmentDate(
        $row['APPOINTMENT_DATE'] ?? ''
    );


$displayDate =
    displayAppointmentDate(
        $row['APPOINTMENT_DATE'] ?? ''
    );

?>

<tr>


<!-- NUMBER -->
<td class="row-number">

</td>


<!-- APPOINTMENT ID -->

<td
    data-order="<?= (int)$row['APPOINTMENT_ID'] ?>"
    data-search="<?= (int)$row['APPOINTMENT_ID'] ?>"
>

    <strong>

        #<?= (int)$row['APPOINTMENT_ID'] ?>

    </strong>

</td>


<!-- PATIENT -->

<td>

    <div class="fw-semibold">

        <?= htmlspecialchars(
            $row['PATIENT_NAME'] ?? ''
        ) ?>

    </div>

    <small class="text-muted">

        <?= htmlspecialchars(
            $row['EMAIL'] ?? ''
        ) ?>

    </small>

</td>


<!-- GENDER -->

<td>

    <?= htmlspecialchars(
        $row['GENDER'] ?? '-'
    ) ?>

</td>


<!-- PHONE -->

<td>

    <?= htmlspecialchars(
        $row['PHONE'] ?? '-'
    ) ?>

</td>


<!-- DEPARTMENT -->

<td>

    <?= htmlspecialchars(
        $row['DEPARTMENT'] ?? '-'
    ) ?>

</td>


<!-- DOCTOR -->

<td>

<?php if (!empty($row['DOCTOR_NAME'])): ?>

    <strong>

        <?= htmlspecialchars(
            $row['DOCTOR_NAME']
        ) ?>

    </strong>

<?php else: ?>

    <span class="text-muted">

        Not Assigned

    </span>

<?php endif; ?>

</td>


<!-- DATE -->

<td
    data-order="<?= htmlspecialchars(
        $normalizedDate
    ) ?>"
>

    <?= htmlspecialchars(
        $displayDate
    ) ?>

</td>


<!-- TIME -->

<td>

    <?= htmlspecialchars(
        $row['APPOINTMENT_TIME'] ?? '-'
    ) ?>

</td>


<!-- STATUS -->

<td>

<?php if (
    strtoupper($status) === 'APPROVED'
): ?>

    <span class="status status-approved">

        <i class="bi bi-check-circle-fill"></i>

        Approved

    </span>

<?php elseif (
    strtoupper($status) === 'REJECTED'
): ?>

    <span class="status status-rejected">

        <i class="bi bi-x-circle-fill"></i>

        Rejected

    </span>

<?php elseif (
    strtoupper($status) === 'PENDING'
): ?>

    <span class="status status-pending">

        <i class="bi bi-hourglass-split"></i>

        Pending

    </span>

<?php else: ?>

    <span class="status status-other">

        <?= htmlspecialchars(
            $status ?: '-'
        ) ?>

    </span>

<?php endif; ?>

</td>


<!-- PROCESSING -->

<td>

<?php if (
    strtoupper($status) === 'APPROVED'
): ?>

    <span class="processing approved">

        <i class="bi bi-lightning-charge-fill"></i>

        Auto Approved

    </span>

<?php elseif (
    strtoupper($status) === 'REJECTED'
): ?>

    <span class="processing rejected">

        <i class="bi bi-lightning-charge-fill"></i>

        Auto Rejected

    </span>

<?php elseif (
    strtoupper($status) === 'PENDING'
): ?>

    <span class="processing pending">

        <i class="bi bi-hourglass"></i>

        Old Pending Record

    </span>

<?php else: ?>

    <span class="text-muted small">

        Completed

    </span>

<?php endif; ?>

</td>


</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>


</div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js">
</script>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>

<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js">
</script>

<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js">
</script>


<script>

$(document).ready(function () {


    /* =====================================================
       DATATABLE
    ===================================================== */

    const table =
        $('#appointmentTable')
        .DataTable({

            pageLength: 10,

            lengthMenu: [
                [10,25,50,100],
                [10,25,50,100]
            ],

            /*
             * Column:
             *
             * 0 = No.
             * 1 = Appointment ID
             *
             * Therefore newest sorting uses column 1.
             */
            order: [
                [1, 'desc']
            ],

            columnDefs: [

                /*
                 * No column should not be sortable/searchable.
                 */
                {
                    targets: 0,
                    searchable: false,
                    orderable: false
                },

                /*
                 * Appointment ID is numeric.
                 */
                {
                    targets: 1,
                    type: 'num'
                }

            ],

            searching: true,

            ordering: true,

            info: true,

            paging: true

        });


    /* =====================================================
       AUTOMATIC ROW NUMBER

       Page 1 = 1 - 10
       Page 2 = 11 - 20
       Page 3 = 21 - 30

       It also recalculates after searching/filtering.
    ===================================================== */

    table.on(
        'order.dt search.dt draw.dt',
        function () {

            let info =
                table.page.info();

            table
                .column(
                    0,
                    {
                        search: 'applied',
                        order: 'applied',
                        page: 'current'
                    }
                )
                .nodes()
                .each(
                    function (cell, i) {

                        cell.innerHTML =
                            info.start + i + 1;

                    }
                );

        }
    );


    /*
     * Run numbering immediately.
     */
    table.draw();


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#appointmentSearch')
    .on(
        'input',
        function () {

            const keyword =
                $(this)
                .val()
                .trim();

            table
                .search(
                    keyword,
                    false,
                    true
                )
                .draw();

        }
    );


    /* =====================================================
       STATUS FILTER

       Status is now column 9 because No. was added.
    ===================================================== */

    $('#statusFilter')
    .on(
        'change',
        function () {

            const status =
                $(this).val();


            if (status === '') {

                table
                    .column(9)
                    .search('')
                    .draw();

            } else {

                table
                    .column(9)
                    .search(
                        '^' + status + '$',
                        true,
                        false
                    )
                    .draw();

            }

        }
    );


    /* =====================================================
       SORT FILTER

       Column 1 = Appointment ID
    ===================================================== */

    $('#sortFilter')
    .on(
        'change',
        function () {

            const mode =
                $(this).val();


            if (mode === 'asc') {

                table
                    .order([
                        [1, 'asc']
                    ])
                    .draw();

            } else {

                table
                    .order([
                        [1, 'desc']
                    ])
                    .draw();

            }

        }
    );


    /* =====================================================
       DEFAULT NEWEST FIRST
    ===================================================== */

    $('#sortFilter').val('desc');


    table
        .order([
            [1, 'desc']
        ])
        .draw();


});

</script>


</body>

</html>