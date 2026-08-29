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
   HELPERS
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function isValidDateInput($date)
{
    $date =
        trim(
            (string)$date
        );

    if (
        !preg_match(
            '/^\d{4}-\d{2}-\d{2}$/',
            $date
        )
    ) {
        return false;
    }

    $dateObject =
        DateTime::createFromFormat(
            '!Y-m-d',
            $date
        );

    if (!$dateObject) {
        return false;
    }

    return (
        $dateObject->format('Y-m-d')
        ===
        $date
    );
}


function getDayOffsetFromToday($dateString)
{
    if (
        !isValidDateInput(
            $dateString
        )
    ) {
        throw new Exception(
            "Invalid date selected."
        );
    }

    $today =
        DateTime::createFromFormat(
            '!Y-m-d',
            date('Y-m-d')
        );

    $target =
        DateTime::createFromFormat(
            '!Y-m-d',
            $dateString
        );

    $seconds =
        $target->getTimestamp()
        -
        $today->getTimestamp();

    return (int)round(
        $seconds / 86400
    );
}


/* =========================================================
   MEDICATION FREQUENCY
========================================================= */

function getMedicationTimes($frequency)
{
    $frequency =
        strtoupper(
            trim(
                (string)$frequency
            )
        );


    if (
        $frequency === 'ONCE DAILY' ||
        preg_match('/\bOD\b/', $frequency)
    ) {
        return ['08:00'];
    }


    if (
        $frequency === 'TWICE DAILY' ||
        preg_match('/\bBD\b/', $frequency) ||
        preg_match('/\bBID\b/', $frequency)
    ) {
        return [
            '08:00',
            '20:00'
        ];
    }


    if (
        $frequency === 'THREE TIMES DAILY' ||
        preg_match('/\bTDS\b/', $frequency) ||
        preg_match('/\bTID\b/', $frequency)
    ) {
        return [
            '08:00',
            '14:00',
            '20:00'
        ];
    }


    if (
        $frequency === 'FOUR TIMES DAILY' ||
        preg_match('/\bQID\b/', $frequency)
    ) {
        return [
            '06:00',
            '12:00',
            '18:00',
            '23:59'
        ];
    }


    if (
        $frequency === 'EVERY 6 HOURS'
    ) {
        return [
            '06:00',
            '12:00',
            '18:00',
            '23:59'
        ];
    }


    if (
        $frequency === 'EVERY 8 HOURS'
    ) {
        return [
            '06:00',
            '14:00',
            '22:00'
        ];
    }


    if (
        $frequency === 'EVERY 12 HOURS'
    ) {
        return [
            '08:00',
            '20:00'
        ];
    }


    if (
        $frequency === 'AS NEEDED' ||
        preg_match('/\bPRN\b/', $frequency)
    ) {
        return [];
    }


    /*
       Legacy / free-text frequency.
       Default to one morning dose.
    */

    return ['08:00'];
}


/* =========================================================
   GET SAFE NEXT SCHEDULE ID

   We intentionally use MAX + 1 because historical
   SCHEDULE_ID values may already be higher than
   MEDICATION_SCHEDULE_SEQ.
========================================================= */

