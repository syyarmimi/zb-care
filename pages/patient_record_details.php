<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

$admission_id = (int)($_GET['admission_id'] ?? 0);

if (!$admission_id) {
    die("Admission ID not found");
}


/* =========================================================
   HELPER
========================================================= */

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayDate($value, $withTime = false)
{
    if (empty($value)) {
        return '-';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return e($value);
    }

    return strtoupper(
        date(
            $withTime ? 'd-M-y h:i A' : 'd-M-y',
            $timestamp
        )
    );
}


/* =========================================================
   PATIENT + ADMISSION INFO
========================================================= */

$stmt = $conn->prepare("
    SELECT
        p.*,
        a.ADMISSION_ID,
        a.ADMISSION_DATE,
        a.DISCHARGE_DATE,
        b.BED_NUMBER,
        w.WARD_NAME

    FROM SYARMIMI.ADMISSION a

    JOIN SYARMIMI.PATIENT p
        ON a.PATIENT_ID = p.PATIENT_ID

    LEFT JOIN SYARMIMI.BED b
        ON a.BED_ID = b.BED_ID

    LEFT JOIN SYARMIMI.WARD w
        ON b.WARD_ID = w.WARD_ID

    WHERE a.ADMISSION_ID = :id
");

$stmt->execute([
    ':id' => $admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die("Patient not found");
}

$patient_id = (int)$patient['PATIENT_ID'];


/* =========================================================
   DIAGNOSIS
========================================================= */

$diagnosisStmt = $conn->prepare("
    SELECT *
    FROM SYARMIMI.DIAGNOSIS
    WHERE PATIENT_ID = :pid
    ORDER BY DATE_RECORDED DESC
");

$diagnosisStmt->execute([
    ':pid' => $patient_id
]);

$diagnoses = $diagnosisStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   MEDICATION
========================================================= */

$medicationStmt = $conn->prepare("
    SELECT
        m.MEDICATION_NAME,
        mo.DOSAGE,
        mo.FREQUENCY,

        CASE
            WHEN ma.MEDORDER_ID IS NOT NULL
            THEN 'Administered'
            ELSE 'Pending'
        END AS STATUS,

        ma.ADMIN_TIME

    FROM SYARMIMI.MEDICATION_ORDER mo

    JOIN SYARMIMI.MEDICATION m
        ON mo.MEDICATION_ID = m.MEDICATION_ID

    LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
        ON mo.MEDORDER_ID = ma.MEDORDER_ID

    WHERE mo.PATIENT_ID = :pid

    ORDER BY mo.MEDORDER_ID DESC
");

$medicationStmt->execute([
    ':pid' => $patient_id
]);

$medications = $medicationStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   STATUS
========================================================= */

$isAdmitted = empty($patient['DISCHARGE_DATE']);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Patient Record</title>

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
    font-family:'Segoe UI', Arial, sans-serif;
    color:#1f2937;
}

.sidebar{
    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;
    height:100vh;
    flex-shrink:0;
}

.main-content{
    flex:1;
    min-width:0;
    min-height:100vh;
    padding:28px 30px 45px;
}


/* =========================================================
   BACK BUTTON
========================================================= */

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:8px 13px;
    margin-bottom:18px;
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:8px;
    color:#475569;
    font-size:13px;
    font-weight:600;
    text-decoration:none;
    transition:.2s;
}

.back-btn:hover{
    background:#f8fafc;
    border-color:#cbd5e1;
    color:#111827;
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
    font-size:28px;
    font-weight:700;
}

.page-subtitle{
    margin-top:5px;
    color:#94a3b8;
    font-size:13px;
}

.header-status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 11px;
    border-radius:7px;
    font-size:12px;
    font-weight:650;
}

.header-status-admitted{
    background:#ecfdf5;
    color:#15803d;
}

.header-status-discharged{
    background:#f1f5f9;
    color:#64748b;
}


/* =========================================================
   PATIENT SUMMARY
========================================================= */

.patient-summary{
    display:flex;
    align-items:center;
    gap:18px;
    margin-bottom:20px;
    padding:20px 22px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
}

.patient-avatar{
    width:58px;
    height:58px;
    min-width:58px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#eff6ff;
    border-radius:50%;
    color:#2563eb;
    font-size:25px;
}

.patient-summary-info{
    flex:1;
}

.patient-name{
    margin:0;
    color:#111827;
    font-size:19px;
    font-weight:700;
}

.patient-meta{
    display:flex;
    flex-wrap:wrap;
    gap:8px 18px;
    margin-top:6px;
    color:#64748b;
    font-size:12px;
}

.patient-meta-item{
    display:flex;
    align-items:center;
    gap:5px;
}

.admission-number{
    padding:6px 10px;
    background:#f1f5f9;
    border-radius:6px;
    color:#475569;
    font-size:11px;
    font-weight:650;
}


/* =========================================================
   NAVIGATION
========================================================= */

.record-nav{
    margin-bottom:20px;
    padding:8px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:12px;
}

.nav-btn{
    padding:9px 13px;
    background:transparent;
    border:0;
    border-radius:8px;
    color:#64748b;
    font-size:12px;
    font-weight:600;
    transition:.2s;
}

.nav-btn:hover{
    background:#f8fafc;
    color:#2563eb;
}

.nav-btn.active{
    background:#eff6ff;
    color:#2563eb;
}

.nav-btn i{
    margin-right:5px;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    overflow:hidden;
}

.card-header-custom{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    padding:19px 22px;
    background:#fff;
    border-bottom:1px solid #edf0f3;
}

.card-title-custom{
    margin:0;
    color:#1f2937;
    font-size:16px;
    font-weight:650;
}

.card-description{
    margin-top:3px;
    color:#94a3b8;
    font-size:12px;
}

.card-body-custom{
    padding:22px;
}


/* =========================================================
   INFORMATION GRID
========================================================= */

.info-grid{
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:0;
    border:1px solid #edf0f3;
    border-radius:10px;
    overflow:hidden;
}

.info-item{
    min-height:90px;
    padding:17px 18px;
    border-right:1px solid #edf0f3;
    border-bottom:1px solid #edf0f3;
}

.info-item:nth-child(3n){
    border-right:0;
}

.info-label{
    display:flex;
    align-items:center;
    gap:6px;
    margin-bottom:7px;
    color:#94a3b8;
    font-size:11px;
    font-weight:600;
    text-transform:uppercase;
}

.info-label i{
    color:#64748b;
    font-size:12px;
}

.info-value{
    color:#1f2937;
    font-size:14px;
    font-weight:600;
    word-break:break-word;
}

.info-value-secondary{
    font-weight:500;
    color:#475569;
}


/* =========================================================
   ADMISSION OVERVIEW
========================================================= */

.admission-grid{
    display:grid;
    grid-template-columns:repeat(4, 1fr);
    gap:14px;
}

.admission-item{
    min-height:115px;
    padding:18px;
    background:#f8fafc;
    border:1px solid #edf0f3;
    border-radius:10px;
}

.admission-icon{
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:13px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:8px;
    color:#2563eb;
}

.admission-label{
    color:#94a3b8;
    font-size:11px;
    font-weight:600;
    text-transform:uppercase;
}

.admission-value{
    margin-top:4px;
    color:#1f2937;
    font-size:14px;
    font-weight:650;
}


/* =========================================================
   BADGES
========================================================= */

.soft-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:6px 9px;
    border-radius:6px;
    font-size:11px;
    font-weight:650;
}

