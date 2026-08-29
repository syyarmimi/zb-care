<?php

session_start();

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['doctor', 'admin'], true)
) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   ROLE
========================================================= */

$role = $_SESSION['role'];

$doctor_id =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );


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
   CONVERT APPOINTMENT DATE
========================================================= */

function convertAppointmentDate($date)
{
    $date =
        trim(
            (string)$date
        );

    if ($date === '') {
        return '';
    }

    if (
        preg_match(
            '/^\d{2}-[A-Za-z]{3}-\d{2}$/',
            $date
        )
    ) {

        $dateObj =
            DateTime::createFromFormat(
                'd-M-y',
                strtoupper($date)
            );

        if ($dateObj) {

            return $dateObj
                ->format('Y-m-d');
        }
    }

    if (
        preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {

        $dateObj =
            DateTime::createFromFormat(
                'Y-m-d',
                $date
            );

        if ($dateObj) {

            return $dateObj
                ->format('Y-m-d');
        }
    }

    $timestamp =
        strtotime($date);

    if ($timestamp !== false) {

        return date(
            'Y-m-d',
            $timestamp
        );
    }

    return '';
}


/* =========================================================
   1. CURRENT / ADMITTED PATIENTS
========================================================= */

$sql = "

    SELECT

        A.ADMISSION_ID,

        P.PATIENT_ID,

        P.NAME,

        P.IC_NUMBER,

        W.WARD_NAME,

        B.BED_NUMBER,

        HS.USERNAME
        AS DOCTOR_NAME,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        )
        AS ADMISSION_DATE,

        TO_CHAR(
            A.ADMISSION_DATE,
            'YYYY-MM-DD'
        )
        AS ADMISSION_SORT,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'DD-MON-YYYY'
        )
        AS EXPECTED_DISCHARGE_DATE,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'YYYY-MM-DD'
        )
        AS EXPECTED_SORT,

        CASE

            WHEN
                A.EXPECTED_DISCHARGE_DATE
                IS NOT NULL

            THEN
                GREATEST(
                    1,
                    TRUNC(
                        A.EXPECTED_DISCHARGE_DATE
                    )
                    -
                    TRUNC(
                        A.ADMISSION_DATE
                    )
                    +
                    1
                )

            ELSE
                GREATEST(
                    1,
                    TRUNC(SYSDATE)
                    -
                    TRUNC(
                        A.ADMISSION_DATE
                    )
                    +
                    1
                )

        END
        AS STAY_DAYS

    FROM
        SYARMIMI.ADMISSION A

    JOIN
        SYARMIMI.PATIENT P

        ON
            A.PATIENT_ID =
            P.PATIENT_ID

    JOIN
        SYARMIMI.BED B

        ON
            A.BED_ID =
            B.BED_ID

    JOIN
        SYARMIMI.WARD W

        ON
            B.WARD_ID =
            W.WARD_ID

    LEFT JOIN
        SYARMIMI.HOSPITAL_STAFF HS

        ON
            A.ACCOUNT_ID =
            HS.ACCOUNT_ID

    WHERE
        A.DISCHARGE_DATE
        IS NULL

";


if ($role === 'doctor') {

    $sql .= "

        AND
            A.ACCOUNT_ID =
            :doctor_id

    ";
}


$sql .= "

    ORDER BY

        A.ADMISSION_DATE DESC,

        A.ADMISSION_ID DESC

";


$currentPatients =
    $conn->prepare($sql);


if ($role === 'doctor') {

    $currentPatients->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $currentPatients->execute();
}


$currentList =
    $currentPatients
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   2. WALK-IN PATIENTS
========================================================= */

$sql = "

    SELECT

        W.CONSULTATION_ID,

        P.PATIENT_ID,

        P.NAME,

        P.IC_NUMBER,

        W.DEPARTMENT,

        W.STATUS,

        HS.USERNAME
        AS DOCTOR_NAME,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'DD-MON-YYYY'
        )
        AS CONSULTATION_DATE,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'YYYY-MM-DD'
        )
        AS CONSULTATION_SORT

    FROM
        SYARMIMI.WALKIN_CONSULTATION W

    JOIN
        SYARMIMI.PATIENT P

        ON
            W.PATIENT_ID =
            P.PATIENT_ID

    LEFT JOIN
        SYARMIMI.HOSPITAL_STAFF HS

        ON
            W.ACCOUNT_ID =
            HS.ACCOUNT_ID

    WHERE
        1 = 1

";


if ($role === 'doctor') {

    $sql .= "

        AND
            W.ACCOUNT_ID =
            :doctor_id

    ";
}


$sql .= "

    ORDER BY

        W.CONSULTATION_DATE DESC,

        W.CONSULTATION_ID DESC

";


$walkinStmt =
    $conn->prepare($sql);


if ($role === 'doctor') {

    $walkinStmt->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $walkinStmt->execute();
}


$walkinPatients =
    $walkinStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   3. APPOINTMENT PATIENTS
========================================================= */

$sql = "

    SELECT

        A.APPOINTMENT_ID,

        A.PATIENT_ID,

        NVL(
            P.NAME,
            A.PATIENT_NAME
        )
        AS NAME,

        A.APPOINTMENT_DATE,

        A.APPOINTMENT_TIME,

        A.DEPARTMENT,

        A.STATUS,

        A.PHONE,

        A.IC_NUMBER,

        A.GENDER,

        A.DOCTOR_NAME

    FROM
        SYARMIMI.APPOINTMENT A

    LEFT JOIN
        SYARMIMI.PATIENT P

        ON
            A.PATIENT_ID =
            P.PATIENT_ID

    WHERE
        1 = 1

";


if ($role === 'doctor') {

    $sql .= "

        AND
            A.ACCOUNT_ID =
            :doctor_id

    ";
}


$sql .= "

    ORDER BY
        A.APPOINTMENT_ID DESC

";


$appointmentStmt =
    $conn->prepare($sql);


if ($role === 'doctor') {

    $appointmentStmt->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $appointmentStmt->execute();
}


$appointmentPatients =
    $appointmentStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   4. DIAGNOSIS / CLINICAL REVIEW HISTORY
========================================================= */

