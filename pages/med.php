<?php

session_start();

date_default_timezone_set(
    'Asia/Kuala_Lumpur'
);

include("../config/config.php");


/* =========================================================
   SECURITY
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header(
        "Location: ../auth/login.php"
    );

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
   CHECK TABLE COLUMN

   Used for backward compatibility with historical tables.
========================================================= */

function columnExists(
    PDO $conn,
    string $table,
    string $column
): bool {

    $stmt =
        $conn->prepare("

            SELECT COUNT(*)

            FROM
                ALL_TAB_COLUMNS

            WHERE
                OWNER = 'SYARMIMI'

            AND
                TABLE_NAME = UPPER(?)

            AND
                COLUMN_NAME = UPPER(?)

        ");

    $stmt->execute([
        $table,
        $column
    ]);

    return (
        (int)$stmt->fetchColumn()
        > 0
    );
}


/* =========================================================
   DATABASE STRUCTURE CHECK
========================================================= */

$prepHasScheduleId =
    columnExists(
        $conn,
        'PHARMACY_PREPARATION',
        'SCHEDULE_ID'
    );


$prepHasAccountId =
    columnExists(
        $conn,
        'PHARMACY_PREPARATION',
        'ACCOUNT_ID'
    );


$prepHasStaffId =
    columnExists(
        $conn,
        'PHARMACY_PREPARATION',
        'STAFF_ID'
    );


$adminHasScheduleId =
    columnExists(
        $conn,
        'MEDICATION_ADMIN',
        'SCHEDULE_ID'
    );


$adminHasAccountId =
    columnExists(
        $conn,
        'MEDICATION_ADMIN',
        'ACCOUNT_ID'
    );


/* =========================================================
   PREPARATION STAFF COLUMN
========================================================= */

$prepStaffColumn =
    'NULL';


if ($prepHasAccountId) {

    $prepStaffColumn =
        'PP.ACCOUNT_ID';

}
elseif ($prepHasStaffId) {

    $prepStaffColumn =
        'PP.STAFF_ID';
}


/* =========================================================
   DATE FILTER
========================================================= */

$dateFilter =
    trim(
        $_GET['date']
        ?? 'all'
    );


$allowedDateFilters = [

    'all',
    'today',
    'yesterday',
    '7days',
    'month',
    'custom'

];


if (
    !in_array(
        $dateFilter,
        $allowedDateFilters,
        true
    )
) {

    $dateFilter =
        'all';
}


/* =========================================================
   CUSTOM DATE
========================================================= */

$customDate =
    trim(
        $_GET['custom_date']
        ?? ''
    );


$validCustomDate =
    '';


if (
    $dateFilter === 'custom'
    &&
    preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $customDate
    )
) {

    $object =
        DateTime::createFromFormat(
            'Y-m-d',
            $customDate
        );


    if (
        $object
        &&
        $object->format('Y-m-d')
        ===
        $customDate
    ) {

        $validCustomDate =
            $customDate;
    }
}


/* =========================================================
   LATEST SCHEDULED PHARMACY PREPARATION
========================================================= */

$scheduledPrepJoin = "";


if ($prepHasScheduleId) {

    $scheduledPrepJoin = "

        LEFT JOIN
        (
            SELECT

                PP1.PREP_ID,
                PP1.STATUS,
                PP1.PREPARED_TIME,
                PP1.MEDORDER_ID,
                PP1.SCHEDULE_ID,
                " . (
                    $prepHasAccountId
                    ? "PP1.ACCOUNT_ID"
                    :
                    (
                        $prepHasStaffId
                        ? "PP1.STAFF_ID"
                        : "NULL AS ACCOUNT_ID"
                    )
                ) . "

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

            ) X

            ON
                PP1.PREP_ID =
                X.MAX_PREP_ID

        ) PP

        ON
            PP.SCHEDULE_ID =
            MS.SCHEDULE_ID

    ";

}
else {

    $scheduledPrepJoin = "

        LEFT JOIN
            SYARMIMI.PHARMACY_PREPARATION PP

        ON
            PP.MEDORDER_ID =
            MO.MEDORDER_ID

    ";
}


/* =========================================================
   LATEST MEDICATION ADMINISTRATION
========================================================= */

$adminJoin = "";


if ($adminHasScheduleId) {

    $adminJoin = "

        LEFT JOIN
        (
            SELECT

                MA1.ADMIN_ID,
                MA1.ADMIN_TIME,
                MA1.MEDORDER_ID,
                MA1.SCHEDULE_ID,
                MA1.ACCOUNT_ID

            FROM
                SYARMIMI.MEDICATION_ADMIN MA1

            JOIN
            (
                SELECT

                    SCHEDULE_ID,

                    MAX(ADMIN_ID)
                    AS MAX_ADMIN_ID

                FROM
                    SYARMIMI.MEDICATION_ADMIN

                WHERE
                    SCHEDULE_ID
                    IS NOT NULL

                GROUP BY
                    SCHEDULE_ID

            ) X

            ON
                MA1.ADMIN_ID =
                X.MAX_ADMIN_ID

        ) MA

        ON
            MA.SCHEDULE_ID =
            MS.SCHEDULE_ID

    ";

}
else {

    $adminJoin = "

        LEFT JOIN
            SYARMIMI.MEDICATION_ADMIN MA

        ON
            1 = 0

    ";
}


/* =========================================================
   INPATIENT SCHEDULED MEDICATION

   One row = one scheduled dose.

   Includes:
   - Pending Preparation
   - Ready For Nurse Pickup
   - Collected By Nurse
   - Administered
   - Cancelled - Discharged
========================================================= */