.badge-admitted{
    background:#ecfdf5;
    color:#15803d;
}

.badge-discharged{
    background:#f1f5f9;
    color:#64748b;
}

.badge-administered{
    background:#ecfdf5;
    color:#15803d;
}

.badge-pending{
    background:#fff7ed;
    color:#c2410c;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{
    overflow-x:auto;
}

.table{
    margin-bottom:0;
    vertical-align:middle;
}

.table thead th{
    padding:12px 14px;
    background:#f8fafc;
    border-bottom:1px solid #e5e7eb;
    color:#64748b;
    font-size:11px;
    font-weight:650;
    text-transform:uppercase;
    white-space:nowrap;
}

.table tbody td{
    padding:14px;
    border-color:#eef1f4;
    color:#374151;
    font-size:13px;
}

.table tbody tr:hover td{
    background:#fafbfc;
}

.medication-name{
    display:flex;
    align-items:center;
    gap:8px;
    color:#1f2937;
    font-weight:650;
}

.med-icon{
    width:29px;
    height:29px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff7ed;
    border-radius:7px;
    color:#ea580c;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{
    padding:50px 20px;
    text-align:center;
    color:#94a3b8;
}

.empty-icon{
    width:50px;
    height:50px;
    margin:0 auto 12px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#f8fafc;
    border-radius:50%;
    color:#cbd5e1;
    font-size:21px;
}