$sql = "

    SELECT

        D.DIAGNOSIS_ID,

        P.PATIENT_ID,

        P.NAME,

        D.ADMISSION_ID,

        D.APPOINTMENT_ID,

        D.CONSULTATION_ID,

        D.DIAGNOSIS_DETAILS,

        D.ALLERGIES,

        HS.USERNAME
        AS DOCTOR_NAME,

        CASE

            WHEN
                D.ADMISSION_ID
                IS NOT NULL

            THEN
                'Admission Review'

            WHEN
                D.APPOINTMENT_ID
                IS NOT NULL

            THEN
                'Appointment'

            WHEN
                D.CONSULTATION_ID
                IS NOT NULL

            THEN
                'Walk-In'

            ELSE
                'Diagnosis'

        END
        AS DIAGNOSIS_TYPE,

        TO_CHAR(
            D.DATE_RECORDED,
            'DD-MON-YYYY'
        )
        AS DATE_RECORDED,

        TO_CHAR(
            D.DATE_RECORDED,
            'HH24:MI'
        )
        AS TIME_RECORDED,

        TO_CHAR(
            D.DATE_RECORDED,
            'YYYY-MM-DD'
        )
        AS DATE_SORT

    FROM
        SYARMIMI.DIAGNOSIS D

    JOIN
        SYARMIMI.PATIENT P

        ON
            D.PATIENT_ID =
            P.PATIENT_ID

    LEFT JOIN
        SYARMIMI.HOSPITAL_STAFF HS

        ON
            D.ACCOUNT_ID =
            HS.ACCOUNT_ID

    WHERE
        1 = 1

";


if ($role === 'doctor') {

    $sql .= "

        AND
            D.ACCOUNT_ID =
            :doctor_id

    ";
}


$sql .= "

    ORDER BY

        D.DATE_RECORDED DESC,

        D.DIAGNOSIS_ID DESC

";


$diagnosedPatients =
    $conn->prepare($sql);


if ($role === 'doctor') {

    $diagnosedPatients->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $diagnosedPatients->execute();
}


$diagnosedList =
    $diagnosedPatients
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   5. DISCHARGED PATIENTS
========================================================= */

$sql = "

    SELECT

        A.ADMISSION_ID,

        P.PATIENT_ID,

        P.NAME,

        P.IC_NUMBER,

        W.WARD_NAME,

        B.BED_NUMBER,

        HS.USERNAME
        AS DOCTOR_NAME,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        )
        AS ADMISSION_DATE,

        TO_CHAR(
            A.ADMISSION_DATE,
            'YYYY-MM-DD'
        )
        AS ADMISSION_SORT,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'DD-MON-YYYY'
        )
        AS EXPECTED_DISCHARGE_DATE,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'YYYY-MM-DD'
        )
        AS EXPECTED_SORT,

        TO_CHAR(
            A.DISCHARGE_DATE,
            'DD-MON-YYYY'
        )
        AS DISCHARGE_DATE,

        TO_CHAR(
            A.DISCHARGE_DATE,
            'YYYY-MM-DD'
        )
        AS DISCHARGE_SORT,

        CASE

            WHEN
                A.EXPECTED_DISCHARGE_DATE
                IS NOT NULL

            AND
                TRUNC(
                    A.DISCHARGE_DATE
                )
                <
                TRUNC(
                    A.EXPECTED_DISCHARGE_DATE
                )

            THEN
                'Early'

            ELSE
                'Normal'

        END
        AS DISCHARGE_TYPE

    FROM
        SYARMIMI.ADMISSION A

    JOIN
        SYARMIMI.PATIENT P

        ON
            A.PATIENT_ID =
            P.PATIENT_ID

    LEFT JOIN
        SYARMIMI.BED B

        ON
            A.BED_ID =
            B.BED_ID

    LEFT JOIN
        SYARMIMI.WARD W

        ON
            B.WARD_ID =
            W.WARD_ID

    LEFT JOIN
        SYARMIMI.HOSPITAL_STAFF HS

        ON
            A.ACCOUNT_ID =
            HS.ACCOUNT_ID

    WHERE
        A.DISCHARGE_DATE
        IS NOT NULL

";


if ($role === 'doctor') {

    $sql .= "

        AND
            A.ACCOUNT_ID =
            :doctor_id

    ";
}


$sql .= "

    ORDER BY

        A.DISCHARGE_DATE DESC,

        A.ADMISSION_ID DESC

";


$dischargedPatients =
    $conn->prepare($sql);


if ($role === 'doctor') {

    $dischargedPatients->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $dischargedPatients->execute();
}


$dischargedList =
    $dischargedPatients
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   6. STATISTICS
========================================================= */

$totalCurrent =
    count($currentList);

$totalDiagnosed =
    count($diagnosedList);

$totalDischarged =
    count($dischargedList);

$totalAppointments =
    count($appointmentPatients);

$totalWalkin =
    count($walkinPatients);


/* =========================================================
   7. MEDICATION COUNT
========================================================= */

if ($role === 'doctor') {

    $medStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER

            WHERE
                ACCOUNT_ID =
                :doctor_id

        ");

    $medStmt->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $medStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER

        ");

    $medStmt->execute();
}


$totalMedication =
    (int)$medStmt
        ->fetchColumn();


/* =========================================================
   8. DISCHARGE MEDICATION COUNT
========================================================= */

if ($role === 'doctor') {

    $dischargeMedStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER

            WHERE
                UPPER(
                    TRIM(
                        NVL(
                            ORDER_TYPE,
                            'UNKNOWN'
                        )
                    )
                )
                =
                'DISCHARGE'

            AND
                ACCOUNT_ID =
                :doctor_id

        ");

    $dischargeMedStmt->execute([
        ':doctor_id' =>
            $doctor_id
    ]);

}
else {

    $dischargeMedStmt =
        $conn->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER

            WHERE
                UPPER(
                    TRIM(
                        NVL(
                            ORDER_TYPE,
                            'UNKNOWN'
                        )
                    )
                )
                =
                'DISCHARGE'

        ");
}


$totalDischargeMedication =
    (int)$dischargeMedStmt
        ->fetchColumn();

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
Patient Management
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
>


<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:#f5f7fa;
    color:#1f2937;
    font-family:'Segoe UI',Arial,sans-serif;
}

.content{
    flex:1;
    min-width:0;
    min-height:100vh;
    padding:28px;
}

.page-header{
    margin-bottom:24px;
}

.page-title{
    margin:0;
    color:#111827;
    font-size:28px;
    font-weight:750;
}

.page-subtitle{
    margin-top:5px;
    color:#8a94a3;
    font-size:13px;
}


/* =========================================================
   STATS
========================================================= */

.stats-card{
    height:100%;
    padding:18px;
    background:#fff;
    border:1px solid #e7eaee;
    border-radius:12px;
    transition:.2s;
}

.stats-card:hover{
    transform:translateY(-2px);
    border-color:#d6dce3;
}

.stat-content{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
}

.stat-label{
    color:#8a94a3;
    font-size:12px;
    font-weight:600;
}