function getNextScheduleId(PDO $conn)
{
    return
        (int)$conn
            ->query("

                SELECT

                    NVL(
                        MAX(SCHEDULE_ID),
                        0
                    )
                    +
                    1

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE

            ")
            ->fetchColumn();
}


/* =========================================================
   GENERATE MISSING MEDICATION SCHEDULES
========================================================= */

function generateSchedulesBetween(
    PDO $conn,
    int $medOrderId,
    string $frequency,
    DateTime $startDate,
    DateTime $endDate
) {

    $times =
        getMedicationTimes(
            $frequency
        );


    if (
        empty($times)
    ) {
        return 0;
    }


    if (
        $startDate > $endDate
    ) {
        return 0;
    }


    $createdCount =
        0;


    $currentDate =
        clone $startDate;


    while (
        $currentDate <= $endDate
    ) {

        $dateText =
            $currentDate->format(
                'Y-m-d'
            );


        $dayOffset =
            getDayOffsetFromToday(
                $dateText
            );


        $dayOffsetSql =
            (int)$dayOffset;


        foreach (
            $times
            as
            $time
        ) {

            /* =============================================
               CHECK DUPLICATE
            ============================================= */

            $checkStmt =
                $conn->prepare("

                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE

                    WHERE
                        MEDORDER_ID = ?

                    AND
                        TRUNC(
                            SCHEDULE_DATE
                        )
                        =
                        TRUNC(
                            SYSDATE
                        )
                        +
                        $dayOffsetSql

                    AND
                        TRIM(
                            SCHEDULE_TIME
                        )
                        =
                        TRIM(?)

                ");


            $checkStmt->execute([
                $medOrderId,
                $time
            ]);


            if (
                (int)$checkStmt
                    ->fetchColumn()
                >
                0
            ) {
                continue;
            }


            /* =============================================
               NEW SAFE ID
            ============================================= */

            $newScheduleId =
                getNextScheduleId(
                    $conn
                );


            /* =============================================
               INSERT SCHEDULE
            ============================================= */

            $insertStmt =
                $conn->prepare("

                    INSERT INTO
                        SYARMIMI.MEDICATION_SCHEDULE
                    (
                        SCHEDULE_ID,
                        MEDORDER_ID,
                        SCHEDULE_DATE,
                        SCHEDULE_TIME,
                        STATUS
                    )

                    VALUES
                    (
                        ?,
                        ?,

                        TRUNC(
                            SYSDATE
                        )
                        +
                        $dayOffsetSql,

                        ?,

                        'Pending Preparation'
                    )

                ");


            $insertStmt->execute([
                $newScheduleId,
                $medOrderId,
                $time
            ]);


            $createdCount++;
        }


        $currentDate->modify(
            '+1 day'
        );
    }


    return $createdCount;
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


if (
    $admissionId <= 0
) {
    die(
        "Invalid admission record."
    );
}


/* =========================================================
   VARIABLES
========================================================= */

$errorMessage =
    '';


/* =========================================================
   GET ACTIVE ADMISSION
========================================================= */

$admissionStmt =
    $conn->prepare("

        SELECT

            A.ADMISSION_ID,
            A.PATIENT_ID,
            A.BED_ID,
            A.ADMISSION_DATE,
            A.EXPECTED_DISCHARGE_DATE,

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

            P.NAME
            AS PATIENT_NAME,

            P.IC_NUMBER,

            B.BED_NUMBER,

            W.WARD_NAME

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

        WHERE
            A.ADMISSION_ID = ?

        AND
            A.ACCOUNT_ID = ?

        AND
            A.DISCHARGE_DATE
            IS NULL

    ");


$admissionStmt->execute([
    $admissionId,
    $doctorId
]);


$admission =
    $admissionStmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$admission) {

    die(
        "Active admission not found or you do not have permission to manage this patient."
    );
}


/* =========================================================
   CURRENT EXPECTED DATE
========================================================= */

$currentExpectedValue =
    trim(
        $admission[
            'EXPECTED_DATE_VALUE'
        ]
        ?? ''
    );


$currentExpectedObject =
    null;


if (
    $currentExpectedValue !== ''
) {

    $currentExpectedObject =
        DateTime::createFromFormat(
            '!Y-m-d',
            $currentExpectedValue
        );
}


$isInitialSetup =
    !$currentExpectedObject;


/* =========================================================
   MEDICATION LIST
========================================================= */

$medicationStmt =
    $conn->prepare("

        SELECT

            MO.MEDORDER_ID,
            MO.MEDICATION_ID,
            MO.DOSAGE,
            MO.FREQUENCY,

            TO_CHAR(
                MO.MED_START_DATE,
                'DD-MON-RR'
            )
            AS MED_START_DISPLAY,

            TO_CHAR(
                MO.MED_END_DATE,
                'DD-MON-RR'
            )
            AS MED_END_DISPLAY,

            M.MEDICATION_NAME

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
            MO.MEDORDER_ID

    ");


$medicationStmt->execute([
    $admissionId
]);


$medicationOrders =
    $medicationStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   UPDATE STAY
========================================================= */

if (
    $_SERVER['REQUEST_METHOD']
    ===
    'POST'

    &&
    isset(
        $_POST['update_stay']
    )
) {

    $newExpectedDate =
        trim(
            $_POST[
                'new_expected_discharge_date'
            ]
            ?? ''
        );


    if (
        !isValidDateInput(
            $newExpectedDate
        )
    ) {

        $errorMessage =
            "Please select a valid expected discharge date.";

    }
    else {

        $newExpectedObject =
            DateTime::createFromFormat(
                '!Y-m-d',
                $newExpectedDate
            );


        $todayObject =
            DateTime::createFromFormat(
                '!Y-m-d',
                date('Y-m-d')
            );


        if (
            $newExpectedObject
            <
            $todayObject
        ) {

            $errorMessage =
                "Expected discharge date cannot be before today.";

        }
        elseif (
            !$isInitialSetup
            &&
            $newExpectedObject
            <=
            $currentExpectedObject
        ) {

            $errorMessage =
                "The new expected discharge date must be later than the current expected discharge date.";

        }
        else {

            try {

                $conn->beginTransaction();


                /* =================================================
                   LOCK ADMISSION
                ================================================= */

                $lockStmt =
                    $conn->prepare("

                        SELECT

                            ADMISSION_ID,

                            TO_CHAR(
                                EXPECTED_DISCHARGE_DATE,
                                'YYYY-MM-DD'
                            )
                            AS CURRENT_EXPECTED_DATE

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


                $lockedAdmission =
                    $lockStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$lockedAdmission) {

                    throw new Exception(
                        "This admission is no longer active."
                    );
                }


                $lockedExpectedValue =
                    trim(
                        $lockedAdmission[
                            'CURRENT_EXPECTED_DATE'
                        ]
                        ?? ''
                    );


                $lockedExpectedObject =
                    null;


                if (
                    $lockedExpectedValue !== ''
                ) {

                    $lockedExpectedObject =
                        DateTime::createFromFormat(
                            '!Y-m-d',
                            $lockedExpectedValue
                        );
                }


                if (
                    $lockedExpectedObject
                    &&
                    $newExpectedObject
                    <=
                    $lockedExpectedObject
                ) {

                    throw new Exception(
                        "The new expected discharge date must be later than the patient's current expected discharge date."
                    );
                }


                /* =================================================
                   NEW EXPECTED DATE
                ================================================= */

                $newDayOffset =
                    getDayOffsetFromToday(
                        $newExpectedDate
                    );


                $newDayOffsetSql =
                    (int)$newDayOffset;


                /* =================================================
                   UPDATE ADMISSION
                ================================================= */

                $updateAdmission =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.ADMISSION

                        SET

                            EXPECTED_DISCHARGE_DATE =
                                TRUNC(
                                    SYSDATE
                                )
                                +
                                $newDayOffsetSql

                        WHERE
                            ADMISSION_ID = ?

                        AND
                            ACCOUNT_ID = ?

                        AND
                            DISCHARGE_DATE
                            IS NULL

                    ");


                $updateAdmission->execute([
                    $admissionId,
                    $doctorId
                ]);


                $medicationCount =
                    0;


                $scheduleCount =
                    0;


                /* =================================================
                   INITIAL SETUP / HISTORICAL ADMISSION
                ================================================= */

                if (
                    !$lockedExpectedObject
                ) {

                    $legacyMedicationStmt =
                        $conn->prepare("

                            SELECT

                                MEDORDER_ID,
                                FREQUENCY

                            FROM
                                SYARMIMI.MEDICATION_ORDER

                            WHERE
                                ADMISSION_ID = ?

                            AND
                                MED_END_DATE
                                IS NULL

                            FOR UPDATE

                        ");


                    $legacyMedicationStmt->execute([
                        $admissionId
                    ]);


                    $orders =
                        $legacyMedicationStmt
                            ->fetchAll(
                                PDO::FETCH_ASSOC
                            );


                    foreach (
                        $orders
                        as
                        $order
                    ) {

                        $medOrderId =
                            (int)$order[
                                'MEDORDER_ID'
                            ];


                        $frequency =
                            trim(
                                $order[
                                    'FREQUENCY'
                                ]
                                ?? ''
                            );


                        $updateMedication =
                            $conn->prepare("

                                UPDATE
                                    SYARMIMI.MEDICATION_ORDER

                                SET

                                    MED_START_DATE =
                                        NVL(
                                            MED_START_DATE,
                                            TRUNC(SYSDATE)
                                        ),

                                    MED_END_DATE =
                                        TRUNC(SYSDATE)
                                        +
                                        $newDayOffsetSql

                                WHERE
                                    MEDORDER_ID = ?

                                AND
                                    ADMISSION_ID = ?

                            ");


                        $updateMedication->execute([
                            $medOrderId,
                            $admissionId
                        ]);


                        $medicationCount++;


                        $scheduleStart =
                            clone $todayObject;


                        $scheduleCount +=
                            generateSchedulesBetween(
                                $conn,
                                $medOrderId,
                                $frequency,
                                $scheduleStart,
                                $newExpectedObject
                            );
                    }

                }


                /* =================================================
                   NORMAL EXTENSION
                ================================================= */

                else {

                    $oldDayOffset =
                        getDayOffsetFromToday(
                            $lockedExpectedValue
                        );


                    $oldDayOffsetSql =
                        (int)$oldDayOffset;


                    $extendMedicationStmt =
                        $conn->prepare("

                            SELECT

                                MEDORDER_ID,
                                FREQUENCY

                            FROM
                                SYARMIMI.MEDICATION_ORDER

                            WHERE
                                ADMISSION_ID = ?

                            AND
                                MED_END_DATE
                                IS NOT NULL

                            AND
                                TRUNC(
                                    MED_END_DATE
                                )
                                =
                                TRUNC(
                                    SYSDATE
                                )
                                +
                                $oldDayOffsetSql

                            FOR UPDATE

                        ");


                    $extendMedicationStmt->execute([
                        $admissionId
                    ]);


                    $orders =
                        $extendMedicationStmt
                            ->fetchAll(
                                PDO::FETCH_ASSOC
                            );


                    foreach (
                        $orders
                        as
                        $order
                    ) {

                        $medOrderId =
                            (int)$order[
                                'MEDORDER_ID'
                            ];


                        $frequency =
                            trim(
                                $order[
                                    'FREQUENCY'
                                ]
                                ?? ''
                            );


                        $updateMedication =
                            $conn->prepare("

                                UPDATE
                                    SYARMIMI.MEDICATION_ORDER

                                SET

                                    MED_END_DATE =
                                        TRUNC(SYSDATE)
                                        +
                                        $newDayOffsetSql

                                WHERE
                                    MEDORDER_ID = ?

                                AND
                                    ADMISSION_ID = ?

                            ");


                        $updateMedication->execute([
                            $medOrderId,
                            $admissionId
                        ]);


                        $medicationCount++;


                        $scheduleStart =
                            clone $lockedExpectedObject;


                        $scheduleStart->modify(
                            '+1 day'
                        );


                        $scheduleCount +=
                            generateSchedulesBetween(
                                $conn,
                                $medOrderId,
                                $frequency,
                                $scheduleStart,
                                $newExpectedObject
                            );
                    }
                }


                /* =================================================
                   VERIFY
                ================================================= */

                $verifyStmt =
                    $conn->prepare("

                        SELECT

                            TO_CHAR(
                                EXPECTED_DISCHARGE_DATE,
                                'YYYY-MM-DD'
                            )

                        FROM
                            SYARMIMI.ADMISSION

                        WHERE
                            ADMISSION_ID = ?

                    ");


                $verifyStmt->execute([
                    $admissionId
                ]);


                $verifiedDate =
                    trim(
                        (string)$verifyStmt
                            ->fetchColumn()
                    );


                if (
                    $verifiedDate
                    !==
                    $newExpectedDate
                ) {

                    throw new Exception(
                        "Unable to verify updated expected discharge date."
                    );
                }


                /* =================================================
                   COMMIT
                ================================================= */

                $conn->commit();


                $_SESSION[
                    'success_title'
                ] =
                    $lockedExpectedObject

                    ?

                    'Patient Stay Extended'

                    :

                    'Expected Discharge Date Set';


                if (
                    $lockedExpectedObject
                ) {

                    $_SESSION[
                        'success_message'
                    ] =

                        "Expected discharge date has been extended to "
                        .
                        strtoupper(
                            $newExpectedObject
                                ->format(
                                    'd-M-y'
                                )
                        )
                        .
                        ". "
                        .
                        $medicationCount
                        .
                        " medication order(s) were extended and "
                        .
                        $scheduleCount
                        .
                        " additional medication schedule(s) were generated.";

                }
                else {

                    $_SESSION[
                        'success_message'
                    ] =

                        "Expected discharge date has been set to "
                        .
                        strtoupper(
                            $newExpectedObject
                                ->format(
                                    'd-M-y'
                                )
                        )
                        .
                        ". "
                        .
                        $medicationCount
                        .
                        " medication order(s) were initialized and "
                        .
                        $scheduleCount
                        .
                        " medication schedule(s) were generated.";
                }


                header(
                    "Location: treatment.php"
                );


                exit;

            }
            catch (
                Throwable $e
            ) {

                if (
                    $conn->inTransaction()
                ) {
                    $conn->rollBack();
                }


                $errorMessage =
                    "Unable to update patient stay: "
                    .
                    $e->getMessage();
            }
        }
    }
}


/* =========================================================
   MINIMUM DATE
========================================================= */

$todayObject =
    DateTime::createFromFormat(
        '!Y-m-d',
        date('Y-m-d')
    );


if (
    $currentExpectedObject
) {

    $minimumObject =
        clone $currentExpectedObject;


    $minimumObject->modify(
        '+1 day'
    );


    if (
        $minimumObject < $todayObject
    ) {

        $minimumObject =
            clone $todayObject;
    }


    $minimumDate =
        $minimumObject->format(
            'Y-m-d'
        );

}
else {

    $minimumDate =
        date('Y-m-d');
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
<?= $isInitialSetup
    ? 'Set Expected Discharge Date'
    : 'Extend Patient Stay'
?>
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
    color:#1e293b;
    font-family:'Segoe UI',Arial,sans-serif;
}

.main-content{
    margin-left:260px;
    min-height:100vh;
    padding:18px 28px 45px;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:8px 12px;
    margin-bottom:18px;
    background:#fff;
    border:1px solid #dbe2ea;
    border-radius:9px;
    color:#475569;
    text-decoration:none;
    font-size:12px;
    font-weight:600;
}

.back-btn:hover{
    background:#f8fafc;
    color:#1e293b;
}

.page-header{
    margin-bottom:20px;
}

.page-title{
    margin:0;
    font-size:27px;
    font-weight:800;
    color:#0f172a;
}

.page-subtitle{
    margin-top:5px;
    color:#94a3b8;
    font-size:13px;
}

.card-box{
    background:#fff;
    border:1px solid #e5e9ef;
    border-radius:14px;
    padding:22px;
    margin-bottom:18px;
}

.patient-card{
    border-left:4px solid #2563eb;
}

.patient-name{
    font-size:20px;
    font-weight:750;
    color:#0f172a;
}

.patient-meta{
    margin-top:5px;
    color:#64748b;
    font-size:12px;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:12px;
    margin-top:20px;
}

.info-item{
    background:#f8fafc;
    border:1px solid #e8edf2;
    border-radius:9px;
    padding:13px 14px;
}

.info-label{
    margin-bottom:4px;
    color:#94a3b8;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
}

.info-value{
    color:#334155;
    font-size:13px;
    font-weight:650;
}

.section-title{
    display:flex;
    align-items:center;
    gap:9px;
    margin-bottom:17px;
    font-size:15px;
    font-weight:750;
    color:#1e293b;
}

.section-icon{
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:9px;
    background:#eff6ff;
    color:#2563eb;
}

.table{
    margin-bottom:0;
}

.table thead th{
    padding:10px 12px;
    background:#f8fafc;
    border-bottom:1px solid #e5e7eb;
    color:#64748b;
    font-size:10px;
    text-transform:uppercase;
}

.table tbody td{
    padding:12px;
    border-color:#edf0f3;
    color:#475569;
    font-size:12px;
}

.notice{
    display:flex;
    gap:10px;
    padding:13px 14px;
    margin-bottom:20px;
    background:#eff6ff;
    border:1px solid #bfdbfe;
    border-radius:9px;
    color:#1e40af;
    font-size:12px;
    line-height:1.55;
}

.legacy-notice{
    background:#fff7ed;
    border-color:#fed7aa;
    color:#9a3412;
}

.form-label{
    color:#475569;
    font-size:12px;
    font-weight:650;
}

.form-control{
    height:45px;
    border:1px solid #dbe2ea;
    border-radius:9px;
    font-size:13px;
}

.btn-confirm{
    height:45px;
    border:0;
    border-radius:9px;
    background:#2563eb;
    color:#fff;
    font-size:13px;
    font-weight:650;
}

.btn-confirm:hover{
    background:#1d4ed8;
    color:#fff;
}

@media(max-width:991px){

    .main-content{
        margin-left:260px;
        padding:18px;
    }

    .info-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

</style>

</head>


<body>

<?php
include(
    "../includes/sidebar_doctor.php"
);
?>


<div class="main-content">


<a
    href="treatment.php"
    class="back-btn"
>

<i class="bi bi-arrow-left"></i>

Back to Treatment

</a>


<div class="page-header">

<h1 class="page-title">

<?= $isInitialSetup
    ? 'Set Expected Discharge Date'
    : 'Extend Patient Stay'
?>

</h1>

<div class="page-subtitle">

<?= $isInitialSetup
    ?
    'Set the expected discharge date for this admitted patient.'
    :
    'Extend the expected discharge date when additional hospital care is required.'
?>

</div>

</div>


<?php if (
    $errorMessage !== ''
): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-triangle-fill me-2"></i>

<?= h(
    $errorMessage
) ?>

</div>

<?php endif; ?>


<div class="card-box patient-card">

<div class="patient-name">

<?= h(
    $admission[
        'PATIENT_NAME'
    ]
) ?>

</div>

<div class="patient-meta">

<?= h(
    $admission[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

&nbsp;•&nbsp;

Bed
<?= h(
    $admission[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>

<?php if (
    !empty(
        $admission[
            'IC_NUMBER'
        ]
    )
): ?>

&nbsp;•&nbsp;

IC:
<?= h(
    $admission[
        'IC_NUMBER'
    ]
) ?>

<?php endif; ?>

</div>


<div class="info-grid">

<div class="info-item">

<div class="info-label">
Admission Date
</div>

<div class="info-value">

<?= h(
    $admission[
        'ADMISSION_DATE_DISPLAY'
    ]
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Current Expected Discharge
</div>

<div class="info-value">

<?= h(
    $admission[
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
    $admission[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">
Bed
</div>

<div class="info-value">

<?= h(
    $admission[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>

</div>

</div>

</div>

</div>


<div class="card-box">

<div class="section-title">

<span class="section-icon">
<i class="bi bi-capsule"></i>
</span>

Current Medication

</div>


<?php if (
    $medicationOrders
): ?>

<div class="table-responsive">

<table class="table">

<thead>

<tr>

<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Start</th>
<th>End</th>

</tr>

</thead>

<tbody>


<?php foreach (
    $medicationOrders
    as
    $med
): ?>

<tr>

<td>

<strong>

<?= h(
    $med[
        'MEDICATION_NAME'
    ]
) ?>

</strong>

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
        'MED_START_DISPLAY'
    ]
    ?: '-'
) ?>

</td>

<td>

<?= h(
    $med[
        'MED_END_DISPLAY'
    ]
    ?: '-'
) ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<?php else: ?>

<div class="text-muted small">

No inpatient medication orders found.

</div>

<?php endif; ?>

</div>


<div class="card-box">

<div class="section-title">

<span class="section-icon">

<i class="bi bi-calendar-plus"></i>

</span>

<?= $isInitialSetup
    ? 'Expected Discharge'
    : 'Extend Admission'
?>

</div>


<?php if (
    $isInitialSetup
): ?>

<div class="notice legacy-notice">

<i class="bi bi-exclamation-circle-fill"></i>

<div>

This admission does not currently have an expected discharge date.

When the date is set, medication orders without an end date will be initialized from today and their medication schedules will be generated until the selected date.

</div>

</div>

<?php else: ?>

<div class="notice">

<i class="bi bi-info-circle-fill"></i>

<div>

Extending the expected discharge date will only extend medications that currently end on the original expected discharge date.

Existing pharmacy and nurse medication history will remain unchanged.

</div>

</div>

<?php endif; ?>


<form
    method="POST"
    id="stayForm"
>

<input
    type="hidden"
    name="admission_id"
    value="<?= (int)$admissionId ?>"
>

<input
    type="hidden"
    name="update_stay"
    value="1"
>


<div class="row g-3">

<div class="col-md-7">

<label
    for="new_expected_discharge_date"
    class="form-label"
>

<?= $isInitialSetup
    ?
    'Expected Discharge Date'
    :
    'New Expected Discharge Date'
?>

</label>


<input
    type="date"
    id="new_expected_discharge_date"
    name="new_expected_discharge_date"
    class="form-control"
    min="<?= h(
        $minimumDate
    ) ?>"
    required
>

</div>


<div class="col-md-5 d-flex align-items-end">

<button
    type="submit"
    class="btn btn-confirm w-100"
>

<i class="bi bi-calendar-check me-1"></i>

<?= $isInitialSetup
    ?
    'Confirm Expected Discharge'
    :
    'Confirm Extension'
?>

</button>

</div>

</div>

</form>

</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>

const stayForm =
    document.getElementById(
        'stayForm'
    );

const dateInput =
    document.getElementById(
        'new_expected_discharge_date'
    );

const isInitialSetup =
    <?= $isInitialSetup ? 'true' : 'false' ?>;

const currentExpected =
    <?= json_encode(
        $admission[
            'EXPECTED_DATE_DISPLAY'
        ]
        ?: 'Not Set'
    ) ?>;


if (stayForm) {

    stayForm.addEventListener(
        'submit',
        function(event)
        {

            if (!dateInput.value) {
                return;
            }

            event.preventDefault();


            const selectedDate =
                new Date(
                    dateInput.value
                    +
                    'T00:00:00'
                );


            const displayDate =
                selectedDate
                    .toLocaleDateString(
                        'en-GB',
                        {
                            day:'2-digit',
                            month:'short',
                            year:'2-digit'
                        }
                    )
                    .toUpperCase();


            Swal.fire({

                icon:'question',

                title:
                    isInitialSetup
                    ?
                    'Set Expected Discharge Date?'
                    :
                    'Extend Patient Stay?',

                html:
                    isInitialSetup

                    ?

                    `
                    <div style="font-size:14px;line-height:1.8">
                        Expected Discharge:
                        <strong>${displayDate}</strong>
                    </div>
                    `

                    :

                    `
                    <div style="font-size:14px;line-height:1.8">
                        Current:
                        <strong>${currentExpected}</strong>
                        <br>
                        New:
                        <strong>${displayDate}</strong>
                    </div>
                    `,

                showCancelButton:true,

                confirmButtonText:
                    isInitialSetup
                    ?
                    'Yes, Set Date'
                    :
                    'Yes, Extend Stay',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#2563eb',

                reverseButtons:true

            }).then(
                function(result)
                {

                    if (
                        result.isConfirmed
                    ) {

                        Swal.fire({

                            title:'Updating Patient Stay',

                            text:'Please wait...',

                            allowOutsideClick:false,

                            allowEscapeKey:false,

                            didOpen:() => {

                                Swal.showLoading();

                            }

                        });


                        stayForm.submit();
                    }
                }
            );
        }
    );
}

</script>


</body>

</html>