<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {

    header("Location: ../auth/login.php");
    exit();

}


/* =========================================================
   CURRENT DOCTOR
========================================================= */

$doctorId =
    (int)($_SESSION['user_id'] ?? 0);


if ($doctorId <= 0) {

    die("Invalid doctor account.");

}


/* =========================================================
   DOCTOR INFORMATION
========================================================= */

$doctorStmt =
    $conn->prepare("

        SELECT
            USERNAME,
            DEPARTMENT

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            ACCOUNT_ID = ?

    ");


$doctorStmt->execute([
    $doctorId
]);


$doctor =
    $doctorStmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$doctor) {

    die("Doctor account not found.");

}


$doctorDisplayName =
    'Dr. ' .
    trim(
        $doctor['USERNAME']
        ?? 'Doctor'
    );



/* =========================================================
   APPOINTMENTS
   LATEST RECORD FIRST
========================================================= */

$sql = "

    SELECT

        A.APPOINTMENT_ID,

        A.PATIENT_ID,

        A.PATIENT_NAME,

        A.IC_NUMBER,

        A.GENDER,

        A.PHONE,

        A.EMAIL,

        A.ADDRESS,

        A.DEPARTMENT,

        A.DOCTOR_NAME,

        A.APPOINTMENT_DATE,

        A.APPOINTMENT_TIME,

        A.STATUS,

        A.NOTES,

        A.ACCOUNT_ID,

        CASE

            WHEN EXISTS
            (
                SELECT 1

                FROM SYARMIMI.DIAGNOSIS D

                WHERE
                    D.APPOINTMENT_ID =
                    A.APPOINTMENT_ID
            )

            THEN 'YES'

            ELSE 'NO'

        END AS HAS_DIAGNOSIS

    FROM
        SYARMIMI.APPOINTMENT A

    WHERE
        A.ACCOUNT_ID = ?

    ORDER BY
        A.APPOINTMENT_ID DESC

";


$stmt =
    $conn->prepare($sql);


$stmt->execute([
    $doctorId
]);


$appointments =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );



/* =========================================================
   DATE HELPERS
========================================================= */

function normalizeAppointmentDate($value)
{

    if (
        $value === null ||
        trim($value) === ''
    ) {

        return '';

    }


    $value =
        trim($value);


    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $value
        )
    ) {

        return $value;

    }


    if (
        preg_match(
            '/^\d{2}-[A-Za-z]{3}-\d{2}$/',
            $value
        )
    ) {

        $date =
            DateTime::createFromFormat(
                'd-M-y',
                strtoupper($value)
            );


        if ($date) {

            return
                $date->format(
                    'Y-m-d'
                );

        }

    }


    if (
        preg_match(
            '/^\d{2}-[A-Za-z]{3}-\d{4}$/',
            $value
        )
    ) {

        $date =
            DateTime::createFromFormat(
                'd-M-Y',
                strtoupper($value)
            );


        if ($date) {

            return
                $date->format(
                    'Y-m-d'
                );

        }

    }


    $timestamp =
        strtotime($value);


    if ($timestamp !== false) {

        return
            date(
                'Y-m-d',
                $timestamp
            );

    }


    return '';

}



function displayAppointmentDate($value)
{

    $normalized =
        normalizeAppointmentDate(
            $value
        );


    if ($normalized === '') {

        return
            $value ?: '-';

    }


    return strtoupper(
        date(
            'd-M-y',
            strtotime(
                $normalized
            )
        )
    );

}



/* =========================================================
   COUNTERS
========================================================= */

$today =
    date('Y-m-d');


$activeAppointments = 0;

$completedAppointments = 0;

$admittedAppointments = 0;

$todayActiveAppointments = 0;


foreach ($appointments as $appointment) {

    $status =
        strtoupper(
            trim(
                $appointment['STATUS']
                ?? ''
            )
        );


    $hasDiagnosis =
        strtoupper(
            trim(
                $appointment['HAS_DIAGNOSIS']
                ?? 'NO'
            )
        );


    $appointmentDate =
        normalizeAppointmentDate(
            $appointment['APPOINTMENT_DATE']
            ?? ''
        );


    if (
        $status === 'APPROVED'
        &&
        $hasDiagnosis === 'NO'
    ) {

        $activeAppointments++;


        if (
            $appointmentDate === $today
        ) {

            $todayActiveAppointments++;

        }

    }


    if (
        $status === 'COMPLETED'
        ||
        (
            $hasDiagnosis === 'YES'
            &&
            $status !== 'ADMITTED'
        )
    ) {

        $completedAppointments++;

    }


    if (
        $status === 'ADMITTED'
    ) {

        $admittedAppointments++;

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
Doctor Appointments
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

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;

    color:#1f2937;
}


/* =========================================================
   CONTENT
========================================================= */

.content{

    flex:1;

    min-width:0;

    padding:28px;

    min-height:100vh;
}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    margin-bottom:24px;
}