.stat-number{
    margin-top:5px;
    color:#111827;
    font-size:30px;
    line-height:1;
    font-weight:700;
}

.stat-subtitle{
    margin-top:7px;
    color:#94a3b8;
    font-size:10px;
}

.stat-icon{
    width:42px;
    height:42px;
    min-width:42px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:10px;
    font-size:18px;
}

.icon-admitted{
    background:#eff6ff;
    color:#2563eb;
}

.icon-diagnosed{
    background:#ecfdf5;
    color:#15803d;
}

.icon-medication{
    background:#fff7ed;
    color:#ea580c;
}

.icon-discharged{
    background:#f3f4f6;
    color:#475569;
}

.icon-discharge-med{
    background:#f5f3ff;
    color:#7c3aed;
}


/* =========================================================
   FILTER
========================================================= */

.filter-card{
    margin-bottom:18px;
    padding:18px;
    background:#fff;
    border:1px solid #e7eaee;
    border-radius:12px;
}

.filter-title{
    margin-bottom:13px;
    color:#374151;
    font-size:13px;
    font-weight:650;
}

.filter-label{
    margin-bottom:6px;
    color:#64748b;
    font-size:11px;
    font-weight:600;
}

.form-control,
.form-select{
    min-height:43px;
    border:1px solid #dfe3e8;
    border-radius:8px;
    color:#374151;
    font-size:13px;
}

.form-control:focus,
.form-select:focus{
    border-color:#93c5fd;
    box-shadow:0 0 0 3px rgba(59,130,246,.07);
}

.search-wrapper{
    position:relative;
}

.search-wrapper i{
    position:absolute;
    top:50%;
    left:14px;
    z-index:2;
    color:#94a3b8;
    font-size:13px;
    transform:translateY(-50%);
}

.search-wrapper input{
    padding-left:38px;
}


/* =========================================================
   DATE
========================================================= */

.date-filter-box{
    margin-top:16px;
    padding:15px;
    background:#f8fafc;
    border:1px solid #e8ebef;
    border-radius:10px;
}

.date-filter-header{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:12px;
    color:#475569;
    font-size:12px;
    font-weight:650;
}

.date-filter-header i{
    color:#2563eb;
}

.btn-date{
    min-height:43px;
    border-radius:8px;
    font-size:12px;
    font-weight:600;
}

.btn-download{
    background:#16a34a;
    border-color:#16a34a;
    color:#fff;
}

.btn-download:hover{
    background:#15803d;
    border-color:#15803d;
    color:#fff;
}

.date-active-message{
    display:none;
    margin-top:12px;
    padding:10px 12px;
    background:#eff6ff;
    border-radius:7px;
    color:#2563eb;
    font-size:11px;
}

.date-active-message.show{
    display:block;
}


/* =========================================================
   TABLE CARD
========================================================= */

.table-card{
    padding:20px;
    background:#fff;
    border:1px solid #e7eaee;
    border-radius:12px;
}

.table-card-header{
    margin-bottom:15px;
}

.table-card-title{
    margin:0;
    color:#1f2937;
    font-size:16px;
    font-weight:650;
}

.table-card-subtitle{
    margin-top:3px;
    color:#94a3b8;
    font-size:11px;
}


/* =========================================================
   TABS
========================================================= */

.patient-tabs{
    display:flex;
    gap:5px;
    margin-bottom:18px !important;
    padding:5px;
    background:#f8fafc;
    border:1px solid #e7eaee !important;
    border-radius:10px;
}

.patient-tabs .nav-item{
    flex:1;
}

.patient-tabs .nav-link{
    width:100%;
    padding:9px 10px;
    border:0 !important;
    border-radius:7px !important;
    color:#64748b;
    font-size:11px;
    font-weight:600;
    white-space:nowrap;
}

