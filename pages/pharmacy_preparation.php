<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pharmacist'
) {
    header("Location: ../auth/login.php");
    exit();
}

$staffId =
    (int)(
        $_SESSION['user_id']
        ?? 0
    );

if ($staffId <= 0) {
    die("Invalid pharmacist account.");
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
   FILTER VALUES
========================================================= */

$currentType =
    $_GET['type']
    ?? '';

$currentSearch =
    $_GET['search']
    ?? '';

$currentSort =
    $_GET['sort']
    ?? 'desc';

$currentRecordDate =
    trim(
        $_GET['record_date']
        ?? ''
    );


if (
    $currentRecordDate !== ''
    &&
    !preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $currentRecordDate
    )
) {
    $currentRecordDate = '';
}


$allowedTypes = [
    '',
    'Appointment',
    'Walk-In',
    'Admission',
    'Discharge'
];


if (
    !in_array(
        $currentType,
        $allowedTypes,
        true
    )
) {
    $currentType = '';
}


if (
    $currentSort !== 'asc'
    &&
    $currentSort !== 'desc'
) {
    $currentSort = 'desc';
}


/* =========================================================
   REDIRECT HELPER
========================================================= */

function redirectWithFilters(
    $extra = []
) {

    global
        $currentType,
        $currentSearch,
        $currentSort,
        $currentRecordDate;

    $params = [];


    if ($currentType !== '') {
        $params['type'] =
            $currentType;
    }


    if ($currentSearch !== '') {
        $params['search'] =
            $currentSearch;
    }


    $params['sort'] =
        $currentSort;


    if ($currentRecordDate !== '') {
        $params['record_date'] =
            $currentRecordDate;
    }


    foreach (
        $extra
        as
        $key => $value
    ) {
        $params[$key] =
            $value;
    }


    header(
        "Location: pharmacy_preparation.php?"
        .
        http_build_query($params)
    );

    exit();
}


/* =========================================================
   PREPARE ADMISSION SCHEDULED DOSE
========================================================= */