$inpatientSQL = "

    SELECT

        'SCHEDULED'
        AS RECORD_SOURCE,

        'INPATIENT'
        AS ORDER_TYPE,

        MO.MEDORDER_ID,

        MS.SCHEDULE_ID,

        P.NAME
        AS PATIENT_NAME,

        P.IC_NUMBER,

        M.MEDICATION_NAME,

        MO.DOSAGE,

        MO.FREQUENCY,

        NVL(
            W.WARD_NAME,
            '-'
        )
        AS WARD_NAME,

        NVL(
            B.BED_NUMBER,
            '-'
        )
        AS BED_NUMBER,


        /* =============================================
           FILTER DATE
        ============================================= */

        MS.SCHEDULE_DATE
        AS FILTER_DATE,


        TO_CHAR(
            MS.SCHEDULE_DATE,
            'DD-MON-YYYY'
        )
        AS DISPLAY_DATE,


        TO_CHAR(
            MS.SCHEDULE_DATE,
            'YYYY-MM-DD'
        )
        AS SORT_DATE,


        NVL(
            MS.SCHEDULE_TIME,
            '-'
        )
        AS SCHEDULE_TIME,


        /* =============================================
           STATUS
        ============================================= */

        MS.STATUS
        AS SCHEDULE_STATUS,


        PP.STATUS
        AS PREPARATION_STATUS,


        PP.PREPARED_TIME,


        /* =============================================
           STAFF
        ============================================= */

        $prepStaffColumn
        AS PREPARED_STAFF_ID,


        MA.ADMIN_TIME,


        " . (
            $adminHasAccountId
            ? "MA.ACCOUNT_ID"
            : "NULL"
        ) . "
        AS ADMIN_STAFF_ID,


        /* =============================================
           ADMISSION
        ============================================= */

        A.ADMISSION_ID,

        A.DISCHARGE_DATE

    FROM
        SYARMIMI.MEDICATION_SCHEDULE MS


    JOIN
        SYARMIMI.MEDICATION_ORDER MO

        ON
            MS.MEDORDER_ID =
            MO.MEDORDER_ID


    JOIN
        SYARMIMI.PATIENT P

        ON
            MO.PATIENT_ID =
            P.PATIENT_ID


    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID


    JOIN
        SYARMIMI.ADMISSION A

        ON
            MO.ADMISSION_ID =
            A.ADMISSION_ID


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


    $scheduledPrepJoin


    $adminJoin


    WHERE
        MO.ADMISSION_ID
        IS NOT NULL


    AND
    (
        MO.ORDER_TYPE
        IS NULL

        OR

        UPPER(
            TRIM(
                MO.ORDER_TYPE
            )
        )
        =
        'INPATIENT'
    )

";


/* =========================================================
   PATIENT PICKUP PREPARATION JOIN

   Appointment
   Walk-In
   Discharge
========================================================= */

$pickupPrepJoin = "

    LEFT JOIN
    (
        SELECT

            PP1.*

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
                MEDORDER_ID
                IS NOT NULL

";


if ($prepHasScheduleId) {

    $pickupPrepJoin .= "

            AND
                SCHEDULE_ID
                IS NULL

    ";
}


$pickupPrepJoin .= "

            GROUP BY
                MEDORDER_ID

        ) X

        ON
            PP1.PREP_ID =
            X.MAX_PREP_ID

    ) PP

    ON
        PP.MEDORDER_ID =
        MO.MEDORDER_ID

";


/* =========================================================
   APPOINTMENT / WALK-IN / DISCHARGE MEDICATION

   These medications:
   - are patient pickup
   - do NOT go to nurse
   - do NOT have medication schedule
========================================================= */

$pickupSQL = "

    SELECT

        'PATIENT PICKUP'
        AS RECORD_SOURCE,


        CASE

            WHEN
                UPPER(
                    TRIM(
                        NVL(
                            MO.ORDER_TYPE,
                            'UNKNOWN'
                        )
                    )
                )
                =
                'DISCHARGE'

            THEN
                'DISCHARGE'


            WHEN
                UPPER(
                    TRIM(
                        NVL(
                            MO.ORDER_TYPE,
                            'UNKNOWN'
                        )
                    )
                )
                =
                'APPOINTMENT'

            THEN
                'APPOINTMENT'


            WHEN
                UPPER(
                    TRIM(
                        NVL(
                            MO.ORDER_TYPE,
                            'UNKNOWN'
                        )
                    )
                )
                =
                'WALKIN'

            THEN
                'WALKIN'


            WHEN
                MO.APPOINTMENT_ID
                IS NOT NULL

            THEN
                'APPOINTMENT'


            WHEN
                MO.CONSULTATION_ID
                IS NOT NULL

            THEN
                'WALKIN'


            ELSE
                'UNKNOWN'

        END
        AS ORDER_TYPE,


        MO.MEDORDER_ID,


        NULL
        AS SCHEDULE_ID,


        P.NAME
        AS PATIENT_NAME,


        P.IC_NUMBER,


        M.MEDICATION_NAME,


        MO.DOSAGE,


        MO.FREQUENCY,


        'Pharmacy Counter'
        AS WARD_NAME,


        '-'
        AS BED_NUMBER,


        /* =============================================
           DATE

           Discharge medication has MED_START_DATE.
           Appointment / Walk-in old data may not.

           Preparation date used if available.
        ============================================= */

        NVL(
            PP.PREPARED_TIME,
            NVL(
                MO.MED_START_DATE,
                SYSDATE
            )
        )
        AS FILTER_DATE,


        TO_CHAR(
            NVL(
                PP.PREPARED_TIME,
                NVL(
                    MO.MED_START_DATE,
                    SYSDATE
                )
            ),
            'DD-MON-YYYY'
        )
        AS DISPLAY_DATE,


        TO_CHAR(
            NVL(
                PP.PREPARED_TIME,
                NVL(
                    MO.MED_START_DATE,
                    SYSDATE
                )
            ),
            'YYYY-MM-DD'
        )
        AS SORT_DATE,


        '-'
        AS SCHEDULE_TIME,


        NULL
        AS SCHEDULE_STATUS,


        PP.STATUS
        AS PREPARATION_STATUS,


        PP.PREPARED_TIME,


        " . (
            $prepHasAccountId
            ? "PP.ACCOUNT_ID"
            :
            (
                $prepHasStaffId
                ? "PP.STAFF_ID"
                : "NULL"
            )
        ) . "
        AS PREPARED_STAFF_ID,


        NULL
        AS ADMIN_TIME,


        NULL
        AS ADMIN_STAFF_ID,


        NULL
        AS ADMISSION_ID,


        NULL
        AS DISCHARGE_DATE


    FROM
        SYARMIMI.MEDICATION_ORDER MO


    JOIN
        SYARMIMI.PATIENT P

        ON
            MO.PATIENT_ID =
            P.PATIENT_ID


    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID


    $pickupPrepJoin


    WHERE
        MO.ADMISSION_ID
        IS NULL


    AND
    (
        UPPER(
            TRIM(
                NVL(
                    MO.ORDER_TYPE,
                    'UNKNOWN'
                )
            )
        )
        IN
        (
            'APPOINTMENT',
            'WALKIN',
            'DISCHARGE'
        )


        OR


        (
            MO.ORDER_TYPE
            IS NULL

            AND
            (
                MO.APPOINTMENT_ID
                IS NOT NULL

                OR

                MO.CONSULTATION_ID
                IS NOT NULL
            )
        )
    )