.patient-tabs .nav-link.active{
    background:#fff;
    color:#2563eb;
    box-shadow:0 1px 4px rgba(15,23,42,.08);
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{
    overflow-x:auto;
    border:1px solid #edf0f3;
    border-radius:9px;
}

.table{
    width:100% !important;
    margin-bottom:0 !important;
    vertical-align:middle;
}

.table thead th{
    padding:11px 12px !important;
    background:#f8fafc !important;
    border-bottom:1px solid #e5e7eb !important;
    color:#64748b !important;
    font-size:10px;
    font-weight:650;
    text-transform:uppercase;
    white-space:nowrap;
}

.table tbody td{
    padding:12px !important;
    border-color:#eef1f4 !important;
    color:#374151;
    font-size:12px;
}

.patient-name{
    color:#1f2937;
    font-weight:650;
}

.patient-sub{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}


/* =========================================================
   NUMBER
========================================================= */

.number-cell{
    width:55px;
    text-align:center;
}

.number-circle{
    width:30px;
    height:30px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    border-radius:8px;
    background:#f1f5f9;
    color:#64748b;
    font-size:10px;
    font-weight:700;
}


/* =========================================================
   BADGES
========================================================= */

.status-badge{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:5px 8px;
    border-radius:6px;
    font-size:10px;
    font-weight:650;
    white-space:nowrap;
}

.status-approved{
    background:#ecfdf5;
    color:#15803d;
}

.status-completed{
    background:#eff6ff;
    color:#2563eb;
}

.status-admitted{
    background:#fff7ed;
    color:#c2410c;
}

.status-pending{
    background:#fef3c7;
    color:#92400e;
}

.status-early{
    background:#fff7ed;
    color:#c2410c;
}

.status-normal{
    background:#ecfdf5;
    color:#15803d;
}

.status-review{
    background:#f5f3ff;
    color:#7c3aed;
}

.status-other{
    background:#f3f4f6;
    color:#64748b;
}


/* =========================================================
   ACTION
========================================================= */

.action-buttons{
    white-space:nowrap;
}

.btn-view,
.btn-discharge{
    padding:6px 9px;
    border-radius:7px;
    font-size:10px;
    font-weight:600;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_filter{
    display:none;
}

.dataTables_wrapper .dataTables_length{
    margin-top:15px;
    color:#64748b;
    font-size:11px;
}

.dataTables_wrapper .dataTables_info{
    padding-top:19px !important;
    color:#94a3b8 !important;
    font-size:11px;
}

.dataTables_wrapper .dataTables_paginate{
    padding-top:13px !important;
}


/* =========================================================
   MODAL
========================================================= */

.custom-modal-overlay{
    display:none;
    position:fixed;
    inset:0;
    z-index:99999;
    padding:20px;
    background:rgba(15,23,42,.48);
    backdrop-filter:blur(3px);
    align-items:center;
    justify-content:center;
}

.custom-modal-overlay.show{
    display:flex;
}

.custom-modal{
    width:100%;
    max-width:410px;
    padding:28px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    box-shadow:0 24px 60px rgba(15,23,42,.20);
    text-align:center;
}

.modal-icon{
    width:54px;
    height:54px;
    margin:0 auto 17px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:50%;
    font-size:23px;
    background:#fff7ed;
    border:1px solid #fed7aa;
    color:#ea580c;
}

.modal-buttons{
    display:flex;
    justify-content:center;
    gap:9px;
}

.modal-buttons button{
    min-width:105px;
    padding:9px 16px;
    border:0;
    border-radius:8px;
    font-size:12px;
    font-weight:600;
}

.btn-modal-cancel{
    background:#f1f5f9;
    color:#475569;
}

.btn-modal-confirm{
    background:#dc2626;
    color:#fff;
}


@media(max-width:1100px){

    .patient-tabs{
        overflow-x:auto;
        flex-wrap:nowrap;
    }

    .patient-tabs .nav-item{
        flex:0 0 auto;
    }
}

@media(max-width:768px){

    .content{
        padding:18px;
    }

    .page-title{
        font-size:23px;
    }
}

</style>

</head>


<body>

<div class="d-flex">

<?php

if ($role === 'admin') {
    include("../includes/sidebar_admin.php");
}
else {
    include("../includes/sidebar_doctor.php");
}

?>


<div class="content">


<div class="page-header">

<h1 class="page-title">
Patient Management
</h1>

<div class="page-subtitle">
View complete patient activity across appointments, consultations, admissions, clinical reviews and discharge.
</div>

</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="row g-3 mb-4">

<div class="col-xl col-md-6">
<div class="stats-card">

<div class="stat-content">

<div>
<div class="stat-label">Admitted Patients</div>
<div class="stat-number"><?= $totalCurrent ?></div>
<div class="stat-subtitle">Currently in hospital</div>
</div>

<div class="stat-icon icon-admitted">
<i class="bi bi-hospital"></i>
</div>

</div>

</div>
</div>


<div class="col-xl col-md-6">
<div class="stats-card">

<div class="stat-content">

<div>
<div class="stat-label">Clinical Records</div>
<div class="stat-number"><?= $totalDiagnosed ?></div>
<div class="stat-subtitle">Diagnosis & reviews</div>
</div>

<div class="stat-icon icon-diagnosed">
<i class="bi bi-clipboard2-check"></i>
</div>

</div>

</div>
</div>


<div class="col-xl col-md-6">
<div class="stats-card">

<div class="stat-content">

<div>
<div class="stat-label">Medication</div>
<div class="stat-number"><?= $totalMedication ?></div>
<div class="stat-subtitle">All medication orders</div>
</div>

<div class="stat-icon icon-medication">
<i class="bi bi-capsule"></i>
</div>

</div>

</div>
</div>


<div class="col-xl col-md-6">
<div class="stats-card">

<div class="stat-content">

<div>
<div class="stat-label">Discharge Medicine</div>
<div class="stat-number"><?= $totalDischargeMedication ?></div>
<div class="stat-subtitle">Take-home prescriptions</div>
</div>

<div class="stat-icon icon-discharge-med">
<i class="bi bi-prescription2"></i>
</div>

</div>

</div>
</div>


<div class="col-xl col-md-6">
<div class="stats-card">

<div class="stat-content">

<div>
<div class="stat-label">Discharged</div>
<div class="stat-number"><?= $totalDischarged ?></div>
<div class="stat-subtitle">Completed admissions</div>
</div>

<div class="stat-icon icon-discharged">
<i class="bi bi-box-arrow-right"></i>
</div>

</div>

</div>
</div>

</div>


<!-- =====================================================
     FILTER
===================================================== -->

<div class="filter-card">

<div class="filter-title">
<i class="bi bi-funnel me-1"></i>
Search & Filter
</div>


<div class="row g-2">

<div class="col-lg-5">

<div class="filter-label">
Search Patient
</div>

<div class="search-wrapper">

<i class="bi bi-search"></i>

<input
    type="text"
    id="searchBox"
    class="form-control"
    placeholder="Search patient, ward, doctor, diagnosis..."
>

</div>
</div>


<div class="col-lg-3">

<div class="filter-label">
Record Type
</div>

<select
    id="typeFilter"
    class="form-select"
>

<option value="">
All Types
</option>

<option value="current">
Admitted Patients
</option>

<option value="walkin">
Walk-In
</option>

<option value="appointment">
Appointment
</option>

<option value="diagnosed">
Clinical Records
</option>

<option value="discharged">
Discharged
</option>

</select>

</div>


<div class="col-lg-4">

<div class="filter-label">
Sort By
</div>

<select
    id="sortOrder"
    class="form-select"
>

<option value="latest">
Newest First
</option>

<option value="oldest">
Oldest First
</option>

<option value="asc">
Patient Name A-Z
</option>

<option value="desc">
Patient Name Z-A
</option>

</select>

</div>

</div>


<div class="date-filter-box">

<div class="date-filter-header">

<i class="bi bi-calendar3"></i>

View Patient Records by Date

</div>


<div class="row g-2 align-items-end">

<div class="col-lg-4 col-md-6">

<div class="filter-label">
Select Date
</div>

<input
    type="date"
    id="recordDate"
    class="form-control"
>

</div>


<div class="col-lg-2 col-md-3">

<button
    type="button"
    id="todayBtn"
    class="btn btn-primary btn-date w-100"
>

<i class="bi bi-calendar-day me-1"></i>
Today

</button>

</div>


<div class="col-lg-2 col-md-3">

<button
    type="button"
    id="clearDateBtn"
    class="btn btn-outline-secondary btn-date w-100"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>
Clear

</button>

</div>


<div class="col-lg-4">

<button
    type="button"
    id="downloadPatientBtn"
    class="btn btn-download btn-date w-100"
>

<i class="bi bi-download me-1"></i>
Download Patient List

</button>

</div>

</div>


<div
    id="dateActiveMessage"
    class="date-active-message"
>

Showing records for

<strong id="selectedDateText"></strong>

</div>

</div>

</div>


<!-- =====================================================
     TABLE CARD
===================================================== -->

<div class="table-card">

<div class="table-card-header">

<h5 class="table-card-title">
Patient Records
</h5>

<div class="table-card-subtitle">
Review the complete patient journey from consultation to discharge.
</div>

</div>


<ul class="nav nav-tabs patient-tabs">

<li class="nav-item">
<button
    class="nav-link active"
    data-bs-toggle="tab"
    data-bs-target="#current"
>
<i class="bi bi-hospital me-1"></i>
Admitted
</button>
</li>

<li class="nav-item">
<button
    class="nav-link"
    data-bs-toggle="tab"
    data-bs-target="#walkin"
>
<i class="bi bi-person-walking me-1"></i>
Walk-In
</button>
</li>

<li class="nav-item">
<button
    class="nav-link"
    data-bs-toggle="tab"
    data-bs-target="#appointment"
>
<i class="bi bi-calendar-event me-1"></i>
Appointments
</button>
</li>

<li class="nav-item">
<button
    class="nav-link"
    data-bs-toggle="tab"
    data-bs-target="#diagnosed"
>
<i class="bi bi-clipboard2-check me-1"></i>
Clinical Records
</button>
</li>

<li class="nav-item">
<button
    class="nav-link"
    data-bs-toggle="tab"
    data-bs-target="#discharged"
>
<i class="bi bi-box-arrow-right me-1"></i>
Discharged
</button>
</li>

</ul>


<div class="tab-content">


<!-- =====================================================
     ADMITTED
===================================================== -->

<div
    class="tab-pane fade show active"
    id="current"
>

<div class="table-responsive">

<table
    class="table"
    id="currentTable"
>

<thead>
<tr>
<th>No.</th>
<th>Patient</th>
<th>Ward</th>
<th>Bed</th>
<th>Doctor</th>
<th>Admission</th>
<th>Expected Discharge</th>
<th>Stay</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach ($currentList as $p): ?>

<tr
    data-record-date="<?= h($p['ADMISSION_SORT']) ?>"
>

<td class="number-cell"></td>

<td>

<div class="patient-name">
<?= h($p['NAME']) ?>
</div>

<div class="patient-sub">
<?= h($p['IC_NUMBER'] ?: '-') ?>
</div>

</td>

<td><?= h($p['WARD_NAME']) ?></td>

<td>
<span class="status-badge status-other">
Bed <?= h($p['BED_NUMBER']) ?>
</span>
</td>

<td>
<?= h($p['DOCTOR_NAME'] ?: '-') ?>
</td>

<td
    data-date="<?= h($p['ADMISSION_SORT']) ?>"
>
<?= h($p['ADMISSION_DATE']) ?>
</td>

<td>

<?php if (!empty($p['EXPECTED_DISCHARGE_DATE'])): ?>

<?= h($p['EXPECTED_DISCHARGE_DATE']) ?>

<?php else: ?>

<span class="status-badge status-pending">
Not Set
</span>

<?php endif; ?>

</td>

<td>

<span class="status-badge status-approved">

<?= max(
    1,
    (int)$p['STAY_DAYS']
) ?>

day(s)

</span>

</td>

<td class="action-buttons">

<a
    href="patient_details.php?id=<?= urlencode($p['ADMISSION_ID']) ?>"
    class="btn btn-outline-primary btn-sm btn-view me-1"
>
<i class="bi bi-eye me-1"></i>
View
</a>


<?php if ($role === 'admin'): ?>

<button
    type="button"
    class="btn btn-outline-danger btn-sm btn-discharge discharge-btn"
    data-url="discharge_patient.php?admission_id=<?= urlencode($p['ADMISSION_ID']) ?>"
    data-patient="<?= h($p['NAME']) ?>"
>

<i class="bi bi-box-arrow-right me-1"></i>
Discharge

</button>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


<!-- =====================================================
     WALK-IN
===================================================== -->

<div
    class="tab-pane fade"
    id="walkin"
>

<div class="table-responsive">

<table
    class="table"
    id="walkinTable"
>

<thead>
<tr>
<th>No.</th>
<th>Patient</th>
<th>Date</th>
<th>Department</th>
<th>Doctor</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach ($walkinPatients as $w): ?>

<tr
    data-record-date="<?= h($w['CONSULTATION_SORT']) ?>"
>

<td class="number-cell"></td>

<td>

<div class="patient-name">
<?= h($w['NAME']) ?>
</div>

<div class="patient-sub">
<?= h($w['IC_NUMBER'] ?: '-') ?>
</div>

</td>

<td data-date="<?= h($w['CONSULTATION_SORT']) ?>">
<?= h($w['CONSULTATION_DATE']) ?>
</td>

<td><?= h($w['DEPARTMENT'] ?: '-') ?></td>

<td><?= h($w['DOCTOR_NAME'] ?: '-') ?></td>

<td>

<span class="status-badge status-other">
<?= h($w['STATUS'] ?: '-') ?>
</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


<!-- =====================================================
     APPOINTMENT
===================================================== -->

<div
    class="tab-pane fade"
    id="appointment"
>

<div class="table-responsive">

<table
    class="table"
    id="appointmentTable"
>

<thead>
<tr>
<th>No.</th>
<th>Patient</th>
<th>Date</th>
<th>Time</th>
<th>Department</th>
<th>Doctor</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach ($appointmentPatients as $a): ?>

<?php

$appointmentSortDate =
    convertAppointmentDate(
        $a['APPOINTMENT_DATE']
        ?? ''
    );

$status =
    strtolower(
        trim(
            $a['STATUS']
            ?? ''
        )
    );

?>

<tr
    data-record-date="<?= h($appointmentSortDate) ?>"
>

<td class="number-cell"></td>

<td>

<div class="patient-name">
<?= h(
    $a['NAME']
    ?: 'Unknown Patient'
) ?>
</div>

<div class="patient-sub">
<?= h($a['IC_NUMBER'] ?: '-') ?>
</div>

</td>

<td data-date="<?= h($appointmentSortDate) ?>">
<?= h($a['APPOINTMENT_DATE']) ?>
</td>

<td>
<?= h($a['APPOINTMENT_TIME'] ?: '-') ?>
</td>

<td><?= h($a['DEPARTMENT'] ?: '-') ?></td>

<td><?= h($a['DOCTOR_NAME'] ?: '-') ?></td>

<td>

<?php if ($status === 'approved'): ?>

<span class="status-badge status-approved">
<i class="bi bi-check-circle"></i>
Approved
</span>

<?php elseif ($status === 'completed'): ?>

<span class="status-badge status-completed">
<i class="bi bi-check2-all"></i>
Completed
</span>

<?php elseif ($status === 'admitted'): ?>

<span class="status-badge status-admitted">
<i class="bi bi-hospital"></i>
Admitted
</span>

<?php elseif ($status === 'pending'): ?>

<span class="status-badge status-pending">
<i class="bi bi-hourglass-split"></i>
Pending
</span>

<?php else: ?>

<span class="status-badge status-other">
<?= h($a['STATUS']) ?>
</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


<!-- =====================================================
     CLINICAL RECORDS
===================================================== -->

<div
    class="tab-pane fade"
    id="diagnosed"
>

<div class="table-responsive">

<table
    class="table"
    id="diagnosedTable"
>

<thead>
<tr>
<th>No.</th>
<th>Patient</th>
<th>Type</th>
<th>Diagnosis / Review</th>
<th>Allergies</th>
<th>Doctor</th>
<th>Date / Time</th>
</tr>
</thead>

<tbody>

<?php foreach ($diagnosedList as $d): ?>

<tr
    data-record-date="<?= h($d['DATE_SORT']) ?>"
>

<td class="number-cell"></td>

<td>
<span class="patient-name">
<?= h($d['NAME']) ?>
</span>
</td>

<td>

<span class="status-badge status-review">
<?= h($d['DIAGNOSIS_TYPE']) ?>
</span>

</td>

<td>
<?= h($d['DIAGNOSIS_DETAILS']) ?>
</td>

<td>
<?= h($d['ALLERGIES'] ?: '-') ?>
</td>

<td>
<?= h($d['DOCTOR_NAME'] ?: '-') ?>
</td>

<td data-date="<?= h($d['DATE_SORT']) ?>">

<?= h($d['DATE_RECORDED']) ?>

<div class="patient-sub">
<?= h($d['TIME_RECORDED']) ?>
</div>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


<!-- =====================================================
     DISCHARGED
===================================================== -->

<div
    class="tab-pane fade"
    id="discharged"
>

<div class="table-responsive">

<table
    class="table"
    id="dischargedTable"
>

<thead>
<tr>
<th>No.</th>
<th>Patient</th>
<th>Ward / Bed</th>
<th>Doctor</th>
<th>Admission</th>
<th>Expected</th>
<th>Actual Discharge</th>
<th>Type</th>
<th>Stay</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach ($dischargedList as $d): ?>

<?php

$admissionDate =
    !empty($d['ADMISSION_SORT'])
    ?
    new DateTime(
        $d['ADMISSION_SORT']
    )
    :
    null;

$dischargeDate =
    !empty($d['DISCHARGE_SORT'])
    ?
    new DateTime(
        $d['DISCHARGE_SORT']
    )
    :
    null;

$days = 0;

if (
    $admissionDate &&
    $dischargeDate
) {

    $days =
        $admissionDate
        ->diff(
            $dischargeDate
        )
        ->days
        +
        1;
}

?>

<tr
    data-record-date="<?= h($d['DISCHARGE_SORT']) ?>"
>

<td class="number-cell"></td>

<td>

<div class="patient-name">
<?= h($d['NAME']) ?>
</div>

<div class="patient-sub">
<?= h($d['IC_NUMBER'] ?: '-') ?>
</div>

</td>

<td>

<?= h($d['WARD_NAME'] ?: '-') ?>

<div class="patient-sub">
Bed <?= h($d['BED_NUMBER'] ?: '-') ?>
</div>

</td>

<td>
<?= h($d['DOCTOR_NAME'] ?: '-') ?>
</td>

<td data-date="<?= h($d['ADMISSION_SORT']) ?>">
<?= h($d['ADMISSION_DATE']) ?>
</td>

<td>

<?= h(
    $d['EXPECTED_DISCHARGE_DATE']
    ?: 'Not Set'
) ?>

</td>

<td data-date="<?= h($d['DISCHARGE_SORT']) ?>">

<?= h($d['DISCHARGE_DATE']) ?>

</td>

<td>

<?php if ($d['DISCHARGE_TYPE'] === 'Early'): ?>

<span class="status-badge status-early">
<i class="bi bi-exclamation-triangle"></i>
Early
</span>

<?php else: ?>

<span class="status-badge status-normal">
<i class="bi bi-check-circle"></i>
Normal
</span>

<?php endif; ?>

</td>

<td>

<span class="status-badge status-other">

<i class="bi bi-clock"></i>

<?= $days ?> Day(s)

</span>

</td>

<td>

<a
    href="patient_details.php?id=<?= urlencode($d['ADMISSION_ID']) ?>"
    class="btn btn-outline-primary btn-sm btn-view"
>
<i class="bi bi-eye me-1"></i>
View
</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>
</table>

</div>
</div>


</div>
</div>


</div>
</div>


<?php if ($role === 'admin'): ?>

<div
    id="dischargeModal"
    class="custom-modal-overlay"
>

<div class="custom-modal">

<div class="modal-icon">
<i class="bi bi-exclamation-triangle"></i>
</div>

<h3>
Discharge Patient?
</h3>

<p id="dischargeMessage">
Are you sure you want to continue?
</p>

<div class="modal-buttons">

<button
    type="button"
    class="btn-modal-cancel"
    id="cancelDischarge"
>
Cancel
</button>

<button
    type="button"
    class="btn-modal-confirm"
    id="confirmDischarge"
>
<i class="bi bi-box-arrow-right me-1"></i>
Continue
</button>

</div>

</div>
</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>


<script>

let currentTable;
let walkinTable;
let appointmentTable;
let diagnosedTable;
let dischargedTable;

let selectedRecordDate = '';
let selectedDischargeURL = null;


/* =========================================================
   GLOBAL DATE FILTER
========================================================= */

$.fn.dataTable.ext.search.push(

function(
    settings,
    data,
    dataIndex
)
{

    if (!selectedRecordDate) {
        return true;
    }

    const row =
        settings
        .aoData[
            dataIndex
        ]
        .nTr;

    if (!row) {
        return true;
    }

    const recordDate =
        row.getAttribute(
            'data-record-date'
        )
        ||
        '';

    return (
        recordDate
        ===
        selectedRecordDate
    );
}

);


/* =========================================================
   NUMBERING FUNCTION
========================================================= */

function updateTableNumbers(api)
{
    const info =
        api.page.info();

    api
        .column(
            0,
            {
                page:'current',
                search:'applied',
                order:'applied'
            }
        )
        .nodes()
        .each(
            function(
                cell,
                index
            )
            {

                cell.innerHTML =
                    '<div class="number-circle">'
                    +
                    (
                        info.start
                        +
                        index
                        +
                        1
                    )
                    +
                    '</div>';
            }
        );
}


$(document).ready(function(){


    /* =====================================================
       ADMITTED
    ===================================================== */

    currentTable =
        $('#currentTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [5,'desc']
            ],

            columnDefs:[
                {
                    orderable:false,
                    searchable:false,
                    targets:0
                },
                {
                    orderable:false,
                    targets:8
                }
            ],

            drawCallback:function()
            {
                updateTableNumbers(
                    this.api()
                );
            }
        });


    /* =====================================================
       WALK-IN
    ===================================================== */

    walkinTable =
        $('#walkinTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [2,'desc']
            ],

            columnDefs:[
                {
                    orderable:false,
                    searchable:false,
                    targets:0
                }
            ],

            drawCallback:function()
            {
                updateTableNumbers(
                    this.api()
                );
            }
        });


    /* =====================================================
       APPOINTMENT
    ===================================================== */

    appointmentTable =
        $('#appointmentTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [2,'desc']
            ],

            columnDefs:[
                {
                    orderable:false,
                    searchable:false,
                    targets:0
                }
            ],

            drawCallback:function()
            {
                updateTableNumbers(
                    this.api()
                );
            }
        });


    /* =====================================================
       CLINICAL RECORDS
    ===================================================== */

    diagnosedTable =
        $('#diagnosedTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [6,'desc']
            ],

            columnDefs:[
                {
                    orderable:false,
                    searchable:false,
                    targets:0
                }
            ],

            drawCallback:function()
            {
                updateTableNumbers(
                    this.api()
                );
            }
        });


    /* =====================================================
       DISCHARGED
    ===================================================== */

    dischargedTable =
        $('#dischargedTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [6,'desc']
            ],

            columnDefs:[
                {
                    orderable:false,
                    searchable:false,
                    targets:0
                },
                {
                    orderable:false,
                    targets:9
                }
            ],

            drawCallback:function()
            {
                updateTableNumbers(
                    this.api()
                );
            }
        });


    /* =====================================================
       DATE INPUT
    ===================================================== */

    $('#recordDate').on(
        'change',
        function()
        {

            selectedRecordDate =
                this.value;

            updateDateMessage();
            redrawAllTables();
        }
    );


    /* =====================================================
       TODAY
    ===================================================== */

    $('#todayBtn').on(
        'click',
        function()
        {

            const today =
                new Date();

            const year =
                today.getFullYear();

            const month =
                String(
                    today.getMonth()
                    +
                    1
                )
                .padStart(
                    2,
                    '0'
                );

            const day =
                String(
                    today.getDate()
                )
                .padStart(
                    2,
                    '0'
                );

            selectedRecordDate =
                `${year}-${month}-${day}`;

            $('#recordDate')
                .val(
                    selectedRecordDate
                );

            updateDateMessage();
            redrawAllTables();
        }
    );


    /* =====================================================
       CLEAR DATE
    ===================================================== */

    $('#clearDateBtn').on(
        'click',
        function()
        {

            selectedRecordDate = '';

            $('#recordDate')
                .val('');

            updateDateMessage();
            redrawAllTables();
        }
    );


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#searchBox').on(
        'input',
        function()
        {

            const table =
                getActiveDataTable();

            if (!table) {
                return;
            }

            table
                .search(
                    this.value
                )
                .draw();
        }
    );


    /* =====================================================
       TYPE FILTER
    ===================================================== */

    $('#typeFilter').on(
        'change',
        function()
        {

            if (!this.value) {
                return;
            }

            const target =
                document.querySelector(
                    '[data-bs-target="#'
                    +
                    this.value
                    +
                    '"]'
                );

            if (target) {
                target.click();
            }
        }
    );


    /* =====================================================
       SORT
    ===================================================== */

    $('#sortOrder').on(
        'change',
        applyCurrentSort
    );


    /* =====================================================
       TAB CHANGED
    ===================================================== */

    document
        .querySelectorAll(
            '[data-bs-toggle="tab"]'
        )
        .forEach(
            function(tab)
            {

                tab.addEventListener(
                    'shown.bs.tab',
                    function()
                    {

                        const table =
                            getActiveDataTable();

                        if (!table) {
                            return;
                        }

                        table
                            .columns
                            .adjust();

                        table
                            .search(
                                $('#searchBox')
                                .val()
                            )
                            .draw();

                        applyCurrentSort();
                    }
                );
            }
        );


    /* =====================================================
       DOWNLOAD
    ===================================================== */

    $('#downloadPatientBtn').on(
        'click',
        downloadSelectedPatientList
    );


    /* =====================================================
       DISCHARGE
    ===================================================== */

    <?php if ($role === 'admin'): ?>

    document
        .querySelectorAll(
            '.discharge-btn'
        )
        .forEach(
            function(button)
            {

                button.addEventListener(
                    'click',
                    function()
                    {

                        selectedDischargeURL =
                            this.dataset.url;

                        document
                            .getElementById(
                                'dischargeMessage'
                            )
                            .innerHTML =
                            'Continue to discharge workflow for <strong>'
                            +
                            escapeHtml(
                                this.dataset.patient
                            )
                            +
                            '</strong>?';

                        document
                            .getElementById(
                                'dischargeModal'
                            )
                            .classList
                            .add(
                                'show'
                            );
                    }
                );
            }
        );


    document
        .getElementById(
            'cancelDischarge'
        )
        .addEventListener(
            'click',
            closeDischargeModal
        );


    document
        .getElementById(
            'confirmDischarge'
        )
        .addEventListener(
            'click',
            function()
            {

                if (selectedDischargeURL) {

                    window.location.href =
                        selectedDischargeURL;
                }
            }
        );


    document
        .getElementById(
            'dischargeModal'
        )
        .addEventListener(
            'click',
            function(event)
            {

                if (
                    event.target
                    ===
                    this
                ) {

                    closeDischargeModal();
                }
            }
        );

    <?php endif; ?>

});


