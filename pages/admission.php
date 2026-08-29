<?php

session_start();

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");


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
   SEARCH PATIENT BY IC
========================================================= */

$patientData = null;

if (isset($_POST['search_ic'])) {

    $ic = trim(
        $_POST['search_ic_number'] ?? ''
    );

    if ($ic !== '') {

        $stmt = $conn->prepare("
            SELECT *
            FROM SYARMIMI.PATIENT
            WHERE IC_NUMBER = ?
        ");

        $stmt->execute([
            $ic
        ]);

        $patientData = $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }
}


/* =========================================================
   REGISTER PATIENT
========================================================= */

if (isset($_POST['register_patient'])) {

    $ic = trim(
        $_POST['ic'] ?? ''
    );

    $name = trim(
        $_POST['name'] ?? ''
    );

    $age = trim(
        $_POST['age'] ?? ''
    );

    $gender = trim(
        $_POST['gender'] ?? ''
    );

    $phone = trim(
        $_POST['phone'] ?? ''
    );

    $address = trim(
        $_POST['address'] ?? ''
    );


    try {

        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.PATIENT
            WHERE IC_NUMBER = ?
        ");

        $check->execute([
            $ic
        ]);


        if ((int)$check->fetchColumn() > 0) {

            $_SESSION['swal'] = [
                'icon' => 'warning',
                'title' => 'Patient Already Exists',
                'text' => 'A patient with this IC number is already registered.'
            ];

            header("Location: admission.php");
            exit();
        }


        $stmt = $conn->query("
            SELECT
                NVL(
                    MAX(PATIENT_ID),
                    0
                ) + 1
                AS NEW_ID
            FROM SYARMIMI.PATIENT
        ");

        $newId = $stmt->fetch(
            PDO::FETCH_ASSOC
        )['NEW_ID'];


        $insertPatient = $conn->prepare("
            INSERT INTO SYARMIMI.PATIENT
            (
                PATIENT_ID,
                IC_NUMBER,
                NAME,
                AGE,
                GENDER,
                PHONE,
                ADDRESS
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ");

        $insertPatient->execute([
            $newId,
            $ic,
            strtoupper($name),
            $age,
            $gender,
            $phone,
            strtoupper($address)
        ]);


        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Patient Registered',
            'text' => 'The patient has been registered successfully.'
        ];


        header("Location: admission.php");
        exit();


    } catch (PDOException $e) {

        die(
            "Database Error: " .
            h($e->getMessage())
        );
    }
}


/* =========================================================
   CREATE ADMISSION
========================================================= */

if (isset($_POST['submit'])) {

    $patient = (int)(
        $_POST['patient_id'] ?? 0
    );

    $bed = (int)(
        $_POST['bed'] ?? 0
    );

    $doctor = (int)(
        $_POST['doctor'] ?? 0
    );


    try {

        $conn->beginTransaction();


        /* =================================================
           VALIDATION
        ================================================= */

        if (
            $patient <= 0 ||
            $bed <= 0 ||
            $doctor <= 0
        ) {

            throw new Exception(
                "Please complete all admission fields."
            );
        }


        /* =================================================
           CHECK ACTIVE ADMISSION
        ================================================= */

        $check = $conn->prepare("
            SELECT COUNT(*)

            FROM SYARMIMI.ADMISSION

            WHERE PATIENT_ID = ?

            AND DISCHARGE_DATE IS NULL
        ");

        $check->execute([
            $patient
        ]);


        if ((int)$check->fetchColumn() > 0) {

            throw new Exception(
                "This patient already has an active hospital admission."
            );
        }


        /* =================================================
           CHECK BED
        ================================================= */

        $bedCheck = $conn->prepare("
            SELECT STATUS

            FROM SYARMIMI.BED

            WHERE BED_ID = ?

            FOR UPDATE
        ");

        $bedCheck->execute([
            $bed
        ]);

        $bedStatus = strtoupper(
            trim(
                $bedCheck->fetchColumn() ?? ''
            )
        );


        if ($bedStatus !== 'AVAILABLE') {

            throw new Exception(
                "Selected bed is no longer available."
            );
        }


        /* =================================================
           CREATE ADMISSION ID
        ================================================= */

        $stmt = $conn->query("
            SELECT
                NVL(
                    MAX(ADMISSION_ID),
                    0
                ) + 1
                AS NEW_ID

            FROM SYARMIMI.ADMISSION
        ");

        $newId = $stmt->fetch(
            PDO::FETCH_ASSOC
        )['NEW_ID'];


        /* =================================================
           INSERT ADMISSION
        ================================================= */

        $stmt = $conn->prepare("
            INSERT INTO SYARMIMI.ADMISSION
            (
                ADMISSION_ID,
                PATIENT_ID,
                BED_ID,
                ACCOUNT_ID,
                ADMISSION_DATE,
                IS_SEEN
            )
            VALUES
            (
                :id,
                :patient,
                :bed,
                :doctor,
                SYSDATE,
                0
            )
        ");

        $stmt->execute([
            ':id' => $newId,
            ':patient' => $patient,
            ':bed' => $bed,
            ':doctor' => $doctor
        ]);


        /* =================================================
           DEFAULT DIAGNOSIS
        ================================================= */

        $diagStmt = $conn->prepare("
            INSERT INTO SYARMIMI.DIAGNOSIS
            (
                DIAGNOSIS_ID,
                DIAGNOSIS_DETAILS,
                ALLERGIES,
                DATE_RECORDED,
                PATIENT_ID,
                ADMISSION_ID,
                ACCOUNT_ID
            )
            VALUES
            (
                (
                    SELECT
                        NVL(
                            MAX(DIAGNOSIS_ID),
                            0
                        ) + 1
                    FROM SYARMIMI.DIAGNOSIS
                ),
                '-',
                '-',
                SYSDATE,
                :patient,
                :admission,
                :doctor
            )
        ");

        $diagStmt->execute([
            ':patient' => $patient,
            ':admission' => $newId,
            ':doctor' => $doctor
        ]);


        /* =================================================
           OCCUPY BED
        ================================================= */

        $updateBed = $conn->prepare("
            UPDATE SYARMIMI.BED

            SET STATUS = 'Occupied'

            WHERE BED_ID = :bed
        ");

        $updateBed->execute([
            ':bed' => $bed
        ]);


        $conn->commit();


        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Admission Created',
            'text' => 'Patient has been admitted successfully.'
        ];


        header("Location: admission.php");
        exit();


    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }


        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Admission Failed',
            'text' => $e->getMessage()
        ];


        header("Location: admission.php");
        exit();
    }
}


/* =========================================================
   DISCHARGE
========================================================= */

if (isset($_GET['discharge'])) {

    $id = (int)$_GET['discharge'];


    try {

        $conn->beginTransaction();


        $stmt = $conn->prepare("
            SELECT BED_ID

            FROM SYARMIMI.ADMISSION

            WHERE ADMISSION_ID = :id

            AND DISCHARGE_DATE IS NULL

            FOR UPDATE
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $bedData = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$bedData) {

            throw new Exception(
                "Active admission record not found."
            );
        }


        $updateAdmission = $conn->prepare("
            UPDATE SYARMIMI.ADMISSION

            SET DISCHARGE_DATE = SYSDATE

            WHERE ADMISSION_ID = :id
        ");

        $updateAdmission->execute([
            ':id' => $id
        ]);


        $updateBed = $conn->prepare("
            UPDATE SYARMIMI.BED

            SET STATUS = 'Available'

            WHERE BED_ID = :bed
        ");

        $updateBed->execute([
            ':bed' => $bedData['BED_ID']
        ]);


        $conn->commit();


        $_SESSION['swal'] = [
            'icon' => 'success',
            'title' => 'Patient Discharged',
            'text' => 'The patient has been discharged and the bed is now available.'
        ];


        header("Location: admission.php");
        exit();


    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }


        $_SESSION['swal'] = [
            'icon' => 'error',
            'title' => 'Discharge Failed',
            'text' => $e->getMessage()
        ];


        header("Location: admission.php");
        exit();
    }
}


/* =========================================================
   PATIENT LIST
========================================================= */

$patients = $conn->query("
    SELECT
        PATIENT_ID,
        NAME,
        IC_NUMBER

    FROM SYARMIMI.PATIENT

    ORDER BY NAME
")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   AVAILABLE BEDS
========================================================= */

$beds = $conn->query("
    SELECT
        B.BED_ID,
        B.BED_NUMBER,
        W.WARD_NAME

    FROM SYARMIMI.BED B

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE UPPER(
        TRIM(B.STATUS)
    ) = 'AVAILABLE'

    ORDER BY
        W.WARD_NAME,
        B.BED_NUMBER
")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   DOCTORS
========================================================= */

$doctors = $conn->query("
    SELECT
        ACCOUNT_ID,
        USERNAME,
        DEPARTMENT

    FROM SYARMIMI.HOSPITAL_STAFF

    WHERE LOWER(
        TRIM(ROLE)
    ) = 'doctor'

    ORDER BY
        DEPARTMENT,
        USERNAME
")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   SUMMARY
========================================================= */

$totalPatientCount = (int)$conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.PATIENT
")->fetchColumn();


$activeAdmissionCount = (int)$conn->query("
    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

    WHERE DISCHARGE_DATE IS NULL
")->fetchColumn();


$availableBedCount = (int)$conn->query("
    SELECT COUNT(*)

    FROM SYARMIMI.BED

    WHERE UPPER(
        TRIM(STATUS)
    ) = 'AVAILABLE'
")->fetchColumn();


/* =========================================================
   INPATIENT RECORDS
========================================================= */

$inpatients = $conn->query("

    SELECT

        A.ADMISSION_ID AS ID,

        P.NAME AS PATIENT_NAME,

        P.IC_NUMBER,

        'Inpatient' AS TYPE,

        S.USERNAME AS DOCTOR_NAME,

        W.WARD_NAME AS LOCATION,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-RR'
        ) AS RECORD_DATE,

        TO_CHAR(
            A.ADMISSION_DATE,
            'YYYY-MM-DD HH24:MI:SS'
        ) AS SORT_DATE,

        'Admitted' AS STATUS

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID =
           P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID =
           B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID =
           W.WARD_ID

    LEFT JOIN SYARMIMI.HOSPITAL_STAFF S
        ON A.ACCOUNT_ID =
           S.ACCOUNT_ID

    WHERE A.DISCHARGE_DATE IS NULL

    ORDER BY
        A.ADMISSION_DATE DESC,
        A.ADMISSION_ID DESC

")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   APPOINTMENT RECORDS

   PATIENT_NAME is directly from APPOINTMENT so appointments
   still appear even when PATIENT_ID has not been linked.
========================================================= */

$appointments = $conn->query("

    SELECT

        A.APPOINTMENT_ID AS ID,

        NVL(
            P.NAME,
            A.PATIENT_NAME
        ) AS PATIENT_NAME,

        NVL(
            P.IC_NUMBER,
            A.IC_NUMBER
        ) AS IC_NUMBER,

        'Appointment' AS TYPE,

        A.DOCTOR_NAME,

        A.DEPARTMENT AS LOCATION,

        A.APPOINTMENT_DATE AS RECORD_DATE,

        CASE

            WHEN REGEXP_LIKE(
                A.APPOINTMENT_DATE,
                '^[0-9]{2}-[A-Za-z]{3}-[0-9]{2}$'
            )

            THEN TO_CHAR(
                TO_DATE(
                    UPPER(
                        A.APPOINTMENT_DATE
                    ),
                    'DD-MON-RR'
                ),
                'YYYY-MM-DD'
            )

            WHEN REGEXP_LIKE(
                A.APPOINTMENT_DATE,
                '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            )

            THEN A.APPOINTMENT_DATE

            ELSE NULL

        END AS SORT_DATE,

        A.STATUS

    FROM SYARMIMI.APPOINTMENT A

    LEFT JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID =
           P.PATIENT_ID

    ORDER BY
        A.APPOINTMENT_ID DESC

")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   WALK-IN RECORDS
========================================================= */

$walkins = $conn->query("

    SELECT

        W.CONSULTATION_ID AS ID,

        P.NAME AS PATIENT_NAME,

        P.IC_NUMBER,

        'Walk-In' AS TYPE,

        S.USERNAME AS DOCTOR_NAME,

        W.DEPARTMENT AS LOCATION,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'DD-MON-RR'
        ) AS RECORD_DATE,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'YYYY-MM-DD HH24:MI:SS'
        ) AS SORT_DATE,

        W.STATUS

    FROM SYARMIMI.WALKIN_CONSULTATION W

    LEFT JOIN SYARMIMI.PATIENT P
        ON W.PATIENT_ID =
           P.PATIENT_ID

    LEFT JOIN SYARMIMI.HOSPITAL_STAFF S
        ON W.ACCOUNT_ID =
           S.ACCOUNT_ID

    ORDER BY
        W.CONSULTATION_DATE DESC,
        W.CONSULTATION_ID DESC

")->fetchAll(
    PDO::FETCH_ASSOC
);


/* =========================================================
   MERGE RECORDS
========================================================= */

$allRecords = array_merge(
    $inpatients,
    $appointments,
    $walkins
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

<title>Patient Admission</title>

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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


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
    font-family:'Segoe UI',Arial,sans-serif;
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


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:14px;
    margin-bottom:22px;
}

.summary-card{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;

    padding:20px;

    background:#fff;

    border:1px solid #e5e7eb;
    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.035);
}

.summary-label{
    color:#64748b;
    font-size:13px;
    font-weight:600;
}

.summary-number{
    margin-top:5px;
    color:#111827;
    font-size:31px;
    font-weight:750;
}

.summary-icon{
    width:46px;
    height:46px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:12px;

    font-size:19px;
}

.icon-patient{
    background:#eff6ff;
    color:#2563eb;
}

.icon-admission{
    background:#fff7ed;
    color:#ea580c;
}

.icon-bed{
    background:#ecfdf5;
    color:#15803d;
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
    align-items:center;
    gap:12px;
    margin-bottom:19px;
}

.card-icon{
    width:40px;
    height:40px;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:10px;

    font-size:17px;
}

.card-icon-search{
    background:#eff6ff;
    color:#2563eb;
}

.card-icon-register{
    background:#f5f3ff;
    color:#7c3aed;
}

.card-icon-admit{
    background:#ecfdf5;
    color:#15803d;
}

.card-icon-list{
    background:#fff7ed;
    color:#ea580c;
}

.card-title-clean{
    margin:0;
    color:#1f2937;
    font-size:18px;
    font-weight:700;
}

.card-subtitle{
    margin-top:3px;
    color:#94a3b8;
    font-size:13px;
}


/* =========================================================
   FORMS
========================================================= */

.form-label{
    margin-bottom:7px;
    color:#475569;
    font-size:13px;
    font-weight:650;
}

.form-control,
.form-select{
    min-height:46px;

    border:1px solid #dfe3e8;
    border-radius:9px;

    color:#374151;
    font-size:14px;
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
    left:14px;

    z-index:2;

    color:#94a3b8;

    transform:translateY(-50%);
}

.search-wrapper input{
    padding-left:41px;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn-main{
    min-height:46px;
    border-radius:9px;
    font-size:13px;
    font-weight:650;
}

.btn-search{
    background:#2563eb;
    border-color:#2563eb;
    color:#fff;
}

.btn-search:hover{
    background:#1d4ed8;
    border-color:#1d4ed8;
    color:#fff;
}

.btn-register{
    background:#7c3aed;
    border-color:#7c3aed;
    color:#fff;
}

.btn-register:hover{
    background:#6d28d9;
    border-color:#6d28d9;
    color:#fff;
}

.btn-admit{
    background:#16a34a;
    border-color:#16a34a;
    color:#fff;
}

.btn-admit:hover{
    background:#15803d;
    border-color:#15803d;
    color:#fff;
}


/* =========================================================
   PATIENT FOUND
========================================================= */

.patient-found{
    margin-top:18px;
    padding:17px;

    background:#f0fdf4;

    border:1px solid #bbf7d0;
    border-radius:10px;
}

.patient-found-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:14px;
}

.patient-found-title{
    display:flex;
    align-items:center;
    gap:7px;

    color:#15803d;

    font-size:13px;
    font-weight:700;
}

.patient-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:12px;
}

.patient-item{
    padding:13px 14px;

    background:#fff;

    border:1px solid #dcfce7;
    border-radius:9px;
}

.patient-label{
    color:#94a3b8;
    font-size:10px;
    font-weight:650;
    text-transform:uppercase;
}

.patient-value{
    margin-top:4px;
    color:#1f2937;
    font-size:13px;
    font-weight:650;
}


/* =========================================================
   FILTER AREA
========================================================= */

.filter-area{
    margin-bottom:18px;
    padding:16px;

    background:#f8fafc;

    border:1px solid #e5e7eb;
    border-radius:10px;
}

.filter-label{
    margin-bottom:7px;

    color:#64748b;

    font-size:10px;
    font-weight:700;

    text-transform:uppercase;
    letter-spacing:.4px;
}


/* =========================================================
   FILTER RESULT INFO
========================================================= */

.filter-result-info{
    display:flex;
    align-items:center;
    gap:7px;

    margin-top:13px;

    padding:9px 12px;

    background:#eff6ff;

    border-radius:7px;

    color:#2563eb;

    font-size:11px;
    font-weight:600;
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

.patient-name{
    color:#111827;
    font-size:13px;
    font-weight:650;
}

.patient-ic{
    margin-top:3px;
    color:#94a3b8;
    font-size:10px;
}

.number-cell{
    width:48px;
    text-align:center;
    color:#64748b;
    font-weight:650;
}


/* =========================================================
   BADGES
========================================================= */

.status-badge{
    display:inline-flex;
    align-items:center;
    gap:5px;

    padding:6px 8px;

    border-radius:6px;

    font-size:10px;
    font-weight:650;

    white-space:nowrap;
}

.badge-inpatient{
    background:#eff6ff;
    color:#2563eb;
}

.badge-appointment{
    background:#fff7ed;
    color:#c2410c;
}

.badge-walkin{
    background:#ecfeff;
    color:#0e7490;
}

.badge-approved{
    background:#ecfdf5;
    color:#15803d;
}

.badge-pending{
    background:#fef3c7;
    color:#92400e;
}

.badge-rejected{
    background:#fff1f2;
    color:#be123c;
}

.badge-admitted{
    background:#ecfdf5;
    color:#15803d;
}

.badge-completed{
    background:#eff6ff;
    color:#2563eb;
}

.badge-other{
    background:#f3f4f6;
    color:#64748b;
}


/* =========================================================
   DISCHARGE
========================================================= */

.btn-discharge{
    padding:6px 10px;

    background:#fff;

    border:1px solid #fecaca;
    border-radius:7px;

    color:#dc2626;

    font-size:10px;
    font-weight:650;
}

.btn-discharge:hover{
    background:#dc2626;
    border-color:#dc2626;
    color:#fff;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_filter{
    display:none !important;
}

.dataTables_wrapper .dataTables_length{
    margin-top:14px;

    color:#64748b;

    font-size:11px;
}

.dataTables_wrapper .dataTables_length select{
    min-height:32px;

    padding:4px 25px 4px 8px;

    border:1px solid #dfe3e8;
    border-radius:6px;

    font-size:11px;
}

.dataTables_wrapper .dataTables_info{
    padding-top:17px !important;

    color:#94a3b8 !important;

    font-size:11px;
}

.dataTables_wrapper .dataTables_paginate{
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

.dataTables_empty{
    padding:35px !important;

    color:#94a3b8 !important;

    text-align:center;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px){

    .summary-grid{
        grid-template-columns:1fr;
    }

    .patient-grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:768px){

    .main-content{
        padding:18px;
    }

    .page-title{
        font-size:24px;
    }

    .content-card{
        padding:17px;
    }
}

</style>

</head>


<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<h1 class="page-title">
Patient Admission
</h1>

<div class="page-subtitle">
Search and register patients, manage hospital admissions and review patient records.
</div>

</div>


<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


<div class="summary-card">

<div>

<div class="summary-label">
Registered Patients
</div>

<div class="summary-number">
<?= $totalPatientCount ?>
</div>

</div>

<div class="summary-icon icon-patient">

<i class="bi bi-people"></i>

</div>

</div>


<div class="summary-card">

<div>

<div class="summary-label">
Active Admissions
</div>

<div class="summary-number">
<?= $activeAdmissionCount ?>
</div>

</div>

<div class="summary-icon icon-admission">

<i class="bi bi-hospital"></i>

</div>

</div>


<div class="summary-card">

<div>

<div class="summary-label">
Available Beds
</div>

<div class="summary-number">
<?= $availableBedCount ?>
</div>

</div>

<div class="summary-icon icon-bed">

<i class="bi bi-hospital-bed"></i>

</div>

</div>


</div>


<!-- =====================================================
     SEARCH PATIENT
===================================================== -->

<div class="content-card">


<div class="card-heading">

<div class="card-icon card-icon-search">

<i class="bi bi-search"></i>

</div>

<div>

<h5 class="card-title-clean">
Search Patient
</h5>

<div class="card-subtitle">
Search an existing patient using their IC number.
</div>

</div>

</div>


<form method="POST">

<div class="row g-2">

<div class="col-lg-10">

<div class="search-wrapper">

<i class="bi bi-person-vcard"></i>

<input
    type="text"
    name="search_ic_number"
    class="form-control ic-format"
    placeholder="Enter IC number • xxxxxx-xx-xxxx"
    maxlength="14"
    required
    value="<?= h(
        $_POST['search_ic_number']
        ?? ''
    ) ?>"
>

</div>

</div>


<div class="col-lg-2">

<button
    type="submit"
    name="search_ic"
    class="btn btn-search btn-main w-100"
>

<i class="bi bi-search me-1"></i>
Search

</button>

</div>

</div>

</form>


<?php if ($patientData): ?>


<div class="patient-found">


<div class="patient-found-header">

<div class="patient-found-title">

<i class="bi bi-check-circle-fill"></i>

Patient found in system

</div>


<a
    href="admission.php"
    class="btn btn-sm btn-outline-secondary"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>
Reset

</a>

</div>


<div class="patient-grid">


<div class="patient-item">

<div class="patient-label">
Patient Name
</div>

<div class="patient-value">
<?= h($patientData['NAME']) ?>
</div>

</div>


<div class="patient-item">

<div class="patient-label">
IC Number
</div>

<div class="patient-value">
<?= h($patientData['IC_NUMBER']) ?>
</div>

</div>


<div class="patient-item">

<div class="patient-label">
Phone Number
</div>

<div class="patient-value">

<?= h(
    $patientData['PHONE']
    ?? '-'
) ?>

</div>

</div>


</div>

</div>


<?php elseif (isset($_POST['search_ic'])): ?>


<div class="alert alert-warning mt-3 mb-0">

<i class="bi bi-exclamation-circle me-1"></i>

Patient not found. You may register the patient below.

</div>


<?php endif; ?>


</div>


<!-- =====================================================
     REGISTER PATIENT
===================================================== -->

<div class="content-card">


<div class="card-heading">

<div class="card-icon card-icon-register">

<i class="bi bi-person-plus"></i>

</div>

<div>

<h5 class="card-title-clean">
Register New Patient
</h5>

<div class="card-subtitle">
Create a new patient record before hospital admission.
</div>

</div>

</div>


<form method="POST">


<div class="row g-3">


<div class="col-md-4">

<label class="form-label">
IC Number
</label>

<input
    type="text"
    name="ic"
    class="form-control ic-format"
    placeholder="xxxxxx-xx-xxxx"
    maxlength="14"
    required
>

</div>


<div class="col-md-5">

<label class="form-label">
Patient Name
</label>

<input
    type="text"
    id="patientName"
    name="name"
    class="form-control"
    placeholder="PATIENT FULL NAME"
    required
>

</div>


<div class="col-md-3">

<label class="form-label">
Age
</label>

<input
    type="number"
    name="age"
    class="form-control"
    min="0"
    max="120"
    placeholder="Age"
    required
>

</div>


<div class="col-md-4">

<label class="form-label">
Gender
</label>

<select
    name="gender"
    class="form-select"
    required
>

<option value="">
Select Gender
</option>

<option value="Male">
Male
</option>

<option value="Female">
Female
</option>

</select>

</div>


<div class="col-md-4">

<label class="form-label">
Phone Number
</label>

<input
    type="text"
    id="phone"
    name="phone"
    class="form-control"
    placeholder="01x-xxx-xxxx"
    maxlength="13"
    required
>

</div>


<div class="col-md-4">

<label class="form-label">
Address
</label>

<input
    type="text"
    id="patientAddress"
    name="address"
    class="form-control"
    placeholder="Patient address"
    required
>

</div>


<div class="col-12">

<button
    type="submit"
    name="register_patient"
    class="btn btn-register btn-main px-4"
>

<i class="bi bi-person-plus me-1"></i>
Register Patient

</button>

</div>


</div>

</form>


</div>


<!-- =====================================================
     CREATE ADMISSION
===================================================== -->

<div class="content-card">


<div class="card-heading">

<div class="card-icon card-icon-admit">

<i class="bi bi-hospital"></i>

</div>

<div>

<h5 class="card-title-clean">
Create Admission
</h5>

<div class="card-subtitle">
Assign a patient to an available hospital bed and responsible doctor.
</div>

</div>

</div>


<form method="POST">


<div class="row g-3 align-items-end">


<div class="col-lg-4">

<label class="form-label">
Patient
</label>

<select
    name="patient_id"
    class="form-select"
    required
>

<option value="">
Select Patient
</option>

<?php foreach ($patients as $p): ?>

<option
    value="<?= h($p['PATIENT_ID']) ?>"

    <?= (
        $patientData &&
        $patientData['PATIENT_ID'] ==
        $p['PATIENT_ID']
    )
        ? 'selected'
        : ''
    ?>
>

<?= h($p['NAME']) ?>

(
<?= h($p['IC_NUMBER']) ?>
)

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-lg-3">

<label class="form-label">
Available Bed
</label>

<select
    name="bed"
    class="form-select"
    required
>

<option value="">
Select Bed
</option>

<?php foreach ($beds as $b): ?>

<option
    value="<?= h($b['BED_ID']) ?>"
>

Bed
<?= h($b['BED_NUMBER']) ?>

•
<?= h($b['WARD_NAME']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-lg-4">

<label class="form-label">
Responsible Doctor
</label>

<select
    name="doctor"
    class="form-select"
    required
>

<option value="">
Select Doctor
</option>

<?php foreach ($doctors as $d): ?>

<option
    value="<?= h($d['ACCOUNT_ID']) ?>"
>

<?= (
    stripos(
        trim($d['USERNAME']),
        'Dr.'
    ) === 0
)
    ? h($d['USERNAME'])
    : 'Dr. ' . h($d['USERNAME'])
?>

•
<?= h($d['DEPARTMENT']) ?>

</option>

<?php endforeach; ?>

</select>

</div>


<div class="col-lg-1">

<button
    type="submit"
    name="submit"
    class="btn btn-admit btn-main w-100"
    title="Create Admission"
>

<i class="bi bi-plus-lg"></i>

</button>

</div>


</div>

</form>


</div>


<!-- =====================================================
     PATIENT RECORDS
===================================================== -->

<div class="content-card">


<div class="card-heading">

<div class="card-icon card-icon-list">

<i class="bi bi-list-ul"></i>

</div>

<div>

<h5 class="card-title-clean">
Patient Records
</h5>

<div class="card-subtitle">
Search and filter admissions, appointments and walk-in consultations.
</div>

</div>

</div>


<!-- =================================================
     FILTERS
================================================= -->

<div class="filter-area">


<div class="row g-2">


<div class="col-lg-5">

<div class="filter-label">
Search
</div>

<div class="search-wrapper">

<i class="bi bi-search"></i>

<input
    type="text"
    id="globalSearch"
    class="form-control"
    placeholder="Search patient, IC, doctor, ward, department or status..."
>

</div>

</div>


<div class="col-lg-4">

<div class="filter-label">
Patient Type
</div>

<select
    id="typeFilter"
    class="form-select"
>

<option value="">
All Patient Types
</option>

<option value="Inpatient">
Inpatient
</option>

<option value="Appointment">
Appointment
</option>

<option value="Walk-In">
Walk-In
</option>

</select>

</div>


<div class="col-lg-3">

<div class="filter-label">
Sort
</div>

<select
    id="sortFilter"
    class="form-select"
>

<option value="latest">
Newest First
</option>

<option value="oldest">
Oldest First
</option>

</select>

</div>


</div>


<div class="filter-result-info">

<i class="bi bi-info-circle"></i>

<span id="filterStatusText">
Showing all patient records
</span>

</div>


</div>


<!-- =================================================
     TABLE
================================================= -->

<div class="table-responsive">


<table
    id="patientTable"
    class="table"
>


<thead>

<tr>

<th>No.</th>

<th>Patient</th>

<th>Type</th>

<th>Doctor</th>

<th>Ward / Department</th>

<th>Date</th>

<th>Status</th>

<th>Action</th>

<th>Type Filter</th>

<th>Sort Date</th>

</tr>

</thead>


<tbody>


<?php foreach ($allRecords as $row): ?>


<?php

$type = trim(
    $row['TYPE']
    ?? ''
);


$statusText = trim(
    $row['STATUS']
    ?? ''
);


$status = strtolower(
    $statusText
);


/* =========================================================
   DOCTOR DISPLAY
========================================================= */

$doctorDisplay = trim(
    $row['DOCTOR_NAME']
    ?? ''
);


if (
    $doctorDisplay !== '' &&
    stripos(
        $doctorDisplay,
        'Dr.'
    ) !== 0
) {

    $doctorDisplay =
        'Dr. ' .
        $doctorDisplay;
}

?>


<tr>


<!-- NUMBER -->

<td class="number-cell"></td>


<!-- PATIENT -->

<td>

<div class="patient-name">

<?= h(
    $row['PATIENT_NAME']
    ?: 'Unknown Patient'
) ?>

</div>

<div class="patient-ic">

<?= h(
    $row['IC_NUMBER']
    ?? '-'
) ?>

</div>

</td>


<!-- TYPE DISPLAY -->

<td>

<?php if ($type === 'Inpatient'): ?>

<span class="status-badge badge-inpatient">

<i class="bi bi-hospital"></i>

Inpatient

</span>


<?php elseif ($type === 'Appointment'): ?>

<span class="status-badge badge-appointment">

<i class="bi bi-calendar-event"></i>

Appointment

</span>


<?php elseif ($type === 'Walk-In'): ?>

<span class="status-badge badge-walkin">

<i class="bi bi-person-walking"></i>

Walk-In

</span>


<?php else: ?>

<span class="status-badge badge-other">

<?= h($type) ?>

</span>

<?php endif; ?>

</td>


<!-- DOCTOR -->

<td>

<?php if ($doctorDisplay !== ''): ?>

<?= h($doctorDisplay) ?>

<?php else: ?>

<span class="text-muted">
-
</span>

<?php endif; ?>

</td>


<!-- LOCATION -->

<td>

<?= h(
    $row['LOCATION']
    ?? '-'
) ?>

</td>


<!-- DATE -->

<td>

<?= h(
    $row['RECORD_DATE']
    ?? '-'
) ?>

</td>


<!-- STATUS -->

<td>

<?php if ($status === 'admitted'): ?>

<span class="status-badge badge-admitted">

<i class="bi bi-hospital"></i>
Admitted

</span>


<?php elseif ($status === 'approved'): ?>

<span class="status-badge badge-approved">

<i class="bi bi-check-circle"></i>
Approved

</span>


<?php elseif ($status === 'pending'): ?>

<span class="status-badge badge-pending">

<i class="bi bi-hourglass-split"></i>
Pending

</span>


<?php elseif ($status === 'rejected'): ?>

<span class="status-badge badge-rejected">

<i class="bi bi-x-circle"></i>
Rejected

</span>


<?php elseif ($status === 'completed'): ?>

<span class="status-badge badge-completed">

<i class="bi bi-check2-all"></i>
Completed

</span>


<?php else: ?>

<span class="status-badge badge-other">

<?= h(
    $statusText
    ?: '-'
) ?>

</span>

<?php endif; ?>

</td>


<!-- ACTION -->

<td>

<?php if ($type === 'Inpatient'): ?>

<button
    type="button"
    class="btn btn-discharge dischargeBtn"

    data-id="<?= h($row['ID']) ?>"

    data-name="<?= h(
        $row['PATIENT_NAME']
        ?? ''
    ) ?>"
>

<i class="bi bi-box-arrow-right me-1"></i>

Discharge

</button>


<?php else: ?>

<span class="text-muted">
—
</span>

<?php endif; ?>

</td>


<!-- HIDDEN PLAIN TYPE -->

<td>
<?= h($type) ?>
</td>


<!-- HIDDEN SORT DATE -->

<td>
<?= h(
    $row['SORT_DATE']
    ?? ''
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


<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>


<script>

$(document).ready(function(){


    /* =====================================================
       DATATABLE
    ===================================================== */

    const patientTable =
        $('#patientTable')
        .DataTable({

            searching: true,

            paging: true,

            info: true,

            pageLength: 10,

            lengthMenu: [
                [10,25,50,100],
                [10,25,50,100]
            ],

            /*
             Sort Date = hidden column 9
            */
            order: [
                [9, 'desc']
            ],

            /*
             Hide DataTables built-in search.
             We use our custom search field.
            */
            dom: 'lrtip',

            columnDefs: [

                /*
                 Number
                */
                {
                    targets: 0,
                    orderable: false,
                    searchable: false
                },

                /*
                 Action
                */
                {
                    targets: 7,
                    orderable: false,
                    searchable: false
                },

                /*
                 Hidden exact patient type
                */
                {
                    targets: 8,
                    visible: false,
                    searchable: true
                },

                /*
                 Hidden sorting date
                */
                {
                    targets: 9,
                    visible: false,
                    searchable: false
                }

            ],


            drawCallback: function(){

                const api =
                    this.api();

                const info =
                    api.page.info();


                /*
                 Update 1,2,3 numbering
                */
                api
                    .column(
                        0,
                        {
                            page: 'current',
                            search: 'applied',
                            order: 'applied'
                        }
                    )
                    .nodes()
                    .each(
                        function(cell,index){

                            cell.innerHTML =
                                info.start
                                +
                                index
                                +
                                1;
                        }
                    );


                updateFilterMessage(
                    api
                );

            }

        });


    /* =====================================================
       GLOBAL SEARCH
    ===================================================== */

    $('#globalSearch').on(
        'input',
        function(){

            const value =
                $(this)
                .val()
                .trim();


            patientTable
                .search(
                    value
                )
                .draw();

        }
    );


    /* =====================================================
       TYPE FILTER

       IMPORTANT:
       Filtering hidden plain-text TYPE column.
       No badge HTML involved.
    ===================================================== */

    $('#typeFilter').on(
        'change',
        function(){

            const type =
                $(this).val();


            if (type === '') {

                patientTable
                    .column(8)
                    .search('')
                    .draw();

                return;
            }


            patientTable
                .column(8)
                .search(
                    '^'
                    +
                    escapeRegex(
                        type
                    )
                    +
                    '$',
                    true,
                    false
                )
                .draw();

        }
    );


    /* =====================================================
       SORT
    ===================================================== */

    $('#sortFilter').on(
        'change',
        function(){

            const sort =
                $(this).val();


            if (
                sort ===
                'oldest'
            ) {

                patientTable
                    .order([
                        [9,'asc']
                    ])
                    .draw();

            } else {

                patientTable
                    .order([
                        [9,'desc']
                    ])
                    .draw();
            }

        }
    );


    /* =====================================================
       DISCHARGE
    ===================================================== */

    $(document).on(
        'click',
        '.dischargeBtn',
        function(){

            const admissionId =
                this.dataset.id;


            const patientName =
                this.dataset.name;


            Swal.fire({

                icon:
                    'warning',

                title:
                    'Discharge Patient?',

                html:
                    'Are you sure you want to discharge <strong>'
                    +
                    escapeHtml(
                        patientName
                    )
                    +
                    '</strong>?',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Discharge',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#dc2626',

                cancelButtonColor:
                    '#64748b'

            })
            .then(
                function(result){

                    if (
                        result.isConfirmed
                    ) {

                        window.location.href =
                            'admission.php?discharge='
                            +
                            encodeURIComponent(
                                admissionId
                            );
                    }

                }
            );

        }
    );


    /* =====================================================
       FILTER MESSAGE
    ===================================================== */

    function updateFilterMessage(api){

        const total =
            api.rows().count();


        const filtered =
            api.rows({
                search:'applied'
            }).count();


        const search =
            $('#globalSearch')
            .val()
            .trim();


        const type =
            $('#typeFilter')
            .val();


        let text = '';


        if (
            search === ''
            &&
            type === ''
        ) {

            text =
                'Showing all '
                +
                total
                +
                ' patient record(s)';

        } else {

            text =
                'Showing '
                +
                filtered
                +
                ' matching record(s)';


            if (
                type !== ''
            ) {

                text +=
                    ' • Type: '
                    +
                    type;
            }


            if (
                search !== ''
            ) {

                text +=
                    ' • Search: "'
                    +
                    search
                    +
                    '"';
            }
        }


        $('#filterStatusText')
            .text(
                text
            );

    }


    /* =====================================================
       REGEX
    ===================================================== */

    function escapeRegex(value){

        return value.replace(
            /[.*+?^${}()|[\]\\]/g,
            '\\$&'
        );

    }


});


/* =========================================================
   IC FORMAT
========================================================= */

document
.querySelectorAll(
    '.ic-format'
)
.forEach(
function(element){

    element.addEventListener(
        'input',
        function(){

            let value =
                this.value
                .replace(
                    /\D/g,
                    ''
                )
                .substring(
                    0,
                    12
                );


            let formatted =
                '';


            if (
                value.length > 0
            ) {

                formatted +=
                    value.substring(
                        0,
                        6
                    );
            }


            if (
                value.length > 6
            ) {

                formatted +=
                    '-'
                    +
                    value.substring(
                        6,
                        8
                    );
            }


            if (
                value.length > 8
            ) {

                formatted +=
                    '-'
                    +
                    value.substring(
                        8,
                        12
                    );
            }


            this.value =
                formatted;

        }
    );

}
);


/* =========================================================
   PHONE FORMAT
========================================================= */

document
.getElementById(
    'phone'
)
?.addEventListener(
    'input',
    function(){

        let value =
            this.value
            .replace(
                /\D/g,
                ''
            )
            .substring(
                0,
                11
            );


        let formatted =
            '';


        if (
            value.length > 0
        ) {

            formatted +=
                value.substring(
                    0,
                    3
                );
        }


        if (
            value.length > 3
        ) {

            formatted +=
                '-'
                +
                value.substring(
                    3,
                    6
                );
        }


        if (
            value.length > 6
        ) {

            formatted +=
                '-'
                +
                value.substring(
                    6,
                    11
                );
        }


        this.value =
            formatted;

    }
);


/* =========================================================
   UPPERCASE
========================================================= */

document
.getElementById(
    'patientName'
)
?.addEventListener(
    'input',
    function(){

        this.value =
            this.value
            .toUpperCase();

    }
);


document
.getElementById(
    'patientAddress'
)
?.addEventListener(
    'input',
    function(){

        this.value =
            this.value
            .toUpperCase();

    }
);


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text){

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


<!-- =====================================================
     SWEET ALERT
===================================================== -->

<?php if (
    isset(
        $_SESSION['swal']
    )
): ?>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function(){

        Swal.fire({

            icon:
                <?= json_encode(
                    $_SESSION['swal']['icon']
                ) ?>,

            title:
                <?= json_encode(
                    $_SESSION['swal']['title']
                ) ?>,

            text:
                <?= json_encode(
                    $_SESSION['swal']['text']
                ) ?>,

            confirmButtonColor:
                '#2563eb'

        });

    }
);

</script>


<?php

unset(
    $_SESSION['swal']
);

?>


<?php endif; ?>


</body>

</html>