.page-title{

    margin:0;

    font-size:26px;

    font-weight:700;

    color:#111827;
}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;
}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card{

    height:100%;

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;
}


.stat-label{

    color:#8a94a3;

    font-size:12px;

    font-weight:600;
}


.stat-number{

    margin-top:5px;

    color:#111827;

    font-size:27px;

    font-weight:700;
}


.stat-icon{

    width:38px;

    height:38px;

    border-radius:9px;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:16px;
}


.icon-active{

    background:#eff6ff;

    color:#2563eb;
}


.icon-today{

    background:#ecfdf5;

    color:#16a34a;
}


.icon-completed{

    background:#f3f4f6;

    color:#475569;
}


.icon-admitted{

    background:#fff7ed;

    color:#ea580c;
}


/* =========================================================
   CONTENT BOX
========================================================= */

.content-box{

    margin-top:22px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box{

    margin-bottom:18px;

    padding:14px;

    background:#f8fafc;

    border:1px solid #e8ebef;

    border-radius:10px;
}


.form-control,
.form-select{

    min-height:42px;

    border-radius:8px;

    border-color:#dfe3e8;

    font-size:13px;
}


/* =========================================================
   TABLE
========================================================= */

.table{

    margin-bottom:0;

    vertical-align:middle;
}


.table thead th{

    padding:11px 12px;

    background:#f8fafc;

    border-bottom:1px solid #e5e7eb;

    color:#64748b;

    font-size:11px;

    font-weight:650;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:13px 12px;

    border-color:#eef1f4;

    color:#374151;

    font-size:13px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-name{

    color:#1f2937;

    font-weight:650;
}


.patient-email{

    margin-top:2px;

    color:#9ca3af;

    font-size:11px;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:6px;

    font-size:11px;

    font-weight:650;
}


.status-active{

    background:#eff6ff;

    color:#2563eb;
}


.status-completed{

    background:#ecfdf5;

    color:#15803d;
}


.status-admitted{

    background:#fff7ed;

    color:#c2410c;
}


.status-other{

    background:#f3f4f6;

    color:#64748b;
}


/* =========================================================
   DATE TAG
========================================================= */

.date-tag{

    display:inline-flex;

    margin-top:4px;

    padding:4px 7px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;
}


.tag-today{

    background:#ecfdf5;

    color:#15803d;
}


.tag-upcoming{

    background:#eff6ff;

    color:#2563eb;
}


/* =========================================================
   BUTTON
========================================================= */

.action-btn{

    padding:6px 9px;

    border-radius:7px;

    font-size:11px;
}


/* =========================================================
   DONE INFO
========================================================= */

.done-text{

    display:inline-flex;

    align-items:center;

    gap:5px;

    color:#15803d;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   PAGINATION
========================================================= */

.appointment-pagination{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-top:16px;
}


.pagination-info{

    color:#64748b;

    font-size:12px;
}


.pagination-buttons{

    display:flex;

    align-items:center;

    gap:5px;
}


.page-btn{

    min-width:34px;

    height:34px;

    padding:0 9px;

    border:1px solid #dbe1e8;

    border-radius:7px;

    background:#fff;

    color:#475569;

    font-size:12px;

    font-weight:600;
}


.page-btn.active{

    background:#2563eb;

    border-color:#2563eb;

    color:#fff;
}


.page-btn:disabled{

    opacity:.4;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-box{

    padding:50px 20px;

    text-align:center;

    color:#94a3b8;
}


/* =========================================================
   MODAL
========================================================= */

.detail-row{

    padding:10px 0;

    border-bottom:1px solid #eef1f4;
}


.detail-label{

    color:#94a3b8;

    font-size:11px;

    text-transform:uppercase;
}


.detail-value{

    margin-top:3px;

    color:#1f2937;

    font-size:14px;

    font-weight:500;
}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:768px){

    .content{

        padding:18px;
    }


    .appointment-pagination{

        flex-direction:column;

        align-items:flex-start;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include(
    "../includes/sidebar_doctor.php"
);
?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<h1 class="page-title">

My Appointments

</h1>


<div class="page-subtitle">

Manage active, completed and admitted appointment records.

</div>

</div>



<!-- =====================================================
     STATS
===================================================== -->

<div class="row g-3">


<div class="col-xl-3 col-md-6">

<div class="stat-card">

<div class="d-flex justify-content-between">

<div>

<div class="stat-label">
Active
</div>

<div class="stat-number">
<?= $activeAppointments ?>
</div>

</div>


<div class="stat-icon icon-active">

<i class="bi bi-calendar2-check"></i>

</div>

</div>

</div>

</div>


<div class="col-xl-3 col-md-6">

<div class="stat-card">

<div class="d-flex justify-content-between">

<div>

<div class="stat-label">
Today To Treat
</div>

<div class="stat-number">
<?= $todayActiveAppointments ?>
</div>

</div>


<div class="stat-icon icon-today">

<i class="bi bi-clipboard2-pulse"></i>

</div>

</div>

</div>

</div>


<div class="col-xl-3 col-md-6">

<div class="stat-card">

<div class="d-flex justify-content-between">

<div>

<div class="stat-label">
Completed
</div>

<div class="stat-number">
<?= $completedAppointments ?>
</div>

</div>


<div class="stat-icon icon-completed">

<i class="bi bi-check-circle"></i>

</div>

</div>

</div>

</div>


<div class="col-xl-3 col-md-6">

<div class="stat-card">

<div class="d-flex justify-content-between">

<div>

<div class="stat-label">
Admitted
</div>

<div class="stat-number">
<?= $admittedAppointments ?>
</div>

</div>


<div class="stat-icon icon-admitted">

<i class="bi bi-hospital"></i>

</div>

</div>

</div>

</div>


</div>



<!-- =====================================================
     CONTENT
===================================================== -->

<div class="content-box">


<div class="mb-3">

<h5 class="mb-1">

Appointment Schedule

</h5>


<small class="text-muted">

<?= htmlspecialchars(
    $doctorDisplayName
) ?>

<?php if (
    !empty(
        $doctor['DEPARTMENT']
    )
): ?>

•
<?= htmlspecialchars(
    $doctor['DEPARTMENT']
) ?>

<?php endif; ?>

</small>

</div>



<!-- =================================================
     FILTER
================================================== -->

<div class="filter-box">

<div class="row g-2">


<div class="col-lg-4">

<input
    type="text"
    id="appointmentSearch"
    class="form-control"
    placeholder="Search patient, IC, phone..."
>

</div>


<div class="col-lg-3">

<input
    type="date"
    id="appointmentDateFilter"
    class="form-control"
>

</div>


<div class="col-lg-3">

<select
    id="appointmentStatusFilter"
    class="form-select"
>

<option value="">
All Status
</option>

<option value="active">
Active
</option>

<option value="completed">
Completed
</option>

<option value="admitted">
Admitted
</option>

</select>

</div>


<div class="col-lg-2">

<button
    type="button"
    id="clearFilters"
    class="btn btn-outline-secondary w-100"
    style="min-height:42px;"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>

Clear

</button>

</div>


</div>

</div>



<!-- =================================================
     TABLE
================================================== -->

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>No.</th>
<th>Patient</th>
<th>IC Number</th>
<th>Department</th>
<th>Date</th>
<th>Time</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>


<tbody id="appointmentTableBody">


<?php foreach (
    $appointments
    as
    $a
): ?>


<?php

$status =
    strtoupper(
        trim(
            $a['STATUS']
            ?? ''
        )
    );


$hasDiagnosis =
    strtoupper(
        trim(
            $a['HAS_DIAGNOSIS']
            ?? 'NO'
        )
    );


$normalizedDate =
    normalizeAppointmentDate(
        $a['APPOINTMENT_DATE']
        ?? ''
    );


$isToday =
    $normalizedDate
    ===
    $today;


$isUpcoming =
    $normalizedDate
    >
    $today;


if (
    $status === 'ADMITTED'
) {

    $displayState =
        'admitted';

}

elseif (
    $status === 'COMPLETED'
    ||
    $hasDiagnosis === 'YES'
) {

    $displayState =
        'completed';

}

elseif (
    $status === 'APPROVED'
) {

    $displayState =
        'active';

}

else {

    $displayState =
        'other';

}


$searchText =
    strtolower(
        trim(

            ($a['PATIENT_NAME'] ?? '')
            .
            ' '
            .
            ($a['IC_NUMBER'] ?? '')
            .
            ' '
            .
            ($a['PHONE'] ?? '')
            .
            ' '
            .
            ($a['EMAIL'] ?? '')
            .
            ' '
            .
            ($a['DEPARTMENT'] ?? '')

        )
    );

?>


<tr
    class="appointment-row"

    data-id="<?= (int)$a['APPOINTMENT_ID'] ?>"

    data-date="<?= htmlspecialchars(
        $normalizedDate
    ) ?>"

    data-status="<?= htmlspecialchars(
        $displayState
    ) ?>"

    data-search="<?= htmlspecialchars(
        $searchText
    ) ?>"
>


<td class="appointment-number">
</td>


<td>

<div class="patient-name">

<?= htmlspecialchars(
    $a['PATIENT_NAME']
    ?? ''
) ?>

</div>


<div class="patient-email">

<?= htmlspecialchars(
    $a['EMAIL']
    ?? ''
) ?>

</div>

</td>


<td>

<?= htmlspecialchars(
    $a['IC_NUMBER']
    ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $a['DEPARTMENT']
    ?? '-'
) ?>

</td>


<td>

<div>

<?= htmlspecialchars(
    displayAppointmentDate(
        $a['APPOINTMENT_DATE']
        ?? ''
    )
) ?>

</div>


<?php if (
    $isToday
    &&
    $displayState === 'active'
): ?>

<span class="date-tag tag-today">

Today

</span>

<?php elseif (
    $isUpcoming
    &&
    $displayState === 'active'
): ?>

<span class="date-tag tag-upcoming">

Upcoming

</span>

<?php endif; ?>


</td>


<td>

<?= htmlspecialchars(
    $a['APPOINTMENT_TIME']
    ?? '-'
) ?>

</td>


<td>


<?php if (
    $displayState === 'active'
): ?>

<span class="status-badge status-active">

<i class="bi bi-clock"></i>

Active

</span>


<?php elseif (
    $displayState === 'completed'
): ?>

<span class="status-badge status-completed">

<i class="bi bi-check-circle"></i>

Completed

</span>


<?php elseif (
    $displayState === 'admitted'
): ?>

<span class="status-badge status-admitted">

<i class="bi bi-hospital"></i>

Admitted

</span>


<?php else: ?>

<span class="status-badge status-other">

<?= htmlspecialchars(
    $a['STATUS']
    ?? '-'
) ?>

</span>

<?php endif; ?>


</td>


<td>


<div class="d-flex flex-wrap gap-2">


<button
    type="button"
    class="btn btn-outline-secondary action-btn"
    data-bs-toggle="modal"
    data-bs-target="#patientModal<?= (int)$a['APPOINTMENT_ID'] ?>"
>

<i class="bi bi-eye me-1"></i>

View

</button>



<?php if (
    $displayState === 'active'
    &&
    $isToday
): ?>


<a
    href="treatment.php?type=appointment&id=<?= urlencode(
        $a['APPOINTMENT_ID']
    ) ?>"
    class="btn btn-primary action-btn"
>

<i class="bi bi-clipboard2-pulse me-1"></i>

Treat

</a>


<?php elseif (
    $displayState === 'completed'
): ?>


<span class="done-text">

<i class="bi bi-check-circle-fill"></i>

Done

</span>


<?php elseif (
    $displayState === 'admitted'
): ?>


<span class="text-warning small fw-semibold">

<i class="bi bi-hospital me-1"></i>

Admitted

</span>


<?php endif; ?>


</div>


</td>


</tr>



<!-- =================================================
     MODAL
================================================== -->

<div
    class="modal fade"
    id="patientModal<?= (int)$a['APPOINTMENT_ID'] ?>"
    tabindex="-1"
>


<div class="modal-dialog modal-dialog-centered">


<div class="modal-content border-0 rounded-4">


<div class="modal-header border-0 pb-0">

<div>

<h5 class="modal-title">

Patient Details

</h5>


<small class="text-muted">

Appointment
#<?= (int)$a['APPOINTMENT_ID'] ?>

</small>

</div>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>

</div>


<div class="modal-body">


<div class="detail-row">

<div class="detail-label">
Patient Name
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['PATIENT_NAME']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
IC Number
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['IC_NUMBER']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Gender
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['GENDER']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Phone
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['PHONE']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Email
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['EMAIL']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Address
</div>

<div class="detail-value">

<?= htmlspecialchars(
    $a['ADDRESS']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Appointment
</div>

<div class="detail-value">

<?= htmlspecialchars(
    displayAppointmentDate(
        $a['APPOINTMENT_DATE']
        ?? ''
    )
) ?>

at

<?= htmlspecialchars(
    $a['APPOINTMENT_TIME']
    ?? '-'
) ?>

</div>

</div>


<div class="detail-row">

<div class="detail-label">
Treatment Status
</div>

<div class="detail-value">

<?= ucfirst(
    htmlspecialchars(
        $displayState
    )
) ?>

</div>

</div>


<div class="detail-row border-0">

<div class="detail-label">
Symptoms / Notes
</div>

<div class="detail-value">

<?= nl2br(
    htmlspecialchars(
        $a['NOTES']
        ??
        'No notes provided.'
    )
) ?>

</div>

</div>


</div>


<?php if (
    $displayState === 'active'
    &&
    $isToday
): ?>

<div class="modal-footer border-0 pt-0">

<a
    href="treatment.php?type=appointment&id=<?= urlencode(
        $a['APPOINTMENT_ID']
    ) ?>"
    class="btn btn-primary"
>

<i class="bi bi-clipboard2-pulse me-1"></i>

Start Treatment

</a>

</div>

<?php endif; ?>


</div>

</div>

</div>


<?php endforeach; ?>


</tbody>

</table>

</div>



<!-- =================================================
     EMPTY FILTER
================================================== -->

<div
    id="noFilterResult"
    class="empty-box"
    style="display:none;"
>

<i class="bi bi-search fs-2 d-block mb-2"></i>

<h6>
No matching appointments
</h6>

<p class="mb-0">
Try changing the filters.
</p>

</div>



<!-- =================================================
     PAGINATION
================================================== -->

<div
    id="appointmentPagination"
    class="appointment-pagination"
>

<div
    id="paginationInfo"
    class="pagination-info"
></div>


<div
    id="paginationButtons"
    class="pagination-buttons"
></div>

</div>


</div>


</div>

</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   APPOINTMENT FILTER
========================================================= */

const appointmentRows =
    Array.from(
        document.querySelectorAll(
            ".appointment-row"
        )
    );


const appointmentSearch =
    document.getElementById(
        "appointmentSearch"
    );


const appointmentDateFilter =
    document.getElementById(
        "appointmentDateFilter"
    );


const appointmentStatusFilter =
    document.getElementById(
        "appointmentStatusFilter"
    );


const clearFilters =
    document.getElementById(
        "clearFilters"
    );


const paginationInfo =
    document.getElementById(
        "paginationInfo"
    );


const paginationButtons =
    document.getElementById(
        "paginationButtons"
    );


const appointmentPagination =
    document.getElementById(
        "appointmentPagination"
    );


const noFilterResult =
    document.getElementById(
        "noFilterResult"
    );


const rowsPerPage = 8;


let currentPage = 1;


let filteredRows =
    [...appointmentRows];


/* =========================================================
   KEEP LATEST RECORD FIRST
========================================================= */

function sortLatestFirst(rows)
{

    return rows.sort(
        function(a,b)
        {

            const idA =
                parseInt(
                    a.dataset.id || 0
                );


            const idB =
                parseInt(
                    b.dataset.id || 0
                );


            return idB - idA;

        }
    );

}


filteredRows =
    sortLatestFirst(
        filteredRows
    );



function filterAppointments()
{

    const search =
        appointmentSearch
        ?
        appointmentSearch.value
            .trim()
            .toLowerCase()
        :
        "";


    const selectedDate =
        appointmentDateFilter
        ?
        appointmentDateFilter.value
        :
        "";


    const selectedStatus =
        appointmentStatusFilter
        ?
        appointmentStatusFilter.value
        :
        "";


    filteredRows =
        appointmentRows.filter(
            function(row)
            {

                const rowSearch =
                    (
                        row.dataset.search
                        ||
                        ""
                    )
                    .toLowerCase();


                const rowDate =
                    row.dataset.date
                    ||
                    "";


                const rowStatus =
                    row.dataset.status
                    ||
                    "";


                const matchSearch =
                    search === ""
                    ||
                    rowSearch.includes(
                        search
                    );


                const matchDate =
                    selectedDate === ""
                    ||
                    rowDate === selectedDate;


                const matchStatus =
                    selectedStatus === ""
                    ||
                    rowStatus === selectedStatus;


                return (
                    matchSearch
                    &&
                    matchDate
                    &&
                    matchStatus
                );

            }
        );


    filteredRows =
        sortLatestFirst(
            filteredRows
        );


    currentPage = 1;


    renderAppointments();

}



function renderAppointments()
{

    appointmentRows.forEach(
        function(row)
        {

            row.style.display =
                "none";

        }
    );


    const total =
        filteredRows.length;


    if (total === 0)
    {

        if (noFilterResult)
        {

            noFilterResult.style.display =
                "block";

        }


        if (appointmentPagination)
        {

            appointmentPagination.style.display =
                "none";

        }


        return;

    }


    if (noFilterResult)
    {

        noFilterResult.style.display =
            "none";

    }


    if (appointmentPagination)
    {

        appointmentPagination.style.display =
            "flex";

    }


    const totalPages =
        Math.ceil(
            total / rowsPerPage
        );


    if (
        currentPage > totalPages
    )
    {

        currentPage =
            totalPages;

    }


    const start =
        (
            currentPage - 1
        )
        *
        rowsPerPage;


    const end =
        Math.min(
            start + rowsPerPage,
            total
        );


    filteredRows
        .slice(
            start,
            end
        )
        .forEach(
            function(row,index)
            {

                row.style.display =
                    "";


                const number =
                    row.querySelector(
                        ".appointment-number"
                    );


                if (number)
                {

                    number.textContent =
                        start
                        +
                        index
                        +
                        1;

                }

            }
        );


    if (paginationInfo)
    {

        paginationInfo.textContent =
            "Showing "
            +
            (
                start + 1
            )
            +
            "–"
            +
            end
            +
            " of "
            +
            total
            +
            " appointments";

    }


    renderPagination(
        totalPages
    );

}



function renderPagination(
    totalPages
)
{

    if (!paginationButtons)
    {

        return;

    }


    paginationButtons.innerHTML =
        "";


    const previous =
        document.createElement(
            "button"
        );


    previous.type =
        "button";


    previous.className =
        "page-btn";


    previous.innerHTML =
        '<i class="bi bi-chevron-left"></i>';


    previous.disabled =
        currentPage === 1;


    previous.onclick =
        function()
        {

            if (currentPage > 1)
            {

                currentPage--;

                renderAppointments();

            }

        };


    paginationButtons.appendChild(
        previous
    );


    for (
        let page = 1;
        page <= totalPages;
        page++
    )
    {

        const button =
            document.createElement(
                "button"
            );


        button.type =
            "button";


        button.className =
            "page-btn";


        if (
            page === currentPage
        )
        {

            button.classList.add(
                "active"
            );

        }


        button.textContent =
            page;


        button.onclick =
            function()
            {

                currentPage =
                    page;


                renderAppointments();

            };


        paginationButtons.appendChild(
            button
        );

    }


    const next =
        document.createElement(
            "button"
        );


    next.type =
        "button";


    next.className =
        "page-btn";


    next.innerHTML =
        '<i class="bi bi-chevron-right"></i>';


    next.disabled =
        currentPage
        ===
        totalPages;


    next.onclick =
        function()
        {

            if (
                currentPage
                <
                totalPages
            )
            {

                currentPage++;

                renderAppointments();

            }

        };


    paginationButtons.appendChild(
        next
    );

}



if (appointmentSearch)
{

    appointmentSearch.addEventListener(
        "input",
        filterAppointments
    );

}


if (appointmentDateFilter)
{

    appointmentDateFilter.addEventListener(
        "change",
        filterAppointments
    );

}


if (appointmentStatusFilter)
{

    appointmentStatusFilter.addEventListener(
        "change",
        filterAppointments
    );

}


if (clearFilters)
{

    clearFilters.addEventListener(
        "click",
        function()
        {

            appointmentSearch.value =
                "";


            appointmentDateFilter.value =
                "";


            appointmentStatusFilter.value =
                "";


            filterAppointments();

        }
    );

}


if (
    appointmentRows.length > 0
)
{

    filterAppointments();

}

</script>


</body>

</html>