/* =========================================================
   GET ACTIVE TAB
========================================================= */

function getActiveTab()
{
    return document.querySelector(
        '.tab-pane.active'
    );
}


/* =========================================================
   GET ACTIVE DATATABLE
========================================================= */

function getActiveDataTable()
{
    const activeTab =
        getActiveTab();

    if (!activeTab) {
        return null;
    }

    const tableElement =
        activeTab.querySelector(
            'table'
        );

    if (!tableElement) {
        return null;
    }

    return $('#' + tableElement.id)
        .DataTable();
}


/* =========================================================
   FIND DATE COLUMN
========================================================= */

function findDateColumn(table)
{
    let dateIndex = -1;

    table
        .columns()
        .every(
            function(index)
            {

                const header =
                    $(this.header())
                    .text()
                    .trim()
                    .toLowerCase();

                if (
                    header.includes(
                        'actual discharge'
                    )
                ) {

                    dateIndex =
                        index;

                    return false;
                }

                if (
                    dateIndex === -1
                    &&
                    (
                        header.includes(
                            'date'
                        )
                        ||
                        header.includes(
                            'admission'
                        )
                    )
                ) {

                    dateIndex =
                        index;
                }
            }
        );

    return dateIndex;
}


/* =========================================================
   APPLY SORT
========================================================= */

