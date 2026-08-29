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
   SELECTED DATE
========================================================= */

$selectedDate =
    trim(
        $_GET['date']
        ?? ''
    );


if (
    $selectedDate !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $selectedDate
    )
) {

    $selectedDate = '';

}


/* =========================================================
   TOTAL DOCTORS
========================================================= */

$totalDoctors =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            LOWER(
                TRIM(
                    ROLE
                )
            )
            =
            'doctor'

    ")->fetchColumn();


/* =========================================================
   TODAY AVAILABLE DOCTORS

   IMPORTANT:
   Count only today's availability.
========================================================= */

$availableDoctors =
    (int)$conn->query("

        SELECT
            COUNT(
                DISTINCT D.ACCOUNT_ID
            )

        FROM
            SYARMIMI.DOCTOR_AVAILABILITY D

        JOIN
            SYARMIMI.HOSPITAL_STAFF H

            ON
            D.ACCOUNT_ID =
            H.ACCOUNT_ID

        WHERE
            LOWER(
                TRIM(
                    H.ROLE
                )
            )
            =
            'doctor'

        AND
            TRUNC(
                D.AVAILABLE_DATE
            )
            =
            TRUNC(SYSDATE)

        AND
            UPPER(
                TRIM(
                    D.STATUS
                )
            )
            =
            'AVAILABLE'

    ")->fetchColumn();


/* =========================================================
   TODAY UNAVAILABLE DOCTORS

   Doctors with explicit Unavailable status today.
========================================================= */

$unavailableDoctors =
    (int)$conn->query("

        SELECT
            COUNT(
                DISTINCT D.ACCOUNT_ID
            )

        FROM
            SYARMIMI.DOCTOR_AVAILABILITY D

        JOIN
            SYARMIMI.HOSPITAL_STAFF H

            ON
            D.ACCOUNT_ID =
            H.ACCOUNT_ID

        WHERE
            LOWER(
                TRIM(
                    H.ROLE
                )
            )
            =
            'doctor'

        AND
            TRUNC(
                D.AVAILABLE_DATE
            )
            =
            TRUNC(SYSDATE)

        AND
            UPPER(
                TRIM(
                    D.STATUS
                )
            )
            =
            'UNAVAILABLE'

    ")->fetchColumn();


/* =========================================================
   DOCTORS WITHOUT TODAY STATUS
========================================================= */

$notScheduledToday =
    max(
        0,
        $totalDoctors
        -
        $availableDoctors
        -
        $unavailableDoctors
    );


/* =========================================================
   TODAY DOCTOR CARDS

   Slot count included in one query.
========================================================= */

$todayDoctorsStmt =
    $conn->query("

        SELECT

            H.ACCOUNT_ID,

            H.USERNAME,

            H.DEPARTMENT,

            D.STATUS,

            D.START_TIME,

            D.END_TIME,

            (
                SELECT COUNT(*)

                FROM
                    SYARMIMI.DOCTOR_SLOT DS

                WHERE
                    DS.ACCOUNT_ID =
                    H.ACCOUNT_ID

                AND
                    TRUNC(
                        DS.SLOT_DATE
                    )
                    =
                    TRUNC(SYSDATE)

                AND
                    UPPER(
                        TRIM(
                            DS.STATUS
                        )
                    )
                    =
                    'AVAILABLE'
            )
            AS AVAILABLE_SLOTS,

            (
                SELECT COUNT(*)

                FROM
                    SYARMIMI.DOCTOR_SLOT DS

                WHERE
                    DS.ACCOUNT_ID =
                    H.ACCOUNT_ID

                AND
                    TRUNC(
                        DS.SLOT_DATE
                    )
                    =
                    TRUNC(SYSDATE)

                AND
                    UPPER(
                        TRIM(
                            DS.STATUS
                        )
                    )
                    =
                    'BOOKED'
            )
            AS BOOKED_SLOTS

        FROM
            SYARMIMI.HOSPITAL_STAFF H

        JOIN
            SYARMIMI.DOCTOR_AVAILABILITY D

            ON
            H.ACCOUNT_ID =
            D.ACCOUNT_ID

        WHERE
            LOWER(
                TRIM(
                    H.ROLE
                )
            )
            =
            'doctor'

        AND
            TRUNC(
                D.AVAILABLE_DATE
            )
            =
            TRUNC(SYSDATE)

        ORDER BY
            H.USERNAME

    ");


$todayDoctors =
    $todayDoctorsStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   DOCTOR AVAILABILITY HISTORY / SUMMARY

   One row per doctor.

   When date is selected:
   upcoming date column will show that selected date only.
========================================================= */

$sql = "

    SELECT

        H.ACCOUNT_ID,

        H.USERNAME,

        H.DEPARTMENT,

        (
            SELECT
                D1.STATUS

            FROM
                SYARMIMI.DOCTOR_AVAILABILITY D1

            WHERE
                D1.ACCOUNT_ID =
                H.ACCOUNT_ID

            AND
                TRUNC(
                    D1.AVAILABLE_DATE
                )
                =
                TRUNC(SYSDATE)

            FETCH FIRST 1 ROW ONLY
        )
        AS TODAY_STATUS,

        (
            SELECT COUNT(*)

            FROM
                SYARMIMI.DOCTOR_SLOT DS

            WHERE
                DS.ACCOUNT_ID =
                H.ACCOUNT_ID

            AND
                UPPER(
                    TRIM(
                        DS.STATUS
                    )
                )
                =
                'AVAILABLE'

            AND
                TRUNC(
                    DS.SLOT_DATE
                )
                >=
                TRUNC(SYSDATE)
        )
        AS AVAILABLE_SLOTS,

        (
            SELECT COUNT(*)

            FROM
                SYARMIMI.DOCTOR_SLOT DS

            WHERE
                DS.ACCOUNT_ID =
                H.ACCOUNT_ID

            AND
                UPPER(
                    TRIM(
                        DS.STATUS
                    )
                )
                =
                'BOOKED'

            AND
                TRUNC(
                    DS.SLOT_DATE
                )
                >=
                TRUNC(SYSDATE)
        )
        AS BOOKED_SLOTS,

        (
            SELECT
                LISTAGG(
                    TO_CHAR(
                        X.AVAILABLE_DATE,
                        'DD-MON-YYYY'
                    ),
                    '|'
                )
                WITHIN GROUP (
                    ORDER BY
                        X.AVAILABLE_DATE
                )

            FROM
            (
                SELECT
                    D2.AVAILABLE_DATE

                FROM
                    SYARMIMI.DOCTOR_AVAILABILITY D2

                WHERE
                    D2.ACCOUNT_ID =
                    H.ACCOUNT_ID

                AND
                    TRUNC(
                        D2.AVAILABLE_DATE
                    )
                    >=
                    TRUNC(SYSDATE)

";


$params = [];


if ($selectedDate !== '') {

    $sql .= "

                AND
                    TRUNC(
                        D2.AVAILABLE_DATE
                    )
                    =
                    TO_DATE(
                        :selected_date,
                        'YYYY-MM-DD'
                    )

    ";


    $params[
        ':selected_date'
    ] =
        $selectedDate;
}


$sql .= "

                ORDER BY
                    D2.AVAILABLE_DATE

                FETCH FIRST 5 ROWS ONLY
            )
            X
        )
        AS UPCOMING_DATES

    FROM
        SYARMIMI.HOSPITAL_STAFF H

    WHERE
        LOWER(
            TRIM(
                H.ROLE
            )
        )
        =
        'doctor'

    ORDER BY
        H.USERNAME

";


$doctorStmt =
    $conn->prepare(
        $sql
    );


$doctorStmt->execute(
    $params
);


$doctorList =
    $doctorStmt->fetchAll(
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
Doctor Availability
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
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


.main-content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:30px;
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

    font-size:30px;

    font-weight:750;
}


.page-subtitle{

    margin-top:6px;

    color:#64748b;

    font-size:14px;
}


.live-badge{

    display:flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#ecfdf5;

    border:1px solid #bbf7d0;

    border-radius:8px;

    color:#15803d;

    font-size:11px;

    font-weight:650;
}


.live-dot{

    width:7px;

    height:7px;

    background:#22c55e;

    border-radius:50%;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:22px;
}


.summary-card{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:14px;

    padding:19px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:13px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.035);
}


.summary-label{

    color:#64748b;

    font-size:12px;

    font-weight:600;
}


.summary-number{

    margin-top:5px;

    color:#111827;

    font-size:29px;

    font-weight:750;

    line-height:1;
}


.summary-icon{

    width:45px;

    height:45px;

    min-width:45px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    font-size:18px;
}


.icon-total{

    background:#eff6ff;

    color:#2563eb;
}


.icon-available{

    background:#ecfdf5;

    color:#15803d;
}


.icon-unavailable{

    background:#fff1f2;

    color:#dc2626;
}


.icon-unscheduled{

    background:#f8fafc;

    color:#64748b;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{

    margin-bottom:20px;

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.card-heading{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:19px;
}


.card-heading-left{

    display:flex;

    align-items:center;

    gap:11px;
}


.card-icon{

    width:39px;

    height:39px;

    min-width:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:16px;
}


.icon-today{

    background:#ecfdf5;

    color:#15803d;
}


.icon-history{

    background:#eff6ff;

    color:#2563eb;
}


.card-title{

    margin:0;

    color:#1f2937;

    font-size:18px;

    font-weight:700;
}


.card-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:12px;
}


/* =========================================================
   DOCTOR GRID
========================================================= */

.doctor-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;
}


.doctor-card{

    position:relative;

    padding:18px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:12px;

    transition:.2s;
}


.doctor-card:hover{

    transform:translateY(-2px);

    border-color:#d7dde5;

    box-shadow:
        0 5px 14px
        rgba(15,23,42,.05);
}


.doctor-card.available-doctor{

    border-top:
        3px solid #22c55e;
}


.doctor-card.unavailable-doctor{

    border-top:
        3px solid #ef4444;
}


.doctor-header{

    display:flex;

    align-items:flex-start;

    gap:11px;

    margin-bottom:15px;
}


.doctor-avatar{

    width:42px;

    height:42px;

    min-width:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border-radius:10px;

    color:#2563eb;

    font-size:18px;
}


.doctor-name{

    color:#111827;

    font-size:13px;

    font-weight:700;
}


.doctor-department{

    margin-top:3px;

    color:#94a3b8;

    font-size:10px;
}


/* =========================================================
   STATUS
========================================================= */

.status-pill{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;
}


.status-available{

    background:#ecfdf5;

    color:#15803d;
}


.status-unavailable{

    background:#fff1f2;

    color:#dc2626;
}


.status-unscheduled{

    background:#f3f4f6;

    color:#64748b;
}


/* =========================================================
   DUTY HOURS
========================================================= */

.duty-box{

    display:flex;

    align-items:center;

    gap:6px;

    margin-top:12px;

    color:#64748b;

    font-size:10px;
}


.duty-box i{

    color:#94a3b8;
}


/* =========================================================
   SLOT SUMMARY
========================================================= */

.slot-grid{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:8px;

    margin-top:14px;
}


.slot-box{

    padding:10px;

    background:#f8fafc;

    border:1px solid #eef1f4;

    border-radius:8px;
}


.slot-label{

    color:#94a3b8;

    font-size:9px;
}


.slot-number{

    margin-top:3px;

    color:#111827;

    font-size:16px;

    font-weight:700;
}


.slot-available{

    color:#15803d;
}


.slot-booked{

    color:#dc2626;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state{

    grid-column:1 / -1;

    padding:36px;

    background:#f8fafc;

    border:1px dashed #d8dee6;

    border-radius:10px;

    color:#94a3b8;

    text-align:center;

    font-size:12px;
}


.empty-state i{

    display:block;

    margin-bottom:9px;

    font-size:28px;
}


/* =========================================================
   FILTERS
========================================================= */

.filter-box{

    margin-bottom:18px;

    padding:15px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:10px;
}


.filter-label{

    margin-bottom:7px;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    letter-spacing:.3px;

    text-transform:uppercase;
}


.form-control,
.form-select{

    min-height:44px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:12px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


.search-wrapper{

    position:relative;
}


.search-wrapper i{

    position:absolute;

    top:50%;

    left:13px;

    color:#94a3b8;

    transform:translateY(-50%);
}


.search-wrapper input{

    padding-left:39px;
}


.btn-date-search{

    min-height:44px;

    border-radius:8px;

    font-size:11px;

    font-weight:650;
}


.btn-clear-date{

    min-height:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:8px;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   ACTIVE DATE
========================================================= */

.date-message{

    display:flex;

    align-items:center;

    gap:7px;

    margin-bottom:17px;

    padding:10px 12px;

    background:#eff6ff;

    border-radius:8px;

    color:#2563eb;

    font-size:11px;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{

    overflow-x:auto;

    border:1px solid #edf0f3;

    border-radius:10px;
}


.table{

    width:100% !important;

    margin-bottom:0 !important;

    vertical-align:middle;
}


.table thead th{

    padding:12px 10px !important;

    background:#f8fafc !important;

    border-bottom:
        1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:13px 10px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.table-doctor{

    display:flex;

    align-items:center;

    gap:9px;
}


.table-avatar{

    width:34px;

    height:34px;

    min-width:34px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border-radius:8px;

    color:#2563eb;
}


.table-doctor-name{

    color:#111827;

    font-size:12px;

    font-weight:650;
}


.table-department{

    margin-top:2px;

    color:#94a3b8;

    font-size:9px;
}


/* =========================================================
   UPCOMING DATE
========================================================= */

.date-list{

    display:flex;

    flex-wrap:wrap;

    gap:5px;
}


.date-chip{

    display:inline-flex;

    padding:5px 7px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:6px;

    color:#475569;

    font-size:9px;

    white-space:nowrap;
}


/* =========================================================
   SLOT BADGE
========================================================= */

.slot-badge{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    min-width:32px;

    padding:5px 7px;

    border-radius:6px;

    font-size:10px;

    font-weight:700;
}


.slot-badge-available{

    background:#ecfdf5;

    color:#15803d;
}


.slot-badge-booked{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   ACTION
========================================================= */

.btn-schedule{

    padding:7px 9px;

    background:#2563eb;

    border:1px solid #2563eb;

    border-radius:7px;

    color:#fff;

    font-size:9px;

    font-weight:650;

    white-space:nowrap;
}


.btn-schedule:hover{

    background:#1d4ed8;

    border-color:#1d4ed8;

    color:#fff;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_filter{

    display:none !important;
}


.dataTables_wrapper
.dataTables_length{

    margin-top:14px;

    color:#64748b;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_info{

    padding-top:17px !important;

    color:#94a3b8 !important;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_paginate{

    padding-top:11px !important;
}


.page-link{

    min-width:32px;

    height:32px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:6px !important;

    font-size:11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .summary-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }


    .doctor-grid{

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );
    }

}


@media(max-width:900px){

    .main-content{

        padding:18px;
    }


    .doctor-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }


    .page-header{

        flex-direction:column;
    }

}


@media(max-width:600px){

    .summary-grid,
    .doctor-grid{

        grid-template-columns:1fr;
    }


    .page-title{

        font-size:24px;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include("../includes/sidebar_admin.php");
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Doctor Availability

</h1>


<div class="page-subtitle">

Monitor doctor availability, duty periods and appointment slot capacity.

</div>


</div>


<div class="live-badge">

<span class="live-dot"></span>

Today's Availability

</div>


</div>



<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


<div class="summary-card">


<div>

<div class="summary-label">

Total Doctors

</div>


<div class="summary-number">

<?= $totalDoctors ?>

</div>

</div>


<div class="summary-icon icon-total">

<i class="bi bi-people"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

Available Today

</div>


<div class="summary-number">

<?= $availableDoctors ?>

</div>

</div>


<div class="summary-icon icon-available">

<i class="bi bi-check-circle"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

Unavailable Today

</div>


<div class="summary-number">

<?= $unavailableDoctors ?>

</div>

</div>


<div class="summary-icon icon-unavailable">

<i class="bi bi-x-circle"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

No Status Today

</div>


<div class="summary-number">

<?= $notScheduledToday ?>

</div>

</div>


<div class="summary-icon icon-unscheduled">

<i class="bi bi-calendar-x"></i>

</div>


</div>


</div>



<!-- =====================================================
     TODAY
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-today">

<i class="bi bi-calendar-check"></i>

</div>


<div>


<h5 class="card-title">

Today's Doctor Availability

</h5>


<div class="card-subtitle">

Live doctor status and today's appointment slot capacity.

</div>


</div>


</div>


<span class="badge bg-light text-secondary">

<?= count(
    $todayDoctors
) ?>

doctor(s)

</span>


</div>



<div class="doctor-grid">


<?php if (
    empty(
        $todayDoctors
    )
): ?>


<div class="empty-state">


<i class="bi bi-calendar-x"></i>


No doctor availability has been recorded for today.


</div>


<?php endif; ?>



<?php foreach (
    $todayDoctors
    as
    $doc
): ?>


<?php

$status =
    strtoupper(
        trim(
            $doc[
                'STATUS'
            ]
            ?? ''
        )
    );


$isAvailable =
    $status ===
    'AVAILABLE';


$username =
    trim(
        $doc[
            'USERNAME'
        ]
        ?? ''
    );


$doctorName =
    stripos(
        $username,
        'Dr.'
    )
    ===
    0
        ?
        $username
        :
        'Dr. ' .
        $username;

?>


<div
    class="
        doctor-card
        <?=
        $isAvailable
            ?
            'available-doctor'
            :
            'unavailable-doctor'
        ?>
    "
>


<div class="doctor-header">


<div class="doctor-avatar">

<i class="bi bi-person-badge"></i>

</div>


<div class="flex-grow-1">


<div class="doctor-name">

<?= h(
    $doctorName
) ?>

</div>


<div class="doctor-department">

<?= h(
    $doc[
        'DEPARTMENT'
    ]
    ??
    'No Department'
) ?>

</div>


</div>


</div>



<?php if (
    $isAvailable
): ?>


<span class="status-pill status-available">

<i
    class="bi bi-circle-fill"
    style="font-size:6px;"
></i>

Available

</span>


<?php else: ?>


<span class="status-pill status-unavailable">

<i
    class="bi bi-circle-fill"
    style="font-size:6px;"
></i>

Unavailable

</span>


<?php endif; ?>



<div class="duty-box">

<i class="bi bi-clock"></i>

<?php if (
    !empty(
        $doc[
            'START_TIME'
        ]
    )
    ||
    !empty(
        $doc[
            'END_TIME'
        ]
    )
): ?>


<?= h(
    $doc[
        'START_TIME'
    ]
    ??
    '-'
) ?>

—

<?= h(
    $doc[
        'END_TIME'
    ]
    ??
    '-'
) ?>


<?php else: ?>


Duty hours not specified


<?php endif; ?>


</div>



<?php if (
    $isAvailable
): ?>


<div class="slot-grid">


<div class="slot-box">

<div class="slot-label">

Available Slots

</div>

<div class="slot-number slot-available">

<?= (int)(
    $doc[
        'AVAILABLE_SLOTS'
    ]
    ?? 0
) ?>

</div>

</div>


<div class="slot-box">

<div class="slot-label">

Booked Slots

</div>

<div class="slot-number slot-booked">

<?= (int)(
    $doc[
        'BOOKED_SLOTS'
    ]
    ?? 0
) ?>

</div>

</div>


</div>


<?php endif; ?>


</div>


<?php endforeach; ?>


</div>


</div>



<!-- =====================================================
     HISTORY
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-history">

<i class="bi bi-calendar3"></i>

</div>


<div>


<h5 class="card-title">

Availability & Slot Overview

</h5>


<div class="card-subtitle">

Search doctors, view upcoming available dates and open individual schedules.

</div>


</div>


</div>


</div>



<!-- =================================================
     FILTER
================================================= -->

<div class="filter-box">


<div class="row g-2">


<div class="col-lg-4">


<div class="filter-label">

Search Doctor

</div>


<div class="search-wrapper">


<i class="bi bi-search"></i>


<input
    type="text"
    id="doctorSearch"
    class="form-control"
    placeholder="Search doctor or department..."
>


</div>


</div>



<div class="col-lg-2">


<div class="filter-label">

Doctor Name

</div>


<select
    id="sortDoctor"
    class="form-select"
>


<option value="az">

Doctor A-Z

</option>


<option value="za">

Doctor Z-A

</option>


</select>


</div>



<div class="col-lg-2">


<div class="filter-label">

Availability Date

</div>


<select
    id="sortDate"
    class="form-select"
>


<option value="latest">

Latest Date

</option>


<option value="oldest">

Oldest Date

</option>


</select>


</div>



<form
    method="GET"
    class="contents"
></form>


<div class="col-lg-2">


<form
    method="GET"
    id="dateFilterForm"
>


<div class="filter-label">

Filter Date

</div>


<input
    type="date"
    name="date"
    class="form-control"
    value="<?= h(
        $selectedDate
    ) ?>"
>


</form>


</div>



<div class="col-lg-1 d-flex align-items-end">


<button
    type="submit"
    form="dateFilterForm"
    class="btn btn-primary btn-date-search w-100"
>

<i class="bi bi-search"></i>

</button>


</div>



<div class="col-lg-1 d-flex align-items-end">


<a
    href="doctor_availability_admin.php"
    class="btn btn-outline-secondary btn-clear-date w-100"
    title="Clear date"
>

<i class="bi bi-arrow-counterclockwise"></i>

</a>


</div>


</div>


</div>



<?php if (
    $selectedDate !== ''
): ?>


<div class="date-message">

<i class="bi bi-funnel-fill"></i>

Showing availability dates matching

<strong>

<?= h(
    strtoupper(
        date(
            'd-M-Y',
            strtotime(
                $selectedDate
            )
        )
    )
) ?>

</strong>

</div>


<?php endif; ?>



<!-- =================================================
     TABLE
================================================= -->

<div class="table-responsive">


<table
    id="availabilityTable"
    class="table"
>


<thead>


<tr>

<th>Doctor</th>

<th>Today Status</th>

<th>Upcoming Dates</th>

<th>Available Slots</th>

<th>Booked Slots</th>

<th>Action</th>

<th>Name Sort</th>

<th>Date Sort</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $doctorList
    as
    $doctor
): ?>


<?php

$username =
    trim(
        $doctor[
            'USERNAME'
        ]
        ?? ''
    );


$displayDoctor =
    stripos(
        $username,
        'Dr.'
    )
    ===
    0
        ?
        $username
        :
        'Dr. ' .
        $username;


$todayStatus =
    strtoupper(
        trim(
            $doctor[
                'TODAY_STATUS'
            ]
            ?? ''
        )
    );


$dateList =
    [];


if (
    !empty(
        $doctor[
            'UPCOMING_DATES'
        ]
    )
) {

    $dateList =
        explode(
            '|',
            $doctor[
                'UPCOMING_DATES'
            ]
        );
}


$dateSortValue =
    !empty(
        $dateList
    )
        ?
        strtotime(
            $dateList[0]
        )
        :
        0;

?>


<tr>


<!-- DOCTOR -->

<td>


<div class="table-doctor">


<div class="table-avatar">

<i class="bi bi-person-badge"></i>

</div>


<div>


<div class="table-doctor-name">

<?= h(
    $displayDoctor
) ?>

</div>


<div class="table-department">

<?= h(
    $doctor[
        'DEPARTMENT'
    ]
    ??
    '-'
) ?>

</div>


</div>


</div>


</td>



<!-- TODAY STATUS -->

<td>


<?php if (
    $todayStatus ===
    'AVAILABLE'
): ?>


<span class="status-pill status-available">

<i
    class="bi bi-circle-fill"
    style="font-size:6px;"
></i>

Available

</span>


<?php elseif (
    $todayStatus ===
    'UNAVAILABLE'
): ?>


<span class="status-pill status-unavailable">

<i
    class="bi bi-circle-fill"
    style="font-size:6px;"
></i>

Unavailable

</span>


<?php else: ?>


<span class="status-pill status-unscheduled">

<i class="bi bi-dash-circle"></i>

No Status

</span>


<?php endif; ?>


</td>



<!-- UPCOMING -->

<td>


<?php if (
    !empty(
        $dateList
    )
): ?>


<div class="date-list">


<?php foreach (
    $dateList
    as
    $date
): ?>


<span class="date-chip">

<i class="bi bi-calendar3 me-1"></i>

<?= h(
    $date
) ?>

</span>


<?php endforeach; ?>


</div>


<?php else: ?>


<span class="text-muted">

No upcoming dates

</span>


<?php endif; ?>


</td>



<!-- AVAILABLE SLOT -->

<td>


<span class="slot-badge slot-badge-available">

<?= (int)(
    $doctor[
        'AVAILABLE_SLOTS'
    ]
    ?? 0
) ?>

</span>


</td>



<!-- BOOKED SLOT -->

<td>


<span class="slot-badge slot-badge-booked">

<?= (int)(
    $doctor[
        'BOOKED_SLOTS'
    ]
    ?? 0
) ?>

</span>


</td>



<!-- ACTION -->

<td>


<a
    href="doctor_slot_view.php?doctor=<?= urlencode(
        $doctor[
            'ACCOUNT_ID'
        ]
    ) ?>"
    class="btn btn-schedule"
>

<i class="bi bi-calendar-week me-1"></i>

View Schedule

</a>


</td>



<!-- HIDDEN NAME SORT -->

<td>

<?= h(
    strtolower(
        $displayDoctor
    )
) ?>

</td>



<!-- HIDDEN DATE SORT -->

<td>

<?= h(
    $dateSortValue
) ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>



<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
></script>


<script>

$(document).ready(
function()
{

    /* =====================================================
       DATATABLE

       IMPORTANT:
       Only initialise once.
    ===================================================== */

    const table =
        $('#availabilityTable')
        .DataTable({

            pageLength:
                10,

            lengthMenu:
            [
                [10,25,50,100],
                [10,25,50,100]
            ],

            dom:
                'lrtip',

            /*
             Hidden name sort = 6.
            */

            order:
            [
                [6,'asc']
            ],

            columnDefs:
            [

                {
                    targets:
                        5,

                    orderable:
                        false,

                    searchable:
                        false
                },


                {
                    targets:
                        6,

                    visible:
                        false,

                    searchable:
                        false
                },


                {
                    targets:
                        7,

                    visible:
                        false,

                    searchable:
                        false
                }

            ]

        });



    /* =====================================================
       SEARCH
    ===================================================== */

    $('#doctorSearch').on(
        'input',
        function()
        {

            table
                .search(
                    this.value
                )
                .draw();

        }
    );



    /* =====================================================
       DOCTOR SORT
    ===================================================== */

    $('#sortDoctor').on(
        'change',
        function()
        {

            if (
                this.value ===
                'za'
            ) {

                table
                    .order([
                        [6,'desc']
                    ])
                    .draw();

            }
            else {

                table
                    .order([
                        [6,'asc']
                    ])
                    .draw();

            }

        }
    );



    /* =====================================================
       DATE SORT
    ===================================================== */

    $('#sortDate').on(
        'change',
        function()
        {

            if (
                this.value ===
                'oldest'
            ) {

                table
                    .order([
                        [7,'asc']
                    ])
                    .draw();

            }
            else {

                table
                    .order([
                        [7,'desc']
                    ])
                    .draw();

            }

        }
    );

}
);

</script>


</body>

</html>