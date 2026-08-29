<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    die("Access Denied");
}

$doctorId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

if ($doctorId <= 0) {
    die("Invalid doctor account.");
}


/* =========================================================
   HELPER
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
   ADMISSION ID
========================================================= */

$admissionId =
    (int)(
        $_GET['admission_id']
        ??
        $_POST['admission_id']
        ??
        0
    );

if ($admissionId <= 0) {
    die("Invalid admission.");
}

$errorMessage = '';


/* =========================================================
   GET ACTIVE ADMISSION + PATIENT
========================================================= */

$patientStmt =
    $conn->prepare("

        SELECT

            A.ADMISSION_ID,
            A.PATIENT_ID,
            A.BED_ID,

            TO_CHAR(
                A.ADMISSION_DATE,
                'DD-MON-RR'
            )
            AS ADMISSION_DATE_DISPLAY,

            TO_CHAR(
                A.EXPECTED_DISCHARGE_DATE,
                'DD-MON-RR'
            )
            AS EXPECTED_DATE_DISPLAY,

            TO_CHAR(
                A.EXPECTED_DISCHARGE_DATE,
                'YYYY-MM-DD'
            )
            AS EXPECTED_DATE_VALUE,

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
            AS STAY_DAYS,

            CASE

                WHEN
                    A.EXPECTED_DISCHARGE_DATE
                    IS NOT NULL

                AND
                    TRUNC(SYSDATE)
                    <
                    TRUNC(
                        A.EXPECTED_DISCHARGE_DATE
                    )

                THEN 1

                ELSE 0

            END
            AS IS_EARLY_DISCHARGE,

            P.NAME
            AS PATIENT_NAME,

            P.IC_NUMBER,
            P.GENDER,
            P.PHONE,

            B.BED_NUMBER,

            W.WARD_NAME

        FROM
            SYARMIMI.ADMISSION A

        JOIN
            SYARMIMI.PATIENT P
            ON A.PATIENT_ID =
               P.PATIENT_ID

        LEFT JOIN
            SYARMIMI.BED B
            ON A.BED_ID =
               B.BED_ID

        LEFT JOIN
            SYARMIMI.WARD W
            ON B.WARD_ID =
               W.WARD_ID

        WHERE
            A.ADMISSION_ID = ?

        AND
            A.ACCOUNT_ID = ?

        AND
            A.DISCHARGE_DATE
            IS NULL

    ");

$patientStmt->execute([
    $admissionId,
    $doctorId
]);

$patient =
    $patientStmt->fetch(
        PDO::FETCH_ASSOC
    );

if (!$patient) {

    die(
        "Active admission not found or you do not have permission to review this patient."
    );
}


/* =========================================================
   SAVE NEW CLINICAL REVIEW

   IMPORTANT:
   INSERT NEW DIAGNOSIS ROW.
   DO NOT overwrite previous diagnosis.
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['save_review'])
) {

    $reviewDetails =
        trim(
            $_POST['review_details']
            ?? ''
        );

    $allergies =
        trim(
            $_POST['allergies']
            ?? ''
        );


    if ($reviewDetails === '') {

        $errorMessage =
            "Please enter the patient's latest clinical condition.";

    }
    else {

        try {

            $conn->beginTransaction();


            /* =============================================
               LOCK ADMISSION FIRST
            ============================================= */

            $lockStmt =
                $conn->prepare("

                    SELECT
                        PATIENT_ID

                    FROM
                        SYARMIMI.ADMISSION

                    WHERE
                        ADMISSION_ID = ?

                    AND
                        ACCOUNT_ID = ?

                    AND
                        DISCHARGE_DATE
                        IS NULL

                    FOR UPDATE

                ");

            $lockStmt->execute([
                $admissionId,
                $doctorId
            ]);

            $lockedPatientId =
                $lockStmt->fetchColumn();


            if (!$lockedPatientId) {

                throw new Exception(
                    "This admission is no longer active."
                );
            }


            /* =============================================
               NEW DIAGNOSIS ID

               Current project uses MAX + 1 because
               DIAGNOSIS_SEQ has not been confirmed.
            ============================================= */

            $diagnosisId =
                (int)$conn
                    ->query("

                        SELECT

                            NVL(
                                MAX(DIAGNOSIS_ID),
                                0
                            )
                            +
                            1

                        FROM
                            SYARMIMI.DIAGNOSIS

                    ")
                    ->fetchColumn();


            /* =============================================
               INSERT REVIEW
            ============================================= */

            $insertReview =
                $conn->prepare("

                    INSERT INTO
                        SYARMIMI.DIAGNOSIS
                    (
                        DIAGNOSIS_ID,
                        PATIENT_ID,
                        ADMISSION_ID,
                        DIAGNOSIS_DETAILS,
                        ALLERGIES,
                        DATE_RECORDED,
                        ACCOUNT_ID
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        SYSDATE,
                        ?
                    )

                ");

            $insertReview->execute([

                $diagnosisId,

                (int)$lockedPatientId,

                $admissionId,

                $reviewDetails,

                $allergies !== ''
                    ?
                    $allergies
                    :
                    '-',

                $doctorId

            ]);


            $conn->commit();


            $_SESSION['review_success'] =
                "Clinical review saved successfully.";


            header(
                "Location: patient_review.php?admission_id="
                .
                $admissionId
            );

            exit();

        }
        catch (Throwable $e) {

            if (
                $conn->inTransaction()
            ) {
                $conn->rollBack();
            }

            $errorMessage =
                "Unable to save clinical review: "
                .
                $e->getMessage();
        }
    }
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

$successMessage =
    $_SESSION['review_success']
    ?? '';

unset(
    $_SESSION['review_success']
);


/* =========================================================
   DIAGNOSIS / REVIEW HISTORY

   We first show reviews directly linked to this admission.

   We also include the original appointment / walk-in
   diagnosis for this patient if it was recorded before
   or on the admission date.
========================================================= */

$historyStmt =
    $conn->prepare("

        SELECT

            D.DIAGNOSIS_ID,
            D.DIAGNOSIS_DETAILS,
            D.ALLERGIES,

            TO_CHAR(
                D.DATE_RECORDED,
                'DD-MON-RR'
            )
            AS DATE_DISPLAY,

            TO_CHAR(
                D.DATE_RECORDED,
                'HH24:MI'
            )
            AS TIME_DISPLAY,

            D.DATE_RECORDED,

            CASE

                WHEN
                    D.ADMISSION_ID = ?

                THEN
                    'Clinical Review'

                ELSE
                    'Initial Diagnosis'

            END
            AS RECORD_TYPE

        FROM
            SYARMIMI.DIAGNOSIS D

        WHERE
            D.PATIENT_ID = ?

        AND
        (
            D.ADMISSION_ID = ?

            OR

            (
                D.ADMISSION_ID IS NULL

                AND

                D.DATE_RECORDED
                <=
                (
                    SELECT
                        A.ADMISSION_DATE

                    FROM
                        SYARMIMI.ADMISSION A

                    WHERE
                        A.ADMISSION_ID = ?
                )
            )
        )

        ORDER BY
            D.DATE_RECORDED DESC,
            D.DIAGNOSIS_ID DESC

    ");

$historyStmt->execute([

    $admissionId,

    $patient['PATIENT_ID'],

    $admissionId,

    $admissionId

]);

$diagnosisHistory =
    $historyStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   LATEST DIAGNOSIS / REVIEW
========================================================= */

$latestDiagnosis =
    $diagnosisHistory[0]
    ?? null;


/* =========================================================
   CURRENT INPATIENT MEDICATION
========================================================= */

$medicationStmt =
    $conn->prepare("

        SELECT

            MO.MEDORDER_ID,

            M.MEDICATION_NAME,

            MO.DOSAGE,
            MO.FREQUENCY,

            TO_CHAR(
                MO.MED_START_DATE,
                'DD-MON-RR'
            )
            AS START_DATE_DISPLAY,

            TO_CHAR(
                MO.MED_END_DATE,
                'DD-MON-RR'
            )
            AS END_DATE_DISPLAY,

            (
                SELECT COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE MS

                WHERE
                    MS.MEDORDER_ID =
                    MO.MEDORDER_ID

                AND
                    UPPER(
                        TRIM(
                            NVL(
                                MS.STATUS,
                                'PENDING'
                            )
                        )
                    )
                    =
                    'ADMINISTERED'
            )
            AS ADMINISTERED_DOSES,

            (
                SELECT COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE MS

                WHERE
                    MS.MEDORDER_ID =
                    MO.MEDORDER_ID
            )
            AS TOTAL_SCHEDULED_DOSES

        FROM
            SYARMIMI.MEDICATION_ORDER MO

        JOIN
            SYARMIMI.MEDICATION M

            ON
                MO.MEDICATION_ID =
                M.MEDICATION_ID

        WHERE
            MO.ADMISSION_ID = ?

        ORDER BY
            M.MEDICATION_NAME

    ");

$medicationStmt->execute([
    $admissionId
]);

$currentMedications =
    $medicationStmt->fetchAll(
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
Patient Clinical Review
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

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:#f5f7fb;
    font-family:'Segoe UI',Arial,sans-serif;
    color:#1e293b;
}

.main-content{
    margin-left:260px;
    min-height:100vh;
    padding:22px 30px 50px;
}

.page-header{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:15px;
    margin-bottom:16px;
}

.page-title{
    margin:0;
    color:#0f172a;
    font-size:27px;
    font-weight:800;
}

.page-subtitle{
    color:#64748b;
    font-size:13px;
    margin-top:4px;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:6px;

    padding:8px 12px;

    border:1px solid #e2e8f0;
    border-radius:9px;

    background:#fff;
    color:#475569;

    text-decoration:none;
    font-size:12px;
    font-weight:650;
}

.back-btn:hover{
    background:#f8fafc;
    color:#0f172a;
}


/* =========================================================
   CARDS
========================================================= */

.card-box{
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:18px;
    margin-bottom:14px;
}

.patient-card{
    border-left:4px solid #10b981;
}

.section-title{
    display:flex;
    align-items:center;
    gap:9px;

    margin-bottom:14px;

    font-size:16px;
    font-weight:750;
    color:#0f172a;
}

.section-icon{
    width:32px;
    height:32px;

    display:flex;
    align-items:center;
    justify-content:center;

    flex:0 0 32px;

    border-radius:9px;

    background:#eff6ff;
    color:#2563eb;
}

.patient-name{
    color:#0f172a;
    font-size:21px;
    font-weight:800;
}

.patient-ic{
    margin-top:3px;
    color:#64748b;
    font-size:12px;
}


/* =========================================================
   INFO GRID
========================================================= */

.info-grid{
    display:grid;
    grid-template-columns:
        repeat(5,minmax(0,1fr));

    gap:9px;

    margin-top:15px;
}

.info-item{
    min-height:70px;

    padding:11px 12px;

    background:#f8fafc;

    border:1px solid #edf0f3;
    border-radius:9px;
}

.info-label{
    color:#94a3b8;

    font-size:10px;
    font-weight:700;

    text-transform:uppercase;
    letter-spacing:.3px;
}

.info-value{
    margin-top:5px;

    color:#334155;

    font-size:13px;
    font-weight:650;
}


/* =========================================================
   EARLY DISCHARGE INDICATOR
========================================================= */

.early-indicator{
    display:inline-flex;
    align-items:center;
    gap:5px;

    margin-top:12px;

    padding:6px 10px;

    border:1px solid #fed7aa;
    border-radius:20px;

    background:#fff7ed;

    color:#c2410c;

    font-size:11px;
    font-weight:700;
}


/* =========================================================
   LATEST DIAGNOSIS
========================================================= */

.latest-diagnosis{
    padding:15px;

    border:1px solid #bfdbfe;
    border-radius:10px;

    background:#eff6ff;
}

.latest-header{
    display:flex;
    justify-content:space-between;
    gap:10px;

    margin-bottom:7px;
}

.latest-type{
    color:#2563eb;
    font-size:11px;
    font-weight:750;
}

.latest-date{
    color:#64748b;
    font-size:11px;
}

.latest-details{
    color:#1e293b;
    font-size:14px;
    line-height:1.55;
}

.allergy-text{
    margin-top:9px;
    padding-top:9px;

    border-top:1px solid #dbeafe;

    color:#64748b;
    font-size:12px;
}


/* =========================================================
   MEDICATION
========================================================= */

.medication-table{
    margin:0;
}

.medication-table thead th{
    padding:9px 10px;

    background:#f8fafc;

    border-bottom:1px solid #e2e8f0;

    color:#64748b;

    font-size:10px;
    font-weight:750;

    text-transform:uppercase;

    white-space:nowrap;
}

.medication-table tbody td{
    padding:10px;

    border-bottom:1px solid #f1f5f9;

    vertical-align:middle;

    font-size:12px;
}

.medication-name{
    color:#0f172a;
    font-weight:700;
}

.dose-progress{
    display:inline-flex;

    padding:5px 8px;

    border-radius:20px;

    background:#ecfdf5;
    color:#047857;

    font-size:10px;
    font-weight:700;
}


/* =========================================================
   HISTORY
========================================================= */

.history-list{
    position:relative;
}

.history-item{
    position:relative;

    padding:
        0
        0
        17px
        28px;

    margin-left:7px;

    border-left:
        2px solid #e2e8f0;
}

.history-item:last-child{
    padding-bottom:0;
}

.history-dot{
    position:absolute;

    top:2px;
    left:-7px;

    width:12px;
    height:12px;

    border:3px solid #fff;
    border-radius:50%;

    background:#3b82f6;

    box-shadow:
        0 0 0 1px #bfdbfe;
}

.history-top{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;

    margin-bottom:5px;
}

.history-type{
    display:inline-flex;

    padding:4px 8px;

    border-radius:20px;

    background:#eff6ff;
    color:#2563eb;

    font-size:10px;
    font-weight:700;
}

.history-date{
    color:#94a3b8;
    font-size:10px;
}

.history-details{
    color:#334155;

    font-size:13px;
    line-height:1.5;
}

.history-allergy{
    margin-top:5px;

    color:#64748b;
    font-size:11px;
}


/* =========================================================
   FORM
========================================================= */

.form-label{
    margin-bottom:5px;

    color:#334155;

    font-size:13px;
    font-weight:650;
}

.form-control{
    border:1px solid #dbe2ea;
    border-radius:9px;

    font-size:13px;
}

.form-control:focus{
    border-color:#3b82f6;

    box-shadow:
        0 0 0 4px
        rgba(59,130,246,.10);
}

textarea.form-control{
    min-height:115px;
    resize:vertical;
}

.btn-save{
    min-height:42px;

    border:0;
    border-radius:9px;

    background:#2563eb;

    font-size:13px;
    font-weight:700;
}

.btn-save:hover{
    background:#1d4ed8;
}


/* =========================================================
   CARE ACTION
========================================================= */

.care-actions{
    display:flex;
    align-items:center;
    gap:8px;
    flex-wrap:wrap;
}

.care-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;

    min-height:38px;

    padding:8px 13px;

    border-radius:9px;

    text-decoration:none;

    font-size:12px;
    font-weight:650;
}

.btn-stay{
    border:1px solid #bfdbfe;

    background:#eff6ff;
    color:#2563eb;
}

.btn-stay:hover{
    background:#dbeafe;
    color:#1d4ed8;
}

.btn-discharge{
    border:1px solid #fecaca;

    background:#fef2f2;
    color:#dc2626;
}

.btn-discharge:hover{
    background:#fee2e2;
    color:#b91c1c;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-state{
    padding:16px;

    text-align:center;

    color:#94a3b8;
    font-size:12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .info-grid{
        grid-template-columns:
            repeat(3,minmax(0,1fr));
    }
}

@media(max-width:800px){

    .main-content{
        margin-left:260px;
        padding:18px;
    }

    .info-grid{
        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }
}

</style>

</head>


<body>

<?php
include("../includes/sidebar_doctor.php");
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<div>

<h1 class="page-title">

Patient Clinical Review

</h1>

<div class="page-subtitle">

Review the patient's condition, diagnosis history and current inpatient medication.

</div>

</div>


<a
    href="treatment.php"
    class="back-btn"
>

<i class="bi bi-arrow-left"></i>

Back to Treatment

</a>

</div>


<?php if ($errorMessage): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= h($errorMessage) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     PATIENT INFORMATION
===================================================== -->

<div class="card-box patient-card">

<div class="patient-name">

<?= h(
    $patient[
        'PATIENT_NAME'
    ]
) ?>

</div>


<div class="patient-ic">

IC Number:

<?= h(
    $patient[
        'IC_NUMBER'
    ]
    ?: '-'
) ?>

</div>


<div class="info-grid">


<div class="info-item">

<div class="info-label">
Admission Date
</div>

<div class="info-value">

<?= h(
    $patient[
        'ADMISSION_DATE_DISPLAY'
    ]
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Expected Discharge
</div>

<div class="info-value">

<?= h(
    $patient[
        'EXPECTED_DATE_DISPLAY'
    ]
    ?: 'Not Set'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Ward
</div>

<div class="info-value">

<?= h(
    $patient[
        'WARD_NAME'
    ]
    ?: '-'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Bed
</div>

<div class="info-value">

<?= h(
    $patient[
        'BED_NUMBER'
    ]
    ?: '-'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Planned Stay
</div>

<div class="info-value">

<?= max(
    1,
    (int)$patient[
        'STAY_DAYS'
    ]
) ?>

day(s)

</div>

</div>


</div>


<?php if (
    (int)$patient[
        'IS_EARLY_DISCHARGE'
    ]
    === 1
): ?>

<div class="early-indicator">

<i class="bi bi-info-circle"></i>

Discharging today would be considered an early discharge.

</div>

<?php endif; ?>


</div>


<!-- =====================================================
     LATEST DIAGNOSIS
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-clipboard2-pulse"></i>

</span>

Latest Diagnosis / Review

</div>


<?php if ($latestDiagnosis): ?>

<div class="latest-diagnosis">


<div class="latest-header">

<div class="latest-type">

<?= h(
    $latestDiagnosis[
        'RECORD_TYPE'
    ]
) ?>

</div>

<div class="latest-date">

<?= h(
    $latestDiagnosis[
        'DATE_DISPLAY'
    ]
) ?>

&nbsp;

<?= h(
    $latestDiagnosis[
        'TIME_DISPLAY'
    ]
) ?>

</div>

</div>


<div class="latest-details">

<?= nl2br(
    h(
        $latestDiagnosis[
            'DIAGNOSIS_DETAILS'
        ]
    )
) ?>

</div>


<div class="allergy-text">

<strong>
Allergies:
</strong>

<?= h(
    $latestDiagnosis[
        'ALLERGIES'
    ]
    ?: '-'
) ?>

</div>


</div>

<?php else: ?>

<div class="empty-state">

No diagnosis or clinical review found.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     CURRENT MEDICATION
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-capsule-pill"></i>

</span>

Current Inpatient Medication

</div>


<?php if ($currentMedications): ?>

<div class="table-responsive">

<table class="table medication-table">

<thead>

<tr>

<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Start</th>
<th>End</th>
<th>Administration</th>

</tr>

</thead>


<tbody>

<?php foreach (
    $currentMedications
    as
    $med
): ?>

<tr>

<td>

<span class="medication-name">

<?= h(
    $med[
        'MEDICATION_NAME'
    ]
) ?>

</span>

</td>


<td>

<?= h(
    $med[
        'DOSAGE'
    ]
    ?: '-'
) ?>

</td>


<td>

<?= h(
    $med[
        'FREQUENCY'
    ]
    ?: '-'
) ?>

</td>


<td>

<?= h(
    $med[
        'START_DATE_DISPLAY'
    ]
    ?: '-'
) ?>

</td>


<td>

<?= h(
    $med[
        'END_DATE_DISPLAY'
    ]
    ?: '-'
) ?>

</td>


<td>

<span class="dose-progress">

<?= (int)$med[
    'ADMINISTERED_DOSES'
] ?>

/

<?= (int)$med[
    'TOTAL_SCHEDULED_DOSES'
] ?>

dose(s)

</span>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="empty-state">

<i class="bi bi-capsule me-1"></i>

No inpatient medication recorded.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     ADD CLINICAL REVIEW
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-journal-medical"></i>

</span>

Add New Clinical Review

</div>


<form
    method="POST"
    id="reviewForm"
>


<input
    type="hidden"
    name="admission_id"
    value="<?= (int)$admissionId ?>"
>


<input
    type="hidden"
    name="save_review"
    value="1"
>


<div class="mb-3">

<label class="form-label">

Latest Condition / Diagnosis

</label>


<textarea
    name="review_details"
    id="review_details"
    class="form-control"
    placeholder="Example: Patient's pain has reduced. Mobility has improved and patient is responding well to current treatment."
    required
><?= h(
    $_POST[
        'review_details'
    ]
    ?? ''
) ?></textarea>

</div>


<div class="mb-3">

<label class="form-label">

Allergies

</label>


<input
    type="text"
    name="allergies"
    class="form-control"
    placeholder="Example: No known allergies / Penicillin"
    value="<?= h(
        $_POST[
            'allergies'
        ]
        ??
        (
            $latestDiagnosis[
                'ALLERGIES'
            ]
            ??
            ''
        )
    ) ?>"
>

</div>


<button
    type="submit"
    class="btn btn-primary btn-save"
>

<i class="bi bi-check-circle me-1"></i>

Save Clinical Review

</button>


</form>

</div>


<!-- =====================================================
     HISTORY
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-clock-history"></i>

</span>

Diagnosis & Review History

</div>


<?php if ($diagnosisHistory): ?>

<div class="history-list">


<?php foreach (
    $diagnosisHistory
    as
    $history
): ?>

<div class="history-item">


<div class="history-dot"></div>


<div class="history-top">


<span class="history-type">

<?= h(
    $history[
        'RECORD_TYPE'
    ]
) ?>

</span>


<span class="history-date">

<?= h(
    $history[
        'DATE_DISPLAY'
    ]
) ?>

&nbsp;

<?= h(
    $history[
        'TIME_DISPLAY'
    ]
) ?>

</span>


</div>


<div class="history-details">

<?= nl2br(
    h(
        $history[
            'DIAGNOSIS_DETAILS'
        ]
    )
) ?>

</div>


<div class="history-allergy">

<strong>
Allergies:
</strong>

<?= h(
    $history[
        'ALLERGIES'
    ]
    ?: '-'
) ?>

</div>


</div>

<?php endforeach; ?>


</div>

<?php else: ?>

<div class="empty-state">

No diagnosis history available.

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     PATIENT CARE DECISION
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-signpost-split"></i>

</span>

Patient Care Decision

</div>


<div class="care-actions">


<a
    href="extend_admission.php?admission_id=<?= (int)$admissionId ?>"
    class="care-btn btn-stay"
>

<i class="bi bi-calendar-plus"></i>

<?php if (
    empty(
        $patient[
            'EXPECTED_DATE_VALUE'
        ]
    )
): ?>

Set Expected Date

<?php else: ?>

Extend Stay

<?php endif; ?>

</a>


<a
    href="discharge_patient.php?admission_id=<?= (int)$admissionId ?>"
    class="care-btn btn-discharge"
>

<i class="bi bi-box-arrow-right"></i>

<?php if (
    (int)$patient[
        'IS_EARLY_DISCHARGE'
    ]
    === 1
): ?>

Discharge Early

<?php else: ?>

Discharge Patient

<?php endif; ?>

</a>


</div>

</div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


<script>

/* =========================================================
   SUCCESS POPUP
========================================================= */

<?php if (
    $successMessage !== ''
): ?>

Swal.fire({

    icon:
        'success',

    title:
        'Clinical Review Saved',

    text:
        <?= json_encode(
            $successMessage
        ) ?>,

    confirmButtonText:
        'OK',

    confirmButtonColor:
        '#2563eb'

});

<?php endif; ?>


/* =========================================================
   SAVE CONFIRMATION
========================================================= */

const reviewForm =
    document.getElementById(
        'reviewForm'
    );


if (reviewForm) {

    reviewForm.addEventListener(
        'submit',
        function(event)
        {

            event.preventDefault();


            const review =
                document.getElementById(
                    'review_details'
                ).value.trim();


            if (!review) {

                Swal.fire({

                    icon:
                        'warning',

                    title:
                        'Clinical Review Required',

                    text:
                        'Please enter the latest patient condition before saving.',

                    confirmButtonColor:
                        '#2563eb'

                });

                return;
            }


            Swal.fire({

                icon:
                    'question',

                title:
                    'Save Clinical Review?',

                text:
                    'This review will be added to the patient diagnosis history.',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Save Review',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#2563eb',

                reverseButtons:
                    true

            }).then(
                function(result)
                {

                    if (
                        result.isConfirmed
                    ) {

                        Swal.fire({

                            title:
                                'Saving Review',

                            text:
                                'Please wait...',

                            allowOutsideClick:
                                false,

                            allowEscapeKey:
                                false,

                            didOpen:
                                function()
                                {
                                    Swal.showLoading();
                                }

                        });


                        reviewForm.submit();
                    }
                }
            );

        }
    );
}

</script>


</body>

</html>