function applyCurrentSort()
{
    const mode =
        document
        .getElementById(
            'sortOrder'
        )
        .value;

    const table =
        getActiveDataTable();

    if (!table) {
        return;
    }


    if (mode === 'asc') {

        table
            .order([
                [1,'asc']
            ])
            .draw();

        return;
    }


    if (mode === 'desc') {

        table
            .order([
                [1,'desc']
            ])
            .draw();

        return;
    }


    const dateIndex =
        findDateColumn(table);

    if (dateIndex !== -1) {

        table
            .order([
                [
                    dateIndex,
                    mode === 'latest'
                    ?
                    'desc'
                    :
                    'asc'
                ]
            ])
            .draw();
    }
}


/* =========================================================
   REDRAW
========================================================= */

function redrawAllTables()
{
    currentTable?.draw();
    walkinTable?.draw();
    appointmentTable?.draw();
    diagnosedTable?.draw();
    dischargedTable?.draw();
}


/* =========================================================
   DATE MESSAGE
========================================================= */

function updateDateMessage()
{
    const message =
        document.getElementById(
            'dateActiveMessage'
        );

    const selectedText =
        document.getElementById(
            'selectedDateText'
        );

    if (
        !message ||
        !selectedText
    ) {
        return;
    }

    if (!selectedRecordDate) {

        message
            .classList
            .remove(
                'show'
            );

        return;
    }

    const parts =
        selectedRecordDate
        .split('-');

    if (
        parts.length !== 3
    ) {
        return;
    }

    const dateObject =
        new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        );

    selectedText.textContent =
        dateObject.toLocaleDateString(
            'en-GB',
            {
                day:'2-digit',
                month:'long',
                year:'numeric'
            }
        );

    message
        .classList
        .add(
            'show'
        );
}