";


/* =========================================================
   HISTORICAL INPATIENT RECORDS

   Old inpatient orders created before MEDICATION_SCHEDULE.

   Only show orders that actually entered pharmacy workflow.
========================================================= */

$historicalSQL = "

    SELECT

        'HISTORICAL'
        AS RECORD_SOURCE,


        'INPATIENT'
        AS ORDER_TYPE,


        MO.MEDORDER_ID,


        NULL
        AS SCHEDULE_ID,


        P.NAME
        AS PATIENT_NAME,


        P.IC_NUMBER,


        M.MEDICATION_NAME,


        MO.DOSAGE,


        MO.FREQUENCY,


        NVL(
            W.WARD_NAME,
            '-'
        )
        AS WARD_NAME,


        NVL(
            B.BED_NUMBER,
            '-'
        )
        AS BED_NUMBER,


        NVL(
            PP.PREPARED_TIME,
            NVL(
                MO.MED_START_DATE,
                A.ADMISSION_DATE
            )
        )
        AS FILTER_DATE,


        TO_CHAR(
            NVL(
                PP.PREPARED_TIME,
                NVL(
                    MO.MED_START_DATE,
                    A.ADMISSION_DATE
                )
            ),
            'DD-MON-YYYY'
        )
        AS DISPLAY_DATE,


        TO_CHAR(
            NVL(
                PP.PREPARED_TIME,
                NVL(
                    MO.MED_START_DATE,
                    A.ADMISSION_DATE
                )
            ),
            'YYYY-MM-DD'
        )
        AS SORT_DATE,


        'Legacy'
        AS SCHEDULE_TIME,


        NULL
        AS SCHEDULE_STATUS,


        PP.STATUS
        AS PREPARATION_STATUS,


        PP.PREPARED_TIME,


        " . (
            $prepHasAccountId
            ? "PP.ACCOUNT_ID"
            :
            (
                $prepHasStaffId
                ? "PP.STAFF_ID"
                : "NULL"
            )
        ) . "
        AS PREPARED_STAFF_ID,


        NULL
        AS ADMIN_TIME,


        NULL
        AS ADMIN_STAFF_ID,


        A.ADMISSION_ID,


        A.DISCHARGE_DATE


    FROM
        SYARMIMI.MEDICATION_ORDER MO


    JOIN
        SYARMIMI.PATIENT P

        ON
            MO.PATIENT_ID =
            P.PATIENT_ID


    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID


    JOIN
        SYARMIMI.ADMISSION A

        ON
            MO.ADMISSION_ID =
            A.ADMISSION_ID


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


    $pickupPrepJoin


    WHERE
        MO.ADMISSION_ID
        IS NOT NULL


    AND NOT EXISTS
    (
        SELECT
            1

        FROM
            SYARMIMI.MEDICATION_SCHEDULE MSX

        WHERE
            MSX.MEDORDER_ID =
            MO.MEDORDER_ID
    )


    AND
        PP.PREP_ID
        IS NOT NULL

";


/* =========================================================
   COMBINE ALL MEDICATION WORKFLOW
========================================================= */

$combinedSQL = "

    SELECT
        X.*

    FROM
    (

        $inpatientSQL


        UNION ALL


        $pickupSQL


        UNION ALL


        $historicalSQL

    )
    X


    WHERE
        1 = 1

";


$params = [];


/* =========================================================
   DATE FILTER
========================================================= */

if (
    $dateFilter === 'today'
) {

    $combinedSQL .= "

        AND
            TRUNC(
                X.FILTER_DATE
            )
            =
            TRUNC(
                SYSDATE
            )

    ";
}


elseif (
    $dateFilter === 'yesterday'
) {

    $combinedSQL .= "

        AND
            TRUNC(
                X.FILTER_DATE
            )
            =
            TRUNC(
                SYSDATE
            )
            -
            1

    ";
}


elseif (
    $dateFilter === '7days'
) {

    $combinedSQL .= "

        AND
            TRUNC(
                X.FILTER_DATE
            )
            BETWEEN
                TRUNC(SYSDATE) - 6

            AND
                TRUNC(SYSDATE)

    ";
}


elseif (
    $dateFilter === 'month'
) {

    $combinedSQL .= "

        AND
            TRUNC(
                X.FILTER_DATE
            )
            BETWEEN
                TRUNC(
                    SYSDATE,
                    'MM'
                )

            AND
                TRUNC(
                    SYSDATE
                )

    ";
}


elseif (
    $dateFilter === 'custom'
    &&
    $validCustomDate !== ''
) {

    $combinedSQL .= "

        AND
            TRUNC(
                X.FILTER_DATE
            )
            =
            TO_DATE(
                ?,
                'YYYY-MM-DD'
            )

    ";


    $params[] =
        $validCustomDate;
}