if (
    isset(
        $_GET['prepare_schedule']
    )
) {

    $scheduleId =
        (int)(
            $_GET['prepare_schedule']
            ?? 0
        );


    if ($scheduleId <= 0) {

        redirectWithFilters([
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
                    MO.ORDER_TYPE,
                    MO.MED_START_DATE,
                    MO.MED_END_DATE,

                    A.EXPECTED_DISCHARGE_DATE,
                    A.DISCHARGE_DATE

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
            $scheduleId
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


        if (
            empty(
                $schedule[
                    'ADMISSION_ID'
                ]
            )
        ) {

            throw new Exception(
                "Invalid admission medication schedule."
            );
        }


        $orderType =
            strtoupper(
                trim(
                    (string)(
                        $schedule[
                            'ORDER_TYPE'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            $orderType !== ''
            &&
            $orderType !== 'INPATIENT'
        ) {

            throw new Exception(
                "This medication order is not an inpatient medication order."
            );
        }


        if (
            !empty(
                $schedule[
                    'DISCHARGE_DATE'
                ]
            )
        ) {

            throw new Exception(
                "This patient has already been discharged. This record is for history only."
            );
        }


        $scheduleStatus =
            strtoupper(
                trim(
                    (string)(
                        $schedule[
                            'STATUS'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            in_array(
                $scheduleStatus,
                [
                    'CANCELLED',
                    'CANCELLED - DISCHARGED'
                ],
                true
            )
        ) {

            throw new Exception(
                "This medication dose has been cancelled and cannot be prepared."
            );
        }


        if (
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
        ) {

            throw new Exception(
                "This medication dose has already been completed."
            );
        }


        /* =================================================
           ONLY TODAY CAN BE PREPARED
        ================================================= */

        $todayCheck =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.MEDICATION_SCHEDULE

                WHERE
                    SCHEDULE_ID = ?

                AND
                    TRUNC(
                        SCHEDULE_DATE
                    )
                    =
                    TRUNC(
                        SYSDATE
                    )

            ");


        $todayCheck->execute([
            $scheduleId
        ]);


        if (
            (int)$todayCheck
                ->fetchColumn()
            === 0
        ) {

            throw new Exception(
                "Only medication scheduled for today can be prepared."
            );
        }


        /* =================================================
           ACTIVE MEDICATION PERIOD
        ================================================= */

        $periodCheck =
            $conn->prepare("

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
                    MS.SCHEDULE_ID = ?

                AND
                    A.DISCHARGE_DATE
                    IS NULL

                AND
                (
                    MO.MED_START_DATE
                    IS NULL

                    OR

                    TRUNC(
                        MS.SCHEDULE_DATE
                    )
                    >=
                    TRUNC(
                        MO.MED_START_DATE
                    )
                )

                AND
                (
                    MO.MED_END_DATE
                    IS NULL

                    OR

                    TRUNC(
                        MS.SCHEDULE_DATE
                    )
                    <=
                    TRUNC(
                        MO.MED_END_DATE
                    )
                )

                AND
                (
                    A.EXPECTED_DISCHARGE_DATE
                    IS NULL

                    OR

                    TRUNC(
                        MS.SCHEDULE_DATE
                    )
                    <=
                    TRUNC(
                        A.EXPECTED_DISCHARGE_DATE
                    )
                )

            ");


        $periodCheck->execute([
            $scheduleId
        ]);


        if (
            (int)$periodCheck
                ->fetchColumn()
            === 0
        ) {

            throw new Exception(
                "This medication dose is no longer active."
            );
        }


        /* =================================================
           ALREADY PREPARED
        ================================================= */

        $checkPrep =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.PHARMACY_PREPARATION

                WHERE
                    SCHEDULE_ID = ?

            ");


        $checkPrep->execute([
            $scheduleId
        ]);


        if (
            (int)$checkPrep
                ->fetchColumn()
            > 0
        ) {

            $conn->rollBack();


            redirectWithFilters([
                'success' =>
                    'already'
            ]);
        }


        /* =================================================
           INSERT PREPARATION
        ================================================= */

        $insertPrep =
            $conn->prepare("

                INSERT INTO
                    SYARMIMI.PHARMACY_PREPARATION
                (
                    PREP_ID,
                    STATUS,
                    PREPARED_TIME,
                    MEDORDER_ID,
                    ACCOUNT_ID,
                    SCHEDULE_ID
                )

                VALUES
                (
                    SYARMIMI.PREPARATION_SEQ.NEXTVAL,

                    'Ready For Nurse Pickup',

                    SYSDATE,

                    ?,

                    ?,

                    ?
                )

            ");


        $insertPrep->execute([

            $schedule[
                'MEDORDER_ID'
            ],

            $staffId,

            $scheduleId

        ]);


        $updateSchedule =
            $conn->prepare("

                UPDATE
                    SYARMIMI.MEDICATION_SCHEDULE

                SET
                    STATUS =
                    'Ready For Nurse Pickup'

                WHERE
                    SCHEDULE_ID = ?

            ");


        $updateSchedule->execute([
            $scheduleId
        ]);


        $conn->commit();


        redirectWithFilters([
            'success' => '1'
        ]);

    }
    catch (Throwable $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectWithFilters([

            'error' =>
                'prepare_failed',

            'message' =>
                $e->getMessage()

        ]);
    }
}


/* =========================================================
   PREPARE PATIENT-PICKUP MEDICATION
========================================================= */

if (
    isset(
        $_GET['prepare_order']
    )
) {

    $medOrderId =
        (int)(
            $_GET['prepare_order']
            ?? 0
        );


    if ($medOrderId <= 0) {

        redirectWithFilters([
            'error' => 'invalid'
        ]);
    }


    try {

        $conn->beginTransaction();


        $orderStmt =
            $conn->prepare("

                SELECT

                    MEDORDER_ID,
                    PATIENT_ID,
                    ADMISSION_ID,
                    APPOINTMENT_ID,
                    CONSULTATION_ID,
                    ORDER_TYPE

                FROM
                    SYARMIMI.MEDICATION_ORDER

                WHERE
                    MEDORDER_ID = ?

                FOR UPDATE

            ");


        $orderStmt->execute([
            $medOrderId
        ]);


        $order =
            $orderStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$order) {

            throw new Exception(
                "Medication order not found."
            );
        }


        if (
            !empty(
                $order[
                    'ADMISSION_ID'
                ]
            )
        ) {

            throw new Exception(
                "Admission medication must be prepared using its scheduled dose."
            );
        }


        $orderType =
            strtoupper(
                trim(
                    (string)(
                        $order[
                            'ORDER_TYPE'
                        ]
                        ?? ''
                    )
                )
            );


        if ($orderType === '') {

            if (
                !empty(
                    $order[
                        'APPOINTMENT_ID'
                    ]
                )
            ) {

                $orderType =
                    'APPOINTMENT';

            }
            elseif (
                !empty(
                    $order[
                        'CONSULTATION_ID'
                    ]
                )
            ) {

                $orderType =
                    'WALKIN';
            }
        }


        if (
            !in_array(
                $orderType,
                [
                    'APPOINTMENT',
                    'WALKIN',
                    'DISCHARGE'
                ],
                true
            )
        ) {

            throw new Exception(
                "Invalid patient-pickup medication order."
            );
        }


        if (
            $orderType === 'DISCHARGE'
            &&
            empty(
                $order[
                    'PATIENT_ID'
                ]
            )
        ) {

            throw new Exception(
                "Discharge medication is not linked to a patient."
            );
        }


        $checkPrep =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.PHARMACY_PREPARATION

                WHERE
                    MEDORDER_ID = ?

                AND
                    SCHEDULE_ID
                    IS NULL

            ");


        $checkPrep->execute([
            $medOrderId
        ]);


        if (
            (int)$checkPrep
                ->fetchColumn()
            > 0
        ) {

            $conn->rollBack();


            redirectWithFilters([
                'success' =>
                    'already'
            ]);
        }


        $insertPrep =
            $conn->prepare("

                INSERT INTO
                    SYARMIMI.PHARMACY_PREPARATION
                (
                    PREP_ID,
                    STATUS,
                    PREPARED_TIME,
                    MEDORDER_ID,
                    ACCOUNT_ID,
                    SCHEDULE_ID
                )

                VALUES
                (
                    SYARMIMI.PREPARATION_SEQ.NEXTVAL,

                    'Ready For Pickup',

                    SYSDATE,

                    ?,

                    ?,

                    NULL
                )

            ");


        $insertPrep->execute([

            $medOrderId,

            $staffId

        ]);


        $conn->commit();


        redirectWithFilters([
            'success' => '1'
        ]);

    }
    catch (Throwable $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectWithFilters([

            'error' =>
                'prepare_failed',

            'message' =>
                $e->getMessage()

        ]);
    }
}


/* =========================================================
   COLLECT PATIENT-PICKUP MEDICATION
========================================================= */

if (
    isset(
        $_GET['collect']
    )
) {

    $medOrderId =
        (int)(
            $_GET['collect']
            ?? 0
        );


    if ($medOrderId <= 0) {

        redirectWithFilters([
            'error' => 'invalid'
        ]);
    }


    try {

        $conn->beginTransaction();


        $orderStmt =
            $conn->prepare("

                SELECT

                    MEDORDER_ID,
                    ADMISSION_ID,
                    APPOINTMENT_ID,
                    CONSULTATION_ID,
                    ORDER_TYPE

                FROM
                    SYARMIMI.MEDICATION_ORDER

                WHERE
                    MEDORDER_ID = ?

                FOR UPDATE

            ");


        $orderStmt->execute([
            $medOrderId
        ]);


        $order =
            $orderStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$order) {

            throw new Exception(
                "Medication order not found."
            );
        }


        if (
            !empty(
                $order[
                    'ADMISSION_ID'
                ]
            )
        ) {

            throw new Exception(
                "Inpatient medication cannot be collected directly by the patient."
            );
        }


        $orderType =
            strtoupper(
                trim(
                    (string)(
                        $order[
                            'ORDER_TYPE'
                        ]
                        ?? ''
                    )
                )
            );


        if ($orderType === '') {

            if (
                !empty(
                    $order[
                        'APPOINTMENT_ID'
                    ]
                )
            ) {

                $orderType =
                    'APPOINTMENT';

            }
            elseif (
                !empty(
                    $order[
                        'CONSULTATION_ID'
                    ]
                )
            ) {

                $orderType =
                    'WALKIN';
            }
        }


        if (
            !in_array(
                $orderType,
                [
                    'APPOINTMENT',
                    'WALKIN',
                    'DISCHARGE'
                ],
                true
            )
        ) {

            throw new Exception(
                "Invalid patient-pickup medication."
            );
        }


        $prepStmt =
            $conn->prepare("

                SELECT

                    PREP_ID,
                    STATUS

                FROM
                    SYARMIMI.PHARMACY_PREPARATION

                WHERE
                    MEDORDER_ID = ?

                AND
                    SCHEDULE_ID
                    IS NULL

                ORDER BY
                    PREP_ID DESC

                FETCH FIRST
                    1 ROW ONLY

                FOR UPDATE

            ");


        $prepStmt->execute([
            $medOrderId
        ]);


        $prep =
            $prepStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$prep) {

            throw new Exception(
                "Medication has not been prepared yet."
            );
        }


        $prepStatus =
            strtoupper(
                trim(
                    (string)(
                        $prep[
                            'STATUS'
                        ]
                        ?? ''
                    )
                )
            );


        if (
            $prepStatus ===
            'COLLECTED'
        ) {

            $conn->rollBack();


            redirectWithFilters([
                'collected' =>
                    'already'
            ]);
        }


        if (
            $prepStatus !==
            'READY FOR PICKUP'
        ) {

            throw new Exception(
                "This medication is not currently ready for patient collection."
            );
        }


        $updateStmt =
            $conn->prepare("

                UPDATE
                    SYARMIMI.PHARMACY_PREPARATION

                SET
                    STATUS =
                    'Collected'

                WHERE
                    PREP_ID = ?

            ");


        $updateStmt->execute([
            $prep[
                'PREP_ID'
            ]
        ]);


        $conn->commit();


        redirectWithFilters([
            'collected' => '1'
        ]);

    }
    catch (Throwable $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectWithFilters([

            'error' =>
                'collect_failed',

            'message' =>
                $e->getMessage()

        ]);
    }
}


/* =========================================================
   PENDING ADMISSION TODAY
========================================================= */

$pendingAdmissionStmt =
    $conn->query("

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

        AND
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
        (
            MO.MED_START_DATE
            IS NULL

            OR

            TRUNC(
                MS.SCHEDULE_DATE
            )
            >=
            TRUNC(
                MO.MED_START_DATE
            )
        )

        AND
        (
            MO.MED_END_DATE
            IS NULL

            OR

            TRUNC(
                MS.SCHEDULE_DATE
            )
            <=
            TRUNC(
                MO.MED_END_DATE
            )
        )

        AND
        (
            A.EXPECTED_DISCHARGE_DATE
            IS NULL

            OR

            TRUNC(
                MS.SCHEDULE_DATE
            )
            <=
            TRUNC(
                A.EXPECTED_DISCHARGE_DATE
            )
        )

        AND NOT EXISTS
        (
            SELECT 1

            FROM
                SYARMIMI.PHARMACY_PREPARATION PP

            WHERE
                PP.SCHEDULE_ID =
                MS.SCHEDULE_ID
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
            =
            'PENDING PREPARATION'

    ");


$pendingAdmission =
    (int)$pendingAdmissionStmt
        ->fetchColumn();


/* =========================================================
   PENDING APPOINTMENT / WALK-IN
========================================================= */

$pendingOutpatientStmt =
    $conn->query("

        SELECT
            COUNT(*)

        FROM
            SYARMIMI.MEDICATION_ORDER MO

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
                'WALKIN'
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

        AND NOT EXISTS
        (
            SELECT 1

            FROM
                SYARMIMI.PHARMACY_PREPARATION PP

            WHERE
                PP.MEDORDER_ID =
                MO.MEDORDER_ID

            AND
                PP.SCHEDULE_ID
                IS NULL
        )

    ");


$pendingOutpatient =
    (int)$pendingOutpatientStmt
        ->fetchColumn();


/* =========================================================
   PENDING DISCHARGE MEDICATION
========================================================= */

$pendingDischargeStmt =
    $conn->query("

        SELECT
            COUNT(*)

        FROM
            SYARMIMI.MEDICATION_ORDER MO

        WHERE
            MO.ADMISSION_ID
            IS NULL

        AND
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

        AND NOT EXISTS
        (
            SELECT 1

            FROM
                SYARMIMI.PHARMACY_PREPARATION PP

            WHERE
                PP.MEDORDER_ID =
                MO.MEDORDER_ID

            AND
                PP.SCHEDULE_ID
                IS NULL
        )

    ");


$pendingDischarge =
    (int)$pendingDischargeStmt
        ->fetchColumn();


$pendingCount =
    $pendingAdmission
    +
    $pendingOutpatient
    +
    $pendingDischarge;


/* =========================================================
   ADMISSION MEDICATION RECORDS
========================================================= */

$admissionSql = "

    SELECT

        MS.SCHEDULE_ID,

        MO.MEDORDER_ID,

        MO.ADMISSION_ID,

        PA.NAME
        AS PATIENT_NAME,

        M.MEDICATION_NAME,

        MO.DOSAGE,

        MO.FREQUENCY,

        W.WARD_NAME,

        B.BED_NUMBER,

        'Admission'
        AS DISPLAY_ORDER_TYPE,

        MO.ORDER_TYPE
        AS DATABASE_ORDER_TYPE,

        'Nurse Pickup'
        AS COLLECTION_METHOD,

        CASE

            WHEN
                MS.SCHEDULE_DATE
                IS NOT NULL

            THEN
                TO_CHAR(
                    MS.SCHEDULE_DATE,
                    'DD-MON-YYYY'
                )

            ELSE
                '-'

        END
        AS SCHEDULE_DATE_DISPLAY,

        TO_CHAR(
            MS.SCHEDULE_DATE,
            'YYYY-MM-DD'
        )
        AS SCHEDULE_DATE_VALUE,

        TO_CHAR(
            NVL(
                MS.SCHEDULE_DATE,
                NVL(
                    MO.MED_START_DATE,
                    A.ADMISSION_DATE
                )
            ),
            'YYYY-MM-DD'
        )
        AS FILTER_DATE_VALUE,

        NVL(
            MS.SCHEDULE_TIME,
            '-'
        )
        AS SCHEDULE_TIME,

        MS.STATUS
        AS SCHEDULE_STATUS,

        PP.STATUS
        AS PREPARATION_STATUS,

        PP.PREPARED_TIME,

        MO.MED_START_DATE,

        MO.MED_END_DATE,

        A.ADMISSION_DATE,

        A.EXPECTED_DISCHARGE_DATE,

        A.DISCHARGE_DATE,

        1
        AS IS_ADMISSION,

        CASE

            WHEN
                MS.SCHEDULE_ID
                IS NULL

            THEN
                0

            ELSE
                1

        END
        AS HAS_SCHEDULE,


        /* =================================================
           FIXED SORT KEY

           MEDORDER_ID controls newest / oldest medication
           order first.

           Schedule date/time is used as secondary sorting
           within the same medication order.
        ================================================= */

        LPAD(
            TO_CHAR(
                MO.MEDORDER_ID
            ),
            12,
            '0'
        )
        ||
        NVL(
            TO_CHAR(
                MS.SCHEDULE_DATE,
                'YYYYMMDD'
            ),
            TO_CHAR(
                NVL(
                    MO.MED_START_DATE,
                    A.ADMISSION_DATE
                ),
                'YYYYMMDD'
            )
        )
        ||
        REPLACE(
            NVL(
                MS.SCHEDULE_TIME,
                '00:00'
            ),
            ':',
            ''
        )
        AS SORT_KEY

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
        SYARMIMI.MEDICATION_SCHEDULE MS

        ON
            MO.MEDORDER_ID =
            MS.MEDORDER_ID

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
    (
        SELECT

            PP1.SCHEDULE_ID,

            PP1.MEDORDER_ID,

            PP1.STATUS,

            PP1.PREPARED_TIME,

            PP1.PREP_ID

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


$admissionStmt =
    $conn->query(
        $admissionSql
    );


$admissionOrders =
    $admissionStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   APPOINTMENT / WALK-IN / DISCHARGE
========================================================= */

$outpatientSql = "

    SELECT

        NULL
        AS SCHEDULE_ID,

        MO.MEDORDER_ID,

        NULL
        AS ADMISSION_ID,

        P.NAME
        AS PATIENT_NAME,

        M.MEDICATION_NAME,

        MO.DOSAGE,

        MO.FREQUENCY,

        'Pharmacy Counter'
        AS WARD_NAME,

        '-'
        AS BED_NUMBER,

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
                'Discharge'

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
                'Appointment'

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
                'Walk-In'

            WHEN
                MO.APPOINTMENT_ID
                IS NOT NULL

            THEN
                'Appointment'

            WHEN
                MO.CONSULTATION_ID
                IS NOT NULL

            THEN
                'Walk-In'

            ELSE
                'Unknown'

        END
        AS DISPLAY_ORDER_TYPE,

        MO.ORDER_TYPE
        AS DATABASE_ORDER_TYPE,

        'Patient Pickup'
        AS COLLECTION_METHOD,

        '-'
        AS SCHEDULE_DATE_DISPLAY,

        NULL
        AS SCHEDULE_DATE_VALUE,


        /* =================================================
           DATE USED BY DATE FILTER

           IMPORTANT:
           Do not use SYSDATE for old records when
           MED_START_DATE is NULL.
        ================================================= */

        TO_CHAR(
            NVL(
                MO.MED_START_DATE,
                DATE '1900-01-01'
            ),
            'YYYY-MM-DD'
        )
        AS FILTER_DATE_VALUE,

        '-'
        AS SCHEDULE_TIME,

        NULL
        AS SCHEDULE_STATUS,

        PP.STATUS
        AS PREPARATION_STATUS,

        PP.PREPARED_TIME,

        MO.MED_START_DATE,

        MO.MED_END_DATE,

        NULL
        AS ADMISSION_DATE,

        NULL
        AS EXPECTED_DISCHARGE_DATE,

        NULL
        AS DISCHARGE_DATE,

        0
        AS IS_ADMISSION,

        0
        AS HAS_SCHEDULE,


        /* =================================================
           FIXED SORT KEY

           MEDORDER_ID is safest for determining which
           medication order was created later.

           Missing MED_START_DATE no longer becomes SYSDATE.
        ================================================= */

        LPAD(
            TO_CHAR(
                MO.MEDORDER_ID
            ),
            12,
            '0'
        )
        ||
        TO_CHAR(
            NVL(
                MO.MED_START_DATE,
                DATE '1900-01-01'
            ),
            'YYYYMMDD'
        )
        ||
        '0000'
        AS SORT_KEY

    FROM
        SYARMIMI.MEDICATION_ORDER MO

    LEFT JOIN
        SYARMIMI.PATIENT P

        ON
            MO.PATIENT_ID =
            P.PATIENT_ID

    JOIN
        SYARMIMI.MEDICATION M

        ON
            MO.MEDICATION_ID =
            M.MEDICATION_ID

    LEFT JOIN
    (
        SELECT

            PP1.MEDORDER_ID,

            PP1.STATUS,

            PP1.PREPARED_TIME,

            PP1.PREP_ID

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


$outpatientStmt =
    $conn->query(
        $outpatientSql
    );


$outpatientOrders =
    $outpatientStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


$orders =
    array_merge(
        $admissionOrders,
        $outpatientOrders
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
Prepare Medication
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


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

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


.pending-indicator{

    display:flex;

    align-items:center;

    gap:8px;

    padding:10px 14px;

    background:#fff7ed;

    border:1px solid #fed7aa;

    border-radius:10px;

    color:#c2410c;

    font-size:13px;

    font-weight:650;
}


.workflow-alert{

    display:flex;

    align-items:flex-start;

    gap:10px;

    margin-bottom:20px;

    padding:14px 16px;

    border-radius:10px;

    font-size:13px;
}


.reminder-breakdown{

    margin-top:5px;

    color:#7c2d12;

    font-size:12px;
}


.preparation-card{

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.035);
}


.card-title-clean{

    margin:0;

    color:#1f2937;

    font-size:18px;

    font-weight:700;
}


.card-subtitle-clean{

    margin-top:5px;

    margin-bottom:18px;

    color:#64748b;

    font-size:13px;
}


.date-record-box{

    margin-bottom:20px;

    padding:16px;

    background:#f8fafc;

    border:1px solid #dfe5ec;

    border-radius:10px;
}


.date-record-title{

    display:flex;

    align-items:center;

    gap:7px;

    margin-bottom:14px;

    color:#334155;

    font-size:12px;

    font-weight:700;
}


.date-record-title i{

    color:#2563eb;
}


.date-filter-grid{

    display:grid;

    grid-template-columns:
        minmax(240px,1fr)
        258px
        258px;

    gap:8px;

    align-items:end;
}


.date-action-btn{

    min-height:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    border-radius:8px;

    font-size:11px;

    font-weight:650;
}


.date-today-btn{

    border:1px solid #1d72f3;

    background:#1d72f3;

    color:#fff;
}


.date-today-btn:hover{

    background:#155fd0;

    color:#fff;
}


.date-clear-btn{

    border:1px solid #64748b;

    background:#fff;

    color:#475569;
}


.date-clear-btn:hover{

    background:#f8fafc;

    color:#0f172a;
}


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

    font-size:11px;

    font-weight:650;

    letter-spacing:.3px;

    text-transform:uppercase;
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

    padding-left:38px;
}


.form-control,
.form-select{

    min-height:44px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:13px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


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

    border-bottom:1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:700;

    letter-spacing:.2px;

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


.number-cell{

    width:45px;

    text-align:center;

    color:#64748b;

    font-weight:650;
}


.patient-name,
.medication-name{

    color:#111827;

    font-weight:650;
}


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


.badge-admission{
    background:#fff1f2;
    color:#be123c;
}

.badge-appointment{
    background:#eff6ff;
    color:#2563eb;
}

.badge-walkin{
    background:#fff7ed;
    color:#c2410c;
}

.badge-discharge{
    background:#f5f3ff;
    color:#7c3aed;
}

.badge-nurse{
    background:#ecfeff;
    color:#0e7490;
}

.badge-patient{
    background:#ecfdf5;
    color:#15803d;
}

.badge-pending{
    background:#fff7ed;
    color:#c2410c;
}

.badge-ready{
    background:#ecfdf5;
    color:#15803d;
}

.badge-collected{
    background:#eff6ff;
    color:#2563eb;
}

.badge-administered{
    background:#f3e8ff;
    color:#7e22ce;
}

.badge-discharged{
    background:#f1f5f9;
    color:#475569;
}

.badge-unscheduled{
    background:#fef3c7;
    color:#92400e;
}

.badge-cancelled{
    background:#f3f4f6;
    color:#6b7280;
}


.schedule-time{

    color:#0f172a;

    font-size:14px;

    font-weight:700;
}


.schedule-date{

    margin-top:3px;

    color:#94a3b8;

    font-size:10px;
}


.period-muted{

    color:#94a3b8;

    font-size:10px;
}


.action-btn{

    min-height:33px;

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:0 10px;

    border-radius:7px;

    font-size:10px;

    font-weight:650;

    white-space:nowrap;
}


.btn-prepare{
    border:0;
    background:#16a34a;
    color:#fff;
}

.btn-prepare:hover{
    background:#15803d;
    color:#fff;
}

.btn-collect{
    border:0;
    background:#2563eb;
    color:#fff;
}

.btn-collect:hover{
    background:#1d4ed8;
    color:#fff;
}

.action-disabled{
    background:#f3f4f6;
    border:1px solid #e5e7eb;
    color:#94a3b8;
}


.due-now{
    color:#dc2626;
    font-size:10px;
    font-weight:650;
}

.upcoming{
    color:#2563eb;
    font-size:10px;
    font-weight:650;
}

.overdue{
    color:#dc2626;
    font-size:10px;
    font-weight:650;
}


.dataTables_wrapper
.dataTables_filter{
    display:none;
}


.dataTables_wrapper
.dataTables_length{

    margin-bottom:12px;

    color:#64748b;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_info{

    padding-top:15px !important;

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


@media(max-width:1100px){

    .date-filter-grid{

        grid-template-columns:
            1fr;
    }
}


@media(max-width:900px){

    .main-content{

        padding:18px;
    }


    .page-header{

        flex-direction:column;
    }
}

</style>

</head>


<body>


<div class="d-flex">


<?php
include(
    "../includes/sidebar_pharma.php"
);
?>


<div class="main-content">


<div class="page-header">


<div>


<h1 class="page-title">

Prepare Medication

</h1>


<div class="page-subtitle">

Manage inpatient scheduled doses, appointment and walk-in prescriptions, and discharge medication for patient pickup.

</div>


</div>


<div class="pending-indicator">

<i class="bi bi-hourglass-split"></i>

Pending:

<strong>

<?= $pendingCount ?>

</strong>

</div>


</div>


<?php if (
    $pendingCount > 0
): ?>


<div class="alert alert-warning workflow-alert">


<i class="bi bi-bell-fill"></i>


<div>


<strong>

<?= $pendingCount ?>

dose(s) / medication(s) require preparation.

</strong>


<div class="reminder-breakdown">

Admission doses today:
<?= $pendingAdmission ?>

&nbsp;•&nbsp;

Appointment / Walk-In:
<?= $pendingOutpatient ?>

&nbsp;•&nbsp;

Discharge Medication:
<?= $pendingDischarge ?>

</div>


</div>


</div>


<?php else: ?>


<div class="alert alert-success workflow-alert">


<i class="bi bi-check-circle"></i>


<div>

All medication preparation tasks are completed.

</div>


</div>


<?php endif; ?>


<div class="preparation-card">


<h5 class="card-title-clean">

Medication Preparation Queue

</h5>


<div class="card-subtitle-clean">

Admission medication, historical schedules, appointment prescriptions, walk-in prescriptions and discharge medication are displayed below.

</div>


<div class="date-record-box">


<div class="date-record-title">

<i class="bi bi-calendar3"></i>

View Medication Records by Date

</div>


<div class="date-filter-grid">


<div>


<div class="filter-label">

Select Date

</div>


<input
    type="date"
    id="recordDateFilter"
    class="form-control"
    value="<?= h(
        $currentRecordDate
    ) ?>"
>


</div>


<button
    type="button"
    id="todayDateBtn"
    class="
        btn
        date-action-btn
        date-today-btn
    "
>

<i class="bi bi-calendar2-check"></i>

Today

</button>


<button
    type="button"
    id="clearDateBtn"
    class="
        btn
        date-action-btn
        date-clear-btn
    "
>

<i class="bi bi-arrow-counterclockwise"></i>

Clear

</button>


</div>


</div>


<div class="filter-box">


<div class="row g-2">


<div class="col-lg-5">


<div class="filter-label">

Search

</div>


<div class="search-wrapper">


<i class="bi bi-search"></i>


<input
    type="text"
    id="searchInput"
    class="form-control"
    placeholder="Search patient, medication, type, location or time..."
    value="<?= h(
        $currentSearch
    ) ?>"
>


</div>


</div>


<div class="col-lg-4">


<div class="filter-label">

Order Type

</div>


<select
    id="typeFilter"
    class="form-select"
>


<option value="">

All Types

</option>


<option
    value="Admission"
    <?= $currentType === 'Admission'
        ? 'selected'
        : '' ?>
>

Admission

</option>


<option
    value="Appointment"
    <?= $currentType === 'Appointment'
        ? 'selected'
        : '' ?>
>

Appointment

</option>


<option
    value="Walk-In"
    <?= $currentType === 'Walk-In'
        ? 'selected'
        : '' ?>
>

Walk-In

</option>


<option
    value="Discharge"
    <?= $currentType === 'Discharge'
        ? 'selected'
        : '' ?>
>

Discharge

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


<option
    value="desc"
    <?= $currentSort === 'desc'
        ? 'selected'
        : '' ?>
>

Newest First

</option>


<option
    value="asc"
    <?= $currentSort === 'asc'
        ? 'selected'
        : '' ?>
>

Oldest First

</option>


</select>


</div>


</div>


</div>


<div class="table-responsive">


<table
    id="medicationTable"
    class="table"
>


<thead>


<tr>

<th>No.</th>
<th>Patient</th>
<th>Type</th>
<th>Collection</th>
<th>Location</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Scheduled</th>
<th>Status</th>
<th>Action</th>
<th>Sort Key</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $orders
    as
    $row
): ?>


<?php


$isAdmission =
    (int)(
        $row[
            'IS_ADMISSION'
        ]
        ?? 0
    )
    === 1;


$hasSchedule =
    (int)(
        $row[
            'HAS_SCHEDULE'
        ]
        ?? 0
    )
    === 1;


$isDischarged =
    $isAdmission
    &&
    !empty(
        $row[
            'DISCHARGE_DATE'
        ]
    );


$orderType =
    $row[
        'DISPLAY_ORDER_TYPE'
    ]
    ?? 'Unknown';


$isDischargeMedication =
    $orderType
    ===
    'Discharge';


$recordDate =
    $row[
        'FILTER_DATE_VALUE'
    ]
    ?? '';


$isTodaySchedule =
    $isAdmission
    &&
    $hasSchedule
    &&
    !empty(
        $row[
            'SCHEDULE_DATE_VALUE'
        ]
    )
    &&
    $row[
        'SCHEDULE_DATE_VALUE'
    ]
    ===
    date('Y-m-d');


$scheduleStatusUpper =
    strtoupper(
        trim(
            (string)(
                $row[
                    'SCHEDULE_STATUS'
                ]
                ?? ''
            )
        )
    );


$prepStatusUpper =
    strtoupper(
        trim(
            (string)(
                $row[
                    'PREPARATION_STATUS'
                ]
                ?? ''
            )
        )
    );


if (
    $isAdmission
    &&
    !$hasSchedule
) {

    $displayStatus =
        'Not Scheduled';

}
elseif (
    $isAdmission
    &&
    in_array(
        $scheduleStatusUpper,
        [
            'CANCELLED',
            'CANCELLED - DISCHARGED'
        ],
        true
    )
) {

    $displayStatus =
        'Cancelled - Discharged';

}
elseif ($isAdmission) {

    if (
        $prepStatusUpper !== ''
    ) {

        if (
            $prepStatusUpper ===
            'COLLECTED'
        ) {

            $displayStatus =
                'Collected By Nurse';

        }
        else {

            $displayStatus =
                $row[
                    'PREPARATION_STATUS'
                ];
        }

    }
    elseif (
        in_array(
            $scheduleStatusUpper,
            [
                'ADMINISTERED',
                'GIVEN',
                'DELIVERED',
                'COMPLETED'
            ],
            true
        )
    ) {

        $displayStatus =
            'Administered';

    }
    elseif (
        $scheduleStatusUpper ===
        'COLLECTED BY NURSE'
    ) {

        $displayStatus =
            'Collected By Nurse';

    }
    else {

        $displayStatus =
            'Pending Preparation';
    }

}
else {

    $displayStatus =
        $row[
            'PREPARATION_STATUS'
        ]
        ??
        'Pending Preparation';
}


$timeIndicator =
    '';


if (
    $isAdmission
    &&
    $hasSchedule
    &&
    $isTodaySchedule
    &&
    !$isDischarged
    &&
    $displayStatus ===
        'Pending Preparation'
    &&
    !empty(
        $row[
            'SCHEDULE_TIME'
        ]
    )
    &&
    $row[
        'SCHEDULE_TIME'
    ]
    !== '-'
) {

    $nowHm =
        date('H:i');


    $scheduleHm =
        substr(
            $row[
                'SCHEDULE_TIME'
            ],
            0,
            5
        );


    $currentTs =
        strtotime(
            date('Y-m-d')
            .
            ' '
            .
            $nowHm
        );


    $scheduledTs =
        strtotime(
            date('Y-m-d')
            .
            ' '
            .
            $scheduleHm
        );


    $differenceMinutes =
        (
            $scheduledTs
            -
            $currentTs
        )
        /
        60;


    if (
        abs(
            $differenceMinutes
        )
        <= 30
    ) {

        $timeIndicator =
            'Due Now';

    }
    elseif (
        $differenceMinutes
        > 30
    ) {

        $timeIndicator =
            'Upcoming';

    }
    else {

        $timeIndicator =
            'Overdue';
    }
}

?>


<tr

    data-order-type="<?= h(
        $orderType
    ) ?>"

    data-record-date="<?= h(
        $recordDate
    ) ?>"

>


<td class="number-cell"></td>


<td>


<span class="patient-name">

<?= h(
    $row[
        'PATIENT_NAME'
    ]
    ??
    'Unknown Patient'
) ?>

</span>


<?php if (
    $isDischarged
): ?>


<div class="mt-1">

<span class="status-badge badge-discharged">

<i class="bi bi-box-arrow-right"></i>

Discharged

</span>

</div>


<?php endif; ?>


</td>


<td>


<?php if (
    $orderType ===
    'Admission'
): ?>


<span class="status-badge badge-admission">

<i class="bi bi-hospital"></i>

Admission

</span>


<?php elseif (
    $orderType ===
    'Appointment'
): ?>


<span class="status-badge badge-appointment">

<i class="bi bi-calendar-event"></i>

Appointment

</span>


<?php elseif (
    $orderType ===
    'Walk-In'
): ?>


<span class="status-badge badge-walkin">

<i class="bi bi-person-walking"></i>

Walk-In

</span>


<?php elseif (
    $orderType ===
    'Discharge'
): ?>


<span class="status-badge badge-discharge">

<i class="bi bi-house-check"></i>

Discharge

</span>


<?php else: ?>


<span class="status-badge">

Unknown

</span>


<?php endif; ?>


</td>


<td>


<?php if (
    $isAdmission
): ?>


<span class="status-badge badge-nurse">

<i class="bi bi-person-badge"></i>

Nurse Pickup

</span>


<?php else: ?>


<span class="status-badge badge-patient">

<i class="bi bi-person-check"></i>

Patient Pickup

</span>


<?php endif; ?>


</td>


<td>


<?= h(
    $row[
        'WARD_NAME'
    ]
    ?? '-'
) ?>


<?php if (
    !empty(
        $row[
            'BED_NUMBER'
        ]
    )
    &&
    $row[
        'BED_NUMBER'
    ]
    !== '-'
): ?>


<div class="period-muted">

Bed
<?= h(
    $row[
        'BED_NUMBER'
    ]
) ?>

</div>


<?php endif; ?>


<?php if (
    $isDischarged
): ?>


<div class="period-muted mt-1">

<i class="bi bi-clock-history"></i>

Past Admission

</div>


<?php elseif (
    $isDischargeMedication
): ?>


<div class="period-muted mt-1">

<i class="bi bi-house-check"></i>

Take-home Medication

</div>


<?php endif; ?>


</td>


<td>


<span class="medication-name">

<?= h(
    $row[
        'MEDICATION_NAME'
    ]
) ?>

</span>


</td>


<td>

<?= h(
    $row[
        'DOSAGE'
    ]
    ?? '-'
) ?>

</td>


<td>

<?= h(
    $row[
        'FREQUENCY'
    ]
    ?? '-'
) ?>

</td>


<td>


<?php if (
    $isAdmission
    &&
    !$hasSchedule
): ?>


<span class="status-badge badge-unscheduled">

<i class="bi bi-calendar-x"></i>

Not Scheduled

</span>


<?php elseif (
    $isAdmission
): ?>


<div class="schedule-time">

<?= h(
    $row[
        'SCHEDULE_TIME'
    ]
    ?? '-'
) ?>

</div>


<div class="schedule-date">

<?= h(
    $row[
        'SCHEDULE_DATE_DISPLAY'
    ]
    ?? '-'
) ?>

</div>


<?php if (
    $displayStatus
    ===
    'Pending Preparation'
    &&
    $isTodaySchedule
    &&
    !$isDischarged
): ?>


<?php if (
    $timeIndicator
    ===
    'Due Now'
): ?>


<div class="due-now mt-1">

<i class="bi bi-clock-fill"></i>

Due Now

</div>


<?php elseif (
    $timeIndicator
    ===
    'Overdue'
): ?>


<div class="overdue mt-1">

<i class="bi bi-exclamation-circle"></i>

Overdue

</div>


<?php else: ?>


<div class="upcoming mt-1">

<i class="bi bi-clock"></i>

Upcoming

</div>


<?php endif; ?>


<?php elseif (
    $isDischarged
): ?>


<div class="period-muted mt-1">

Historical record

</div>


<?php elseif (
    $displayStatus
    ===
    'Cancelled - Discharged'
): ?>


<div class="period-muted mt-1">

Cancelled after discharge

</div>


<?php elseif (
    $displayStatus
    ===
    'Pending Preparation'
    &&
    !$isTodaySchedule
): ?>


<div class="period-muted mt-1">

Scheduled record

</div>


<?php endif; ?>


<?php else: ?>


<span class="period-muted">

<?= $isDischargeMedication
    ?
    'Take-home prescription'
    :
    'One-time prescription'
?>

</span>


<?php endif; ?>


</td>


<td>


<?php if (
    $displayStatus
    ===
    'Not Scheduled'
): ?>


<span class="status-badge badge-unscheduled">

<i class="bi bi-calendar-x"></i>

Not Scheduled

</span>


<?php elseif (
    $displayStatus
    ===
    'Pending Preparation'
): ?>


<span class="status-badge badge-pending">

<i class="bi bi-hourglass-split"></i>

Pending

</span>


<?php elseif (
    $displayStatus
    ===
    'Ready For Nurse Pickup'
): ?>


<span class="status-badge badge-ready">

<i class="bi bi-check-circle"></i>

Ready For Nurse

</span>


<?php elseif (
    $displayStatus
    ===
    'Ready For Pickup'
): ?>


<span class="status-badge badge-ready">

<i class="bi bi-check-circle"></i>

Ready For Pickup

</span>


<?php elseif (
    $displayStatus
    ===
    'Collected'
): ?>


<span class="status-badge badge-collected">

<i class="bi bi-check2-all"></i>

Collected

</span>


<?php elseif (
    $displayStatus
    ===
    'Collected By Nurse'
): ?>


<span class="status-badge badge-collected">

<i class="bi bi-person-check"></i>

Collected By Nurse

</span>


<?php elseif (
    $displayStatus
    ===
    'Administered'
): ?>


<span class="status-badge badge-administered">

<i class="bi bi-check2-circle"></i>

Administered

</span>


<?php elseif (
    $displayStatus
    ===
    'Cancelled - Discharged'
): ?>


<span class="status-badge badge-cancelled">

<i class="bi bi-x-circle"></i>

Cancelled - Discharged

</span>


<?php else: ?>


<span class="status-badge">

<?= h(
    $displayStatus
) ?>

</span>


<?php endif; ?>


</td>


<td>


<?php if (
    $isAdmission
    &&
    $displayStatus
    ===
    'Cancelled - Discharged'
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-x-circle"></i>

Cancelled

</span>


<?php elseif (
    $isAdmission
    &&
    $isDischarged
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-clock-history"></i>

History

</span>


<?php elseif (
    $isAdmission
    &&
    !$hasSchedule
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-calendar-x"></i>

Waiting Schedule

</span>


<?php elseif (
    $isAdmission
    &&
    $displayStatus
    ===
    'Pending Preparation'
    &&
    $isTodaySchedule
): ?>


<a
    href="?prepare_schedule=<?= urlencode(
        $row[
            'SCHEDULE_ID'
        ]
    ) ?>&type=<?= urlencode(
        $currentType
    ) ?>&search=<?= urlencode(
        $currentSearch
    ) ?>&sort=<?= urlencode(
        $currentSort
    ) ?>&record_date=<?= urlencode(
        $currentRecordDate
    ) ?>"
    class="
        action-btn
        btn-prepare
        text-decoration-none
        prepareBtn
    "
>


<i class="bi bi-box-seam"></i>

Prepare Dose


</a>


<?php elseif (
    $isAdmission
    &&
    $displayStatus
    ===
    'Pending Preparation'
    &&
    !$isTodaySchedule
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-eye"></i>

View Only

</span>


<?php elseif (
    !$isAdmission
    &&
    $displayStatus
    ===
    'Pending Preparation'
): ?>


<a
    href="?prepare_order=<?= urlencode(
        $row[
            'MEDORDER_ID'
        ]
    ) ?>&type=<?= urlencode(
        $currentType
    ) ?>&search=<?= urlencode(
        $currentSearch
    ) ?>&sort=<?= urlencode(
        $currentSort
    ) ?>&record_date=<?= urlencode(
        $currentRecordDate
    ) ?>"
    class="
        action-btn
        btn-prepare
        text-decoration-none
        prepareBtn
    "
>


<i class="bi bi-box-seam"></i>

Prepare


</a>


<?php elseif (
    !$isAdmission
    &&
    $displayStatus
    ===
    'Ready For Pickup'
): ?>


<a
    href="?collect=<?= urlencode(
        $row[
            'MEDORDER_ID'
        ]
    ) ?>&type=<?= urlencode(
        $currentType
    ) ?>&search=<?= urlencode(
        $currentSearch
    ) ?>&sort=<?= urlencode(
        $currentSort
    ) ?>&record_date=<?= urlencode(
        $currentRecordDate
    ) ?>"
    class="
        action-btn
        btn-collect
        text-decoration-none
        collectBtn
    "
>


<i class="bi bi-check-circle"></i>

Collected


</a>


<?php elseif (
    $isAdmission
    &&
    $displayStatus
    ===
    'Ready For Nurse Pickup'
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-hourglass"></i>

Waiting Nurse

</span>


<?php elseif (
    $isAdmission
    &&
    $displayStatus
    ===
    'Collected By Nurse'
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-person-check"></i>

With Nurse

</span>


<?php elseif (
    $displayStatus
    ===
    'Administered'
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-check2-circle"></i>

Completed

</span>


<?php elseif (
    $displayStatus
    ===
    'Collected'
): ?>


<span class="action-btn action-disabled">

<i class="bi bi-check2-all"></i>

Completed

</span>


<?php else: ?>


<span class="action-btn action-disabled">

No Action

</span>


<?php endif; ?>


</td>


<td>

<?= h(
    $row[
        'SORT_KEY'
    ]
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
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js">
</script>


<script>


$(document).ready(
function()
{


    let selectedOrderType =
        $('#typeFilter')
            .val()
        ||
        '';


    let selectedRecordDate =
        $('#recordDateFilter')
            .val()
        ||
        '';


    /* =====================================================
       CUSTOM TYPE + DATE FILTER
    ===================================================== */

    $.fn.dataTable.ext.search.push(
        function(
            settings,
            data,
            dataIndex
        )
        {

            if (
                settings.nTable.id
                !==
                'medicationTable'
            ) {

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


            const rowType =
                row.getAttribute(
                    'data-order-type'
                )
                ||
                '';


            const rowRecordDate =
                row.getAttribute(
                    'data-record-date'
                )
                ||
                '';


            /* =============================================
               TYPE FILTER
            ============================================= */

            if (
                selectedOrderType
                !== ''
                &&
                rowType
                !==
                selectedOrderType
            ) {

                return false;
            }


            /* =============================================
               DATE FILTER
            ============================================= */

            if (
                selectedRecordDate
                !== ''
                &&
                rowRecordDate
                !==
                selectedRecordDate
            ) {

                return false;
            }


            return true;
        }
    );


    /* =====================================================
       DATATABLE
    ===================================================== */

    const table =
        $('#medicationTable')
            .DataTable({

                pageLength:
                    10,

                lengthMenu:
                [
                    [10,25,50,100],
                    [10,25,50,100]
                ],


                /*
                 * COLUMN INDEX
                 *
                 * 0  = No.
                 * 1  = Patient
                 * 2  = Type
                 * 3  = Collection
                 * 4  = Location
                 * 5  = Medication
                 * 6  = Dosage
                 * 7  = Frequency
                 * 8  = Scheduled
                 * 9  = Status
                 * 10 = Action
                 * 11 = Hidden SORT KEY
                 */

                order:
                [
                    [
                        11,
                        <?= json_encode(
                            $currentSort
                        ) ?>
                    ]
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
                            10,

                        orderable:
                            false,

                        searchable:
                            false
                    },


                    {
                        targets:
                            11,

                        visible:
                            false,

                        searchable:
                            false,

                        type:
                            'string'
                    }

                ],


                searching:
                    true,

                paging:
                    true,

                info:
                    true,

                orderMulti:
                    false,


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
                                    info.start
                                    +
                                    index
                                    +
                                    1;
                            }
                        );
                }

            });


    /* =====================================================
       RESTORE SEARCH
    ===================================================== */

    const previousSearch =
        <?= json_encode(
            $currentSearch
        ) ?>;


    if (
        previousSearch
        !== ''
    ) {

        $('#searchInput')
            .val(
                previousSearch
            );


        table
            .search(
                previousSearch
            )
            .page(
                'first'
            )
            .draw();
    }


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#searchInput').on(
        'input',
        function()
        {

            const value =
                this.value
                    .trim();


            table
                .search(
                    value
                )
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       DATE
    ===================================================== */

    $('#recordDateFilter').on(
        'change',
        function()
        {

            selectedRecordDate =
                this.value
                ||
                '';


            table
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       TODAY
    ===================================================== */

    $('#todayDateBtn').on(
        'click',
        function()
        {

            const now =
                new Date();


            const year =
                now.getFullYear();


            const month =
                String(
                    now.getMonth()
                    +
                    1
                )
                .padStart(
                    2,
                    '0'
                );


            const day =
                String(
                    now.getDate()
                )
                .padStart(
                    2,
                    '0'
                );


            const today =
                `${year}-${month}-${day}`;


            $('#recordDateFilter')
                .val(
                    today
                );


            selectedRecordDate =
                today;


            table
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       CLEAR DATE
    ===================================================== */

    $('#clearDateBtn').on(
        'click',
        function()
        {

            $('#recordDateFilter')
                .val('');


            selectedRecordDate =
                '';


            table
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       TYPE
    ===================================================== */

    $('#typeFilter').on(
        'change',
        function()
        {

            selectedOrderType =
                this.value
                ||
                '';


            table
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       SORT
    ===================================================== */

    $('#sortFilter').on(
        'change',
        function()
        {

            const direction =
                this.value ===
                'asc'
                ?
                'asc'
                :
                'desc';


            table
                .order([
                    [
                        11,
                        direction
                    ]
                ])
                .page(
                    'first'
                )
                .draw();
        }
    );


    /* =====================================================
       BUILD ACTION URL USING CURRENT LIVE FILTERS
    ===================================================== */

    function buildActionUrlWithCurrentFilters(
        originalUrl
    )
    {

        const actionUrl =
            new URL(
                originalUrl,
                window.location.href
            );


        const liveType =
            $('#typeFilter')
                .val()
            ||
            '';


        const liveSearch =
            $('#searchInput')
                .val()
                .trim()
            ||
            '';


        const liveSort =
            $('#sortFilter')
                .val()
            ||
            'desc';


        const liveRecordDate =
            $('#recordDateFilter')
                .val()
            ||
            '';


        /* =============================================
           TYPE
        ============================================= */

        if (
            liveType !== ''
        ) {

            actionUrl
                .searchParams
                .set(
                    'type',
                    liveType
                );

        }
        else {

            actionUrl
                .searchParams
                .delete(
                    'type'
                );
        }


        /* =============================================
           SEARCH
        ============================================= */

        if (
            liveSearch !== ''
        ) {

            actionUrl
                .searchParams
                .set(
                    'search',
                    liveSearch
                );

        }
        else {

            actionUrl
                .searchParams
                .delete(
                    'search'
                );
        }


        /* =============================================
           SORT
        ============================================= */

        actionUrl
            .searchParams
            .set(
                'sort',
                liveSort
            );


        /* =============================================
           RECORD DATE
        ============================================= */

        if (
            liveRecordDate !== ''
        ) {

            actionUrl
                .searchParams
                .set(
                    'record_date',
                    liveRecordDate
                );

        }
        else {

            actionUrl
                .searchParams
                .delete(
                    'record_date'
                );
        }


        return actionUrl.toString();
    }


    /* =====================================================
       PREPARE
    ===================================================== */

    $(document).on(
        'click',
        '.prepareBtn',
        function(event)
        {

            event.preventDefault();


            const url =
                buildActionUrlWithCurrentFilters(
                    this.href
                );


            Swal.fire({

                icon:
                    'question',

                title:
                    'Prepare Medication?',

                text:
                    'Confirm that this medication should be prepared.',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Prepare',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#16a34a',

                cancelButtonColor:
                    '#64748b'

            })
            .then(
                function(result)
                {

                    if (
                        result.isConfirmed
                    ) {

                        window.location.href =
                            url;
                    }
                }
            );
        }
    );


    /* =====================================================
       COLLECT
    ===================================================== */

    $(document).on(
        'click',
        '.collectBtn',
        function(event)
        {

            event.preventDefault();


            const url =
                buildActionUrlWithCurrentFilters(
                    this.href
                );


            Swal.fire({

                icon:
                    'question',

                title:
                    'Confirm Collection',

                text:
                    'Confirm that the medication has been handed to the patient.',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Collected',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#2563eb',

                cancelButtonColor:
                    '#64748b'

            })
            .then(
                function(result)
                {

                    if (
                        result.isConfirmed
                    ) {

                        window.location.href =
                            url;
                    }
                }
            );
        }
    );

}
);


/* =========================================================
   PREPARE SUCCESS
========================================================= */

<?php if (
    isset(
        $_GET[
            'success'
        ]
    )
): ?>


document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        <?php if (
            $_GET[
                'success'
            ]
            ===
            'already'
        ): ?>


        Swal.fire({

            icon:
                'info',

            title:
                'Already Prepared',

            text:
                'This medication has already been prepared.',

            confirmButtonColor:
                '#2563eb'
        });


        <?php else: ?>


        Swal.fire({

            icon:
                'success',

            title:
                'Medication Ready',

            text:
                'The medication has been prepared successfully.',

            confirmButtonColor:
                '#16a34a'
        });


        <?php endif; ?>

    }
);


<?php endif; ?>


/* =========================================================
   COLLECT SUCCESS
========================================================= */

<?php if (
    isset(
        $_GET[
            'collected'
        ]
    )
): ?>


document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        <?php if (
            $_GET[
                'collected'
            ]
            ===
            'already'
        ): ?>


        Swal.fire({

            icon:
                'info',

            title:
                'Already Collected',

            text:
                'This medication has already been collected.',

            confirmButtonColor:
                '#2563eb'
        });


        <?php else: ?>


        Swal.fire({

            icon:
                'success',

            title:
                'Medication Collected',

            text:
                'Medication has been marked as collected successfully.',

            confirmButtonColor:
                '#2563eb'
        });


        <?php endif; ?>

    }
);


<?php endif; ?>


/* =========================================================
   ERROR POPUP
========================================================= */

<?php if (
    isset(
        $_GET['error']
    )
): ?>


document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        Swal.fire({

            icon:
                'error',

            title:
                'Unable to Process Medication',

            text:
                <?= json_encode(
                    $_GET[
                        'message'
                    ]
                    ??
                    'Unable to process medication.'
                ) ?>,

            confirmButtonColor:
                '#dc2626',

            confirmButtonText:
                'Close'
        });

    }
);


<?php endif; ?>


</script>


</body>

</html>