/* =========================================================
   DOWNLOAD
========================================================= */

function downloadSelectedPatientList()
{
    const activeTab =
        getActiveTab();

    if (!activeTab) {
        return;
    }

    const tableElement =
        activeTab.querySelector(
            'table'
        );

    if (!tableElement) {
        return;
    }

    const table =
        $('#' + tableElement.id)
        .DataTable();

    const headers = [];


    $(tableElement)
        .find('thead th')
        .each(
            function()
            {

                const heading =
                    $(this)
                    .text()
                    .replace(
                        /\s+/g,
                        ' '
                    )
                    .trim();

                const lower =
                    heading
                    .toLowerCase();

                if (
                    lower !== 'action'
                    &&
                    lower !== 'no.'
                ) {

                    headers.push(
                        heading
                    );
                }
            }
        );


    const rows = [];


    table
        .rows({
            search:'applied',
            order:'applied'
        })
        .every(
            function()
            {

                const node =
                    this.node();

                if (!node) {
                    return;
                }

                const recordDate =
                    node.getAttribute(
                        'data-record-date'
                    )
                    ||
                    '';

                if (
                    selectedRecordDate
                    &&
                    recordDate
                    !==
                    selectedRecordDate
                ) {
                    return;
                }

                const rowData = [];


                $(node)
                    .find('td')
                    .each(
                        function(index)
                        {

                            const heading =
                                $(tableElement)
                                .find('thead th')
                                .eq(index)
                                .text()
                                .replace(
                                    /\s+/g,
                                    ' '
                                )
                                .trim()
                                .toLowerCase();

                            if (
                                heading === 'action'
                                ||
                                heading === 'no.'
                            ) {
                                return;
                            }

                            rowData.push(
                                $(this)
                                .text()
                                .replace(
                                    /\s+/g,
                                    ' '
                                )
                                .trim()
                            );
                        }
                    );

                rows.push(
                    rowData
                );
            }
        );


    if (
        rows.length === 0
    ) {

        alert(
            'No patient records found.'
        );

        return;
    }


    const csvRows = [];

    csvRows.push(
        escapeCSV(
            'ZB-CARE Patient List'
        )
    );

    csvRows.push(
        escapeCSV(
            'Category: '
            +
            getCategoryName(
                activeTab.id
            )
        )
    );

    csvRows.push(
        escapeCSV(
            'Date: '
            +
            (
                selectedRecordDate
                ||
                'All Records'
            )
        )
    );

    csvRows.push('');

    csvRows.push(
        headers
        .map(
            escapeCSV
        )
        .join(',')
    );


    rows.forEach(
        function(row)
        {

            csvRows.push(
                row
                .map(
                    escapeCSV
                )
                .join(',')
            );
        }
    );


    const blob =
        new Blob(
            [
                '\uFEFF'
                +
                csvRows.join(
                    '\r\n'
                )
            ],
            {
                type:
                    'text/csv;charset=utf-8;'
            }
        );


    const url =
        URL.createObjectURL(
            blob
        );


    const link =
        document.createElement(
            'a'
        );


    link.href =
        url;


    link.download =
        'ZB-CARE-Patient-'
        +
        activeTab.id
        +
        '-'
        +
        (
            selectedRecordDate
            ||
            'ALL'
        )
        +
        '.csv';


    document.body
        .appendChild(
            link
        );


    link.click();


    link.remove();


    URL.revokeObjectURL(
        url
    );
}


/* =========================================================
   CATEGORY NAME
========================================================= */

function getCategoryName(tabId)
{
    const names = {

        current:
            'Admitted Patients',

        walkin:
            'Walk-In Patients',

        appointment:
            'Appointments',

        diagnosed:
            'Clinical Records',

        discharged:
            'Discharged Patients'

    };

    return (
        names[tabId]
        ||
        'Patient Records'
    );
}


/* =========================================================
   CSV
========================================================= */

function escapeCSV(value)
{
    return '"'
        +
        String(
            value
            ??
            ''
        )
        .replace(
            /"/g,
            '""'
        )
        +
        '"';
}


/* =========================================================
   CLOSE MODAL
========================================================= */

function closeDischargeModal()
{
    const modal =
        document.getElementById(
            'dischargeModal'
        );

    if (modal) {

        modal
            .classList
            .remove(
                'show'
            );
    }

    selectedDischargeURL =
        null;
}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text)
{
    const div =
        document.createElement(
            'div'
        );

    div.textContent =
        text
        ??
        '';

    return div.innerHTML;
}

</script>

</body>
</html>