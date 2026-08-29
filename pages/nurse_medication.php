<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


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


$staff_id =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

if ($staff_id <= 0) {
    die("Invalid nurse account.");
}


/* =========================================================
   SAFE HTML
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
   REDIRECT HELPER
========================================================= */

function redirectMedication($params = [])
{
    $url =
        "nurse_medication.php";

    if (!empty($params)) {

        $url .=
            "?"
            .
            http_build_query(
                $params
            );
    }

    header(
        "Location: " . $url
    );

    exit();
}


/* =========================================================
   GIVE SCHEDULED MEDICATION DOSE

   FLOW:

   Medication Schedule
        ↓
   Pharmacy Preparation
        ↓
   Ready For Nurse Pickup
        ↓
   Nurse Collects
        ↓
   Collected / Collected By Nurse
        ↓
   Nurse Give Dose
        ↓
   Medication Admin
        ↓
   Administered

   IMPORTANT:

   - Today prepared dose = allowed
   - Previous prepared dose = overdue but allowed
   - Future dose = NOT allowed
   - Discharged patient = NOT allowed
   - Cancelled schedule = NOT allowed
========================================================= */

if (
    isset(
        $_POST['give_schedule']
    )
) {

    $schedule_id =
        (int)(
            $_POST['schedule_id']
            ?? 0
        );


    if ($schedule_id <= 0) {

        redirectMedication([
            'error' => 'invalid'
        ]);
    }


    try {

        $conn->beginTransaction();


        /* =================================================
           GET / LOCK SCHEDULE
        ================================================= */

        $scheduleStmt =
            $conn->prepare("

                SELECT

                    MS.SCHEDULE_ID,
                    MS.MEDORDER_ID,
                    MS.SCHEDULE_DATE,
                    MS.SCHEDULE_TIME,
                    MS.STATUS,

                    MO.ADMISSION_ID,
                    MO.MED_START_DATE,
                    MO.MED_END_DATE,

                    A.DISCHARGE_DATE,
                    A.EXPECTED_DISCHARGE_DATE

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE MS

                JOIN
                    SYARMIMI.MEDICATION_ORDER MO

                    ON
                        MS.MEDORDER_ID =
                        MO.MEDORDER_ID

                JOIN
                    SYARMIMI.ADMISSION A

                    ON
                        MO.ADMISSION_ID =
                        A.ADMISSION_ID

                WHERE
                    MS.SCHEDULE_ID = ?

                FOR UPDATE

            ");


        $scheduleStmt->execute([
            $schedule_id
        ]);


        $schedule =
            $scheduleStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$schedule) {

            throw new Exception(
                "Medication schedule not found."
            );
        }


        /* =================================================
           PATIENT MUST STILL BE ADMITTED
        ================================================= */

        if (
            !empty(
                $schedule[
                    'DISCHARGE_DATE'
                ]
            )
        ) {

            throw new Exception(
                "This patient has already been discharged."
            );
        }


        /* =================================================
           DATE VALIDATION

           Previous date = overdue, allowed
           Today = allowed
           Future = blocked
        ================================================= */

        $dateCheck =
            $conn->prepare("

                SELECT COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE

                WHERE
                    SCHEDULE_ID = ?

                AND
                    TRUNC(
                        SCHEDULE_DATE
                    )
                    <=
                    TRUNC(
                        SYSDATE
                    )

            ");


        $dateCheck->execute([
            $schedule_id
        ]);


        if (
            (int)$dateCheck
                ->fetchColumn()
            === 0
        ) {

            throw new Exception(
                "Future medication doses cannot be administered yet."
            );
        }


        /* =================================================
           VERIFY PHARMACY PREPARATION

           IMPORTANT FIX:
           COLLECTED is valid for inpatient nurse workflow.
        ================================================= */

        $prepStmt =
            $conn->prepare("

                SELECT

                    PREP_ID,
                    STATUS

                FROM
                    SYARMIMI.PHARMACY_PREPARATION

                WHERE
                    SCHEDULE_ID = ?

                ORDER BY
                    PREP_ID DESC

                FETCH FIRST
                    1 ROW ONLY

            ");


        $prepStmt->execute([
            $schedule_id
        ]);


        $prep =
            $prepStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$prep) {

            throw new Exception(
                "This medication dose has not been prepared by pharmacy."
            );
        }


        $prepStatus =
            strtoupper(
                trim(
                    $prep['STATUS']
                    ?? ''
                )
            );


        /* =================================================
           VALID PHARMACY STATUSES

           READY FOR NURSE PICKUP
           READY FOR NURSE
           PREPARED
           COLLECTED

           COLLECTED means nurse already picked medication
           from pharmacy, but has not administered it yet.
        ================================================= */

        if (
            !in_array(
                $prepStatus,
                [
                    'READY FOR NURSE PICKUP',
                    'READY FOR NURSE',
                    'PREPARED',
                    'COLLECTED'
                ],
                true
            )
        ) {

            if (
                in_array(
                    $prepStatus,
                    [
                        'ADMINISTERED',
                        'GIVEN',
                        'DELIVERED'
                    ],
                    true
                )
            ) {

                throw new Exception(
                    "This medication dose has already been administered."
                );
            }


            if (
                in_array(
                    $prepStatus,
                    [
                        'CANCELLED',
                        'CANCELLED - DISCHARGED'
                    ],
                    true
                )
            ) {

                throw new Exception(
                    "This medication dose has been cancelled."
                );
            }


            throw new Exception(
                "This medication dose is not ready for nurse administration."
            );
        }


        /* =================================================
           CHECK SCHEDULE STATUS

           Collected By Nurse is ALLOWED.
        ================================================= */

        $scheduleStatus =
            strtoupper(
                trim(
                    $schedule['STATUS']
                    ?? ''
                )
            );


        if (
            in_array(
                $scheduleStatus,
                [
                    'ADMINISTERED',
                    'GIVEN',
                    'DELIVERED',
                    'COMPLETED',
                    'CANCELLED',
                    'CANCELLED - DISCHARGED'
                ],
                true
            )
        ) {

            throw new Exception(
                "This medication dose is no longer available for administration."
            );
        }


        /* =================================================
           CHECK DUPLICATE ADMINISTRATION
        ================================================= */

        $adminCheck =
            $conn->prepare("

                SELECT COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_ADMIN

                WHERE
                    SCHEDULE_ID = ?

            ");


        $adminCheck->execute([
            $schedule_id
        ]);


        if (
            (int)$adminCheck
                ->fetchColumn()
            > 0
        ) {

            throw new Exception(
                "This medication dose has already been recorded as administered."
            );
        }


        /* =================================================
           INSERT MEDICATION ADMINISTRATION
        ================================================= */

        $insertAdmin =
            $conn->prepare("

                INSERT INTO
                    SYARMIMI.MEDICATION_ADMIN
                (
                    ADMIN_ID,
                    ADMIN_TIME,
                    MEDORDER_ID,
                    ACCOUNT_ID,
                    SCHEDULE_ID
                )

                VALUES
                (
                    SYARMIMI.MEDADMIN_SEQ.NEXTVAL,
                    SYSDATE,
                    ?,
                    ?,
                    ?
                )

            ");


        $insertAdmin->execute([

            $schedule[
                'MEDORDER_ID'
            ],

            $staff_id,

            $schedule_id

        ]);


        /* =================================================
           UPDATE SCHEDULE
        ================================================= */

        $updateSchedule =
            $conn->prepare("

                UPDATE
                    SYARMIMI.MEDICATION_SCHEDULE

                SET
                    STATUS =
                    'Administered'

                WHERE
                    SCHEDULE_ID = ?

            ");


        $updateSchedule->execute([
            $schedule_id
        ]);


        /* =================================================
           UPDATE PHARMACY PREPARATION
        ================================================= */

        $updatePrep =
            $conn->prepare("

                UPDATE
                    SYARMIMI.PHARMACY_PREPARATION

                SET
                    STATUS =
                    'Administered'

                WHERE
                    PREP_ID = ?

            ");


        $updatePrep->execute([
            $prep[
                'PREP_ID'
            ]
        ]);


        /* =================================================
           COMMIT
        ================================================= */

        $conn->commit();


        redirectMedication([
            'success' => '1'
        ]);


    }
    catch (Throwable $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectMedication([

            'error' =>
                'give_failed',

            'message' =>
                $e->getMessage()

        ]);
    }
}


/* =========================================================
   GIVE LEGACY MEDICATION

   Old medication orders created before
   MEDICATION_SCHEDULE existed.
========================================================= */

if (
    isset(
        $_POST['give_legacy']
    )
) {

    $medorder_id =
        (int)(
            $_POST['medorder_id']
            ?? 0
        );


    if ($medorder_id <= 0) {

        redirectMedication([
            'error' => 'invalid'
        ]);
    }


    try {

        $conn->beginTransaction();


        /* =================================================
           CHECK ACTIVE ADMISSION ORDER
        ================================================= */

        $orderCheck =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_ORDER MO

                JOIN
                    SYARMIMI.ADMISSION A

                    ON
                        MO.ADMISSION_ID =
                        A.ADMISSION_ID

                WHERE
                    MO.MEDORDER_ID = ?

                AND
                    A.DISCHARGE_DATE
                    IS NULL

            ");


        $orderCheck->execute([
            $medorder_id
        ]);


        if (
            (int)$orderCheck
                ->fetchColumn()
            === 0
        ) {

            throw new Exception(
                "Medication order not found or patient has already been discharged."
            );
        }


        /* =================================================
           CHECK EXISTING LEGACY ADMINISTRATION
        ================================================= */

        $check =
            $conn->prepare("

                SELECT COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_ADMIN

                WHERE
                    MEDORDER_ID = ?

                AND
                    SCHEDULE_ID
                    IS NULL

            ");


        $check->execute([
            $medorder_id
        ]);


        if (
            (int)$check
                ->fetchColumn()
            > 0
        ) {

            throw new Exception(
                "This legacy medication has already been administered."
            );
        }


        /* =================================================
           INSERT LEGACY ADMINISTRATION
        ================================================= */

        $insert =
            $conn->prepare("

                INSERT INTO
                    SYARMIMI.MEDICATION_ADMIN
                (
                    ADMIN_ID,
                    ADMIN_TIME,
                    MEDORDER_ID,
                    ACCOUNT_ID,
                    SCHEDULE_ID
                )

                VALUES
                (
                    SYARMIMI.MEDADMIN_SEQ.NEXTVAL,
                    SYSDATE,
                    ?,
                    ?,
                    NULL
                )

            ");


        $insert->execute([

            $medorder_id,

            $staff_id

        ]);


        /* =================================================
           UPDATE LEGACY PHARMACY PREPARATION
        ================================================= */

        $legacyPrepUpdate =
            $conn->prepare("

                UPDATE
                    SYARMIMI.PHARMACY_PREPARATION

                SET
                    STATUS =
                    'Administered'

                WHERE
                    MEDORDER_ID = ?

                AND
                    SCHEDULE_ID
                    IS NULL

                AND
                    UPPER(
                        TRIM(
                            STATUS
                        )
                    )
                    IN
                    (
                        'READY FOR NURSE PICKUP',
                        'READY FOR NURSE',
                        'PREPARED',
                        'COLLECTED'
                    )

            ");


        $legacyPrepUpdate->execute([
            $medorder_id
        ]);


        $conn->commit();


        redirectMedication([
            'success' => '1'
        ]);


    }
    catch (Throwable $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectMedication([

            'error' =>
                'give_failed',

            'message' =>
                $e->getMessage()

        ]);
    }
}


/* =========================================================
   FILTER VALUES
========================================================= */

$search =
    trim(
        $_GET['search']
        ?? ''
    );


$wardFilter =
    trim(
        $_GET['ward']
        ?? ''
    );


$sort =
    $_GET['sort']
    ?? 'time';


$allowedSorts = [
    'time',
    'patient',
    'ward',
    'medication'
];


if (
    !in_array(
        $sort,
        $allowedSorts,
        true
    )
) {

    $sort =
        'time';
}


/* =========================================================
   SQL SORT

   Date first.
   Old overdue ready doses appear first.
========================================================= */

$orderBy = "

    MS.SCHEDULE_DATE ASC,
    MS.SCHEDULE_TIME ASC,
    PA.NAME ASC

";


if (
    $sort === 'patient'
) {

    $orderBy = "

        UPPER(PA.NAME) ASC,
        MS.SCHEDULE_DATE ASC,
        MS.SCHEDULE_TIME ASC

    ";
}
elseif (
    $sort === 'ward'
) {

    $orderBy = "

        UPPER(W.WARD_NAME) ASC,
        MS.SCHEDULE_DATE ASC,
        MS.SCHEDULE_TIME ASC

    ";
}
elseif (
    $sort === 'medication'
) {

    $orderBy = "

        UPPER(M.MEDICATION_NAME) ASC,
        MS.SCHEDULE_DATE ASC,
        MS.SCHEDULE_TIME ASC

    ";
}


/* =========================================================
   READY MEDICATION DOSES

   SHOW:
   - Active admission
   - Prepared by pharmacy
   - Collected by nurse
   - Today OR past due
   - Not administered
   - Not cancelled

   HIDE:
   - Future doses
   - Discharged patient
   - Cancelled doses
   - Already administered
========================================================= */

$where = "

    WHERE
        A.DISCHARGE_DATE
        IS NULL

    AND
        TRUNC(
            MS.SCHEDULE_DATE
        )
        <=
        TRUNC(
            SYSDATE
        )

    AND
        UPPER(
            TRIM(
                PP.STATUS
            )
        )
        IN
        (
            'READY FOR NURSE PICKUP',
            'READY FOR NURSE',
            'PREPARED',
            'COLLECTED'
        )

    AND
        UPPER(
            TRIM(
                NVL(
                    MS.STATUS,
                    'Pending Preparation'
                )
            )
        )
        NOT IN
        (
            'ADMINISTERED',
            'GIVEN',
            'DELIVERED',
            'COMPLETED',
            'CANCELLED',
            'CANCELLED - DISCHARGED'
        )

    AND NOT EXISTS
    (
        SELECT 1

        FROM
            SYARMIMI.MEDICATION_ADMIN MA

        WHERE
            MA.SCHEDULE_ID =
            MS.SCHEDULE_ID
    )

";


$params = [];


/* =========================================================
   SEARCH FILTER
========================================================= */

if (
    $search !== ''
) {

    $where .= "

        AND
        (
            UPPER(
                PA.NAME
            )
            LIKE
            UPPER(?)

            OR

            UPPER(
                M.MEDICATION_NAME
            )
            LIKE
            UPPER(?)

            OR

            UPPER(
                W.WARD_NAME
            )
            LIKE
            UPPER(?)

            OR

            TO_CHAR(
                A.ADMISSION_ID
            )
            LIKE
            ?
        )

    ";


    $searchValue =
        '%'
        .
        $search
        .
        '%';


    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;

    $params[] =
        $searchValue;
}


/* =========================================================
   WARD FILTER
========================================================= */

if (
    $wardFilter !== ''
) {

    $where .= "

        AND
            UPPER(
                W.WARD_NAME
            )
            =
            UPPER(?)

    ";


    $params[] =
        $wardFilter;
}


/* =========================================================
   FETCH READY DOSES

   IMPORTANT:
   Latest PHARMACY_PREPARATION row only.
========================================================= */

$doseSql = "

    SELECT

        MS.SCHEDULE_ID,

        MS.SCHEDULE_TIME,

        TO_CHAR(
            MS.SCHEDULE_DATE,
            'DD-MON-YY'
        )
        AS SCHEDULE_DATE_DISPLAY,

        TO_CHAR(
            MS.SCHEDULE_DATE,
            'YYYY-MM-DD'
        )
        AS SCHEDULE_DATE_VALUE,

        CASE

            WHEN
                TRUNC(
                    MS.SCHEDULE_DATE
                )
                <
                TRUNC(
                    SYSDATE
                )

            THEN
                1

            ELSE
                0

        END
        AS IS_OVERDUE,

        MS.STATUS
        AS SCHEDULE_STATUS,

        MO.MEDORDER_ID,

        A.ADMISSION_ID,

        PA.NAME,

        M.MEDICATION_NAME,

        MO.DOSAGE,

        MO.FREQUENCY,

        W.WARD_NAME,

        B.BED_NUMBER,

        PP.PREP_ID,

        PP.STATUS
        AS PREPARATION_STATUS,

        PP.PREPARED_TIME

    FROM
        SYARMIMI.MEDICATION_SCHEDULE MS

    JOIN
        SYARMIMI.MEDICATION_ORDER MO

        ON
            MS.MEDORDER_ID =
            MO.MEDORDER_ID

    JOIN
        SYARMIMI.ADMISSION A

        ON
            MO.ADMISSION_ID =
            A.ADMISSION_ID

    JOIN
        SYARMIMI.PATIENT PA

        ON
            A.PATIENT_ID =
            PA.PATIENT_ID

    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID

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

    JOIN
    (
        SELECT

            PP1.PREP_ID,
            PP1.SCHEDULE_ID,
            PP1.MEDORDER_ID,
            PP1.STATUS,
            PP1.PREPARED_TIME

        FROM
            SYARMIMI.PHARMACY_PREPARATION PP1

        JOIN
        (
            SELECT

                SCHEDULE_ID,

                MAX(PREP_ID)
                AS MAX_PREP_ID

            FROM
                SYARMIMI.PHARMACY_PREPARATION

            WHERE
                SCHEDULE_ID
                IS NOT NULL

            GROUP BY
                SCHEDULE_ID

        )
        LATEST

        ON
            PP1.PREP_ID =
            LATEST.MAX_PREP_ID

    )
    PP

    ON
        PP.SCHEDULE_ID =
        MS.SCHEDULE_ID

    $where

    ORDER BY
        $orderBy

";


$doseStmt =
    $conn->prepare(
        $doseSql
    );


$doseStmt->execute(
    $params
);


$rows =
    $doseStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   LEGACY READY MEDICATION

   Supports old data before scheduled-dose system.
========================================================= */

$legacyWhere = "

    WHERE
        A.DISCHARGE_DATE
        IS NULL

    AND
        PP.SCHEDULE_ID
        IS NULL

    AND
        UPPER(
            TRIM(
                PP.STATUS
            )
        )
        IN
        (
            'READY FOR NURSE PICKUP',
            'READY FOR NURSE',
            'PREPARED',
            'COLLECTED'
        )

    AND NOT EXISTS
    (
        SELECT 1

        FROM
            SYARMIMI.MEDICATION_SCHEDULE MS2

        WHERE
            MS2.MEDORDER_ID =
            MO.MEDORDER_ID
    )

    AND NOT EXISTS
    (
        SELECT 1

        FROM
            SYARMIMI.MEDICATION_ADMIN MA2

        WHERE
            MA2.MEDORDER_ID =
            MO.MEDORDER_ID

        AND
            MA2.SCHEDULE_ID
            IS NULL
    )

";


$legacyParams = [];


/* =========================================================
   LEGACY SEARCH
========================================================= */

if (
    $search !== ''
) {

    $legacyWhere .= "

        AND
        (
            UPPER(
                PA.NAME
            )
            LIKE
            UPPER(?)

            OR

            UPPER(
                M.MEDICATION_NAME
            )
            LIKE
            UPPER(?)

            OR

            UPPER(
                W.WARD_NAME
            )
            LIKE
            UPPER(?)

            OR

            TO_CHAR(
                A.ADMISSION_ID
            )
            LIKE
            ?
        )

    ";


    $searchValue =
        '%'
        .
        $search
        .
        '%';


    $legacyParams[] =
        $searchValue;

    $legacyParams[] =
        $searchValue;

    $legacyParams[] =
        $searchValue;

    $legacyParams[] =
        $searchValue;
}


/* =========================================================
   LEGACY WARD FILTER
========================================================= */

if (
    $wardFilter !== ''
) {

    $legacyWhere .= "

        AND
            UPPER(
                W.WARD_NAME
            )
            =
            UPPER(?)

    ";


    $legacyParams[] =
        $wardFilter;
}


/* =========================================================
   LEGACY SORT
========================================================= */

$legacyOrder =
    "MO.MEDORDER_ID DESC";


if (
    $sort === 'patient'
) {

    $legacyOrder =
        "UPPER(PA.NAME) ASC";
}
elseif (
    $sort === 'ward'
) {

    $legacyOrder =
        "UPPER(W.WARD_NAME) ASC";
}
elseif (
    $sort === 'medication'
) {

    $legacyOrder =
        "UPPER(M.MEDICATION_NAME) ASC";
}


/* =========================================================
   FETCH LEGACY RECORDS

   Latest preparation row only.
========================================================= */

$legacySql = "

    SELECT

        MO.MEDORDER_ID,

        A.ADMISSION_ID,

        PA.NAME,

        M.MEDICATION_NAME,

        MO.DOSAGE,

        MO.FREQUENCY,

        W.WARD_NAME,

        B.BED_NUMBER,

        PP.PREP_ID,

        PP.STATUS
        AS PREPARATION_STATUS,

        PP.PREPARED_TIME

    FROM
        SYARMIMI.MEDICATION_ORDER MO

    JOIN
        SYARMIMI.ADMISSION A

        ON
            MO.ADMISSION_ID =
            A.ADMISSION_ID

    JOIN
        SYARMIMI.PATIENT PA

        ON
            A.PATIENT_ID =
            PA.PATIENT_ID

    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID

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

    JOIN
    (
        SELECT

            PP1.PREP_ID,
            PP1.MEDORDER_ID,
            PP1.SCHEDULE_ID,
            PP1.STATUS,
            PP1.PREPARED_TIME

        FROM
            SYARMIMI.PHARMACY_PREPARATION PP1

        JOIN
        (
            SELECT

                MEDORDER_ID,

                MAX(PREP_ID)
                AS MAX_PREP_ID

            FROM
                SYARMIMI.PHARMACY_PREPARATION

            WHERE
                SCHEDULE_ID
                IS NULL

            GROUP BY
                MEDORDER_ID

        )
        LATEST

        ON
            PP1.PREP_ID =
            LATEST.MAX_PREP_ID

    )
    PP

    ON
        PP.MEDORDER_ID =
        MO.MEDORDER_ID

    $legacyWhere

    ORDER BY
        $legacyOrder

";


$legacyStmt =
    $conn->prepare(
        $legacySql
    );


$legacyStmt->execute(
    $legacyParams
);


$legacyRows =
    $legacyStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   READY TO GIVE COUNT

   Includes:
   - today prepared
   - today collected by nurse
   - overdue prepared/collected
   - legacy ready
========================================================= */

$pendingCount =
    count($rows)
    +
    count($legacyRows);


/* =========================================================
   OVERDUE READY COUNT
========================================================= */

$overdueCount = 0;


foreach (
    $rows
    as
    $readyRow
) {

    if (
        (int)(
            $readyRow[
                'IS_OVERDUE'
            ]
            ?? 0
        )
        === 1
    ) {

        $overdueCount++;
    }
}


/* =========================================================
   GIVEN TODAY
========================================================= */

$givenToday =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ADMIN

            WHERE
                TRUNC(
                    ADMIN_TIME
                )
                =
                TRUNC(
                    SYSDATE
                )

        ")
        ->fetchColumn();


/* =========================================================
   ACTIVE WARDS
========================================================= */

$wardCount =
    (int)$conn
        ->query("

            SELECT

                COUNT(
                    DISTINCT W.WARD_ID
                )

            FROM
                SYARMIMI.ADMISSION A

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

            WHERE
                A.DISCHARGE_DATE
                IS NULL

        ")
        ->fetchColumn();


/* =========================================================
   TOTAL SCHEDULED TODAY

   This remains TODAY only.
========================================================= */

$scheduledToday =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_SCHEDULE MS

            JOIN
                SYARMIMI.MEDICATION_ORDER MO

                ON
                    MS.MEDORDER_ID =
                    MO.MEDORDER_ID

            JOIN
                SYARMIMI.ADMISSION A

                ON
                    MO.ADMISSION_ID =
                    A.ADMISSION_ID

            WHERE
                A.DISCHARGE_DATE
                IS NULL

            AND
                TRUNC(
                    MS.SCHEDULE_DATE
                )
                =
                TRUNC(
                    SYSDATE
                )

            AND
                UPPER(
                    TRIM(
                        NVL(
                            MS.STATUS,
                            'Pending Preparation'
                        )
                    )
                )
                NOT IN
                (
                    'CANCELLED',
                    'CANCELLED - DISCHARGED'
                )

        ")
        ->fetchColumn();


/* =========================================================
   WARD LIST
========================================================= */

$wardStmt =
    $conn->query("

        SELECT DISTINCT
            WARD_NAME

        FROM
            SYARMIMI.WARD

        WHERE
            WARD_NAME
            IS NOT NULL

        ORDER BY
            WARD_NAME

    ");


$wards =
    $wardStmt->fetchAll(
        PDO::FETCH_COLUMN
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
Medication Administration | ZB-CARE
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

/* =========================================================
   ROOT
========================================================= */

:root{

    --bg:#f6f8fb;

    --surface:#ffffff;

    --border:#e6eaf0;

    --text:#172033;

    --muted:#8792a5;

    --primary:#2563eb;

    --primary-soft:#eff6ff;

    --success:#16803d;

    --success-soft:#ecfdf3;

    --warning:#b45309;

    --warning-soft:#fff7ed;

    --danger:#dc2626;

    --danger-soft:#fef2f2;

    --purple:#7c3aed;

    --purple-soft:#f5f3ff;

}


/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:
        var(--bg);

    color:
        var(--text);

    font-family:
        "Segoe UI",
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

}


/* =========================================================
   MAIN
========================================================= */

.main-content{

    margin-left:260px;

    min-height:100vh;

    padding:
        28px 30px 45px;

}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

    margin-bottom:23px;

}


.page-title-wrap{

    display:flex;

    align-items:flex-start;

    gap:13px;

}


.page-icon{

    width:47px;

    height:47px;

    min-width:47px;

    display:grid;

    place-items:center;

    border-radius:12px;

    background:
        var(--success-soft);

    color:
        var(--success);

    font-size:21px;

}


.page-title{

    margin:0;

    color:#111827;

    font-size:28px;

    font-weight:750;

    letter-spacing:-.4px;

}


.page-subtitle{

    margin-top:5px;

    color:
        var(--muted);

    font-size:13px;

}


/* =========================================================
   DATE
========================================================= */

.date-chip{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:
        9px 12px;

    border:
        1px solid var(--border);

    border-radius:9px;

    background:#fff;

    color:#64748b;

    font-size:12px;

    font-weight:600;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stat-card{

    min-height:132px;

    padding:19px;

    background:
        var(--surface);

    border:
        1px solid var(--border);

    border-radius:13px;

    transition:.2s;

}


.stat-card:hover{

    transform:
        translateY(-2px);

    border-color:#d6dce5;

}


.stat-top{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:15px;

}


.stat-label{

    color:
        var(--muted);

    font-size:12px;

    font-weight:600;

}


.stat-number{

    margin-top:6px;

    color:#111827;

    font-size:31px;

    line-height:1;

    font-weight:750;

}


.stat-note{

    margin-top:10px;

    color:#9aa4b5;

    font-size:11px;

}


.stat-icon{

    width:39px;

    height:39px;

    display:grid;

    place-items:center;

    border-radius:10px;

    font-size:17px;

}


.icon-pending{

    background:
        var(--warning-soft);

    color:
        var(--warning);

}


.icon-given{

    background:
        var(--success-soft);

    color:
        var(--success);

}


.icon-schedule{

    background:
        var(--primary-soft);

    color:
        var(--primary);

}


.icon-ward{

    background:
        var(--purple-soft);

    color:
        var(--purple);

}


/* =========================================================
   OVERDUE NOTICE
========================================================= */

.overdue-notice{

    display:flex;

    align-items:flex-start;

    gap:9px;

    margin-top:18px;

    padding:12px 14px;

    background:#fff7ed;

    border:1px solid #fed7aa;

    border-radius:10px;

    color:#9a3412;

    font-size:11px;

}


.overdue-notice strong{

    font-weight:750;

}


/* =========================================================
   FILTER
========================================================= */

.filter-card{

    margin-top:21px;

    padding:18px 20px;

    background:#fff;

    border:
        1px solid var(--border);

    border-radius:13px;

}


.filter-header{

    margin-bottom:14px;

}


.filter-title{

    margin:0;

    color:#1f2937;

    font-size:15px;

    font-weight:700;

}


.filter-description{

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;

}


.form-label{

    margin-bottom:5px;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    letter-spacing:.4px;

    text-transform:uppercase;

}


.form-control,
.form-select{

    min-height:41px;

    border:
        1px solid #dfe4eb;

    border-radius:9px;

    font-size:12px;

}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.08);

}


.filter-btn{

    width:100%;

    min-height:41px;

    border:0;

    border-radius:9px;

    background:#172033;

    color:white;

    font-size:12px;

    font-weight:650;

}


.filter-btn:hover{

    background:#263146;

}


.reset-btn{

    width:100%;

    min-height:41px;

    display:grid;

    place-items:center;

    border:
        1px solid #e1e6ed;

    border-radius:9px;

    background:#fff;

    color:#64748b;

    text-decoration:none;

}


/* =========================================================
   QUEUE
========================================================= */

.queue-card{

    margin-top:21px;

    overflow:hidden;

    background:#fff;

    border:
        1px solid var(--border);

    border-radius:13px;

}


.queue-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    padding:
        18px 20px;

    border-bottom:
        1px solid #edf0f3;

}


.queue-title{

    margin:0;

    font-size:16px;

    font-weight:700;

}


.queue-description{

    margin-top:3px;

    color:
        var(--muted);

    font-size:11px;

}


.queue-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 9px;

    border-radius:7px;

    background:
        var(--warning-soft);

    color:
        var(--warning);

    font-size:11px;

    font-weight:700;

}


/* =========================================================
   TABLE
========================================================= */

.table{

    margin-bottom:0;

    vertical-align:middle;

}


.table thead th{

    padding:
        11px 13px;

    border-bottom:
        1px solid #e5e9ef;

    background:#f9fafb;

    color:#687386;

    font-size:10px;

    font-weight:750;

    letter-spacing:.3px;

    text-transform:uppercase;

    white-space:nowrap;

}


.table tbody td{

    padding:
        14px 13px;

    border-color:#eef1f5;

    color:#394456;

    font-size:12px;

}


.table tbody tr:hover td{

    background:#fafcff;

}


.overdue-row td{

    background:#fffdf9;

}


/* =========================================================
   PATIENT
========================================================= */

.patient-box{

    display:flex;

    align-items:center;

    gap:9px;

}


.patient-avatar{

    width:34px;

    height:34px;

    min-width:34px;

    display:grid;

    place-items:center;

    border-radius:9px;

    background:
        var(--primary-soft);

    color:
        var(--primary);

}


.patient-name{

    color:#202939;

    font-size:12px;

    font-weight:700;

}


.patient-id{

    margin-top:2px;

    color:#9aa4b3;

    font-size:9px;

}


/* =========================================================
   MEDICATION
========================================================= */

.medication-box{

    display:flex;

    align-items:center;

    gap:8px;

}


.medication-icon{

    width:30px;

    height:30px;

    display:grid;

    place-items:center;

    border-radius:8px;

    background:
        var(--purple-soft);

    color:
        var(--purple);

}


.medication-name{

    color:#263244;

    font-weight:700;

}


/* =========================================================
   LOCATION
========================================================= */

.location-main{

    color:#394456;

    font-size:11px;

    font-weight:650;

}


.location-sub{

    margin-top:3px;

    color:#9aa4b3;

    font-size:9px;

}


/* =========================================================
   SCHEDULE
========================================================= */

.schedule-time{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 9px;

    border-radius:7px;

    background:
        var(--primary-soft);

    color:
        var(--primary);

    font-size:11px;

    font-weight:750;

}


.overdue-badge{

    display:inline-flex;

    align-items:center;

    gap:4px;

    margin-top:5px;

    padding:
        4px 7px;

    border-radius:6px;

    background:#fff7ed;

    color:#c2410c;

    font-size:9px;

    font-weight:750;

}


.legacy-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        5px 8px;

    border-radius:7px;

    background:#f3f4f6;

    color:#64748b;

    font-size:9px;

    font-weight:700;

}


/* =========================================================
   BADGES
========================================================= */

.soft-badge{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:
        6px 9px;

    border-radius:7px;

    font-size:10px;

    font-weight:700;

    white-space:nowrap;

}


.dosage-badge{

    background:
        var(--primary-soft);

    color:
        var(--primary);

}


.frequency-badge{

    background:#f3f4f6;

    color:#586476;

}


/* =========================================================
   GIVE BUTTON
========================================================= */

.give-btn{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:5px;

    padding:
        8px 11px;

    border:0;

    border-radius:8px;

    background:
        var(--success);

    color:#fff;

    font-size:10px;

    font-weight:700;

    transition:.2s;

    white-space:nowrap;

}


.give-btn:hover{

    background:#126b34;

    transform:
        translateY(-1px);

}


/* =========================================================
   LEGACY
========================================================= */

.legacy-card{

    margin-top:20px;

    border-color:#e8eaee;

}


.legacy-card .queue-badge{

    background:#f3f4f6;

    color:#64748b;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{

    padding:
        50px 20px;

    text-align:center;

}


.empty-icon{

    width:52px;

    height:52px;

    margin:
        0 auto 11px;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:
        var(--success-soft);

    color:
        var(--success);

    font-size:21px;

}


.empty-title{

    color:#475569;

    font-size:13px;

    font-weight:700;

}


.empty-text{

    margin-top:4px;

    color:
        var(--muted);

    font-size:11px;

}


/* =========================================================
   SWEETALERT
========================================================= */

.zb-popup{

    border:0 !important;

    border-radius:18px !important;

    box-shadow:
        0 30px 80px
        rgba(15,23,42,.20) !important;

}


.zb-title{

    color:#172033 !important;

    font-size:19px !important;

    font-weight:750 !important;

}


.zb-popup-icon{

    width:58px;

    height:58px;

    margin:
        5px auto 14px;

    display:grid;

    place-items:center;

    border-radius:15px;

    background:
        var(--success-soft);

    color:
        var(--success);

    font-size:24px;

}


.zb-med-info{

    padding:15px;

    border:
        1px solid #e5e9ef;

    border-radius:12px;

    background:#f8fafc;

    text-align:left;

}


.zb-row{

    display:grid;

    grid-template-columns:
        115px 1fr;

    gap:10px;

    padding:7px 0;

    border-bottom:
        1px solid #edf0f3;

}


.zb-row:last-child{

    border-bottom:0;

}


.zb-label{

    color:#8b96a8;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

}


.zb-value{

    color:#253044;

    font-size:12px;

    font-weight:650;

}


.zb-warning{

    display:flex;

    align-items:flex-start;

    gap:7px;

    margin-top:13px;

    padding:10px;

    border:
        1px solid #fde68a;

    border-radius:8px;

    background:#fffbeb;

    color:#92400e;

    font-size:10px;

    line-height:1.45;

}


.zb-confirm{

    padding:
        10px 16px !important;

    border:0 !important;

    border-radius:8px !important;

    background:
        var(--success) !important;

    color:white !important;

    font-size:11px !important;

    font-weight:700 !important;

}


.zb-cancel{

    padding:
        10px 16px !important;

    border:
        1px solid #e1e5eb !important;

    border-radius:8px !important;

    background:#f8fafc !important;

    color:#64748b !important;

    font-size:11px !important;

    font-weight:700 !important;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1000px){

    .main-content{

        margin-left:260px;

        padding:20px;

    }


    .page-header{

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


<main class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div class="page-title-wrap">


<div class="page-icon">

<i class="bi bi-capsule-pill"></i>

</div>


<div>


<h1 class="page-title">

Medication Administration

</h1>


<div class="page-subtitle">

Administer prepared medication doses according to each patient's prescribed schedule.

</div>


</div>


</div>


<div class="date-chip">

<i class="bi bi-calendar3"></i>

<?= strtoupper(
    date('d M Y')
) ?>

</div>


</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="row g-3">


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Ready to Give

</div>


<div class="stat-number">

<?= $pendingCount ?>

</div>


</div>


<div class="stat-icon icon-pending">

<i class="bi bi-hourglass-split"></i>

</div>


</div>


<div class="stat-note">

Prepared or collected doses waiting for nurse administration.

</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Given Today

</div>


<div class="stat-number">

<?= $givenToday ?>

</div>


</div>


<div class="stat-icon icon-given">

<i class="bi bi-check2-circle"></i>

</div>


</div>


<div class="stat-note">

Dose administration records created today.

</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Scheduled Today

</div>


<div class="stat-number">

<?= $scheduledToday ?>

</div>


</div>


<div class="stat-icon icon-schedule">

<i class="bi bi-clock-history"></i>

</div>


</div>


<div class="stat-note">

Total active inpatient medication doses scheduled today.

</div>


</div>


</div>


<div class="col-xl-3 col-md-6">


<div class="stat-card">


<div class="stat-top">


<div>


<div class="stat-label">

Active Wards

</div>


<div class="stat-number">

<?= $wardCount ?>

</div>


</div>


<div class="stat-icon icon-ward">

<i class="bi bi-hospital"></i>

</div>


</div>


<div class="stat-note">

Wards currently accommodating admitted patients.

</div>


</div>


</div>


</div>


<!-- =====================================================
     OVERDUE NOTICE
===================================================== -->

<?php if (
    $overdueCount > 0
): ?>


<div class="overdue-notice">


<i class="bi bi-exclamation-triangle-fill"></i>


<div>


<strong>

<?= $overdueCount ?>

overdue prepared dose(s)

</strong>

are still waiting for administration.

These doses were prepared or collected previously but were not recorded as administered.

</div>


</div>


<?php endif; ?>


<!-- =====================================================
     FILTER
===================================================== -->

<div class="filter-card">


<div class="filter-header">


<h5 class="filter-title">

Medication Queue Filter

</h5>


<div class="filter-description">

Search by patient, medication, ward or admission number.

</div>


</div>


<form method="GET">


<div class="row g-3 align-items-end">


<div class="col-lg-4">


<label class="form-label">

Search

</label>


<input
    type="text"
    name="search"
    class="form-control"
    placeholder="Search patient or medication..."
    value="<?= h($search) ?>"
>


</div>


<div class="col-lg-3">


<label class="form-label">

Ward

</label>


<select
    name="ward"
    class="form-select"
>


<option value="">

All Wards

</option>


<?php foreach (
    $wards
    as
    $ward
): ?>


<option
    value="<?= h($ward) ?>"

    <?= (
        $wardFilter
        ===
        $ward
    )
        ?
        'selected'
        :
        ''
    ?>
>

<?= h($ward) ?>

</option>


<?php endforeach; ?>


</select>


</div>


<div class="col-lg-3">


<label class="form-label">

Sort By

</label>


<select
    name="sort"
    class="form-select"
>


<option
    value="time"
    <?= $sort === 'time'
        ? 'selected'
        : ''
    ?>
>

Scheduled Time

</option>


<option
    value="patient"
    <?= $sort === 'patient'
        ? 'selected'
        : ''
    ?>
>

Patient Name

</option>


<option
    value="ward"
    <?= $sort === 'ward'
        ? 'selected'
        : ''
    ?>
>

Ward

</option>


<option
    value="medication"
    <?= $sort === 'medication'
        ? 'selected'
        : ''
    ?>
>

Medication

</option>


</select>


</div>


<div class="col-lg-1">


<button
    type="submit"
    class="filter-btn"
    title="Apply Filter"
>

<i class="bi bi-funnel"></i>

</button>


</div>


<div class="col-lg-1">


<a
    href="nurse_medication.php"
    class="reset-btn"
    title="Reset Filter"
>

<i class="bi bi-arrow-counterclockwise"></i>

</a>


</div>


</div>


</form>


</div>


<!-- =====================================================
     READY MEDICATION DOSES
===================================================== -->

<div class="queue-card">


<div class="queue-header">


<div>


<h5 class="queue-title">

Ready Medication Doses

</h5>


<div class="queue-description">

Today's prepared/collected doses and overdue doses waiting for administration.

</div>


</div>


<span class="queue-badge">

<i class="bi bi-clock"></i>

<?= count($rows) ?>

Ready

</span>


</div>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>
Patient
</th>

<th>
Location
</th>

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
Scheduled
</th>

<th>
Status
</th>

<th class="text-center">
Action
</th>

</tr>


</thead>


<tbody>


<?php if (
    count($rows) > 0
): ?>


<?php foreach (
    $rows
    as
    $row
): ?>


<?php

$isOverdue =
    (int)(
        $row[
            'IS_OVERDUE'
        ]
        ?? 0
    )
    === 1;


$prepStatusText =
    trim(
        $row[
            'PREPARATION_STATUS'
        ]
        ?? ''
    );

?>


<tr
    class="<?= $isOverdue
        ? 'overdue-row'
        : ''
    ?>"
>


<!-- PATIENT -->

<td>


<div class="patient-box">


<div class="patient-avatar">

<i class="bi bi-person-fill"></i>

</div>


<div>


<div class="patient-name">

<?= h(
    $row['NAME']
) ?>

</div>


<div class="patient-id">

Admission #

<?= h(
    $row[
        'ADMISSION_ID'
    ]
) ?>

</div>


</div>


</div>


</td>


<!-- LOCATION -->

<td>


<div class="location-main">

<i class="bi bi-hospital me-1"></i>

<?= h(
    $row[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

</div>


<div class="location-sub">

Bed

<?= h(
    $row[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>

</div>


</td>


<!-- MEDICATION -->

<td>


<div class="medication-box">


<div class="medication-icon">

<i class="bi bi-capsule"></i>

</div>


<div class="medication-name">

<?= h(
    $row[
        'MEDICATION_NAME'
    ]
) ?>

</div>


</div>


</td>


<!-- DOSAGE -->

<td>


<span class="soft-badge dosage-badge">

<?= h(
    $row[
        'DOSAGE'
    ]
) ?>

</span>


</td>


<!-- FREQUENCY -->

<td>


<span class="soft-badge frequency-badge">

<?= h(
    $row[
        'FREQUENCY'
    ]
) ?>

</span>


</td>


<!-- SCHEDULE -->

<td>


<span class="schedule-time">

<i class="bi bi-clock"></i>

<?= h(
    $row[
        'SCHEDULE_TIME'
    ]
) ?>

</span>


<div class="location-sub mt-1">

<?= h(
    $row[
        'SCHEDULE_DATE_DISPLAY'
    ]
) ?>

</div>


<?php if ($isOverdue): ?>


<div class="overdue-badge">

<i class="bi bi-exclamation-circle"></i>

Overdue

</div>


<?php endif; ?>


</td>


<!-- STATUS -->

<td>


<?php if (
    strtoupper(
        $prepStatusText
    )
    ===
    'COLLECTED'
): ?>


<span
    class="soft-badge"
    style="
        background:#ecfeff;
        color:#0e7490;
    "
>

<i class="bi bi-person-check"></i>

Collected by Nurse

</span>


<?php else: ?>


<span
    class="soft-badge"
    style="
        background:#ecfdf5;
        color:#15803d;
    "
>

<i class="bi bi-check-circle"></i>

Ready for Nurse

</span>


<?php endif; ?>


</td>


<!-- ACTION -->

<td class="text-center">


<form
    method="POST"
    class="giveDoseForm d-inline"

    data-patient="<?= h(
        $row['NAME']
    ) ?>"

    data-medication="<?= h(
        $row[
            'MEDICATION_NAME'
        ]
    ) ?>"

    data-dosage="<?= h(
        $row[
            'DOSAGE'
        ]
    ) ?>"

    data-frequency="<?= h(
        $row[
            'FREQUENCY'
        ]
    ) ?>"

    data-time="<?= h(
        $row[
            'SCHEDULE_TIME'
        ]
    ) ?>"

    data-date="<?= h(
        $row[
            'SCHEDULE_DATE_DISPLAY'
        ]
    ) ?>"

    data-overdue="<?= $isOverdue
        ? '1'
        : '0'
    ?>"
>


<input
    type="hidden"
    name="schedule_id"
    value="<?= (int)$row['SCHEDULE_ID'] ?>"
>


<input
    type="hidden"
    name="give_schedule"
    value="1"
>


<button
    type="button"
    class="give-btn giveDoseBtn"
>

<i class="bi bi-check2-circle"></i>

Give Dose

</button>


</form>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>


<td colspan="8">


<div class="empty-state">


<div class="empty-icon">

<i class="bi bi-check-lg"></i>

</div>


<div class="empty-title">

No Prepared Doses Waiting

</div>


<div class="empty-text">

There are currently no prepared or collected medication doses waiting for administration.

</div>


</div>


</td>


</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


<!-- =====================================================
     LEGACY MEDICATION
===================================================== -->

<?php if (
    count($legacyRows) > 0
): ?>


<div class="queue-card legacy-card">


<div class="queue-header">


<div>


<h5 class="queue-title">

Previous Medication Records

</h5>


<div class="queue-description">

Prepared inpatient medication created before scheduled-dose tracking was introduced.

</div>


</div>


<span class="queue-badge">

<i class="bi bi-clock-history"></i>

<?= count($legacyRows) ?>

Legacy

</span>


</div>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>
Patient
</th>

<th>
Location
</th>

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
Type
</th>

<th class="text-center">
Action
</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $legacyRows
    as
    $row
): ?>


<tr>


<td>


<div class="patient-box">


<div class="patient-avatar">

<i class="bi bi-person-fill"></i>

</div>


<div>


<div class="patient-name">

<?= h(
    $row['NAME']
) ?>

</div>


<div class="patient-id">

Admission #

<?= h(
    $row[
        'ADMISSION_ID'
    ]
) ?>

</div>


</div>


</div>


</td>


<td>


<div class="location-main">

<?= h(
    $row[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

</div>


<div class="location-sub">

Bed

<?= h(
    $row[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>

</div>


</td>


<td>


<div class="medication-name">

<?= h(
    $row[
        'MEDICATION_NAME'
    ]
) ?>

</div>


</td>


<td>


<span class="soft-badge dosage-badge">

<?= h(
    $row[
        'DOSAGE'
    ]
) ?>

</span>


</td>


<td>


<span class="soft-badge frequency-badge">

<?= h(
    $row[
        'FREQUENCY'
    ]
) ?>

</span>


</td>


<td>


<span class="legacy-badge">

<i class="bi bi-archive"></i>

Legacy Record

</span>


</td>


<td class="text-center">


<form
    method="POST"
    class="giveLegacyForm d-inline"

    data-patient="<?= h(
        $row['NAME']
    ) ?>"

    data-medication="<?= h(
        $row[
            'MEDICATION_NAME'
        ]
    ) ?>"

    data-dosage="<?= h(
        $row[
            'DOSAGE'
        ]
    ) ?>"

    data-frequency="<?= h(
        $row[
            'FREQUENCY'
        ]
    ) ?>"
>


<input
    type="hidden"
    name="medorder_id"
    value="<?= (int)$row['MEDORDER_ID'] ?>"
>


<input
    type="hidden"
    name="give_legacy"
    value="1"
>


<button
    type="button"
    class="give-btn giveLegacyBtn"
>

<i class="bi bi-check2-circle"></i>

Give Medication

</button>


</form>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


<?php endif; ?>


</main>


<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<script>


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value)
{
    const element =
        document.createElement(
            'div'
        );

    element.textContent =
        value
        ??
        '';

    return element.innerHTML;
}


/* =========================================================
   CONFIRM MEDICATION ADMINISTRATION
========================================================= */

function confirmMedicationAdministration(
    form,
    scheduled
)
{

    const patient =
        escapeHtml(
            form.dataset.patient
        );


    const medication =
        escapeHtml(
            form.dataset.medication
        );


    const dosage =
        escapeHtml(
            form.dataset.dosage
        );


    const frequency =
        escapeHtml(
            form.dataset.frequency
        );


    const time =
        scheduled
        ?
        escapeHtml(
            form.dataset.time
        )
        :
        'Legacy Record';


    const scheduleDate =
        scheduled
        ?
        escapeHtml(
            form.dataset.date
        )
        :
        '-';


    const overdue =
        scheduled
        &&
        form.dataset.overdue
        ===
        '1';


    Swal.fire({

        width:
            500,

        showCancelButton:
            true,

        reverseButtons:
            true,

        buttonsStyling:
            false,

        customClass:
        {

            popup:
                'zb-popup',

            title:
                'zb-title',

            confirmButton:
                'zb-confirm',

            cancelButton:
                'zb-cancel'

        },


        title:

            overdue

            ?

            'Confirm Overdue Dose'

            :

            (
                scheduled

                ?

                'Confirm Scheduled Dose'

                :

                'Confirm Medication Administration'
            ),


        html:
        `

            <div class="zb-popup-icon">

                <i class="bi bi-capsule-pill"></i>

            </div>


            <div class="zb-med-info">


                <div class="zb-row">

                    <div class="zb-label">
                        Patient
                    </div>

                    <div class="zb-value">
                        ${patient}
                    </div>

                </div>


                <div class="zb-row">

                    <div class="zb-label">
                        Medication
                    </div>

                    <div class="zb-value">
                        ${medication}
                    </div>

                </div>


                <div class="zb-row">

                    <div class="zb-label">
                        Dosage
                    </div>

                    <div class="zb-value">
                        ${dosage}
                    </div>

                </div>


                <div class="zb-row">

                    <div class="zb-label">
                        Frequency
                    </div>

                    <div class="zb-value">
                        ${frequency}
                    </div>

                </div>


                <div class="zb-row">

                    <div class="zb-label">
                        Scheduled Date
                    </div>

                    <div class="zb-value">
                        ${scheduleDate}
                    </div>

                </div>


                <div class="zb-row">

                    <div class="zb-label">
                        Scheduled Time
                    </div>

                    <div class="zb-value">
                        ${time}
                    </div>

                </div>


            </div>


            ${
                overdue

                ?

                `

                <div class="zb-warning">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    <span>

                        This is an overdue prepared dose from a previous scheduled date. Confirm only if this dose should still be administered.

                    </span>

                </div>

                `

                :

                `

                <div class="zb-warning">

                    <i class="bi bi-exclamation-triangle-fill"></i>

                    <span>

                        Confirm only after verifying the correct patient, medication, dosage and scheduled dose.

                    </span>

                </div>

                `
            }

        `,


        confirmButtonText:
            '<i class="bi bi-check2-circle me-1"></i> Confirm & Give',

        cancelButtonText:
            'Cancel'

    })


    .then(
        function(result)
        {

            if (
                result.isConfirmed
            )
            {

                Swal.fire({

                    width:
                        380,

                    title:
                        'Recording Administration',

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


                form.submit();

            }

        }
    );

}


/* =========================================================
   SCHEDULE BUTTON
========================================================= */

document
.querySelectorAll(
    '.giveDoseBtn'
)
.forEach(
    function(button)
    {

        button.addEventListener(
            'click',
            function()
            {

                confirmMedicationAdministration(

                    this.closest(
                        '.giveDoseForm'
                    ),

                    true

                );

            }
        );

    }
);


/* =========================================================
   LEGACY BUTTON
========================================================= */

document
.querySelectorAll(
    '.giveLegacyBtn'
)
.forEach(
    function(button)
    {

        button.addEventListener(
            'click',
            function()
            {

                confirmMedicationAdministration(

                    this.closest(
                        '.giveLegacyForm'
                    ),

                    false

                );

            }
        );

    }
);


/* =========================================================
   SUCCESS
========================================================= */

<?php if (
    ($_GET['success'] ?? '')
    ===
    '1'
): ?>


Swal.fire({

    width:
        420,

    icon:
        'success',

    title:
        'Medication Administered',

    text:
        'The dose administration has been recorded successfully.',

    confirmButtonColor:
        '#16803d',

    confirmButtonText:
        'Done'

});


<?php endif; ?>


/* =========================================================
   ERROR
========================================================= */

<?php if (
    ($_GET['error'] ?? '')
    ===
    'give_failed'
): ?>


Swal.fire({

    width:
        470,

    icon:
        'error',

    title:
        'Unable to Administer Medication',

    text:
        <?= json_encode(
            $_GET['message']
            ??
            'Unable to record medication administration.'
        ) ?>,

    confirmButtonColor:
        '#dc2626',

    confirmButtonText:
        'Close'

});


<?php endif; ?>


<?php if (
    ($_GET['error'] ?? '')
    ===
    'invalid'
): ?>


Swal.fire({

    icon:
        'error',

    title:
        'Invalid Medication',

    text:
        'The selected medication dose is invalid.',

    confirmButtonColor:
        '#dc2626'

});


<?php endif; ?>


</script>


</body>

</html>