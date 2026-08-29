<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'nurse'
) {

    header("Location: ../auth/login.php");
    exit();

}


date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   TOTAL PATIENTS
========================================================= */

$stmt1 =
    $conn->query("

        SELECT
            COUNT(*) AS TOTAL

        FROM
            SYARMIMI.PATIENT

    ");


$row1 =
    $stmt1->fetch(
        PDO::FETCH_ASSOC
    );


$totalPatients =
    (int)($row1['TOTAL'] ?? 0);



/* =========================================================
   TOTAL ADMISSION
========================================================= */

$stmt2 =
    $conn->query("

        SELECT
            COUNT(*) AS TOTAL

        FROM
            SYARMIMI.ADMISSION

    ");


$row2 =
    $stmt2->fetch(
        PDO::FETCH_ASSOC
    );


$totalAdmissions =
    (int)($row2['TOTAL'] ?? 0);



/* =========================================================
   OCCUPIED BEDS
========================================================= */

$stmt3 =
    $conn->query("

        SELECT
            COUNT(*) AS TOTAL

        FROM
            SYARMIMI.BED

        WHERE
            STATUS = 'Occupied'

    ");


$row3 =
    $stmt3->fetch(
        PDO::FETCH_ASSOC
    );


$occupiedBeds =
    (int)($row3['TOTAL'] ?? 0);



/* =========================================================
   TOTAL BEDS
========================================================= */

$totalBeds =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.BED

        ")
        ->fetchColumn();



/* =========================================================
   ACTIVE WARDS
========================================================= */

$activeWards =
    (int)$conn
        ->query("

            SELECT
                COUNT(
                    DISTINCT WARD_ID
                )

            FROM
                SYARMIMI.BED

        ")
        ->fetchColumn();



/* =========================================================
   RECENT ADMISSIONS
========================================================= */

$recentAdmissions =
    $conn->query("

        SELECT

            p.NAME,

            b.BED_NUMBER,

            w.WARD_NAME,

            a.ADMISSION_ID,

            TO_CHAR(
                a.ADMISSION_DATE,
                'DD-MON-YYYY'
            ) AS ADMISSION_DATE

        FROM
            SYARMIMI.ADMISSION a

        JOIN
            SYARMIMI.PATIENT p

            ON
            a.PATIENT_ID =
            p.PATIENT_ID

        JOIN
            SYARMIMI.BED b

            ON
            a.BED_ID =
            b.BED_ID

        JOIN
            SYARMIMI.WARD w

            ON
            b.WARD_ID =
            w.WARD_ID

        ORDER BY
            a.ADMISSION_ID DESC

        FETCH FIRST 5 ROWS ONLY

    ");



/* =========================================================
   PENDING MEDICATION
========================================================= */

$pendingMed =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER mo

            JOIN
                SYARMIMI.ADMISSION a

                ON
                mo.ADMISSION_ID =
                a.ADMISSION_ID

            JOIN
                SYARMIMI.PHARMACY_PREPARATION pp

                ON
                mo.MEDORDER_ID =
                pp.MEDORDER_ID

            LEFT JOIN
                SYARMIMI.MEDICATION_ADMIN ma

                ON
                mo.MEDORDER_ID =
                ma.MEDORDER_ID

            WHERE
                ma.MEDORDER_ID
                IS NULL

            AND
                pp.STATUS =
                'Ready For Nurse Pickup'

            AND
                a.DISCHARGE_DATE
                IS NULL

        ")
        ->fetchColumn();



/* =========================================================
   PATIENT RECORD CARDS
========================================================= */

$stmtPatient =
    $conn->query("

        SELECT

            p.NAME,

            p.GENDER,

            a.ADMISSION_ID,

            w.WARD_NAME,

            b.BED_NUMBER

        FROM
            SYARMIMI.PATIENT p

        JOIN
            SYARMIMI.ADMISSION a

            ON
            p.PATIENT_ID =
            a.PATIENT_ID

        JOIN
            SYARMIMI.BED b

            ON
            a.BED_ID =
            b.BED_ID

        JOIN
            SYARMIMI.WARD w

            ON
            b.WARD_ID =
            w.WARD_ID

        WHERE
            a.DISCHARGE_DATE
            IS NULL

        ORDER BY
            a.ADMISSION_ID DESC

        FETCH FIRST 12 ROWS ONLY

    ");


$patientCards =
    $stmtPatient->fetchAll(
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
Nurse Dashboard
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
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


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content{

    min-height:100vh;

    margin-left:260px;

    padding:28px;
}


/* =========================================================
   PAGE HEADER
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

    font-size:26px;

    font-weight:700;
}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;
}


.shift-badge{

    display:flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:9px;

    color:#2563eb;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.care-notification{

    margin-bottom:20px;

    padding:14px 16px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    background:#fff7ed;

    border:1px solid #fed7aa;

    border-radius:11px;
}


.care-notification-left{

    display:flex;

    align-items:center;

    gap:11px;
}


.notification-icon{

    width:38px;
    height:38px;

    min-width:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#ffedd5;

    border-radius:9px;

    color:#ea580c;

    font-size:16px;
}


.notification-title{

    color:#9a3412;

    font-size:12px;

    font-weight:700;
}


.notification-text{

    margin-top:2px;

    color:#c2410c;

    font-size:11px;
}


.notification-btn{

    min-height:34px;

    padding:0 11px;

    display:inline-flex;

    align-items:center;

    gap:5px;

    border:0;

    border-radius:7px;

    background:#ea580c;

    color:#fff;

    text-decoration:none;

    font-size:10px;

    font-weight:600;
}


.notification-btn:hover{

    background:#c2410c;

    color:#fff;
}


/* =========================================================
   REAL TIME
========================================================= */

.realtime-card{

    height:100%;

    min-height:155px;

    padding:19px;

    background:
        linear-gradient(
            145deg,
            #ffffff,
            #f8fbff
        );

    border:1px solid #e7eaee;

    border-radius:13px;
}


.realtime-top{

    display:flex;

    justify-content:space-between;

    gap:15px;
}


.realtime-label{

    color:#94a3b8;

    font-size:10px;

    font-weight:600;

    text-transform:uppercase;

    letter-spacing:.5px;
}


.realtime-date{

    margin-top:4px;

    color:#64748b;

    font-size:11px;
}


.realtime-time{

    margin-top:5px;

    color:#0f172a;

    font-size:25px;

    font-weight:750;

    line-height:1;
}


.clock-icon{

    width:38px;
    height:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    background:#eff6ff;

    color:#2563eb;

    font-size:16px;
}


.realtime-divider{

    margin:22px 0 12px;

    border-top:1px solid #e5e7eb;
}


.realtime-footer-title{

    color:#374151;

    font-size:11px;

    font-weight:650;
}


.realtime-footer-text{

    margin-top:2px;

    color:#94a3b8;

    font-size:9px;
}


/* =========================================================
   KPI CARD
========================================================= */

.stat-card{

    height:100%;

    min-height:155px;

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:13px;

    transition:.18s;
}


.stat-card:hover{

    transform:translateY(-2px);

    border-color:#d5dce5;
}


.stat-top{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;
}


.stat-label{

    color:#8a94a3;

    font-size:11px;

    font-weight:600;
}


.stat-number{

    margin-top:8px;

    color:#111827;

    font-size:29px;

    line-height:1;

    font-weight:700;
}


.stat-description{

    margin-top:8px;

    color:#94a3b8;

    font-size:10px;
}


.stat-icon{

    width:39px;
    height:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:16px;
}


.icon-patient{

    background:#eff6ff;

    color:#2563eb;
}


.icon-bed{

    background:#ecfdf5;

    color:#15803d;
}


.icon-medication{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   SECTION CARD
========================================================= */

.section-card{

    margin-top:22px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:13px;
}


.section-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:17px;
}


.section-title{

    margin:0;

    color:#1f2937;

    font-size:16px;

    font-weight:650;
}


.section-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   CARE SUMMARY
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap:12px;
}


.summary-item{

    padding:15px;

    display:flex;

    align-items:center;

    gap:12px;

    background:#f8fafc;

    border:1px solid #e7eaee;

    border-radius:10px;
}


.summary-icon{

    width:38px;
    height:38px;

    min-width:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:15px;
}


.summary-med{

    background:#fff1f2;

    color:#dc2626;
}


.summary-bed{

    background:#ecfdf5;

    color:#15803d;
}


.summary-ward{

    background:#eff6ff;

    color:#2563eb;
}


.summary-label{

    color:#94a3b8;

    font-size:10px;
}


.summary-number{

    margin-top:2px;

    color:#111827;

    font-size:20px;

    font-weight:700;
}


/* =========================================================
   QUICK ACTION
========================================================= */

.quick-actions{

    display:flex;

    gap:8px;

    margin-top:16px;

    flex-wrap:wrap;
}


.quick-btn{

    min-height:36px;

    padding:0 12px;

    display:inline-flex;

    align-items:center;

    gap:6px;

    border-radius:8px;

    text-decoration:none;

    font-size:10px;

    font-weight:600;
}


.quick-primary{

    background:#2563eb;

    border:1px solid #2563eb;

    color:#fff;
}


.quick-primary:hover{

    background:#1d4ed8;

    color:#fff;
}


.quick-outline{

    background:#fff;

    border:1px solid #dbe3ee;

    color:#475569;
}


.quick-outline:hover{

    background:#f8fafc;

    color:#111827;
}


/* =========================================================
   RECENT ADMISSIONS
========================================================= */

.admission-list{

    display:grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap:11px;
}


.admission-item{

    padding:15px;

    display:flex;

    align-items:center;

    gap:12px;

    border:1px solid #e8ebef;

    border-radius:10px;

    background:#fff;

    transition:.18s;
}


.admission-item:hover{

    background:#fafbfc;

    border-color:#d8dee7;
}


.admission-avatar{

    width:42px;
    height:42px;

    min-width:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border-radius:10px;

    color:#2563eb;

    font-size:17px;
}


.admission-info{

    flex:1;

    min-width:0;
}


.admission-name{

    color:#1f2937;

    font-size:12px;

    font-weight:650;

    white-space:nowrap;

    overflow:hidden;

    text-overflow:ellipsis;
}


.admission-location{

    margin-top:3px;

    color:#64748b;

    font-size:10px;
}


.admission-date{

    margin-top:3px;

    color:#94a3b8;

    font-size:9px;
}


.new-badge{

    padding:4px 7px;

    background:#ecfdf5;

    border-radius:6px;

    color:#15803d;

    font-size:9px;

    font-weight:650;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box{

    margin-bottom:17px;

    padding:14px;

    background:#f8fafc;

    border:1px solid #e8ebef;

    border-radius:10px;
}


.filter-label{

    margin-bottom:6px;

    color:#64748b;

    font-size:9px;

    font-weight:650;

    text-transform:uppercase;

    letter-spacing:.4px;
}


.form-control,
.form-select{

    min-height:41px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:11px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


.search-box{

    position:relative;
}


.search-box i{

    position:absolute;

    top:50%;

    left:13px;

    color:#94a3b8;

    font-size:12px;

    transform:translateY(-50%);
}


.search-box input{

    padding-left:36px;
}


/* =========================================================
   PATIENT CARDS
========================================================= */

.patient-grid{

    display:grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap:12px;
}


.patient-card{

    padding:16px;

    border:1px solid #e7eaee;

    border-radius:11px;

    background:#fff;

    transition:.18s;
}


.patient-card:hover{

    border-color:#cbd5e1;

    transform:translateY(-2px);
}


.patient-top{

    display:flex;

    align-items:center;

    gap:11px;
}


.patient-avatar{

    width:43px;
    height:43px;

    min-width:43px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    background:#eff6ff;

    color:#2563eb;

    font-size:17px;
}


.patient-card-name{

    color:#111827;

    font-size:12px;

    font-weight:650;

    line-height:1.3;
}


.gender-badge{

    display:inline-flex;

    margin-top:4px;

    padding:3px 6px;

    background:#f1f5f9;

    border-radius:5px;

    color:#64748b;

    font-size:8px;

    font-weight:600;
}


.patient-details{

    margin-top:14px;

    padding-top:12px;

    border-top:1px solid #eef1f4;
}


.patient-detail-row{

    display:flex;

    justify-content:space-between;

    gap:10px;

    margin-bottom:6px;

    color:#94a3b8;

    font-size:9px;
}


.patient-detail-row strong{

    color:#475569;

    font-weight:600;

    text-align:right;
}


.patient-open-btn{

    width:100%;

    min-height:34px;

    margin-top:9px;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:5px;

    border:1px solid #dbeafe;

    border-radius:7px;

    background:#fff;

    color:#2563eb;

    text-decoration:none;

    font-size:9px;

    font-weight:600;
}


.patient-open-btn:hover{

    background:#eff6ff;

    color:#1d4ed8;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state{

    display:none;

    padding:35px 20px;

    text-align:center;

    color:#94a3b8;

    grid-column:1 / -1;
}


.empty-state i{

    display:block;

    margin-bottom:7px;

    color:#cbd5e1;

    font-size:25px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1150px){

    .patient-grid{

        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }


    .summary-grid{

        grid-template-columns:
            1fr;
    }

}


@media(max-width:850px){

    .main-content{

        margin-left:260px;

        padding:18px;
    }


    .page-header{

        flex-direction:column;
    }


    .admission-list,
    .patient-grid{

        grid-template-columns:
            1fr;
    }


    .care-notification{

        align-items:flex-start;

        flex-direction:column;
    }

}

</style>

</head>


<body>


<?php
include(
    "../includes/sidebar_nurse.php"
);
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Nurse Dashboard

</h1>


<div class="page-subtitle">

Overview of patient care, beds and medication responsibilities.

</div>


</div>


<div class="shift-badge">

<i class="bi bi-person-heart"></i>

Patient Care Workspace

</div>


</div>



<!-- =====================================================
     CARE REMINDER
===================================================== -->

<?php if (
    $pendingMed > 0
): ?>


<div class="care-notification">


<div class="care-notification-left">


<div class="notification-icon">

<i class="bi bi-capsule-pill"></i>

</div>


<div>


<div class="notification-title">

Patient Care Reminder

</div>


<div class="notification-text">

<?= $pendingMed ?>

medication(s) are waiting for nurse administration.

</div>


</div>


</div>


<a
    href="nurse_medication.php"
    class="notification-btn"
>

Review Medication

<i class="bi bi-arrow-right"></i>

</a>


</div>


<?php endif; ?>



<!-- =====================================================
     TOP STATISTICS
===================================================== -->

<div class="row g-3">


<!-- REAL TIME -->

<div class="col-xl-3 col-md-6">


<div class="realtime-card">


<div class="realtime-top">


<div>


<div class="realtime-label">

Current Time

</div>


<div
    id="currentDate"
    class="realtime-date"
>
</div>


<div
    id="currentTime"
    class="realtime-time"
>
</div>


</div>


<div class="clock-icon">

<i class="bi bi-clock"></i>

</div>


</div>


<div class="realtime-divider"></div>


<div class="realtime-footer-title">

ZB-CARE Specialist Hospital

</div>


<div class="realtime-footer-text">

Live Malaysia time

</div>


</div>


</div>



<!-- PATIENT -->

<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Registered Patients

</div>


<div class="stat-number">

<?= $totalPatients ?>

</div>


<div class="stat-description">

Total patient records

</div>


</div>


<div class="stat-icon icon-patient">

<i class="bi bi-people"></i>

</div>


</div>


</div>


</div>



<!-- BEDS -->

<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Occupied Beds

</div>


<div class="stat-number">

<?= $occupiedBeds ?>

<span
    style="
        color:#94a3b8;
        font-size:13px;
        font-weight:500;
    "
>

/ <?= $totalBeds ?>

</span>


</div>


<div class="stat-description">

Beds currently occupied

</div>


</div>


<div class="stat-icon icon-bed">

<i class="bi bi-hospital"></i>

</div>


</div>


</div>


</div>



<!-- MEDICATION -->

<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Pending Medication

</div>


<div class="stat-number">

<?= $pendingMed ?>

</div>


<div class="stat-description">

Waiting for administration

</div>


</div>


<div class="stat-icon icon-medication">

<i class="bi bi-capsule-pill"></i>

</div>


</div>


</div>


</div>


</div>



<!-- =====================================================
     PATIENT CARE SUMMARY
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Patient Care Summary

</h5>


<div class="section-subtitle">

Quick overview of current nursing responsibilities.

</div>


</div>


</div>


<div class="summary-grid">


<div class="summary-item">


<div class="summary-icon summary-med">

<i class="bi bi-capsule-pill"></i>

</div>


<div>


<div class="summary-label">

Pending Medication

</div>


<div class="summary-number">

<?= $pendingMed ?>

</div>


</div>


</div>



<div class="summary-item">


<div class="summary-icon summary-bed">

<i class="bi bi-hospital"></i>

</div>


<div>


<div class="summary-label">

Occupied Beds

</div>


<div class="summary-number">

<?= $occupiedBeds ?>

</div>


</div>


</div>



<div class="summary-item">


<div class="summary-icon summary-ward">

<i class="bi bi-building"></i>

</div>


<div>


<div class="summary-label">

Active Wards

</div>


<div class="summary-number">

<?= $activeWards ?>

</div>


</div>


</div>


</div>



<div class="quick-actions">


<a
    href="nurse_medication.php"
    class="quick-btn quick-primary"
>

<i class="bi bi-capsule"></i>

Medication Tasks

</a>


<a
    href="nurse_patients.php"
    class="quick-btn quick-outline"
>

<i class="bi bi-people"></i>

View Patients

</a>


</div>


</div>



<!-- =====================================================
     RECENT ADMISSIONS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Recent Admissions

</h5>


<div class="section-subtitle">

Latest patients admitted to hospital wards.

</div>


</div>


<span
    class="badge"
    style="
        background:#eff6ff;
        color:#2563eb;
        font-size:9px;
        padding:6px 8px;
    "
>

Latest 5

</span>


</div>



<div class="admission-list">


<?php while (
    $r =
        $recentAdmissions->fetch(
            PDO::FETCH_ASSOC
        )
): ?>


<div class="admission-item">


<div class="admission-avatar">

<i class="bi bi-person"></i>

</div>


<div class="admission-info">


<div class="admission-name">

<?= htmlspecialchars(
    $r['NAME']
) ?>

</div>


<div class="admission-location">

<i class="bi bi-hospital me-1"></i>

<?= htmlspecialchars(
    $r['WARD_NAME']
) ?>

&nbsp;•&nbsp;

Bed
<?= htmlspecialchars(
    $r['BED_NUMBER']
) ?>

</div>


<div class="admission-date">

<?= htmlspecialchars(
    $r['ADMISSION_DATE']
    ?? ''
) ?>

</div>


</div>


<span class="new-badge">

New

</span>


</div>


<?php endwhile; ?>


</div>


</div>



<!-- =====================================================
     PATIENT RECORDS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Current Patient Records

</h5>


<div class="section-subtitle">

Search and access currently admitted patient records.

</div>


</div>


<a
    href="nurse_patients.php"
    class="quick-btn quick-outline"
>

View All

<i class="bi bi-arrow-right"></i>

</a>


</div>



<!-- FILTER -->

<div class="filter-box">


<div class="row g-2">


<div class="col-lg-5">


<div class="filter-label">

Search Patient

</div>


<div class="search-box">


<i class="bi bi-search"></i>


<input
    type="text"
    id="patientSearch"
    class="form-control"
    placeholder="Search patient name..."
>


</div>


</div>



<div class="col-lg-3">


<div class="filter-label">

Gender

</div>


<select
    id="genderFilter"
    class="form-select"
>


<option value="">

All Gender

</option>


<option value="Male">

Male

</option>


<option value="Female">

Female

</option>


</select>


</div>



<div class="col-lg-4">


<div class="filter-label">

Sort

</div>


<select
    id="patientSort"
    class="form-select"
>


<option value="newest">

Newest First

</option>


<option value="az">

Patient Name A-Z

</option>


<option value="za">

Patient Name Z-A

</option>


</select>


</div>


</div>


</div>



<!-- PATIENT GRID -->

<div
    id="patientGrid"
    class="patient-grid"
>


<?php foreach (
    $patientCards
    as
    $p
): ?>


<div
    class="patient-card"

    data-name="<?= htmlspecialchars(
        strtolower(
            $p['NAME']
            ?? ''
        ),
        ENT_QUOTES,
        'UTF-8'
    ) ?>"

    data-gender="<?= htmlspecialchars(
        $p['GENDER']
        ?? '',
        ENT_QUOTES,
        'UTF-8'
    ) ?>"

    data-admission-id="<?= (int)$p['ADMISSION_ID'] ?>"
>


<div class="patient-top">


<div class="patient-avatar">

<i class="bi bi-person"></i>

</div>


<div>


<div class="patient-card-name">

<?= htmlspecialchars(
    $p['NAME']
) ?>

</div>


<span class="gender-badge">

<?= htmlspecialchars(
    $p['GENDER']
) ?>

</span>


</div>


</div>



<div class="patient-details">


<div class="patient-detail-row">

<span>

Ward

</span>


<strong>

<?= htmlspecialchars(
    $p['WARD_NAME']
) ?>

</strong>

</div>


<div class="patient-detail-row">

<span>

Bed

</span>


<strong>

<?= htmlspecialchars(
    $p['BED_NUMBER']
) ?>

</strong>

</div>


</div>



<a
    href="patient_record_details.php?admission_id=<?= urlencode(
        $p['ADMISSION_ID']
    ) ?>"
    class="patient-open-btn"
>

<i class="bi bi-folder2-open"></i>

Open Patient Record

</a>


</div>


<?php endforeach; ?>



<div
    id="patientEmpty"
    class="empty-state"
>

<i class="bi bi-search"></i>

<div class="fw-semibold">

No matching patients

</div>


<div class="small mt-1">

Try another patient name or gender filter.

</div>


</div>


</div>


</div>


</div>



<script>

/* =========================================================
   REAL-TIME CLOCK
========================================================= */

function updateClock()
{

    const now =
        new Date();


    const time =
        new Intl.DateTimeFormat(
            'en-MY',
            {
                timeZone:
                    'Asia/Kuala_Lumpur',

                hour:
                    '2-digit',

                minute:
                    '2-digit',

                second:
                    '2-digit',

                hour12:
                    true
            }
        )
        .format(now);


    const date =
        new Intl.DateTimeFormat(
            'en-GB',
            {
                timeZone:
                    'Asia/Kuala_Lumpur',

                weekday:
                    'short',

                day:
                    '2-digit',

                month:
                    'short',

                year:
                    'numeric'
            }
        )
        .format(now);


    document
        .getElementById(
            'currentTime'
        )
        .textContent =
        time;


    document
        .getElementById(
            'currentDate'
        )
        .textContent =
        date;

}


updateClock();


setInterval(
    updateClock,
    1000
);



/* =========================================================
   PATIENT FILTER / SORT
========================================================= */

const searchInput =
    document.getElementById(
        'patientSearch'
    );


const genderFilter =
    document.getElementById(
        'genderFilter'
    );


const patientSort =
    document.getElementById(
        'patientSort'
    );


const patientGrid =
    document.getElementById(
        'patientGrid'
    );


const emptyState =
    document.getElementById(
        'patientEmpty'
    );


function filterPatients()
{

    const search =
        searchInput
            .value
            .trim()
            .toLowerCase();


    const gender =
        genderFilter
            .value;


    const sort =
        patientSort
            .value;


    let cards =
        Array.from(
            patientGrid
                .querySelectorAll(
                    '.patient-card'
                )
        );


    /* FILTER */

    cards.forEach(
        function(card)
        {

            const name =
                card
                    .dataset
                    .name;


            const cardGender =
                card
                    .dataset
                    .gender;


            const matchesSearch =
                (
                    search === ''
                    ||
                    name.includes(
                        search
                    )
                );


            const matchesGender =
                (
                    gender === ''
                    ||
                    cardGender === gender
                );


            card.style.display =
                (
                    matchesSearch
                    &&
                    matchesGender
                )
                ?
                ''
                :
                'none';

        }
    );


    /* SORT */

    const visibleCards =
        cards.filter(
            function(card)
            {

                return (
                    card.style.display
                    !==
                    'none'
                );

            }
        );


    visibleCards.sort(
        function(a,b)
        {

            if (
                sort === 'az'
            ) {

                return (
                    a.dataset.name
                    .localeCompare(
                        b.dataset.name
                    )
                );

            }


            if (
                sort === 'za'
            ) {

                return (
                    b.dataset.name
                    .localeCompare(
                        a.dataset.name
                    )
                );

            }


            return (
                parseInt(
                    b.dataset.admissionId
                )
                -
                parseInt(
                    a.dataset.admissionId
                )
            );

        }
    );


    visibleCards.forEach(
        function(card)
        {

            patientGrid
                .insertBefore(
                    card,
                    emptyState
                );

        }
    );


    /* EMPTY STATE */

    emptyState.style.display =
        (
            visibleCards.length === 0
        )
        ?
        'block'
        :
        'none';

}



searchInput.addEventListener(
    'input',
    filterPatients
);


genderFilter.addEventListener(
    'change',
    filterPatients
);


patientSort.addEventListener(
    'change',
    filterPatients
);

</script>


</body>

</html>