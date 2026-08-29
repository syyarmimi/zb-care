<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    die("Access Denied");
}


$doctor_id =
    (int)($_SESSION['user_id'] ?? 0);


if ($doctor_id <= 0) {
    die("Invalid doctor account.");
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
   CHECK ORACLE COLUMN TYPE

   Important because APPOINTMENT_DATE in this system may
   contain old VARCHAR2 date values such as:

   21-AUG-26
   2026-08-21

   while other date columns are Oracle DATE.
========================================================= */

function getColumnType(
    PDO $conn,
    string $table,
    string $column
): string {

    $stmt = $conn->prepare("
        SELECT DATA_TYPE
        FROM ALL_TAB_COLUMNS
        WHERE OWNER = 'SYARMIMI'
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");

    $stmt->execute([
        strtoupper($table),
        strtoupper($column)
    ]);

    return strtoupper(
        trim(
            (string)($stmt->fetchColumn() ?: '')
        )
    );
}


$appointmentDateType =
    getColumnType(
        $conn,
        'APPOINTMENT',
        'APPOINTMENT_DATE'
    );


$appointmentDateIsOracleDate = false; // APPOINTMENT_DATE is VARCHAR2 in current schema


/* =========================================================
   CHECK DOB COLUMN TYPE TOO
   Prevents ORA-01858 when old DOB text is copied into a DATE.
========================================================= */

$appointmentDobType =
    getColumnType(
        $conn,
        'APPOINTMENT',
        'DOB'
    );

$appointmentDobIsOracleDate = false; // DOB is VARCHAR2 in current schema


/* =========================================================
   SQL EXPRESSION FOR APPOINTMENT_DATE

   Only used when APPOINTMENT_DATE is VARCHAR2.

   IMPORTANT:
   VALIDATE_CONVERSION prevents ORA-01858 from bad historical
   date strings.
========================================================= */

function appointmentDateStringExpression(
    string $column
): string {

    return "
        CASE

            /* =============================================
               2026-08-28
            ============================================= */

            WHEN REGEXP_LIKE(
                TRIM($column),
                '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            )
            AND VALIDATE_CONVERSION(
                TRIM($column)
                AS DATE,
                'YYYY-MM-DD'
            ) = 1

            THEN TO_DATE(
                TRIM($column),
                'YYYY-MM-DD'
            )


            /* =============================================
               28-AUG-26
            ============================================= */

            WHEN REGEXP_LIKE(
                UPPER(TRIM($column)),
                '^[0-9]{2}-[A-Z]{3}-[0-9]{2}$'
            )
            AND VALIDATE_CONVERSION(
                UPPER(TRIM($column))
                AS DATE,
                'DD-MON-RR',
                'NLS_DATE_LANGUAGE=ENGLISH'
            ) = 1

            THEN TO_DATE(
                UPPER(TRIM($column)),
                'DD-MON-RR',
                'NLS_DATE_LANGUAGE=ENGLISH'
            )


            /* =============================================
               28-AUG-2026
            ============================================= */

            WHEN REGEXP_LIKE(
                UPPER(TRIM($column)),
                '^[0-9]{2}-[A-Z]{3}-[0-9]{4}$'
            )
            AND VALIDATE_CONVERSION(
                UPPER(TRIM($column))
                AS DATE,
                'DD-MON-YYYY',
                'NLS_DATE_LANGUAGE=ENGLISH'
            ) = 1

            THEN TO_DATE(
                UPPER(TRIM($column)),
                'DD-MON-YYYY',
                'NLS_DATE_LANGUAGE=ENGLISH'
            )


            /* =============================================
               28/08/2026
            ============================================= */

            WHEN REGEXP_LIKE(
                TRIM($column),
                '^[0-9]{2}/[0-9]{2}/[0-9]{4}$'
            )
            AND VALIDATE_CONVERSION(
                TRIM($column)
                AS DATE,
                'DD/MM/YYYY'
            ) = 1

            THEN TO_DATE(
                TRIM($column),
                'DD/MM/YYYY'
            )


            ELSE NULL

        END
    ";
}


/* =========================================================
   FORMAT APPOINTMENT DATE FOR HTML
========================================================= */

function displayAppointmentDate($value)
{
    if (empty($value)) {
        return '-';
    }

    $formats = [
        'Y-m-d',
        'd-M-y',
        'd-M-Y',
        'd/m/Y',
        'Y-m-d H:i:s'
    ];

    foreach ($formats as $format) {

        $date =
            DateTime::createFromFormat(
                $format,
                strtoupper(trim($value))
            );

        if ($date) {
            return strtoupper(
                $date->format('d-M-y')
            );
        }
    }

    $timestamp =
        strtotime($value);

    if ($timestamp !== false) {

        return strtoupper(
            date(
                'd-M-y',
                $timestamp
            )
        );
    }

    return (string)$value;
}


/* =========================================================
   NORMALIZE FLEXIBLE DATE TO YYYY-MM-DD
   Accepts historical formats without relying on Oracle NLS.
========================================================= */

function normalizeFlexibleDate($value): ?string
{
    $value = trim((string)($value ?? ''));

    if ($value === '') {
        return null;
    }

    $formats = [
        'Y-m-d',
        'd-M-y',
        'd-M-Y',
        'd/m/Y',
        'Y-m-d H:i:s',
        'd-M-y H:i:s',
        'd-M-Y H:i:s'
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat(
            $format,
            strtoupper($value)
        );

        if ($date instanceof DateTime) {
            $errors = DateTime::getLastErrors();

            if ($errors === false ||
                (($errors['warning_count'] ?? 0) === 0 &&
                 ($errors['error_count'] ?? 0) === 0)) {
                return $date->format('Y-m-d');
            }
        }
    }

    $timestamp = strtotime($value);

    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }

    return null;
}


/* =========================================================
   MEDICATION FREQUENCY -> TIMES
========================================================= */

function getMedicationTimes($frequency)
{
    switch (trim($frequency)) {

        case 'Once Daily':
            return [
                '08:00'
            ];


        case 'Twice Daily':
            return [
                '08:00',
                '20:00'
            ];


        case 'Three Times Daily':
            return [
                '08:00',
                '14:00',
                '20:00'
            ];


        case 'Four Times Daily':
            return [
                '06:00',
                '12:00',
                '18:00',
                '00:00'
            ];


        case 'Every 6 Hours':
            return [
                '06:00',
                '12:00',
                '18:00',
                '00:00'
            ];


        case 'Every 8 Hours':
            return [
                '06:00',
                '14:00',
                '22:00'
            ];


        case 'Every 12 Hours':
            return [
                '08:00',
                '20:00'
            ];


        /*
         No automatic fixed schedule for PRN.
        */

        case 'As Needed':
            return [];


        default:
            return [];
    }
}


/* =========================================================
   GENERATE MEDICATION SCHEDULE
========================================================= */

function generateMedicationSchedule(
    PDO $conn,
    int $medorderId,
    string $startDate,
    string $endDate,
    string $frequency
) {

    $times =
        getMedicationTimes(
            $frequency
        );


    if (empty($times)) {
        return;
    }


    try {

        $start =
            new DateTime(
                $startDate
            );


        $end =
            new DateTime(
                $endDate
            );

    }
    catch (Exception $e) {

        throw new Exception(
            "Invalid medication schedule date."
        );
    }


    if ($end < $start) {

        throw new Exception(
            "Medication end date cannot be earlier than its start date."
        );
    }


    /*
     Include expected discharge/end date.
    */

    $endInclusive =
        clone $end;


    $endInclusive->modify(
        '+1 day'
    );


    $period =
        new DatePeriod(
            $start,
            new DateInterval(
                'P1D'
            ),
            $endInclusive
        );


    $insertSchedule =
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
                SYARMIMI.MEDICATION_SCHEDULE_SEQ.NEXTVAL,
                ?,
                TRUNC(SYSDATE) + ?,
                ?,
                'Pending Preparation'
            )

        ");


    foreach (
        $period
        as
        $date
    ) {

        /*
         * Avoid TO_DATE(?) through PDO_ODBC.  Oracle receives only a
         * numeric day offset, so NLS/date-string conversion cannot fail.
         */
        $scheduleOffset = (int)$start->diff($date)->format('%a');


        foreach (
            $times
            as
            $time
        ) {

            $insertSchedule->execute([
                $medorderId,
                $scheduleOffset,
                $time
            ]);
        }
    }
}


/* =========================================================
   REGENERATE NEW / UNUSED SCHEDULE
========================================================= */

function regenerateMedicationSchedule(
    PDO $conn,
    int $medorderId,
    string $startDate,
    string $endDate,
    string $frequency
) {

    /*
     Delete only schedule rows that pharmacy/nurse has not
     already used.
    */

    $delete =
        $conn->prepare("

            DELETE FROM
                SYARMIMI.MEDICATION_SCHEDULE MS

            WHERE
                MS.MEDORDER_ID = ?

            AND NOT EXISTS
            (
                SELECT 1

                FROM
                    SYARMIMI.PHARMACY_PREPARATION PP

                WHERE
                    PP.SCHEDULE_ID =
                    MS.SCHEDULE_ID
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

        ");


    $delete->execute([
        $medorderId
    ]);


    $check =
        $conn->prepare("

            SELECT COUNT(*)

            FROM
                SYARMIMI.MEDICATION_SCHEDULE

            WHERE
                MEDORDER_ID = ?

        ");


    $check->execute([
        $medorderId
    ]);


    if (
        (int)$check->fetchColumn()
        === 0
    ) {

        generateMedicationSchedule(
            $conn,
            $medorderId,
            $startDate,
            $endDate,
            $frequency
        );
    }
}



/* =========================================================
   OUTPATIENT BILLING

   Billing rules:
   - Appointment consultation = RM 100.00
   - Walk-In consultation     = RM 120.00
   - Each prescribed outpatient medication is charged once
     using MEDICATION.PRICE.
   - Admit Patient does NOT generate a bill here.
     Admission billing will be generated only on discharge.
========================================================= */

function generateOutpatientBill(
    PDO $conn,
    int $patientId,
    ?int $appointmentId,
    ?int $consultationId,
    string $encounterType
): int {

    $encounterType =
        strtoupper(
            trim(
                $encounterType
            )
        );


    if (
        !in_array(
            $encounterType,
            [
                'APPOINTMENT',
                'WALKIN'
            ],
            true
        )
    ) {
        throw new Exception(
            "Invalid outpatient billing type."
        );
    }


    /* =====================================================
       PREVENT DUPLICATE BILL
    ===================================================== */

    if (
        $encounterType ===
        'APPOINTMENT'
    ) {

        if (
            !$appointmentId
        ) {
            throw new Exception(
                "Appointment ID is required for billing."
            );
        }


        $existingBillStmt =
            $conn->prepare("

                SELECT
                    BILL_ID

                FROM
                    SYARMIMI.BILL

                WHERE
                    APPOINTMENT_ID = ?

                FETCH FIRST
                    1 ROW ONLY

            ");


        $existingBillStmt->execute([
            $appointmentId
        ]);

    }
    else {

        if (
            !$consultationId
        ) {
            throw new Exception(
                "Consultation ID is required for billing."
            );
        }


        $existingBillStmt =
            $conn->prepare("

                SELECT
                    BILL_ID

                FROM
                    SYARMIMI.BILL

                WHERE
                    CONSULTATION_ID = ?

                FETCH FIRST
                    1 ROW ONLY

            ");


        $existingBillStmt->execute([
            $consultationId
        ]);
    }


    $existingBillId =
        $existingBillStmt
            ->fetchColumn();


    if ($existingBillId) {

        return (int)$existingBillId;
    }


    /* =====================================================
       CONSULTATION CHARGE
    ===================================================== */

    if (
        $encounterType ===
        'APPOINTMENT'
    ) {

        $consultationFee =
            100.00;


        $consultationDescription =
            'Specialist Appointment Consultation';

    }
    else {

        $consultationFee =
            120.00;


        $consultationDescription =
            'Walk-In Specialist Consultation';
    }


    /* =====================================================
       CREATE BILL
    ===================================================== */

    $insertBill =
        $conn->prepare("

            INSERT INTO
                SYARMIMI.BILL
            (
                BILL_ID,
                PATIENT_ID,
                APPOINTMENT_ID,
                CONSULTATION_ID,
                ADMISSION_ID,
                BILL_DATE,
                TOTAL_AMOUNT,
                STATUS
            )

            VALUES
            (
                SYARMIMI.BILL_SEQ.NEXTVAL,
                ?,
                ?,
                ?,
                NULL,
                SYSDATE,
                0,
                'Unpaid'
            )

        ");


    $insertBill->execute([

        $patientId,

        $appointmentId,

        $consultationId

    ]);


    $billId =
        (int)$conn
            ->query("

                SELECT
                    SYARMIMI.BILL_SEQ.CURRVAL

                FROM
                    DUAL

            ")
            ->fetchColumn();


    if (
        $billId <= 0
    ) {

        throw new Exception(
            "Unable to create patient bill."
        );
    }


    /* =====================================================
       CONSULTATION BILL ITEM
    ===================================================== */

    $insertBillItem =
        $conn->prepare("

            INSERT INTO
                SYARMIMI.BILL_ITEM
            (
                BILL_ITEM_ID,
                BILL_ID,
                ITEM_TYPE,
                DESCRIPTION,
                QUANTITY,
                UNIT_PRICE,
                SUBTOTAL,
                MEDORDER_ID
            )

            VALUES
            (
                SYARMIMI.BILL_ITEM_SEQ.NEXTVAL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )

        ");


    $insertBillItem->execute([

        $billId,

        'CONSULTATION',

        $consultationDescription,

        1,

        $consultationFee,

        $consultationFee,

        null

    ]);


    /* =====================================================
       OUTPATIENT MEDICATIONS

       Current treatment form does not store a supplied
       quantity/duration for outpatient prescriptions.
       Therefore each prescribed medication is billed as
       one dispensed item using MEDICATION.PRICE.
    ===================================================== */

    if (
        $encounterType ===
        'APPOINTMENT'
    ) {

        $medicationBillStmt =
            $conn->prepare("

                SELECT

                    MO.MEDORDER_ID,

                    M.MEDICATION_NAME,

                    NVL(
                        M.PRICE,
                        0
                    )
                    AS UNIT_PRICE,

                    MO.DOSAGE,

                    MO.FREQUENCY

                FROM
                    SYARMIMI.MEDICATION_ORDER MO

                JOIN
                    SYARMIMI.MEDICATION M

                    ON
                    MO.MEDICATION_ID =
                    M.MEDICATION_ID

                WHERE
                    MO.APPOINTMENT_ID = ?

                AND
                    MO.ADMISSION_ID
                    IS NULL

                ORDER BY
                    MO.MEDORDER_ID

            ");


        $medicationBillStmt->execute([
            $appointmentId
        ]);

    }
    else {

        $medicationBillStmt =
            $conn->prepare("

                SELECT

                    MO.MEDORDER_ID,

                    M.MEDICATION_NAME,

                    NVL(
                        M.PRICE,
                        0
                    )
                    AS UNIT_PRICE,

                    MO.DOSAGE,

                    MO.FREQUENCY

                FROM
                    SYARMIMI.MEDICATION_ORDER MO

                JOIN
                    SYARMIMI.MEDICATION M

                    ON
                    MO.MEDICATION_ID =
                    M.MEDICATION_ID

                WHERE
                    MO.CONSULTATION_ID = ?

                AND
                    MO.ADMISSION_ID
                    IS NULL

                ORDER BY
                    MO.MEDORDER_ID

            ");


        $medicationBillStmt->execute([
            $consultationId
        ]);
    }


    $medicationBillRows =
        $medicationBillStmt
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


    foreach (
        $medicationBillRows
        as
        $medicationRow
    ) {

        $medicationName =
            trim(
                (string)(
                    $medicationRow[
                        'MEDICATION_NAME'
                    ]
                    ?? 'Medication'
                )
            );


        $dosage =
            trim(
                (string)(
                    $medicationRow[
                        'DOSAGE'
                    ]
                    ?? ''
                )
            );


        $frequency =
            trim(
                (string)(
                    $medicationRow[
                        'FREQUENCY'
                    ]
                    ?? ''
                )
            );


        $unitPrice =
            (float)(
                $medicationRow[
                    'UNIT_PRICE'
                ]
                ?? 0
            );


        $description =
            $medicationName;


        $instructionParts =
            [];


        if (
            $dosage !== ''
        ) {

            $instructionParts[] =
                $dosage;
        }


        if (
            $frequency !== ''
        ) {

            $instructionParts[] =
                $frequency;
        }


        if (
            !empty(
                $instructionParts
            )
        ) {

            $description .=
                ' (' .
                implode(
                    ', ',
                    $instructionParts
                )
                .
                ')';
        }


        $quantity =
            1;


        $subtotal =
            $unitPrice
            *
            $quantity;


        $insertBillItem->execute([

            $billId,

            'MEDICATION',

            $description,

            $quantity,

            $unitPrice,

            $subtotal,

            (int)$medicationRow[
                'MEDORDER_ID'
            ]

        ]);
    }


    /* =====================================================
       CALCULATE FINAL BILL TOTAL
    ===================================================== */

    $totalStmt =
        $conn->prepare("

            SELECT

                NVL(
                    SUM(
                        SUBTOTAL
                    ),
                    0
                )

            FROM
                SYARMIMI.BILL_ITEM

            WHERE
                BILL_ID = ?

        ");


    $totalStmt->execute([
        $billId
    ]);


    $totalAmount =
        (float)$totalStmt
            ->fetchColumn();


    $updateBill =
        $conn->prepare("

            UPDATE
                SYARMIMI.BILL

            SET
                TOTAL_AMOUNT = ?

            WHERE
                BILL_ID = ?

        ");


    $updateBill->execute([

        $totalAmount,

        $billId

    ]);


    return $billId;
}


/* =========================================================
   BASIC VARIABLES
========================================================= */

$type =
    trim(
        $_GET['type']
        ?? ''
    );


$id =
    (int)(
        $_GET['id']
        ?? 0
    );


$appointmentPatient =
    null;


$walkinPatient =
    null;


$patientInfo =
    null;


$errorMessage =
    '';


/* =========================================================
   DOCTOR INFO
========================================================= */

$doctorStmt =
    $conn->prepare("

        SELECT
            USERNAME,
            DEPARTMENT

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            ACCOUNT_ID = ?

    ");


$doctorStmt->execute([
    $doctor_id
]);


$doctorData =
    $doctorStmt->fetch(
        PDO::FETCH_ASSOC
    );


$doctorName =
    'Doctor';


$doctorDepartment =
    '';


if ($doctorData) {

    $doctorUsername =
        trim(
            $doctorData[
                'USERNAME'
            ]
            ?? ''
        );


    $doctorDepartment =
        trim(
            $doctorData[
                'DEPARTMENT'
            ]
            ?? ''
        );


    $doctorName =
        stripos(
            $doctorUsername,
            'Dr.'
        ) === 0
            ?
            $doctorUsername
            :
            'Dr. ' .
            $doctorUsername;
}


/* =========================================================
   GET APPOINTMENT
========================================================= */

if (
    $type === 'appointment'
    &&
    $id > 0
) {

    $stmt =
        $conn->prepare("

            SELECT *

            FROM
                SYARMIMI.APPOINTMENT

            WHERE
                APPOINTMENT_ID = ?

            AND
                ACCOUNT_ID = ?

        ");


    $stmt->execute([
        $id,
        $doctor_id
    ]);


    $appointmentPatient =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($appointmentPatient) {

        $patientInfo = [

            'NAME' =>
                $appointmentPatient[
                    'PATIENT_NAME'
                ]
                ?? '',

            'WARD_NAME' =>
                'Appointment Patient',

            'BED_NUMBER' =>
                '-',

            'ADMISSION_DATE' =>
                displayAppointmentDate(
                    $appointmentPatient[
                        'APPOINTMENT_DATE'
                    ]
                    ?? ''
                )

        ];
    }
}


/* =========================================================
   GET WALK-IN
========================================================= */

if (
    $type === 'walkin'
    &&
    $id > 0
) {

    $stmt =
        $conn->prepare("

            SELECT

                W.CONSULTATION_ID,

                W.PATIENT_ID,

                W.DEPARTMENT,

                W.STATUS,

                W.ACCOUNT_ID,

                P.NAME,

                P.IC_NUMBER,

                P.AGE,

                P.GENDER,

                P.PHONE,

                P.ADDRESS

            FROM
                SYARMIMI.WALKIN_CONSULTATION W

            JOIN
                SYARMIMI.PATIENT P

                ON
                W.PATIENT_ID =
                P.PATIENT_ID

            WHERE
                W.CONSULTATION_ID = ?

            AND
                W.ACCOUNT_ID = ?

        ");


    $stmt->execute([
        $id,
        $doctor_id
    ]);


    $walkinPatient =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if ($walkinPatient) {

        $patientInfo = [

            'NAME' =>
                $walkinPatient[
                    'NAME'
                ]
                ?? '',

            'WARD_NAME' =>
                'Walk-In Patient',

            'BED_NUMBER' =>
                '-',

            'ADMISSION_DATE' =>
                strtoupper(
                    date(
                        'd-M-y'
                    )
                )

        ];
    }
}


/* =========================================================
   PATIENT DEPARTMENT
========================================================= */

$patientDept = '';


if ($appointmentPatient) {

    $patientDept =
        trim(
            $appointmentPatient[
                'DEPARTMENT'
            ]
            ?? ''
        );

}
elseif ($walkinPatient) {

    $patientDept =
        trim(
            $walkinPatient[
                'DEPARTMENT'
            ]
            ?? ''
        );
}


/* =========================================================
   AVAILABLE BEDS
========================================================= */

$availableBeds = [];


if ($patientDept !== '') {

    $bedStmt =
        $conn->prepare("

            SELECT

                B.BED_ID,

                B.BED_NUMBER,

                W.WARD_NAME

            FROM
                SYARMIMI.BED B

            JOIN
                SYARMIMI.WARD W

                ON
                B.WARD_ID =
                W.WARD_ID

            WHERE

                REGEXP_REPLACE(
                    UPPER(
                        TRIM(
                            W.WARD_NAME
                        )
                    ),
                    'S$',
                    ''
                )

                =

                REGEXP_REPLACE(
                    UPPER(
                        TRIM(
                            ?
                        )
                    ),
                    'S$',
                    ''
                )

            AND
                UPPER(
                    TRIM(
                        B.STATUS
                    )
                )
                =
                'AVAILABLE'

            ORDER BY
                B.BED_NUMBER

        ");


    $bedStmt->execute([
        $patientDept
    ]);


    $availableBeds =
        $bedStmt->fetchAll(
            PDO::FETCH_ASSOC
        );
}


/* =========================================================
   MEDICATION LIST
========================================================= */

$medications =
    $conn
        ->query("

            SELECT

                MEDICATION_ID,

                MEDICATION_NAME,

                DOSAGE_FORM,

                NVL(STOCK,0)
                AS STOCK

            FROM
                SYARMIMI.MEDICATION

            WHERE
                NVL(
                    IS_AVAILABLE,
                    1
                )
                =
                1

            ORDER BY
                MEDICATION_NAME

        ")
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   FOLLOW-UP AVAILABLE SLOTS
========================================================= */

$followUpSlots = [];


try {

    $followStmt =
        $conn->prepare("

            SELECT

                DS.SLOT_ID,

                TO_CHAR(
                    DS.SLOT_DATE,
                    'YYYY-MM-DD'
                )
                AS SLOT_DATE_VALUE,

                TO_CHAR(
                    DS.SLOT_DATE,
                    'DD-MON-YYYY'
                )
                AS SLOT_DATE_DISPLAY,

                DS.SLOT_TIME,

                NVL(
                    DS.MAX_PATIENT,
                    1
                )
                AS MAX_PATIENT,

                NVL(
                    DS.CURRENT_PATIENT,
                    0
                )
                AS CURRENT_PATIENT,

                (
                    NVL(
                        DS.MAX_PATIENT,
                        1
                    )
                    -
                    NVL(
                        DS.CURRENT_PATIENT,
                        0
                    )
                )
                AS REMAINING_SLOT

            FROM
                SYARMIMI.DOCTOR_SLOT DS

            JOIN
                SYARMIMI.DOCTOR_AVAILABILITY DA

                ON
                DS.ACCOUNT_ID =
                DA.ACCOUNT_ID

                AND
                TRUNC(
                    DS.SLOT_DATE
                )
                =
                TRUNC(
                    DA.AVAILABLE_DATE
                )

            WHERE
                DS.ACCOUNT_ID = ?

            AND
                TRUNC(
                    DS.SLOT_DATE
                )
                >
                TRUNC(
                    SYSDATE
                )

            AND
                UPPER(
                    TRIM(
                        DA.STATUS
                    )
                )
                =
                'AVAILABLE'

            AND
                UPPER(
                    TRIM(
                        DS.STATUS
                    )
                )
                =
                'AVAILABLE'

            AND
                NVL(
                    DS.CURRENT_PATIENT,
                    0
                )
                <
                NVL(
                    DS.MAX_PATIENT,
                    1
                )

            ORDER BY

                DS.SLOT_DATE ASC,

                DS.SLOT_TIME ASC

        ");


    $followStmt->execute([
        $doctor_id
    ]);


    $followUpSlots =
        $followStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}
catch (Exception $e) {

    $followUpSlots = [];
}


/* =========================================================
   TODAY QUEUE

   FIX FOR ORA-01858
========================================================= */

$todayAppointments = [];


try {

    if (
        $appointmentDateIsOracleDate
    ) {

        $appointmentTodayCondition = "

            TRUNC(
                APPOINTMENT_DATE
            )
            =
            TRUNC(
                SYSDATE
            )

        ";

    }
    else {

        /* APPOINTMENT_DATE is VARCHAR2. Compare text formats directly.
           This supports old and new records without TO_DATE(), so malformed
           historical values can never raise ORA-01858. */
        $appointmentTodayCondition = "
            (
                TRIM(UPPER(APPOINTMENT_DATE)) = TO_CHAR(SYSDATE, 'YYYY-MM-DD')
                OR TRIM(UPPER(APPOINTMENT_DATE)) = UPPER(TO_CHAR(SYSDATE, 'DD-MON-RR', 'NLS_DATE_LANGUAGE=ENGLISH'))
                OR TRIM(UPPER(APPOINTMENT_DATE)) = UPPER(TO_CHAR(SYSDATE, 'DD-MON-YYYY', 'NLS_DATE_LANGUAGE=ENGLISH'))
                OR TRIM(APPOINTMENT_DATE) = TO_CHAR(SYSDATE, 'DD/MM/YYYY')
            )
        ";
    }


    $queueStmt =
        $conn->prepare("

            SELECT

                APPOINTMENT_ID
                AS RECORD_ID,

                PATIENT_NAME,

                STATUS,

                APPOINTMENT_TIME,

                'APPOINTMENT'
                AS TYPE

            FROM
                SYARMIMI.APPOINTMENT

            WHERE
                ACCOUNT_ID = ?

            AND
                UPPER(
                    TRIM(
                        STATUS
                    )
                )
                =
                'APPROVED'

            AND
                $appointmentTodayCondition


            UNION ALL


            SELECT

                W.CONSULTATION_ID
                AS RECORD_ID,

                P.NAME
                AS PATIENT_NAME,

                W.STATUS,

                'Walk-In'
                AS APPOINTMENT_TIME,

                'WALKIN'
                AS TYPE

            FROM
                SYARMIMI.WALKIN_CONSULTATION W

            JOIN
                SYARMIMI.PATIENT P

                ON
                W.PATIENT_ID =
                P.PATIENT_ID

            WHERE
                W.ACCOUNT_ID = ?

            AND
                UPPER(
                    TRIM(
                        W.STATUS
                    )
                )
                =
                'ASSIGNED'

            ORDER BY
                APPOINTMENT_TIME

        ");


    $queueStmt->execute([
        $doctor_id,
        $doctor_id
    ]);


    $todayAppointments =
        $queueStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}
catch (Exception $e) {

    /*
     Do not kill the whole treatment page just because
     queue retrieval failed.
    */

    $todayAppointments = [];


    if ($errorMessage === '') {

        $errorMessage =
            "Unable to load today's queue: "
            .
            $e->getMessage();
    }
}


/* =========================================================
   CURRENT ADMITTED PATIENTS
========================================================= */

$myAdmissions = [];

try {

    $admissionStmt =
        $conn->prepare("

            SELECT

                A.ADMISSION_ID,
                A.PATIENT_ID,
                A.BED_ID,

                TO_CHAR(
                    A.ADMISSION_DATE,
                    'DD-MON-RR'
                ) AS ADMISSION_DATE_DISPLAY,

                TO_CHAR(
                    A.EXPECTED_DISCHARGE_DATE,
                    'DD-MON-RR'
                ) AS EXPECTED_DATE_DISPLAY,

                TO_CHAR(
                    A.EXPECTED_DISCHARGE_DATE,
                    'YYYY-MM-DD'
                ) AS EXPECTED_DATE_VALUE,

                CASE
                    WHEN A.EXPECTED_DISCHARGE_DATE IS NOT NULL
                    THEN GREATEST(
                        1,
                        TRUNC(A.EXPECTED_DISCHARGE_DATE)
                        - TRUNC(A.ADMISSION_DATE)
                        + 1
                    )
                    ELSE GREATEST(
                        1,
                        TRUNC(SYSDATE)
                        - TRUNC(A.ADMISSION_DATE)
                        + 1
                    )
                END AS STAY_DAYS,

                CASE
                    WHEN A.EXPECTED_DISCHARGE_DATE IS NULL
                    THEN 'CURRENT'
                    ELSE 'PLANNED'
                END AS STAY_TYPE,

                CASE
                    WHEN A.EXPECTED_DISCHARGE_DATE IS NOT NULL
                    AND TRUNC(SYSDATE) < TRUNC(A.EXPECTED_DISCHARGE_DATE)
                    THEN 1
                    ELSE 0
                END AS IS_EARLY_DISCHARGE,

                P.NAME AS PATIENT_NAME,
                B.BED_NUMBER,
                W.WARD_NAME

            FROM SYARMIMI.ADMISSION A

            JOIN SYARMIMI.PATIENT P
                ON A.PATIENT_ID = P.PATIENT_ID

            JOIN SYARMIMI.BED B
                ON A.BED_ID = B.BED_ID

            JOIN SYARMIMI.WARD W
                ON B.WARD_ID = W.WARD_ID

            WHERE
                A.ACCOUNT_ID = ?

            AND
                A.DISCHARGE_DATE IS NULL

            ORDER BY
                A.ADMISSION_DATE DESC

        ");

    $admissionStmt->execute([
        $doctor_id
    ]);

    $myAdmissions =
        $admissionStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}
catch (Exception $e) {

    $myAdmissions = [];

    if ($errorMessage === '') {
        $errorMessage =
            "Unable to load current admitted patients: "
            . $e->getMessage();
    }
}


/* =========================================================
   SAVE TREATMENT
========================================================= */

if (
    $_SERVER[
        'REQUEST_METHOD'
    ]
    ===
    'POST'

    &&
    isset(
        $_POST[
            'save_all'
        ]
    )
) {

    try {

        $conn->beginTransaction();


        $patient_id =
            null;


        $appointment_id =
            null;


        $consultation_id =
            null;


        $admission_id =
            null;


        $expectedDate =
            null;


        $generatedBillId =
            null;


        /* =================================================
           APPOINTMENT PATIENT
        ================================================= */

        if (
            $type ===
            'appointment'
            &&
            $appointmentPatient
        ) {

            $appointment_id =
                (int)$appointmentPatient[
                    'APPOINTMENT_ID'
                ];


            $patient_id =
                !empty(
                    $appointmentPatient[
                        'PATIENT_ID'
                    ]
                )
                    ?
                    (int)$appointmentPatient[
                        'PATIENT_ID'
                    ]
                    :
                    null;


            /* =============================================
               CREATE PATIENT IF NOT LINKED
            ============================================= */

            if (!$patient_id) {

                $newPatientId =
                    (int)$conn
                        ->query("

                            SELECT

                                NVL(
                                    MAX(
                                        PATIENT_ID
                                    ),
                                    0
                                )
                                +
                                1

                            FROM
                                SYARMIMI.PATIENT

                        ")
                        ->fetchColumn();


                $insertPatient =
                    $conn->prepare("

                        INSERT INTO
                            SYARMIMI.PATIENT
                        (
                            PATIENT_ID,
                            IC_NUMBER,
                            NAME,
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
                            ?
                        )

                    ");


                $insertPatient->execute([

                    $newPatientId,

                    $appointmentPatient[
                        'IC_NUMBER'
                    ]
                    ?? null,

                    $appointmentPatient[
                        'PATIENT_NAME'
                    ]
                    ?? '',

                    $appointmentPatient[
                        'GENDER'
                    ]
                    ?? null,

                    $appointmentPatient[
                        'PHONE'
                    ]
                    ?? null,

                    $appointmentPatient[
                        'ADDRESS'
                    ]
                    ?? null

                ]);


                $patient_id =
                    $newPatientId;


                $linkAppointment =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.APPOINTMENT

                        SET
                            PATIENT_ID = ?

                        WHERE
                            APPOINTMENT_ID = ?

                    ");


                $linkAppointment->execute([
                    $patient_id,
                    $appointment_id
                ]);
            }
        }


        /* =================================================
           WALK-IN
        ================================================= */

        elseif (
            $type ===
            'walkin'
            &&
            $walkinPatient
        ) {

            $patient_id =
                (int)$walkinPatient[
                    'PATIENT_ID'
                ];


            $consultation_id =
                (int)$walkinPatient[
                    'CONSULTATION_ID'
                ];
        }


        if (!$patient_id) {

            throw new Exception(
                "Patient record could not be identified."
            );
        }


        /* =================================================
           DECISION
        ================================================= */

        $decision =
            trim(
                $_POST[
                    'decision_type'
                ]
                ?? ''
            );


        if (
            !in_array(
                $decision,
                [
                    'Completed',
                    'Next Appointment',
                    'Admit Patient'
                ],
                true
            )
        ) {

            throw new Exception(
                "Please select a valid treatment decision."
            );
        }


        /* =================================================
           CREATE ADMISSION FIRST
        ================================================= */

        if (
            $decision ===
            'Admit Patient'
        ) {

            $bed_id =
                (int)(
                    $_POST[
                        'bed_id'
                    ]
                    ?? 0
                );


            $expectedDate =
                trim(
                    $_POST[
                        'expected_discharge_date'
                    ]
                    ?? ''
                );


            if ($bed_id <= 0) {

                throw new Exception(
                    "Please select an available bed."
                );
            }


            if (
                !preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $expectedDate
                )
            ) {

                throw new Exception(
                    "Please select a valid expected discharge date."
                );
            }


            $expectedObject =
                DateTime::createFromFormat(
                    'Y-m-d',
                    $expectedDate
                );


            if (
                !$expectedObject
                ||
                $expectedObject->format(
                    'Y-m-d'
                )
                !==
                $expectedDate
            ) {

                throw new Exception(
                    "Expected discharge date is invalid."
                );
            }


            $todayObject =
                new DateTime(
                    date(
                        'Y-m-d'
                    )
                );


            if (
                $expectedObject
                <
                $todayObject
            ) {

                throw new Exception(
                    "Expected discharge date cannot be earlier than today."
                );
            }


            /* Number of calendar days from today. Used for Oracle DATE
               arithmetic so PDO_ODBC never has to bind a date string. */
            $expectedDays = (int)$todayObject->diff($expectedObject)->format('%a');


            /* =============================================
               EXISTING ACTIVE ADMISSION
            ============================================= */

            $checkAdmission =
                $conn->prepare("

                    SELECT COUNT(*)

                    FROM
                        SYARMIMI.ADMISSION

                    WHERE
                        PATIENT_ID = ?

                    AND
                        DISCHARGE_DATE
                        IS NULL

                ");


            $checkAdmission->execute([
                $patient_id
            ]);


            if (
                (int)$checkAdmission
                    ->fetchColumn()
                >
                0
            ) {

                throw new Exception(
                    "This patient already has an active admission."
                );
            }


            /* =============================================
               LOCK BED
            ============================================= */

            $bedCheck =
                $conn->prepare("

                    SELECT

                        B.BED_ID,

                        B.BED_NUMBER,

                        B.STATUS,

                        W.WARD_NAME

                    FROM
                        SYARMIMI.BED B

                    JOIN
                        SYARMIMI.WARD W

                        ON
                        B.WARD_ID =
                        W.WARD_ID

                    WHERE
                        B.BED_ID = ?

                    AND
                        UPPER(
                            TRIM(
                                B.STATUS
                            )
                        )
                        =
                        'AVAILABLE'

                    AND
                        REGEXP_REPLACE(
                            UPPER(
                                TRIM(
                                    W.WARD_NAME
                                )
                            ),
                            'S$',
                            ''
                        )

                        =

                        REGEXP_REPLACE(
                            UPPER(
                                TRIM(
                                    ?
                                )
                            ),
                            'S$',
                            ''
                        )

                    FOR UPDATE

                ");


            $bedCheck->execute([
                $bed_id,
                $patientDept
            ]);


            $selectedBed =
                $bedCheck->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$selectedBed) {

                throw new Exception(
                    "Selected bed is no longer available or does not match the patient's department."
                );
            }


            /* =============================================
               NEW ADMISSION ID
            ============================================= */

            $newAdmissionId =
                (int)$conn
                    ->query("

                        SELECT

                            NVL(
                                MAX(
                                    ADMISSION_ID
                                ),
                                0
                            )
                            +
                            1

                        FROM
                            SYARMIMI.ADMISSION

                    ")
                    ->fetchColumn();


            /* =============================================
               INSERT ADMISSION

               EXPECTED_DISCHARGE_DATE is Oracle DATE,
               so YYYY-MM-DD conversion is explicit.
            ============================================= */

            $insertAdmission =
                $conn->prepare("

                    INSERT INTO
                        SYARMIMI.ADMISSION
                    (
                        ADMISSION_ID,
                        ADMISSION_DATE,
                        DISCHARGE_DATE,
                        PATIENT_ID,
                        BED_ID,
                        ACCOUNT_ID,
                        IS_SEEN,
                        EXPECTED_DISCHARGE_DATE
                    )

                    VALUES
                    (
                        ?,
                        SYSDATE,
                        NULL,
                        ?,
                        ?,
                        ?,
                        0,
                        TRUNC(SYSDATE) + ?
                    )

                ");


            $insertAdmission->execute([

                $newAdmissionId,

                $patient_id,

                $bed_id,

                $doctor_id,

                $expectedDays

            ]);


            $admission_id =
                $newAdmissionId;


            /* =============================================
               OCCUPY BED
            ============================================= */

            $updateBed =
                $conn->prepare("

                    UPDATE
                        SYARMIMI.BED

                    SET
                        STATUS =
                        'Occupied'

                    WHERE
                        BED_ID = ?

                    AND
                        UPPER(
                            TRIM(
                                STATUS
                            )
                        )
                        =
                        'AVAILABLE'

                ");


            $updateBed->execute([
                $bed_id
            ]);


            /*
             Verify instead of relying on Oracle ODBC rowCount.
            */

            $verifyBed =
                $conn->prepare("

                    SELECT STATUS

                    FROM
                        SYARMIMI.BED

                    WHERE
                        BED_ID = ?

                ");


            $verifyBed->execute([
                $bed_id
            ]);


            $bedStatus =
                strtoupper(
                    trim(
                        (string)$verifyBed
                            ->fetchColumn()
                    )
                );


            if (
                $bedStatus !==
                'OCCUPIED'
            ) {

                throw new Exception(
                    "Unable to reserve the selected bed."
                );
            }
        }


        /* =================================================
           DIAGNOSIS
        ================================================= */

        $details =
            trim(
                $_POST[
                    'details'
                ]
                ?? ''
            );


        $allergies =
            trim(
                $_POST[
                    'allergies'
                ]
                ?? ''
            );


        if ($details === '') {

            throw new Exception(
                "Please enter the diagnosis details."
            );
        }


        $diagnosisExists =
            0;


        if ($appointment_id) {

            $checkDiagnosis =
                $conn->prepare("

                    SELECT COUNT(*)

                    FROM
                        SYARMIMI.DIAGNOSIS

                    WHERE
                        APPOINTMENT_ID = ?

                ");


            $checkDiagnosis->execute([
                $appointment_id
            ]);


            $diagnosisExists =
                (int)$checkDiagnosis
                    ->fetchColumn();

        }
        elseif ($consultation_id) {

            $checkDiagnosis =
                $conn->prepare("

                    SELECT COUNT(*)

                    FROM
                        SYARMIMI.DIAGNOSIS

                    WHERE
                        CONSULTATION_ID = ?

                ");


            $checkDiagnosis->execute([
                $consultation_id
            ]);


            $diagnosisExists =
                (int)$checkDiagnosis
                    ->fetchColumn();
        }


        if (
            $diagnosisExists ===
            0
        ) {

            $newDiagnosisId =
                (int)$conn
                    ->query("

                        SELECT

                            NVL(
                                MAX(
                                    DIAGNOSIS_ID
                                ),
                                0
                            )
                            +
                            1

                        FROM
                            SYARMIMI.DIAGNOSIS

                    ")
                    ->fetchColumn();


            $diagnosisStmt =
                $conn->prepare("

                    INSERT INTO
                        SYARMIMI.DIAGNOSIS
                    (
                        DIAGNOSIS_ID,
                        PATIENT_ID,
                        APPOINTMENT_ID,
                        CONSULTATION_ID,
                        DIAGNOSIS_DETAILS,
                        ALLERGIES,
                        DATE_RECORDED,
                        ACCOUNT_ID,
                        ADMISSION_ID
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        SYSDATE,
                        ?,
                        ?
                    )

                ");


            $diagnosisStmt->execute([

                $newDiagnosisId,

                $patient_id,

                $appointment_id,

                $consultation_id,

                $details,

                $allergies !== ''
                    ? $allergies
                    : '-',

                $doctor_id,

                $admission_id

            ]);

        }
        elseif ($admission_id) {

            /*
             If diagnosis already exists and patient is admitted,
             link the existing diagnosis to new admission.
            */

            if ($appointment_id) {

                $linkDiagnosis =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.DIAGNOSIS

                        SET
                            ADMISSION_ID = ?

                        WHERE
                            APPOINTMENT_ID = ?

                    ");


                $linkDiagnosis->execute([
                    $admission_id,
                    $appointment_id
                ]);

            }
            elseif ($consultation_id) {

                $linkDiagnosis =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.DIAGNOSIS

                        SET
                            ADMISSION_ID = ?

                        WHERE
                            CONSULTATION_ID = ?

                    ");


                $linkDiagnosis->execute([
                    $admission_id,
                    $consultation_id
                ]);
            }
        }


        /* =================================================
           MEDICATION
        ================================================= */

        if (
            !empty(
                $_POST[
                    'medication_id'
                ]
            )
            &&
            is_array(
                $_POST[
                    'medication_id'
                ]
            )
        ) {

            foreach (
                $_POST[
                    'medication_id'
                ]
                as
                $index => $medicationValue
            ) {

                $medication_id =
                    (int)$medicationValue;


                if ($medication_id <= 0) {
                    continue;
                }


                $dosage =
                    trim(
                        $_POST[
                            'dosage'
                        ][
                            $index
                        ]
                        ?? ''
                    );


                $frequency =
                    trim(
                        $_POST[
                            'frequency'
                        ][
                            $index
                        ]
                        ?? ''
                    );


                if ($dosage === '') {

                    throw new Exception(
                        "Please enter dosage for every selected medication."
                    );
                }


                if ($frequency === '') {

                    throw new Exception(
                        "Please select frequency for every selected medication."
                    );
                }


                /* =========================================
                   EXISTING ORDER FOR CURRENT ENCOUNTER
                ========================================= */

                $existingMedOrderId =
                    false;


                if ($appointment_id) {

                    $checkMed =
                        $conn->prepare("

                            SELECT
                                MEDORDER_ID

                            FROM
                                SYARMIMI.MEDICATION_ORDER

                            WHERE
                                APPOINTMENT_ID = ?

                            AND
                                MEDICATION_ID = ?

                            FETCH FIRST 1 ROW ONLY

                        ");


                    $checkMed->execute([
                        $appointment_id,
                        $medication_id
                    ]);


                    $existingMedOrderId =
                        $checkMed
                            ->fetchColumn();

                }
                elseif ($consultation_id) {

                    $checkMed =
                        $conn->prepare("

                            SELECT
                                MEDORDER_ID

                            FROM
                                SYARMIMI.MEDICATION_ORDER

                            WHERE
                                CONSULTATION_ID = ?

                            AND
                                MEDICATION_ID = ?

                            FETCH FIRST 1 ROW ONLY

                        ");


                    $checkMed->execute([
                        $consultation_id,
                        $medication_id
                    ]);


                    $existingMedOrderId =
                        $checkMed
                            ->fetchColumn();
                }


                /* =========================================
                   EXISTING ORDER + ADMISSION
                ========================================= */

                if ($existingMedOrderId) {

                    $existingMedOrderId =
                        (int)$existingMedOrderId;


                    if ($admission_id) {

                        $updateExisting =
                            $conn->prepare("

                                UPDATE
                                    SYARMIMI.MEDICATION_ORDER

                                SET

                                    ADMISSION_ID = ?,

                                    MED_START_DATE =
                                        TRUNC(
                                            SYSDATE
                                        ),

                                    MED_END_DATE =
                                        TRUNC(SYSDATE) + ?,

                                    DOSAGE = ?,

                                    FREQUENCY = ?

                                WHERE
                                    MEDORDER_ID = ?

                            ");


                        $updateExisting->execute([

                            $admission_id,

                            $expectedDays,

                            $dosage,

                            $frequency,

                            $existingMedOrderId

                        ]);


                        regenerateMedicationSchedule(

                            $conn,

                            $existingMedOrderId,

                            date(
                                'Y-m-d'
                            ),

                            $expectedDate,

                            $frequency

                        );
                    }


                    continue;
                }


                /* =========================================
                   NEW MED ORDER ID
                ========================================= */

                $newMedOrderId =
                    (int)$conn
                        ->query("

                            SELECT

                                NVL(
                                    MAX(
                                        MEDORDER_ID
                                    ),
                                    0
                                )
                                +
                                1

                            FROM
                                SYARMIMI.MEDICATION_ORDER

                        ")
                        ->fetchColumn();


                /* =========================================
                   ADMITTED MEDICATION
                ========================================= */

                if ($admission_id) {

                    $insertMedication =
                        $conn->prepare("

                            INSERT INTO
                                SYARMIMI.MEDICATION_ORDER
                            (
                                MEDORDER_ID,
                                ADMISSION_ID,
                                PATIENT_ID,
                                APPOINTMENT_ID,
                                CONSULTATION_ID,
                                MEDICATION_ID,
                                DOSAGE,
                                FREQUENCY,
                                ACCOUNT_ID,
                                MED_START_DATE,
                                MED_END_DATE
                            )

                            VALUES
                            (
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                TRUNC(
                                    SYSDATE
                                ),
                                TRUNC(SYSDATE) + ?
                            )

                        ");


                    $insertMedication->execute([

                        $newMedOrderId,

                        $admission_id,

                        $patient_id,

                        $appointment_id,

                        $consultation_id,

                        $medication_id,

                        $dosage,

                        $frequency,

                        $doctor_id,

                        $expectedDays

                    ]);


                    generateMedicationSchedule(

                        $conn,

                        $newMedOrderId,

                        date(
                            'Y-m-d'
                        ),

                        $expectedDate,

                        $frequency

                    );

                }

                /* =========================================
                   OUTPATIENT / WALK-IN MEDICATION
                ========================================= */

                else {

                    $insertMedication =
                        $conn->prepare("

                            INSERT INTO
                                SYARMIMI.MEDICATION_ORDER
                            (
                                MEDORDER_ID,
                                ADMISSION_ID,
                                PATIENT_ID,
                                APPOINTMENT_ID,
                                CONSULTATION_ID,
                                MEDICATION_ID,
                                DOSAGE,
                                FREQUENCY,
                                ACCOUNT_ID,
                                MED_START_DATE,
                                MED_END_DATE
                            )

                            VALUES
                            (
                                ?,
                                NULL,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                ?,
                                NULL,
                                NULL
                            )

                        ");


                    $insertMedication->execute([

                        $newMedOrderId,

                        $patient_id,

                        $appointment_id,

                        $consultation_id,

                        $medication_id,

                        $dosage,

                        $frequency,

                        $doctor_id

                    ]);
                }
            }
        }


        /* =================================================
           COMPLETED
        ================================================= */

        if (
            $decision ===
            'Completed'
        ) {

            if (
                $type ===
                'appointment'
            ) {

                $stmt =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.APPOINTMENT

                        SET
                            STATUS =
                            'Completed'

                        WHERE
                            APPOINTMENT_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $stmt->execute([
                    $appointment_id,
                    $doctor_id
                ]);

            }
            elseif (
                $type ===
                'walkin'
            ) {

                $stmt =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.WALKIN_CONSULTATION

                        SET
                            STATUS =
                            'Completed'

                        WHERE
                            CONSULTATION_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $stmt->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }
        }


        /* =================================================
           ADMIT STATUS
        ================================================= */

        elseif (
            $decision ===
            'Admit Patient'
        ) {

            if (
                $type ===
                'appointment'
            ) {

                $stmt =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.APPOINTMENT

                        SET
                            STATUS =
                            'Admitted'

                        WHERE
                            APPOINTMENT_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $stmt->execute([
                    $appointment_id,
                    $doctor_id
                ]);

            }
            elseif (
                $type ===
                'walkin'
            ) {

                $stmt =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.WALKIN_CONSULTATION

                        SET
                            STATUS =
                            'Admitted'

                        WHERE
                            CONSULTATION_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $stmt->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }
        }


        /* =================================================
           NEXT APPOINTMENT
        ================================================= */

        elseif (
            $decision ===
            'Next Appointment'
        ) {

            $selectedSlotId =
                (int)(
                    $_POST[
                        'next_slot_id'
                    ]
                    ?? 0
                );


            if (
                $selectedSlotId <= 0
            ) {

                throw new Exception(
                    "Please select an available follow-up appointment slot."
                );
            }


            /* =============================================
               VERIFY SLOT
            ============================================= */

            $slotCheck =
                $conn->prepare("

                    SELECT

                        DS.SLOT_ID,

                        TO_CHAR(
                            DS.SLOT_DATE,
                            'YYYY-MM-DD'
                        )
                        AS SLOT_DATE_VALUE,

                        DS.SLOT_TIME,

                        NVL(
                            DS.MAX_PATIENT,
                            1
                        )
                        AS MAX_PATIENT,

                        NVL(
                            DS.CURRENT_PATIENT,
                            0
                        )
                        AS CURRENT_PATIENT

                    FROM
                        SYARMIMI.DOCTOR_SLOT DS

                    JOIN
                        SYARMIMI.DOCTOR_AVAILABILITY DA

                        ON
                        DS.ACCOUNT_ID =
                        DA.ACCOUNT_ID

                        AND
                        TRUNC(
                            DS.SLOT_DATE
                        )
                        =
                        TRUNC(
                            DA.AVAILABLE_DATE
                        )

                    WHERE
                        DS.SLOT_ID = ?

                    AND
                        DS.ACCOUNT_ID = ?

                    AND
                        TRUNC(
                            DS.SLOT_DATE
                        )
                        >
                        TRUNC(
                            SYSDATE
                        )

                    AND
                        UPPER(
                            TRIM(
                                DA.STATUS
                            )
                        )
                        =
                        'AVAILABLE'

                    AND
                        UPPER(
                            TRIM(
                                DS.STATUS
                            )
                        )
                        =
                        'AVAILABLE'

                    AND
                        NVL(
                            DS.CURRENT_PATIENT,
                            0
                        )
                        <
                        NVL(
                            DS.MAX_PATIENT,
                            1
                        )

                    FOR UPDATE

                ");


            $slotCheck->execute([
                $selectedSlotId,
                $doctor_id
            ]);


            $selectedSlot =
                $slotCheck->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$selectedSlot) {

                throw new Exception(
                    "The selected follow-up slot is no longer available."
                );
            }


            $nextDate =
                trim(
                    $selectedSlot[
                        'SLOT_DATE_VALUE'
                    ]
                );


            $nextTime =
                trim(
                    $selectedSlot[
                        'SLOT_TIME'
                    ]
                );


            /* =============================================
               PATIENT COPY DATA
            ============================================= */

            if (
                $type ===
                'appointment'
            ) {

                $nextPatientName =
                    $appointmentPatient[
                        'PATIENT_NAME'
                    ]
                    ?? '';


                $nextPhone =
                    $appointmentPatient[
                        'PHONE'
                    ]
                    ?? null;


                $nextEmail =
                    $appointmentPatient[
                        'EMAIL'
                    ]
                    ?? null;


                $nextDepartment =
                    $appointmentPatient[
                        'DEPARTMENT'
                    ]
                    ?? null;


                $nextNotes =
                    $appointmentPatient[
                        'NOTES'
                    ]
                    ?? null;


                $nextDOB =
                    $appointmentPatient[
                        'DOB'
                    ]
                    ?? null;


                $nextAddress =
                    $appointmentPatient[
                        'ADDRESS'
                    ]
                    ?? null;


                $nextCity =
                    $appointmentPatient[
                        'CITY'
                    ]
                    ?? null;


                $nextState =
                    $appointmentPatient[
                        'STATE'
                    ]
                    ?? null;


                $nextIC =
                    $appointmentPatient[
                        'IC_NUMBER'
                    ]
                    ?? null;


                $nextGender =
                    $appointmentPatient[
                        'GENDER'
                    ]
                    ?? null;

            }
            else {

                $nextPatientName =
                    $walkinPatient[
                        'NAME'
                    ]
                    ?? '';


                $nextPhone =
                    $walkinPatient[
                        'PHONE'
                    ]
                    ?? null;


                $nextEmail =
                    null;


                $nextDepartment =
                    $walkinPatient[
                        'DEPARTMENT'
                    ]
                    ?? null;


                $nextNotes =
                    null;


                $nextDOB =
                    null;


                $nextAddress =
                    $walkinPatient[
                        'ADDRESS'
                    ]
                    ?? null;


                $nextCity =
                    null;


                $nextState =
                    null;


                $nextIC =
                    $walkinPatient[
                        'IC_NUMBER'
                    ]
                    ?? null;


                $nextGender =
                    $walkinPatient[
                        'GENDER'
                    ]
                    ?? null;
            }


            /* =============================================
               DOB STORAGE
               DATE/TIMESTAMP columns receive an explicit YYYY-MM-DD
               conversion. Text columns keep the existing text value.
            ============================================= */

            if ($appointmentDobIsOracleDate) {

                $normalizedDOB = normalizeFlexibleDate($nextDOB);

                if ($nextDOB !== null && trim((string)$nextDOB) !== '' && $normalizedDOB === null) {
                    throw new Exception(
                        "The patient's DOB contains an unsupported historical date format: " .
                        (string)$nextDOB
                    );
                }

                $appointmentDobValueSQL =
                    $normalizedDOB !== null
                        ? "TO_DATE(?, 'YYYY-MM-DD')"
                        : "NULL";

                $appointmentDobValue = $normalizedDOB;

            }
            else {

                $appointmentDobValueSQL = "?";
                $appointmentDobValue = $nextDOB;
            }


            /* =============================================
               APPOINTMENT DATE STORAGE

               THE IMPORTANT ORA-01858 FIX:

               DATE column     -> TO_DATE YYYY-MM-DD
               VARCHAR2 column -> save 28-AUG-26 text
            ============================================= */

            if (
                $appointmentDateIsOracleDate
            ) {

                $appointmentDateValueSQL = "

                    TO_DATE(
                        ?,
                        'YYYY-MM-DD'
                    )

                ";


                $appointmentDateValue =
                    $nextDate;

            }
            else {

                $appointmentDateValueSQL =
                    "?";


                $appointmentDateValue =
                    strtoupper(
                        date(
                            'd-M-y',
                            strtotime(
                                $nextDate
                            )
                        )
                    );
            }


            /* =============================================
               INSERT FOLLOW-UP APPOINTMENT
            ============================================= */

            $newAppointmentStmt =
                $conn->prepare("

                    INSERT INTO
                        SYARMIMI.APPOINTMENT
                    (
                        APPOINTMENT_ID,
                        PATIENT_NAME,
                        PHONE,
                        EMAIL,
                        DEPARTMENT,
                        APPOINTMENT_DATE,
                        NOTES,
                        STATUS,
                        DOB,
                        DOCTOR_NAME,
                        APPOINTMENT_TIME,
                        ADDRESS,
                        CITY,
                        STATE,
                        IC_NUMBER,
                        GENDER,
                        ACCOUNT_ID,
                        PATIENT_ID
                    )

                    VALUES
                    (
                        SYARMIMI.APPOINTMENT_SEQ.NEXTVAL,
                        ?,
                        ?,
                        ?,
                        ?,

                        $appointmentDateValueSQL,

                        ?,
                        'Approved',
                        $appointmentDobValueSQL,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )

                ");


            $appointmentInsertParams = [

                $nextPatientName,

                $nextPhone,

                $nextEmail,

                $nextDepartment,

                $appointmentDateValue,

                $nextNotes

            ];


            if ($appointmentDobValueSQL !== 'NULL') {
                $appointmentInsertParams[] = $appointmentDobValue;
            }


            $appointmentInsertParams = array_merge(
                $appointmentInsertParams,
                [
                    $doctorName,
                    $nextTime,
                    $nextAddress,
                    $nextCity,
                    $nextState,
                    $nextIC,
                    $nextGender,
                    $doctor_id,
                    $patient_id
                ]
            );


            $newAppointmentStmt->execute(
                $appointmentInsertParams
            );


            $newAppointmentId =
                (int)$conn
                    ->query("

                        SELECT
                            SYARMIMI.APPOINTMENT_SEQ.CURRVAL

                        FROM
                            DUAL

                    ")
                    ->fetchColumn();


            /* =============================================
               UPDATE SELECTED SLOT
            ============================================= */

            $newCurrentPatient =
                (int)$selectedSlot[
                    'CURRENT_PATIENT'
                ]
                +
                1;


            $maxPatient =
                (int)$selectedSlot[
                    'MAX_PATIENT'
                ];


            $newSlotStatus =
                (
                    $newCurrentPatient
                    >=
                    $maxPatient
                )
                    ?
                    'Booked'
                    :
                    'Available';


            $updateSlot =
                $conn->prepare("

                    UPDATE
                        SYARMIMI.DOCTOR_SLOT

                    SET

                        CURRENT_PATIENT = ?,

                        STATUS = ?,

                        APPOINTMENT_ID = ?

                    WHERE
                        SLOT_ID = ?

                ");


            $updateSlot->execute([

                $newCurrentPatient,

                $newSlotStatus,

                $newAppointmentId,

                $selectedSlotId

            ]);


            /* =============================================
               COMPLETE CURRENT ENCOUNTER
            ============================================= */

            if (
                $type ===
                'appointment'
            ) {

                $completeCurrent =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.APPOINTMENT

                        SET
                            STATUS =
                            'Completed'

                        WHERE
                            APPOINTMENT_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $completeCurrent->execute([
                    $appointment_id,
                    $doctor_id
                ]);

            }
            else {

                $completeCurrent =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.WALKIN_CONSULTATION

                        SET
                            STATUS =
                            'Completed'

                        WHERE
                            CONSULTATION_ID = ?

                        AND
                            ACCOUNT_ID = ?

                    ");


                $completeCurrent->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }
        }


        /* =================================================
           OUTPATIENT BILL

           Generate bill for:
           - Completed
           - Next Appointment

           Do NOT generate bill for Admit Patient.
           Admission bill will be generated after discharge.
        ================================================= */

        if (
            $decision ===
            'Completed'
            ||
            $decision ===
            'Next Appointment'
        ) {

            $generatedBillId =
                generateOutpatientBill(

                    $conn,

                    (int)$patient_id,

                    $appointment_id
                        ?
                        (int)$appointment_id
                        :
                        null,

                    $consultation_id
                        ?
                        (int)$consultation_id
                        :
                        null,

                    $type ===
                    'appointment'
                        ?
                        'APPOINTMENT'
                        :
                        'WALKIN'

                );
        }


        /* =================================================
           COMMIT
        ================================================= */

        $conn->commit();


        if (
            $generatedBillId
        ) {

            $_SESSION[
                'success_message'
            ] =
                "Diagnosis and treatment saved successfully. Bill #"
                .
                $generatedBillId
                .
                " has been generated.";

        }
        else {

            $_SESSION[
                'success_message'
            ] =
                "Diagnosis and treatment saved successfully.";
        }


        header(
            "Location: treatment.php"
        );


        exit();

    }
    catch (Exception $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        $errorMessage =
            $e->getMessage();
    }
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
Patient Treatment
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


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


.content{

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


.doctor-chip{

    display:inline-flex;

    align-items:center;

    gap:8px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:8px;

    color:#2563eb;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   CARD
========================================================= */

.card-box{

    margin-bottom:20px;

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.section-title{

    display:flex;

    align-items:center;

    gap:8px;

    margin-bottom:18px;

    color:#1f2937;

    font-size:17px;

    font-weight:700;
}


/* =========================================================
   PATIENT INFO
========================================================= */

.patient-info{

    background:#eff6ff;

    border-color:#dbeafe;
}


.patient-name{

    color:#111827;

    font-size:20px;

    font-weight:750;
}


.patient-meta{

    margin-top:8px;

    color:#64748b;

    font-size:13px;
}


/* =========================================================
   FORMS
========================================================= */

.form-label{

    margin-bottom:7px;

    color:#475569;

    font-size:12px;

    font-weight:650;
}


.form-control,
.form-select{

    min-height:45px;

    border:1px solid #dfe3e8;

    border-radius:9px;

    color:#374151;

    font-size:13px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


textarea.form-control{

    min-height:110px;
}


/* =========================================================
   DECISION BOXES
========================================================= */

#bedBox,
#nextAppointmentBox{

    display:none;

    margin-top:18px;

    padding:18px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:10px;
}


#bedBox.show,
#nextAppointmentBox.show{

    display:block;
}


/* =========================================================
   FOLLOW-UP SLOT
========================================================= */

.slot-grid{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:10px;

    max-height:360px;

    overflow-y:auto;

    padding:3px;
}


.slot-option input{

    display:none;
}


.slot-label{

    display:block;

    height:100%;

    padding:13px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:9px;

    cursor:pointer;

    transition:.2s;
}


.slot-option input:checked
+
.slot-label{

    border-color:#2563eb;

    background:#eff6ff;

    box-shadow:
        0 0 0 2px
        rgba(37,99,235,.08);
}


.slot-date{

    color:#111827;

    font-size:12px;

    font-weight:700;
}


.slot-time{

    margin-top:4px;

    color:#2563eb;

    font-size:15px;

    font-weight:700;
}


.slot-left{

    margin-top:5px;

    color:#64748b;

    font-size:10px;
}


/* =========================================================
   MEDICATION
========================================================= */

.medication-row{

    margin-bottom:10px;

    padding:14px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:10px;
}


.btn-remove-med{

    min-height:45px;

    border-radius:8px;
}


.btn-add-med{

    border-radius:8px;

    font-size:12px;

    font-weight:650;
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

    margin-bottom:0;

    vertical-align:middle;
}


.table thead th{

    padding:12px;

    background:#f8fafc;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;
}


.table tbody td{

    padding:13px;

    border-color:#eef1f4;

    color:#374151;

    font-size:12px;
}


/* =========================================================
   ALERT
========================================================= */

.alert{

    border-radius:10px;

    font-size:13px;
}


/* =========================================================
   SAVE
========================================================= */

.btn-save{

    min-height:48px;

    padding:0 25px;

    border-radius:9px;

    font-weight:700;
}



/* =========================================================
   ADMITTED PATIENT ACTIONS
========================================================= */

.admission-action-group{
    display:flex;
    align-items:center;
    gap:6px;
    flex-wrap:wrap;
}

.admission-action-btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:5px;
    padding:7px 10px;
    border-radius:8px;
    font-size:11px;
    font-weight:650;
    text-decoration:none;
    white-space:nowrap;
    transition:.2s ease;
}

.btn-review-patient{
    color:#047857;
    background:#ecfdf5;
    border:1px solid #a7f3d0;
}

.btn-review-patient:hover{
    color:#065f46;
    background:#d1fae5;
}

.btn-manage-stay{
    color:#2563eb;
    background:#eff6ff;
    border:1px solid #bfdbfe;
}

.btn-manage-stay:hover{
    color:#1d4ed8;
    background:#dbeafe;
}

.btn-discharge-patient{
    color:#dc2626;
    background:#fef2f2;
    border:1px solid #fecaca;
}

.btn-discharge-patient:hover{
    color:#b91c1c;
    background:#fee2e2;
}

.no-date{
    display:inline-flex;
    align-items:center;
    gap:5px;
    color:#d97706;
    font-size:11px;
    font-weight:650;
}

.stay-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:999px;
    background:#ecfdf5;
    color:#047857;
    font-size:10px;
    font-weight:700;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .content{

        padding:18px;
    }


    .slot-grid{

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

    .slot-grid{

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
include("../includes/sidebar_doctor.php");
?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>

<h1 class="page-title">
Patient Treatment
</h1>

<div class="page-subtitle">
Record diagnosis, prescribe medication and decide the patient's next care step.
</div>

</div>


<div class="doctor-chip">

<i class="bi bi-person-badge"></i>

<?= h(
    $doctorName
) ?>

<?php if (
    $doctorDepartment !== ''
): ?>

&nbsp;•&nbsp;

<?= h(
    $doctorDepartment
) ?>

<?php endif; ?>

</div>


</div>



<!-- =====================================================
     SUCCESS
===================================================== -->

<?php if (
    isset(
        $_SESSION[
            'success_message'
        ]
    )
): ?>


<div class="alert alert-success">

<i class="bi bi-check-circle me-1"></i>

<?= h(
    $_SESSION[
        'success_message'
    ]
) ?>

</div>


<?php

unset(
    $_SESSION[
        'success_message'
    ]
);

?>


<?php endif; ?>



<!-- =====================================================
     ERROR
===================================================== -->

<?php if (
    $errorMessage !== ''
): ?>


<div class="alert alert-danger">

<strong>
Error:
</strong>

<?= h(
    $errorMessage
) ?>

</div>


<?php endif; ?>



<!-- =====================================================
     NO CURRENT SELECTED PATIENT
===================================================== -->

<?php if (
    !$appointmentPatient
    &&
    !$walkinPatient
): ?>


<!-- =================================================
     TODAY QUEUE
================================================= -->

<div class="card-box">


<div class="section-title">

<i class="bi bi-list-check"></i>

Today's Patient Queue

</div>


<div class="table-responsive">


<table class="table">


<thead>

<tr>

<th>
Time
</th>

<th>
Patient
</th>

<th>
Type
</th>

<th>
Status
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>


<?php if (
    !empty(
        $todayAppointments
    )
): ?>


<?php foreach (
    $todayAppointments
    as
    $row
): ?>


<tr>


<td>

<?= h(
    $row[
        'APPOINTMENT_TIME'
    ]
    ?? '-'
) ?>

</td>


<td>

<strong>

<?= h(
    $row[
        'PATIENT_NAME'
    ]
    ?? '-'
) ?>

</strong>

</td>


<td>

<?php if (
    $row[
        'TYPE'
    ]
    ===
    'APPOINTMENT'
): ?>

<span class="badge bg-primary">

Appointment

</span>

<?php else: ?>

<span class="badge bg-warning text-dark">

Walk-In

</span>

<?php endif; ?>

</td>


<td>

<?= h(
    $row[
        'STATUS'
    ]
    ?? '-'
) ?>

</td>


<td>


<?php if (
    $row[
        'TYPE'
    ]
    ===
    'APPOINTMENT'
): ?>


<a
    href="treatment.php?type=appointment&id=<?= urlencode(
        $row[
            'RECORD_ID'
        ]
    ) ?>"
    class="btn btn-primary btn-sm"
>

<i class="bi bi-clipboard-pulse me-1"></i>

Diagnose

</a>


<?php else: ?>


<a
    href="treatment.php?type=walkin&id=<?= urlencode(
        $row[
            'RECORD_ID'
        ]
    ) ?>"
    class="btn btn-warning btn-sm"
>

<i class="bi bi-person-walking me-1"></i>

Diagnose

</a>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="5"
    class="text-center text-muted py-4"
>

No patients in today's queue.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>



<!-- =================================================
     CURRENT ADMISSIONS
================================================= -->

<div class="card-box">

<div class="d-flex justify-content-between align-items-center mb-3">

<div class="section-title mb-0">

<i class="bi bi-hospital"></i>

My Current Admitted Patients

</div>

<span class="badge bg-success rounded-pill px-3 py-2">
<?= count($myAdmissions) ?> Active
</span>

</div>


<div class="table-responsive">

<table class="table">

<thead>

<tr>
<th>Patient</th>
<th>Ward</th>
<th>Bed</th>
<th>Admission Date</th>
<th>Expected Discharge</th>
<th>Stay</th>
<th>Action</th>
</tr>

</thead>

<tbody>

<?php if (!empty($myAdmissions)): ?>

<?php foreach ($myAdmissions as $admission): ?>

<tr>

<td>
<strong>
<?= h($admission['PATIENT_NAME']) ?>
</strong>
</td>

<td>
<?= h($admission['WARD_NAME']) ?>
</td>

<td>
<span class="badge bg-secondary">
Bed <?= h($admission['BED_NUMBER']) ?>
</span>
</td>

<td>
<?= h($admission['ADMISSION_DATE_DISPLAY']) ?>
</td>

<td>

<?php if (!empty($admission['EXPECTED_DATE_VALUE'])): ?>

<strong>
<?= h($admission['EXPECTED_DATE_DISPLAY']) ?>
</strong>

<?php else: ?>

<span class="no-date">
<i class="bi bi-exclamation-circle"></i>
Not Set
</span>

<?php endif; ?>

</td>

<td>

<span class="stay-badge">
<?= max(1, (int)($admission['STAY_DAYS'] ?? 1)) ?> day(s)
</span>

<div class="text-muted mt-1" style="font-size:10px;">
<?= (($admission['STAY_TYPE'] ?? '') === 'PLANNED')
    ? 'Planned stay'
    : 'Current stay'
?>
</div>

</td>

<td>

<div class="admission-action-group">

<a
    href="patient_review.php?admission_id=<?= (int)$admission['ADMISSION_ID'] ?>"
    class="admission-action-btn btn-review-patient"
>
<i class="bi bi-clipboard2-pulse"></i>
Review
</a>

<a
    href="extend_admission.php?admission_id=<?= (int)$admission['ADMISSION_ID'] ?>"
    class="admission-action-btn btn-manage-stay"
>
<i class="bi <?= !empty($admission['EXPECTED_DATE_VALUE'])
    ? 'bi-calendar-plus'
    : 'bi-calendar-check'
?>"></i>

<?= !empty($admission['EXPECTED_DATE_VALUE'])
    ? 'Extend Stay'
    : 'Set Expected Date'
?>
</a>

<a
    href="discharge_patient.php?admission_id=<?= (int)$admission['ADMISSION_ID'] ?>"
    class="admission-action-btn btn-discharge-patient"
>
<i class="bi bi-box-arrow-right"></i>

<?= ((int)($admission['IS_EARLY_DISCHARGE'] ?? 0) === 1)
    ? 'Discharge Early'
    : 'Discharge'
?>
</a>

</div>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>
<td colspan="7" class="text-center text-muted py-4">
No current admitted patients.
</td>
</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<?php endif; ?>


<!-- =====================================================
     CURRENT PATIENT
===================================================== -->

<?php if (
    $appointmentPatient
    ||
    $walkinPatient
): ?>


<div class="card-box patient-info">


<div class="section-title mb-2">

<i class="bi bi-person-circle"></i>

Current Patient

</div>


<div class="patient-name">

<?= h(
    $patientInfo[
        'NAME'
    ]
    ?? '-'
) ?>

</div>


<div class="patient-meta">

<?= h(
    $patientInfo[
        'WARD_NAME'
    ]
    ?? '-'
) ?>

&nbsp;•&nbsp;

Date:

<?= h(
    $patientInfo[
        'ADMISSION_DATE'
    ]
    ?? '-'
) ?>

</div>


</div>



<form
    method="POST"
    id="treatmentForm"
>


<!-- =================================================
     DIAGNOSIS
================================================= -->

<div class="card-box">


<div class="section-title">

<i class="bi bi-clipboard2-pulse"></i>

Diagnosis

</div>


<label class="form-label">

Diagnosis Details

</label>


<textarea
    name="details"
    class="form-control mb-3"
    rows="4"
    placeholder="Enter diagnosis details..."
    required
><?= h(
    $_POST[
        'details'
    ]
    ?? ''
) ?></textarea>


<label class="form-label">

Allergies

</label>


<input
    type="text"
    name="allergies"
    class="form-control"
    placeholder="Enter known allergies, if any"
    value="<?= h(
        $_POST[
            'allergies'
        ]
        ?? ''
    ) ?>"
>


</div>



<!-- =================================================
     DECISION
================================================= -->

<div class="card-box">


<div class="section-title">

<i class="bi bi-signpost-split"></i>

Treatment Decision

</div>


<label
    for="decision_type"
    class="form-label"
>

Decision

</label>


<select
    name="decision_type"
    id="decision_type"
    class="form-select"
    required
>

<option value="">
Select Decision
</option>

<option value="Completed">
Completed
</option>

<option value="Next Appointment">
Next Appointment
</option>

<option value="Admit Patient">
Admit Patient
</option>

</select>



<!-- =================================================
     ADMIT PATIENT
================================================= -->

<div id="bedBox">


<div class="section-title">

<i class="bi bi-hospital"></i>

Hospital Admission

</div>


<div class="row g-3">


<div class="col-md-6">


<label
    for="bed_id"
    class="form-label"
>

Available Bed

</label>


<select
    name="bed_id"
    id="bed_id"
    class="form-select"
>


<option value="">

Select Available Bed

</option>


<?php foreach (
    $availableBeds
    as
    $bed
): ?>


<option
    value="<?= h(
        $bed[
            'BED_ID'
        ]
    ) ?>"
>

Bed
<?= h(
    $bed[
        'BED_NUMBER'
    ]
) ?>

•

<?= h(
    $bed[
        'WARD_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


<?php if (
    empty(
        $availableBeds
    )
): ?>


<div class="text-danger small mt-2">

<i class="bi bi-exclamation-circle me-1"></i>

No available beds found for
<?= h(
    $patientDept
) ?>.

</div>


<?php endif; ?>


</div>



<div class="col-md-6">


<label
    for="expected_discharge_date"
    class="form-label"
>

Expected Discharge Date

</label>


<input
    type="date"
    name="expected_discharge_date"
    id="expected_discharge_date"
    class="form-control"
    min="<?= h(
        date(
            'Y-m-d'
        )
    ) ?>"
>


<div class="text-muted small mt-2">

Medication schedules for admitted patients will be generated until this date.

</div>


</div>


</div>


</div>



<!-- =================================================
     NEXT APPOINTMENT
================================================= -->

<div id="nextAppointmentBox">


<div class="section-title">

<i class="bi bi-calendar-check"></i>

Select Follow-Up Appointment Slot

</div>


<?php if (
    !empty(
        $followUpSlots
    )
): ?>


<div class="slot-grid">


<?php foreach (
    $followUpSlots
    as
    $slot
): ?>


<div class="slot-option">


<input
    type="radio"
    name="next_slot_id"
    id="slot_<?= h(
        $slot[
            'SLOT_ID'
        ]
    ) ?>"
    value="<?= h(
        $slot[
            'SLOT_ID'
        ]
    ) ?>"
>


<label
    class="slot-label"
    for="slot_<?= h(
        $slot[
            'SLOT_ID'
        ]
    ) ?>"
>


<div class="slot-date">

<?= h(
    $slot[
        'SLOT_DATE_DISPLAY'
    ]
) ?>

</div>


<div class="slot-time">

<?= h(
    $slot[
        'SLOT_TIME'
    ]
) ?>

</div>


<div class="slot-left">

<?= h(
    $slot[
        'REMAINING_SLOT'
    ]
) ?>

slot(s) remaining

</div>


</label>


</div>


<?php endforeach; ?>


</div>


<?php else: ?>


<div class="alert alert-warning mb-0">

<i class="bi bi-calendar-x me-1"></i>

No available future appointment slots are currently available for this doctor.

</div>


<?php endif; ?>


</div>


</div>



<!-- =================================================
     MEDICATION
================================================= -->

<div class="card-box">


<div class="d-flex justify-content-between align-items-center mb-3">


<div class="section-title mb-0">

<i class="bi bi-capsule-pill"></i>

Medication

</div>


<button
    type="button"
    id="addMedicationBtn"
    class="btn btn-outline-primary btn-add-med"
>

<i class="bi bi-plus-circle me-1"></i>

Add Medication

</button>


</div>


<div id="medicationContainer">


<div class="medication-row">


<div class="row g-2 align-items-end">


<div class="col-lg-4">


<label class="form-label">

Medication

</label>


<select
    name="medication_id[]"
    class="form-select"
>


<option value="">

Select Medication

</option>


<?php foreach (
    $medications
    as
    $medication
): ?>


<option
    value="<?= h(
        $medication[
            'MEDICATION_ID'
        ]
    ) ?>"
>

<?= h(
    $medication[
        'MEDICATION_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


</div>



<div class="col-lg-3">


<label class="form-label">

Dosage

</label>


<input
    type="text"
    name="dosage[]"
    class="form-control"
    placeholder="e.g. 500 mg"
>


</div>



<div class="col-lg-4">


<label class="form-label">

Frequency / Instructions

</label>


<select
    name="frequency[]"
    class="form-select"
>


<option value="">

Select Frequency

</option>


<option value="Once Daily">
Once Daily
</option>


<option value="Twice Daily">
Twice Daily
</option>


<option value="Three Times Daily">
Three Times Daily
</option>


<option value="Four Times Daily">
Four Times Daily
</option>


<option value="Every 6 Hours">
Every 6 Hours
</option>


<option value="Every 8 Hours">
Every 8 Hours
</option>


<option value="Every 12 Hours">
Every 12 Hours
</option>


<option value="As Needed">
As Needed
</option>


</select>


</div>



<div class="col-lg-1">


<button
    type="button"
    class="btn btn-outline-danger btn-remove-med w-100"
    title="Remove medication"
>

<i class="bi bi-trash"></i>

</button>


</div>


</div>


</div>


</div>


<div class="small text-muted mt-2">

For admitted patients, fixed medication frequencies automatically generate daily pharmacy/nurse schedules until the expected discharge date.

</div>


</div>



<!-- =================================================
     SAVE
================================================= -->

<div class="d-flex gap-2">


<a
    href="treatment.php"
    class="btn btn-outline-secondary btn-save"
>

Cancel

</a>


<button
    type="submit"
    name="save_all"
    value="1"
    class="btn btn-primary btn-save"
>

<i class="bi bi-check2-circle me-1"></i>

Save Treatment

</button>


</div>


</form>


<?php endif; ?>


</div>


</div>



<!-- =====================================================
     MEDICATION TEMPLATE
===================================================== -->

<template id="medicationTemplate">


<div class="medication-row">


<div class="row g-2 align-items-end">


<div class="col-lg-4">


<label class="form-label">

Medication

</label>


<select
    name="medication_id[]"
    class="form-select"
>


<option value="">

Select Medication

</option>


<?php foreach (
    $medications
    as
    $medication
): ?>


<option
    value="<?= h(
        $medication[
            'MEDICATION_ID'
        ]
    ) ?>"
>

<?= h(
    $medication[
        'MEDICATION_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


</div>



<div class="col-lg-3">


<label class="form-label">

Dosage

</label>


<input
    type="text"
    name="dosage[]"
    class="form-control"
    placeholder="e.g. 500 mg"
>


</div>



<div class="col-lg-4">


<label class="form-label">

Frequency / Instructions

</label>


<select
    name="frequency[]"
    class="form-select"
>


<option value="">
Select Frequency
</option>

<option value="Once Daily">
Once Daily
</option>

<option value="Twice Daily">
Twice Daily
</option>

<option value="Three Times Daily">
Three Times Daily
</option>

<option value="Four Times Daily">
Four Times Daily
</option>

<option value="Every 6 Hours">
Every 6 Hours
</option>

<option value="Every 8 Hours">
Every 8 Hours
</option>

<option value="Every 12 Hours">
Every 12 Hours
</option>

<option value="As Needed">
As Needed
</option>


</select>


</div>



<div class="col-lg-1">


<button
    type="button"
    class="btn btn-outline-danger btn-remove-med w-100"
>

<i class="bi bi-trash"></i>

</button>


</div>


</div>


</div>


</template>



<script>

/* =========================================================
   DECISION UI
========================================================= */

const decisionSelect =
    document.getElementById(
        'decision_type'
    );


const bedBox =
    document.getElementById(
        'bedBox'
    );


const nextAppointmentBox =
    document.getElementById(
        'nextAppointmentBox'
    );


const bedSelect =
    document.getElementById(
        'bed_id'
    );


const expectedDate =
    document.getElementById(
        'expected_discharge_date'
    );


function updateDecisionUI()
{

    if (
        !decisionSelect
    ) {

        return;
    }


    const decision =
        decisionSelect.value;


    if (
        decision ===
        'Admit Patient'
    ) {

        bedBox
            ?.classList
            .add(
                'show'
            );


        nextAppointmentBox
            ?.classList
            .remove(
                'show'
            );


        if (
            bedSelect
        ) {

            bedSelect.required =
                true;
        }


        if (
            expectedDate
        ) {

            expectedDate.required =
                true;
        }


        document
            .querySelectorAll(
                'input[name="next_slot_id"]'
            )
            .forEach(
                function(input)
                {

                    input.required =
                        false;

                }
            );

    }
    else if (
        decision ===
        'Next Appointment'
    ) {

        nextAppointmentBox
            ?.classList
            .add(
                'show'
            );


        bedBox
            ?.classList
            .remove(
                'show'
            );


        if (
            bedSelect
        ) {

            bedSelect.required =
                false;
        }


        if (
            expectedDate
        ) {

            expectedDate.required =
                false;
        }


        const slots =
            document
                .querySelectorAll(
                    'input[name="next_slot_id"]'
                );


        slots.forEach(
            function(
                input,
                index
            )
            {

                /*
                 Radio group only needs one required input.
                */

                input.required =
                    index === 0;

            }
        );

    }
    else {

        bedBox
            ?.classList
            .remove(
                'show'
            );


        nextAppointmentBox
            ?.classList
            .remove(
                'show'
            );


        if (
            bedSelect
        ) {

            bedSelect.required =
                false;
        }


        if (
            expectedDate
        ) {

            expectedDate.required =
                false;
        }


        document
            .querySelectorAll(
                'input[name="next_slot_id"]'
            )
            .forEach(
                function(input)
                {

                    input.required =
                        false;

                }
            );
    }

}


decisionSelect
    ?.addEventListener(
        'change',
        updateDecisionUI
    );


updateDecisionUI();



/* =========================================================
   ADD MEDICATION
========================================================= */

const addMedicationBtn =
    document.getElementById(
        'addMedicationBtn'
    );


const medicationContainer =
    document.getElementById(
        'medicationContainer'
    );


const medicationTemplate =
    document.getElementById(
        'medicationTemplate'
    );


addMedicationBtn
    ?.addEventListener(
        'click',
        function()
        {

            const copy =
                medicationTemplate
                    .content
                    .cloneNode(
                        true
                    );


            medicationContainer
                .appendChild(
                    copy
                );

        }
    );



/* =========================================================
   REMOVE MEDICATION
========================================================= */

document.addEventListener(
    'click',
    function(event)
    {

        const button =
            event.target.closest(
                '.btn-remove-med'
            );


        if (!button) {
            return;
        }


        const rows =
            medicationContainer
                ?.querySelectorAll(
                    '.medication-row'
                );


        const row =
            button.closest(
                '.medication-row'
            );


        if (
            rows
            &&
            rows.length >
            1
        ) {

            row.remove();

        }
        else if (row) {

            /*
             Keep one blank row.
            */

            row
                .querySelectorAll(
                    'input'
                )
                .forEach(
                    function(input)
                    {

                        input.value =
                            '';

                    }
                );


            row
                .querySelectorAll(
                    'select'
                )
                .forEach(
                    function(select)
                    {

                        select.value =
                            '';

                    }
                );
        }

    }
);



/* =========================================================
   FINAL CLIENT VALIDATION
========================================================= */

document
    .getElementById(
        'treatmentForm'
    )
    ?.addEventListener(
        'submit',
        function(event)
        {

            const decision =
                decisionSelect.value;


            if (
                decision ===
                'Next Appointment'
            ) {

                const selectedSlot =
                    document
                        .querySelector(
                            'input[name="next_slot_id"]:checked'
                        );


                if (!selectedSlot) {

                    event.preventDefault();


                    alert(
                        'Please select an available follow-up appointment slot.'
                    );


                    return;
                }
            }


            if (
                decision ===
                'Admit Patient'
            ) {

                if (
                    !bedSelect.value
                ) {

                    event.preventDefault();


                    alert(
                        'Please select an available bed.'
                    );


                    return;
                }


                if (
                    !expectedDate.value
                ) {

                    event.preventDefault();


                    alert(
                        'Please select the expected discharge date.'
                    );


                    return;
                }
            }

        }
    );

</script>


</body>

</html>