.empty-title{
    color:#64748b;
    font-size:13px;
    font-weight:650;
}

.empty-text{
    margin-top:3px;
    font-size:12px;
}


/* =========================================================
   TIMELINE
========================================================= */

.timeline{
    position:relative;
    padding-left:37px;
}

.timeline::before{
    content:'';
    position:absolute;
    top:12px;
    bottom:12px;
    left:14px;
    width:2px;
    background:#e2e8f0;
}

.timeline-item{
    position:relative;
    padding-bottom:28px;
}

.timeline-item:last-child{
    padding-bottom:0;
}

.timeline-dot{
    position:absolute;
    left:-37px;
    top:0;
    width:30px;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:center;
    background:#fff;
    border:2px solid #bfdbfe;
    border-radius:50%;
    color:#2563eb;
    font-size:12px;
    z-index:1;
}

.timeline-dot.success{
    border-color:#bbf7d0;
    color:#16a34a;
}

.timeline-dot.discharge{
    border-color:#cbd5e1;
    color:#64748b;
}

.timeline-title{
    color:#1f2937;
    font-size:13px;
    font-weight:650;
}

.timeline-date{
    margin-top:3px;
    color:#94a3b8;
    font-size:11px;
}

.timeline-description{
    margin-top:5px;
    color:#64748b;
    font-size:12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px){

    .info-grid{
        grid-template-columns:repeat(2,1fr);
    }

    .info-item:nth-child(3n){
        border-right:1px solid #edf0f3;
    }

    .info-item:nth-child(2n){
        border-right:0;
    }

    .admission-grid{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:768px){

    .main-content{
        padding:20px;
    }

    .page-header{
        flex-direction:column;
    }

    .patient-summary{
        align-items:flex-start;
    }

    .info-grid{
        grid-template-columns:1fr;
    }

    .info-item{
        border-right:0 !important;
    }

    .admission-grid{
        grid-template-columns:1fr;
    }
}

</style>

</head>


<body>

<div class="d-flex">

<?php include("../includes/sidebar_nurse.php"); ?>


<main class="main-content">


<!-- =====================================================
     BACK
===================================================== -->

<a href="patient_list.php" class="back-btn">

<i class="bi bi-arrow-left"></i>

Back to Patient List

</a>


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<div>

<h1 class="page-title">
Patient Record
</h1>

<div class="page-subtitle">
View patient information, admission details, diagnosis and medication history.
</div>

</div>


<?php if ($isAdmitted): ?>

<span class="header-status header-status-admitted">

<i class="bi bi-circle-fill" style="font-size:6px;"></i>

Currently Admitted

</span>

<?php else: ?>

<span class="header-status header-status-discharged">

<i class="bi bi-check-circle"></i>

Discharged

</span>

<?php endif; ?>

</div>


<!-- =====================================================
     PATIENT SUMMARY
===================================================== -->

<div class="patient-summary">

<div class="patient-avatar">

<?php if (
    strtolower($patient['GENDER'] ?? '') === 'female'
): ?>

<i class="bi bi-person-fill"></i>

<?php else: ?>

<i class="bi bi-person-fill"></i>

<?php endif; ?>

</div>


<div class="patient-summary-info">

<h2 class="patient-name">

<?= e($patient['NAME']) ?>

</h2>


<div class="patient-meta">

<div class="patient-meta-item">

<i class="bi bi-credit-card-2-front"></i>

<?= e($patient['IC_NUMBER']) ?>

</div>


<div class="patient-meta-item">

<i class="bi bi-person"></i>

<?= e($patient['AGE']) ?> years

</div>


<div class="patient-meta-item">

<i class="bi bi-gender-ambiguous"></i>

<?= e($patient['GENDER']) ?>

</div>


<?php if (!empty($patient['WARD_NAME'])): ?>

<div class="patient-meta-item">

<i class="bi bi-hospital"></i>

<?= e($patient['WARD_NAME']) ?>

</div>

<?php endif; ?>

</div>

</div>


<div class="admission-number">

Admission #<?= e($patient['ADMISSION_ID']) ?>

</div>

</div>


<!-- =====================================================
     NAVIGATION
===================================================== -->

<div class="record-nav">

<button
    type="button"
    class="nav-btn active"
    data-section="profile"
    onclick="showSection('profile', this)"
>

<i class="bi bi-person"></i>

Patient Profile

</button>


<button
    type="button"
    class="nav-btn"
    data-section="admission"
    onclick="showSection('admission', this)"
>

<i class="bi bi-hospital"></i>

Admission Details

</button>


<button
    type="button"
    class="nav-btn"
    data-section="diagnosis"
    onclick="showSection('diagnosis', this)"
>

<i class="bi bi-clipboard2-pulse"></i>

Diagnosis History

</button>


<button
    type="button"
    class="nav-btn"
    data-section="medication"
    onclick="showSection('medication', this)"
>

<i class="bi bi-capsule"></i>

Medication History

</button>


<button
    type="button"
    class="nav-btn"
    data-section="timeline"
    onclick="showSection('timeline', this)"
>

<i class="bi bi-clock-history"></i>

Journey Timeline

</button>

</div>


<!-- =====================================================
     PROFILE
===================================================== -->

<section
    class="section-content"
    id="profile"
>

<div class="content-card">

<div class="card-header-custom">

<div>

<h4 class="card-title-custom">
Patient Information
</h4>

<div class="card-description">
Personal and contact information registered in the system.
</div>

</div>

<i class="bi bi-person-vcard text-muted"></i>

</div>


<div class="card-body-custom">

<div class="info-grid">


<div class="info-item">

<div class="info-label">

<i class="bi bi-credit-card-2-front"></i>

IC Number

</div>

<div class="info-value">

<?= e($patient['IC_NUMBER']) ?: '-' ?>

</div>

</div>


<div class="info-item">

<div class="info-label">

<i class="bi bi-calendar3"></i>

Age

</div>

<div class="info-value">

<?= e($patient['AGE']) ?>

<?php if (!empty($patient['AGE'])): ?>
years
<?php endif; ?>

</div>

</div>


<div class="info-item">

<div class="info-label">

<i class="bi bi-gender-ambiguous"></i>

Gender

</div>

<div class="info-value">

<?= e($patient['GENDER']) ?: '-' ?>

</div>

</div>


<div class="info-item">

<div class="info-label">

<i class="bi bi-telephone"></i>

Phone Number

</div>

<div class="info-value">

<?= e($patient['PHONE']) ?: '-' ?>

</div>

</div>


<div class="info-item" style="grid-column:span 2;">

<div class="info-label">

<i class="bi bi-geo-alt"></i>

Address

</div>

<div class="info-value info-value-secondary">

<?= e($patient['ADDRESS']) ?: '-' ?>

</div>

</div>


</div>

</div>

</div>

</section>


<!-- =====================================================
     ADMISSION
===================================================== -->

<section
    class="section-content d-none"
    id="admission"
>

<div class="content-card">

<div class="card-header-custom">

<div>

<h4 class="card-title-custom">
Admission Information
</h4>

<div class="card-description">
Current hospital admission and ward placement details.
</div>

</div>

<i class="bi bi-hospital text-muted"></i>

</div>


<div class="card-body-custom">

<div class="admission-grid">


<div class="admission-item">

<div class="admission-icon">

<i class="bi bi-building"></i>

</div>

<div class="admission-label">
Ward
</div>

<div class="admission-value">

<?= e($patient['WARD_NAME']) ?: '-' ?>

</div>

</div>


<div class="admission-item">

<div class="admission-icon">

<i class="bi bi-door-open"></i>

</div>

<div class="admission-label">
Bed
</div>

<div class="admission-value">

<?= e($patient['BED_NUMBER']) ?: '-' ?>

</div>

</div>


<div class="admission-item">

<div class="admission-icon">

<i class="bi bi-calendar-plus"></i>

</div>

<div class="admission-label">
Admission Date
</div>

<div class="admission-value">

<?= displayDate($patient['ADMISSION_DATE']) ?>

</div>

</div>


<div class="admission-item">

<div class="admission-icon">

<i class="bi bi-activity"></i>

</div>

<div class="admission-label">
Admission Status
</div>

<div class="admission-value">

<?php if ($isAdmitted): ?>

<span class="soft-badge badge-admitted">

<i class="bi bi-circle-fill" style="font-size:5px;"></i>

Admitted

</span>

<?php else: ?>

<span class="soft-badge badge-discharged">

<i class="bi bi-check-circle"></i>

Discharged

</span>

<?php endif; ?>

</div>

</div>


<?php if (!$isAdmitted): ?>

<div class="admission-item">

<div class="admission-icon">

<i class="bi bi-calendar-check"></i>

</div>

<div class="admission-label">
Discharge Date
</div>

<div class="admission-value">

<?= displayDate($patient['DISCHARGE_DATE']) ?>

</div>

</div>

<?php endif; ?>


</div>

</div>

</div>

</section>


<!-- =====================================================
     DIAGNOSIS
===================================================== -->

<section
    class="section-content d-none"
    id="diagnosis"
>

<div class="content-card">

<div class="card-header-custom">

<div>

<h4 class="card-title-custom">
Diagnosis History
</h4>

<div class="card-description">
Diagnosis and allergy information recorded for this patient.
</div>

</div>

<span class="admission-number">

<?= count($diagnoses) ?> record(s)

</span>

</div>


<?php if (count($diagnoses) > 0): ?>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th style="width:170px;">
Date Recorded
</th>

<th>
Diagnosis
</th>

<th style="width:250px;">
Allergies
</th>

</tr>

</thead>


<tbody>

<?php foreach ($diagnoses as $d): ?>

<tr>

<td>

<div class="d-flex align-items-center gap-2">

<i class="bi bi-calendar3 text-muted"></i>

<?= displayDate($d['DATE_RECORDED']) ?>

</div>

</td>


<td>

<div class="fw-semibold text-dark">

<?= e($d['DIAGNOSIS_DETAILS']) ?: '-' ?>

</div>

</td>


<td>

<?php if (!empty($d['ALLERGIES'])): ?>

<span class="soft-badge badge-pending">

<i class="bi bi-exclamation-triangle"></i>

<?= e($d['ALLERGIES']) ?>

</span>

<?php else: ?>

<span class="text-muted">
No recorded allergies
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

<div class="empty-icon">

<i class="bi bi-clipboard2"></i>

</div>

<div class="empty-title">
No diagnosis records
</div>

<div class="empty-text">
No diagnosis has been recorded for this patient.
</div>

</div>

<?php endif; ?>

</div>

</section>


<!-- =====================================================
     MEDICATION
===================================================== -->

<section
    class="section-content d-none"
    id="medication"
>

<div class="content-card">

<div class="card-header-custom">

<div>

<h4 class="card-title-custom">
Medication History
</h4>

<div class="card-description">
Medication prescriptions and administration records.
</div>

</div>

<span class="admission-number">

<?= count($medications) ?> medication(s)

</span>

</div>


<?php if (count($medications) > 0): ?>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>
Medication
</th>

<th>
Dosage
</th>

<th>
Frequency
</th>

<th>
Status
</th>

<th>
Admin Time
</th>

</tr>

</thead>


<tbody>

<?php foreach ($medications as $m): ?>

<tr>

<td>

<div class="medication-name">

<div class="med-icon">

<i class="bi bi-capsule"></i>

</div>

<?= e($m['MEDICATION_NAME']) ?>

</div>

</td>


<td>

<?= e($m['DOSAGE']) ?: '-' ?>

</td>


<td>

<?= e($m['FREQUENCY']) ?: '-' ?>

</td>


<td>

<?php if (
    $m['STATUS'] === 'Administered'
): ?>

<span class="soft-badge badge-administered">

<i class="bi bi-check-circle"></i>

Administered

</span>

<?php else: ?>

<span class="soft-badge badge-pending">

<i class="bi bi-clock"></i>

Pending

</span>

<?php endif; ?>

</td>


<td>

<?php if (!empty($m['ADMIN_TIME'])): ?>

<div class="d-flex align-items-center gap-2">

<i class="bi bi-clock text-muted"></i>

<?= displayDate($m['ADMIN_TIME'], true) ?>

</div>

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

<div class="empty-icon">

<i class="bi bi-capsule"></i>

</div>

<div class="empty-title">
No medication records
</div>

<div class="empty-text">
No medication has been prescribed for this patient.
</div>

</div>

<?php endif; ?>

</div>

</section>


<!-- =====================================================
     TIMELINE
===================================================== -->

<section
    class="section-content d-none"
    id="timeline"
>

<div class="content-card">

<div class="card-header-custom">

<div>

<h4 class="card-title-custom">
Patient Journey Timeline
</h4>

<div class="card-description">
Overview of the patient's hospital journey.
</div>

</div>

<i class="bi bi-clock-history text-muted"></i>

</div>


<div class="card-body-custom">

<div class="timeline">


<!-- REGISTERED -->

<div class="timeline-item">

<div class="timeline-dot success">

<i class="bi bi-check-lg"></i>

</div>

<div class="timeline-title">
Patient Registered
</div>

<div class="timeline-description">
Patient information registered in ZB-CARE.
</div>

</div>


<!-- ADMITTED -->

<div class="timeline-item">

<div class="timeline-dot">

<i class="bi bi-hospital"></i>

</div>

<div class="timeline-title">
Admitted to Hospital
</div>

<div class="timeline-date">

<?= displayDate(
    $patient['ADMISSION_DATE'],
    true
) ?>

</div>

<div class="timeline-description">

<?php if (!empty($patient['WARD_NAME'])): ?>

Assigned to

<strong>
<?= e($patient['WARD_NAME']) ?>
</strong>

<?php endif; ?>

<?php if (!empty($patient['BED_NUMBER'])): ?>

, Bed

<strong>
<?= e($patient['BED_NUMBER']) ?>
</strong>

<?php endif; ?>.

</div>

</div>


<!-- DIAGNOSIS -->

<?php if (count($diagnoses) > 0): ?>

<div class="timeline-item">

<div class="timeline-dot">

<i class="bi bi-clipboard2-pulse"></i>

</div>

<div class="timeline-title">
Diagnosis Recorded
</div>

<div class="timeline-date">

<?= displayDate(
    $diagnoses[0]['DATE_RECORDED'],
    true
) ?>

</div>

<div class="timeline-description">

<?= e(
    $diagnoses[0]['DIAGNOSIS_DETAILS']
) ?>

</div>

</div>

<?php endif; ?>


<!-- MEDICATION -->

<?php if (count($medications) > 0): ?>

<div class="timeline-item">

<div class="timeline-dot">

<i class="bi bi-capsule"></i>

</div>

<div class="timeline-title">
Medication Prescribed
</div>

<div class="timeline-description">

<?= count($medications) ?>

medication record(s) associated with this patient.

</div>

</div>

<?php endif; ?>


<!-- DISCHARGE -->

<?php if (!$isAdmitted): ?>

<div class="timeline-item">

<div class="timeline-dot discharge">

<i class="bi bi-house-check"></i>

</div>

<div class="timeline-title">
Patient Discharged
</div>

<div class="timeline-date">

<?= displayDate(
    $patient['DISCHARGE_DATE'],
    true
) ?>

</div>

<div class="timeline-description">
Hospital admission completed.
</div>

</div>

<?php endif; ?>


</div>

</div>

</div>

</section>


</main>

</div>


<script>

/* =========================================================
   TAB / SECTION NAVIGATION
========================================================= */

function showSection(sectionId, button)
{
    document
        .querySelectorAll('.section-content')
        .forEach(function(section)
        {
            section.classList.add('d-none');
        });


    const target =
        document.getElementById(sectionId);


    if (target) {

        target.classList.remove('d-none');

    }


    document
        .querySelectorAll('.nav-btn')
        .forEach(function(btn)
        {
            btn.classList.remove('active');
        });


    if (button) {

        button.classList.add('active');

    }
}

</script>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


</body>

</html>