/* =========================================================
   FINAL ORDER
========================================================= */

$combinedSQL .= "

    ORDER BY

        X.FILTER_DATE DESC,

        X.MEDORDER_ID DESC,

        X.SCHEDULE_ID DESC

";


/* =========================================================
   FETCH
========================================================= */

try {

    $stmt =
        $conn->prepare(
            $combinedSQL
        );


    $stmt->execute(
        $params
    );


    $orders =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}
catch (PDOException $e) {

    die(

        "Database Error: "

        .

        h(
            $e->getMessage()
        )

    );
}


/* =========================================================
   STAFF CACHE
========================================================= */

$staffNameCache = [];


function getStaffName(
    PDO $conn,
    $staffId,
    array &$cache
) {

    if (
        empty(
            $staffId
        )
    ) {

        return '';
    }


    $key =
        (string)$staffId;


    if (
        isset(
            $cache[$key]
        )
    ) {

        return $cache[$key];
    }


    $stmt =
        $conn->prepare("

            SELECT
                USERNAME

            FROM
                SYARMIMI.HOSPITAL_STAFF

            WHERE
                ACCOUNT_ID = ?

        ");


    $stmt->execute([
        $staffId
    ]);


    $name =
        $stmt->fetchColumn();


    $cache[$key] =
        $name ?: '';


    return $cache[$key];
}


/* =========================================================
   SUMMARY COUNTERS
========================================================= */

$totalRecords =
    count($orders);


$totalPending =
    0;


$totalReady =
    0;


$totalCollected =
    0;


$totalAdministered =
    0;


$totalCancelled =
    0;


$totalDischargeMedicine =
    0;


/* =========================================================
   FORMAT RECORDS
========================================================= */

foreach (
    $orders
    as
    &$row
) {

    /* =====================================================
       ORDER TYPE
    ===================================================== */

    $orderType =
        strtoupper(
            trim(
                $row[
                    'ORDER_TYPE'
                ]
                ?? ''
            )
        );


    $source =
        strtoupper(
            trim(
                $row[
                    'RECORD_SOURCE'
                ]
                ?? ''
            )
        );


    $scheduleStatus =
        strtoupper(
            trim(
                $row[
                    'SCHEDULE_STATUS'
                ]
                ?? ''
            )
        );


    $prepStatus =
        strtoupper(
            trim(
                $row[
                    'PREPARATION_STATUS'
                ]
                ?? ''
            )
        );


    /* =====================================================
       TYPE DISPLAY
    ===================================================== */

    switch ($orderType) {

        case 'INPATIENT':

            $row[
                'TYPE_DISPLAY'
            ] =
                'Inpatient';

            break;


        case 'APPOINTMENT':

            $row[
                'TYPE_DISPLAY'
            ] =
                'Appointment';

            break;


        case 'WALKIN':

            $row[
                'TYPE_DISPLAY'
            ] =
                'Walk-In';

            break;


        case 'DISCHARGE':

            $row[
                'TYPE_DISPLAY'
            ] =
                'Discharge';

            $totalDischargeMedicine++;

            break;


        default:

            $row[
                'TYPE_DISPLAY'
            ] =
                'Unknown';
    }


    /* =====================================================
       WORKFLOW
    ===================================================== */

    if (
        $orderType ===
        'INPATIENT'
    ) {

        $row[
            'WORKFLOW'
        ] =
            'Nurse Administration';

    }
    else {

        $row[
            'WORKFLOW'
        ] =
            'Patient Pickup';
    }


    /* =====================================================
       FINAL STATUS

       Priority:
       1 Cancelled
       2 Administered
       3 Collected
       4 Ready
       5 Pending
    ===================================================== */

    if (
        in_array(
            $scheduleStatus,
            [
                'CANCELLED',
                'CANCELLED - DISCHARGED'
            ],
            true
        )

        ||

        in_array(
            $prepStatus,
            [
                'CANCELLED',
                'CANCELLED - DISCHARGED'
            ],
            true
        )
    ) {

        $row[
            'FINAL_STATUS'
        ] =
            'Cancelled';

        $totalCancelled++;
    }


    elseif (
        !empty(
            $row[
                'ADMIN_TIME'
            ]
        )

        ||

        in_array(
            $scheduleStatus,
            [
                'ADMINISTERED',
                'GIVEN',
                'DELIVERED',
                'COMPLETED'
            ],
            true
        )

        ||

        $prepStatus ===
        'ADMINISTERED'
    ) {

        $row[
            'FINAL_STATUS'
        ] =
            'Administered';

        $totalAdministered++;
    }


    elseif (
        $scheduleStatus ===
        'COLLECTED BY NURSE'

        ||

        $prepStatus ===
        'COLLECTED'
    ) {

        $row[
            'FINAL_STATUS'
        ] =
            'Collected';

        $totalCollected++;
    }


    elseif (
        in_array(
            $prepStatus,
            [
                'READY FOR PICKUP',
                'READY FOR NURSE PICKUP',
                'READY FOR NURSE',
                'PREPARED'
            ],
            true
        )

        ||

        $scheduleStatus ===
        'READY FOR NURSE PICKUP'
    ) {

        $row[
            'FINAL_STATUS'
        ] =
            'Ready';

        $totalReady++;
    }


    else {

        $row[
            'FINAL_STATUS'
        ] =
            'Pending';

        $totalPending++;
    }


    /* =====================================================
       PREPARED BY
    ===================================================== */

    $row[
        'PREPARED_BY'
    ] =
        getStaffName(
            $conn,
            $row[
                'PREPARED_STAFF_ID'
            ]
            ?? null,
            $staffNameCache
        );


    /* =====================================================
       ADMINISTERED BY
    ===================================================== */

    $row[
        'ADMINISTERED_BY'
    ] =
        getStaffName(
            $conn,
            $row[
                'ADMIN_STAFF_ID'
            ]
            ?? null,
            $staffNameCache
        );


    /* =====================================================
       PREPARED TIME DISPLAY
    ===================================================== */

    $row[
        'PREPARED_DISPLAY'
    ] =
        '-';


    if (
        !empty(
            $row[
                'PREPARED_TIME'
            ]
        )
    ) {

        try {

            $object =
                new DateTime(
                    $row[
                        'PREPARED_TIME'
                    ]
                );


            $row[
                'PREPARED_DISPLAY'
            ] =

                strtoupper(
                    $object->format(
                        'd-M-Y'
                    )
                )

                .

                ' • '

                .

                $object->format(
                    'H:i'
                );

        }
        catch (Throwable $e) {

            $row[
                'PREPARED_DISPLAY'
            ] =
                $row[
                    'PREPARED_TIME'
                ];
        }
    }


    /* =====================================================
       ADMIN TIME DISPLAY
    ===================================================== */

    $row[
        'ADMIN_DISPLAY'
    ] =
        '-';


    if (
        !empty(
            $row[
                'ADMIN_TIME'
            ]
        )
    ) {

        try {

            $object =
                new DateTime(
                    $row[
                        'ADMIN_TIME'
                    ]
                );


            $row[
                'ADMIN_DISPLAY'
            ] =

                strtoupper(
                    $object->format(
                        'd-M-Y'
                    )
                )

                .

                ' • '

                .

                $object->format(
                    'H:i'
                );

        }
        catch (Throwable $e) {

            $row[
                'ADMIN_DISPLAY'
            ] =
                $row[
                    'ADMIN_TIME'
                ];
        }
    }


    /* =====================================================
       RECORD DISPLAY
    ===================================================== */

    if (
        $source ===
        'HISTORICAL'
    ) {

        $row[
            'RECORD_DISPLAY'
        ] =
            'Historical';

    }
    elseif (
        $source ===
        'PATIENT PICKUP'
    ) {

        $row[
            'RECORD_DISPLAY'
        ] =
            'Prescription';

    }
    else {

        $row[
            'RECORD_DISPLAY'
        ] =
            'Scheduled Dose';
    }

}

unset($row);


/* =========================================================
   DATE LABEL
========================================================= */

$dateLabel =
    'All Dates';


switch ($dateFilter) {

    case 'today':

        $dateLabel =
            'Today';

        break;


    case 'yesterday':

        $dateLabel =
            'Yesterday';

        break;


    case '7days':

        $dateLabel =
            'Last 7 Days';

        break;


    case 'month':

        $dateLabel =
            'This Month';

        break;


    case 'custom':

        if (
            $validCustomDate !== ''
        ) {

            $dateLabel =
                strtoupper(
                    date(
                        'd-M-Y',
                        strtotime(
                            $validCustomDate
                        )
                    )
                );
        }

        break;
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
Medication Management
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
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
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


.header-chip{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:8px;

    color:#2563eb;

    font-size:11px;

    font-weight:650;

    white-space:nowrap;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(
            6,
            minmax(0,1fr)
        );

    gap:12px;

    margin-bottom:22px;
}


.summary-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:10px;

    padding:17px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:12px;
}


.summary-label{

    color:#64748b;

    font-size:11px;

    font-weight:600;
}


.summary-number{

    margin-top:4px;

    color:#111827;

    font-size:27px;

    font-weight:750;

    line-height:1;
}


.summary-note{

    margin-top:5px;

    color:#94a3b8;

    font-size:9px;
}


.summary-icon{

    width:39px;

    height:39px;

    min-width:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:16px;
}


.icon-total{

    background:#eff6ff;

    color:#2563eb;
}


.icon-pending{

    background:#fff7ed;

    color:#c2410c;
}


.icon-ready{

    background:#ecfeff;

    color:#0e7490;
}


.icon-collected{

    background:#f5f3ff;

    color:#7c3aed;
}


.icon-admin{

    background:#ecfdf5;

    color:#15803d;
}


.icon-cancelled{

    background:#f3f4f6;

    color:#64748b;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;
}


.card-heading{

    margin-bottom:19px;
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
   FILTER
========================================================= */

.filter-box{

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
}


.form-control,
.form-select{

    min-height:44px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    font-size:12px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
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


.custom-date-box{

    display:none;
}


.custom-date-box.show{

    display:block;
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

    font-size:11px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


/* =========================================================
   NUMBER
========================================================= */

.number-cell{

    width:50px;

    text-align:center;
}


.number-circle{

    width:29px;

    height:29px;

    margin:auto;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f1f5f9;

    border-radius:7px;

    color:#64748b;

    font-size:10px;

    font-weight:700;
}


/* =========================================================
   TEXT
========================================================= */

.patient-name{

    color:#111827;

    font-size:12px;

    font-weight:650;
}


.patient-ic{

    margin-top:3px;

    color:#94a3b8;

    font-size:9px;
}


.medication-name{

    color:#111827;

    font-weight:650;
}


/* =========================================================
   PILLS
========================================================= */

.pill{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:5px 7px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;

    white-space:nowrap;
}


.type-inpatient{

    background:#fff1f2;

    color:#be123c;
}


.type-appointment{

    background:#eff6ff;

    color:#2563eb;
}


.type-walkin{

    background:#fff7ed;

    color:#c2410c;
}


.type-discharge{

    background:#f5f3ff;

    color:#7c3aed;
}


.workflow-nurse{

    background:#ecfeff;

    color:#0e7490;
}


.workflow-patient{

    background:#ecfdf5;

    color:#15803d;
}


.status-pending{

    background:#fff7ed;

    color:#c2410c;
}


.status-ready{

    background:#ecfeff;

    color:#0e7490;
}


.status-collected{

    background:#f5f3ff;

    color:#7c3aed;
}


.status-administered{

    background:#ecfdf5;

    color:#15803d;
}


.status-cancelled{

    background:#f3f4f6;

    color:#64748b;
}


.status-prepared{

    background:#eff6ff;

    color:#2563eb;
}


.status-none{

    background:#f3f4f6;

    color:#64748b;
}


.record-scheduled{

    background:#eff6ff;

    color:#2563eb;
}


.record-prescription{

    background:#ecfdf5;

    color:#15803d;
}


.record-historical{

    background:#f3f4f6;

    color:#64748b;
}


/* =========================================================
   LOCATION
========================================================= */

.location-pill{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 7px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:6px;

    color:#475569;

    font-size:9px;

    white-space:nowrap;
}


/* =========================================================
   SCHEDULE
========================================================= */

.schedule-main{

    color:#111827;

    font-weight:650;
}


.schedule-sub{

    margin-top:3px;

    color:#94a3b8;

    font-size:9px;
}


/* =========================================================
   STAFF
========================================================= */

.staff-line{

    color:#475569;

    font-size:9px;

    line-height:1.55;
}


.staff-label{

    color:#94a3b8;

    font-weight:650;
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


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1350px){

    .summary-grid{

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );
    }
}


@media(max-width:800px){

    .summary-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }


    .main-content{

        padding:18px;
    }


    .page-header{

        flex-direction:column;
    }

}


@media(max-width:550px){

    .summary-grid{

        grid-template-columns:1fr;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php

include(
    "../includes/sidebar_admin.php"
);

?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Medication Management

</h1>


<div class="page-subtitle">

Monitor inpatient administration, appointment and walk-in prescriptions, discharge medication and historical medication activity.

</div>


</div>


<div class="header-chip">

<i class="bi bi-calendar3"></i>

<?= h(
    $dateLabel
) ?>

</div>


</div>


<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


<!-- TOTAL -->

<div class="summary-card">

<div>

<div class="summary-label">
Total Records
</div>

<div class="summary-number">

<?= $totalRecords ?>

</div>

<div class="summary-note">
All medication workflow
</div>

</div>


<div class="summary-icon icon-total">

<i class="bi bi-capsule-pill"></i>

</div>

</div>


<!-- PENDING -->

<div class="summary-card">

<div>

<div class="summary-label">
Pending
</div>

<div class="summary-number">

<?= $totalPending ?>

</div>

<div class="summary-note">
Awaiting preparation
</div>

</div>


<div class="summary-icon icon-pending">

<i class="bi bi-hourglass-split"></i>

</div>

</div>


<!-- READY -->

<div class="summary-card">

<div>

<div class="summary-label">
Ready
</div>

<div class="summary-number">

<?= $totalReady ?>

</div>

<div class="summary-note">
Prepared medication
</div>

</div>


<div class="summary-icon icon-ready">

<i class="bi bi-box-seam"></i>

</div>

</div>


<!-- COLLECTED -->

<div class="summary-card">

<div>

<div class="summary-label">
Collected
</div>

<div class="summary-number">

<?= $totalCollected ?>

</div>

<div class="summary-note">
Nurse / patient pickup
</div>

</div>


<div class="summary-icon icon-collected">

<i class="bi bi-person-check"></i>

</div>

</div>


<!-- ADMINISTERED -->

<div class="summary-card">

<div>

<div class="summary-label">
Administered
</div>

<div class="summary-number">

<?= $totalAdministered ?>

</div>

<div class="summary-note">
Given to inpatient
</div>

</div>


<div class="summary-icon icon-admin">

<i class="bi bi-check2-circle"></i>

</div>

</div>


<!-- CANCELLED -->

<div class="summary-card">

<div>

<div class="summary-label">
Cancelled
</div>

<div class="summary-number">

<?= $totalCancelled ?>

</div>

<div class="summary-note">
Cancelled after discharge
</div>

</div>


<div class="summary-icon icon-cancelled">

<i class="bi bi-x-circle"></i>

</div>

</div>


</div>


<!-- =====================================================
     CONTENT
===================================================== -->

<div class="content-card">


<div class="card-heading">


<h5 class="card-title">

Medication Records

</h5>


<div class="card-subtitle">

Includes scheduled inpatient doses, appointment medication, walk-in medication, discharge prescriptions and historical records.

</div>


</div>


<!-- =====================================================
     FILTER
===================================================== -->

<div class="filter-box">


<div class="row g-2 align-items-end">


<!-- SEARCH -->

<div class="col-lg-3">


<div class="filter-label">
Search
</div>


<div class="search-wrapper">


<i class="bi bi-search"></i>


<input
    type="text"
    id="medSearch"
    class="form-control"
    placeholder="Patient, medication, IC, ward..."
>


</div>


</div>


<!-- TYPE -->

<div class="col-lg-2">


<div class="filter-label">
Medication Type
</div>


<select
    id="typeFilter"
    class="form-select"
>


<option value="">
All Types
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


<option value="Discharge">
Discharge
</option>


</select>


</div>


<!-- WORKFLOW -->

<div class="col-lg-2">


<div class="filter-label">
Workflow
</div>


<select
    id="workflowFilter"
    class="form-select"
>


<option value="">
All Workflow
</option>


<option value="Nurse Administration">
Nurse Administration
</option>


<option value="Patient Pickup">
Patient Pickup
</option>


</select>


</div>


<!-- STATUS -->

<div class="col-lg-2">


<div class="filter-label">
Status
</div>


<select
    id="statusFilter"
    class="form-select"
>


<option value="">
All Status
</option>


<option value="Pending">
Pending
</option>


<option value="Ready">
Ready
</option>


<option value="Collected">
Collected
</option>


<option value="Administered">
Administered
</option>


<option value="Cancelled">
Cancelled
</option>


</select>


</div>


<!-- DATE -->

<div class="col-lg-2">


<div class="filter-label">
Date
</div>


<select
    id="dateFilter"
    class="form-select"
>


<option
    value="all"
    <?= $dateFilter === 'all'
        ? 'selected'
        : ''
    ?>
>
All Dates
</option>


<option
    value="today"
    <?= $dateFilter === 'today'
        ? 'selected'
        : ''
    ?>
>
Today
</option>


<option
    value="yesterday"
    <?= $dateFilter === 'yesterday'
        ? 'selected'
        : ''
    ?>
>
Yesterday
</option>


<option
    value="7days"
    <?= $dateFilter === '7days'
        ? 'selected'
        : ''
    ?>
>
Last 7 Days
</option>


<option
    value="month"
    <?= $dateFilter === 'month'
        ? 'selected'
        : ''
    ?>
>
This Month
</option>


<option
    value="custom"
    <?= $dateFilter === 'custom'
        ? 'selected'
        : ''
    ?>
>
Custom Date
</option>


</select>


</div>


<!-- CUSTOM DATE -->

<div
    class="
        col-lg-2
        custom-date-box
        <?= $dateFilter === 'custom'
            ? 'show'
            : ''
        ?>
    "
    id="customDateBox"
>


<div class="filter-label">
Choose Date
</div>


<input
    type="date"
    id="customDate"
    class="form-control"
    value="<?= h(
        $validCustomDate
    ) ?>"
>


</div>


</div>


</div>


<!-- =====================================================
     TABLE
===================================================== -->

<div class="table-responsive">


<table
    id="medTable"
    class="table"
>


<thead>


<tr>

<th>No.</th>

<th>Patient</th>

<th>Type</th>

<th>Record</th>

<th>Workflow</th>

<th>Location</th>

<th>Medication</th>

<th>Dosage</th>

<th>Frequency</th>

<th>Date / Time</th>

<th>Preparation</th>

<th>Status</th>

<th>Staff</th>

<th>Sort</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $orders
    as
    $row
): ?>


<tr>


<!-- =================================================
     NUMBER
================================================= -->

<td class="number-cell"></td>


<!-- =================================================
     PATIENT
================================================= -->

<td>


<div class="patient-name">

<?= h(
    $row[
        'PATIENT_NAME'
    ]
    ?? '-'
) ?>

</div>


<div class="patient-ic">

<?= h(
    $row[
        'IC_NUMBER'
    ]
    ?? '-'
) ?>

</div>


</td>


<!-- =================================================
     TYPE
================================================= -->

<td>


<?php if (
    $row[
        'TYPE_DISPLAY'
    ]
    ===
    'Inpatient'
): ?>


<span class="pill type-inpatient">

<i class="bi bi-hospital"></i>

Inpatient

</span>


<?php elseif (
    $row[
        'TYPE_DISPLAY'
    ]
    ===
    'Appointment'
): ?>


<span class="pill type-appointment">

<i class="bi bi-calendar-event"></i>

Appointment

</span>


<?php elseif (
    $row[
        'TYPE_DISPLAY'
    ]
    ===
    'Walk-In'
): ?>


<span class="pill type-walkin">

<i class="bi bi-person-walking"></i>

Walk-In

</span>


<?php elseif (
    $row[
        'TYPE_DISPLAY'
    ]
    ===
    'Discharge'
): ?>


<span class="pill type-discharge">

<i class="bi bi-house-check"></i>

Discharge

</span>


<?php else: ?>


<span class="pill status-none">

Unknown

</span>


<?php endif; ?>


</td>


<!-- =================================================
     RECORD
================================================= -->

<td>


<?php if (
    $row[
        'RECORD_DISPLAY'
    ]
    ===
    'Scheduled Dose'
): ?>


<span class="pill record-scheduled">

<i class="bi bi-calendar-check"></i>

Scheduled Dose

</span>


<?php elseif (
    $row[
        'RECORD_DISPLAY'
    ]
    ===
    'Prescription'
): ?>


<span class="pill record-prescription">

<i class="bi bi-prescription2"></i>

Prescription

</span>


<?php else: ?>


<span class="pill record-historical">

<i class="bi bi-clock-history"></i>

Historical

</span>


<?php endif; ?>


</td>


<!-- =================================================
     WORKFLOW
================================================= -->

<td>


<?php if (
    $row[
        'WORKFLOW'
    ]
    ===
    'Nurse Administration'
): ?>


<span class="pill workflow-nurse">

<i class="bi bi-person-badge"></i>

Nurse Administration

</span>


<?php else: ?>


<span class="pill workflow-patient">

<i class="bi bi-person-check"></i>

Patient Pickup

</span>


<?php endif; ?>


</td>


<!-- =================================================
     LOCATION
================================================= -->

<td>


<?php if (
    strtoupper(
        trim(
            $row[
                'ORDER_TYPE'
            ]
            ?? ''
        )
    )
    ===
    'INPATIENT'
): ?>


<span class="location-pill">

<i class="bi bi-hospital"></i>

<?= h(
    $row[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

•

Bed

<?= h(
    $row[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>

</span>


<?php else: ?>


<span class="location-pill">

<i class="bi bi-shop"></i>

Pharmacy Counter

</span>


<?php endif; ?>


</td>


<!-- =================================================
     MEDICATION
================================================= -->

<td>


<div class="medication-name">

<?= h(
    $row[
        'MEDICATION_NAME'
    ]
    ?? '-'
) ?>

</div>


</td>


<!-- =================================================
     DOSAGE
================================================= -->

<td>


<?= h(
    $row[
        'DOSAGE'
    ]
    ?? '-'
) ?>


</td>


<!-- =================================================
     FREQUENCY
================================================= -->

<td>


<?= h(
    $row[
        'FREQUENCY'
    ]
    ?? '-'
) ?>


</td>


<!-- =================================================
     DATE / TIME
================================================= -->

<td>


<div class="schedule-main">

<?= h(
    $row[
        'DISPLAY_DATE'
    ]
    ?? '-'
) ?>

</div>


<div class="schedule-sub">


<?php if (
    strtoupper(
        trim(
            $row[
                'ORDER_TYPE'
            ]
            ?? ''
        )
    )
    ===
    'DISCHARGE'
): ?>


Take-home prescription


<?php elseif (
    strtoupper(
        trim(
            $row[
                'ORDER_TYPE'
            ]
            ?? ''
        )
    )
    ===
    'INPATIENT'
): ?>


<?= h(
    $row[
        'SCHEDULE_TIME'
    ]
    ?? '-'
) ?>


<?php else: ?>


One-time prescription


<?php endif; ?>


</div>


</td>


<!-- =================================================
     PREPARATION
================================================= -->

<td>


<?php if (
    !empty(
        $row[
            'PREPARATION_STATUS'
        ]
    )
): ?>


<span class="pill status-prepared">

<i class="bi bi-box-seam"></i>

<?= h(
    $row[
        'PREPARATION_STATUS'
    ]
) ?>

</span>


<div class="schedule-sub">

<?= h(
    $row[
        'PREPARED_DISPLAY'
    ]
) ?>

</div>


<?php else: ?>


<span class="pill status-none">

Not Prepared

</span>


<?php endif; ?>


</td>


<!-- =================================================
     FINAL STATUS
================================================= -->

<td>


<?php if (
    $row[
        'FINAL_STATUS'
    ]
    ===
    'Pending'
): ?>


<span class="pill status-pending">

<i class="bi bi-hourglass-split"></i>

Pending

</span>


<?php elseif (
    $row[
        'FINAL_STATUS'
    ]
    ===
    'Ready'
): ?>


<span class="pill status-ready">

<i class="bi bi-check-circle"></i>

Ready

</span>


<?php elseif (
    $row[
        'FINAL_STATUS'
    ]
    ===
    'Collected'
): ?>


<span class="pill status-collected">

<i class="bi bi-person-check"></i>

Collected

</span>


<?php elseif (
    $row[
        'FINAL_STATUS'
    ]
    ===
    'Administered'
): ?>


<span class="pill status-administered">

<i class="bi bi-check2-circle"></i>

Administered

</span>


<?php else: ?>


<span class="pill status-cancelled">

<i class="bi bi-x-circle"></i>

Cancelled

</span>


<?php endif; ?>


</td>


<!-- =================================================
     STAFF
================================================= -->

<td>


<?php if (
    !empty(
        $row[
            'ADMINISTERED_BY'
        ]
    )
): ?>


<div class="staff-line">

<span class="staff-label">

Nurse:

</span>

<?= h(
    $row[
        'ADMINISTERED_BY'
    ]
) ?>

</div>


<div class="schedule-sub">

<?= h(
    $row[
        'ADMIN_DISPLAY'
    ]
) ?>

</div>


<?php elseif (
    !empty(
        $row[
            'PREPARED_BY'
        ]
    )
): ?>


<div class="staff-line">

<span class="staff-label">

Pharmacy:

</span>

<?= h(
    $row[
        'PREPARED_BY'
    ]
) ?>

</div>


<div class="schedule-sub">

<?= h(
    $row[
        'PREPARED_DISPLAY'
    ]
) ?>

</div>


<?php else: ?>


<span class="text-muted">

—

</span>


<?php endif; ?>


</td>


<!-- =================================================
     HIDDEN SORT DATE
================================================= -->

<td>

<?= h(
    $row[
        'SORT_DATE'
    ]
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


<script
    src="https://code.jquery.com/jquery-3.7.1.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js">
</script>


<script>

$(document).ready(
function()
{


    /* =====================================================
       DATATABLE
    ===================================================== */

    const table =
        $('#medTable')
            .DataTable({

                dom:
                    'lrtip',

                pageLength:
                    10,

                lengthMenu:
                [
                    [10,25,50,100],
                    [10,25,50,100]
                ],


                order:
                [
                    [13,'desc']
                ],


                columnDefs:
                [

                    {
                        targets:
                            0,

                        orderable:
                            false,

                        searchable:
                            false
                    },


                    {
                        targets:
                            13,

                        visible:
                            false,

                        searchable:
                            false
                    }

                ],


                drawCallback:
                function()
                {

                    const api =
                        this.api();


                    const info =
                        api.page.info();


                    api
                        .column(
                            0,
                            {
                                page:
                                    'current',

                                search:
                                    'applied',

                                order:
                                    'applied'
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

            });


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#medSearch').on(
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
       TYPE FILTER
    ===================================================== */

    $('#typeFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value === ''
            ) {

                table
                    .column(2)
                    .search('')
                    .draw();

                return;
            }


            table
                .column(2)
                .search(
                    '^'
                    +
                    $.fn.dataTable.util.escapeRegex(
                        value
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
       WORKFLOW FILTER
    ===================================================== */

    $('#workflowFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value === ''
            ) {

                table
                    .column(4)
                    .search('')
                    .draw();

                return;
            }


            table
                .column(4)
                .search(
                    '^'
                    +
                    $.fn.dataTable.util.escapeRegex(
                        value
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
       STATUS FILTER
    ===================================================== */

    $('#statusFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value === ''
            ) {

                table
                    .column(11)
                    .search('')
                    .draw();

                return;
            }


            table
                .column(11)
                .search(
                    '^'
                    +
                    $.fn.dataTable.util.escapeRegex(
                        value
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
       DATE FILTER
    ===================================================== */

    $('#dateFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value ===
                'custom'
            ) {

                $('#customDateBox')
                    .addClass(
                        'show'
                    );

                return;
            }


            const url =
                new URL(
                    window.location.href
                );


            url.searchParams.set(
                'date',
                value
            );


            url.searchParams.delete(
                'custom_date'
            );


            window.location.href =
                url.toString();
        }
    );


    /* =====================================================
       CUSTOM DATE
    ===================================================== */

    $('#customDate').on(
        'change',
        function()
        {

            if (
                !this.value
            ) {

                return;
            }


            const url =
                new URL(
                    window.location.href
                );


            url.searchParams.set(
                'date',
                'custom'
            );


            url.searchParams.set(
                'custom_date',
                this.value
            );


            window.location.href =
                url.toString();
        }
    );


});

</script>


</body>

</html>