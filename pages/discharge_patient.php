<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'doctor'], true)
) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];

$user_id = (int)(
    $_SESSION['user_id']
    ?? 0
);

if ($user_id <= 0) {
    die("Invalid user account.");
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


/* =========================================================
   ADMISSION BILLING

   Final bill is generated ONLY after discharge.

   Charges:
   - Appointment consultation : RM 100.00
   - Walk-In consultation     : RM 120.00
   - Orthopaedic ward         : RM 180.00/day
   - Paediatric ward          : RM 150.00/day
   - Neurology ward           : RM 220.00/day
   - Medication               : MEDICATION.PRICE

   IMPORTANT:
   BILL table DOES NOT contain BILL_TYPE.
   Admission bills are identified using ADMISSION_ID.
========================================================= */

function getAdmissionWardRate(string $wardName): float
{
    $ward =
        strtoupper(
            trim($wardName)
        );

    $ward =
        preg_replace(
            '/S$/',
            '',
            $ward
        );


    if (
        $ward === 'ORTHOPAEDIC'
        ||
        $ward === 'ORTHOPEDIC'
    ) {
        return 180.00;
    }


    if (
        $ward === 'PAEDIATRIC'
        ||
        $ward === 'PEDIATRIC'
    ) {
        return 150.00;
    }


    if ($ward === 'NEUROLOGY') {
        return 220.00;
    }


    throw new Exception(
        "No billing rate has been configured for ward: "
        . $wardName
    );
}


function generateAdmissionBill(
    PDO $conn,
    int $admissionId,
    array $dischargeMedOrderIds = []
): int {

    /* =====================================================
       PREVENT DUPLICATE ADMISSION BILL
    ===================================================== */

    $existingStmt =
        $conn->prepare("
            SELECT BILL_ID

            FROM SYARMIMI.BILL

            WHERE ADMISSION_ID = ?

            FETCH FIRST 1 ROW ONLY
        ");

    $existingStmt->execute([
        $admissionId
    ]);

    $existingBillId =
        $existingStmt->fetchColumn();

    if ($existingBillId) {
        return (int)$existingBillId;
    }


    /* =====================================================
       GET FINAL ADMISSION + ORIGINAL ENCOUNTER
    ===================================================== */

    $admissionStmt =
        $conn->prepare("
            SELECT
                A.ADMISSION_ID,
                A.PATIENT_ID,
                A.ADMISSION_DATE,
                A.DISCHARGE_DATE,
                W.WARD_NAME,
                G.APPOINTMENT_ID,
                G.CONSULTATION_ID

            FROM SYARMIMI.ADMISSION A

            JOIN SYARMIMI.BED B
                ON A.BED_ID = B.BED_ID

            JOIN SYARMIMI.WARD W
                ON B.WARD_ID = W.WARD_ID

            LEFT JOIN
            (
                SELECT
                    ADMISSION_ID,
                    MAX(APPOINTMENT_ID) AS APPOINTMENT_ID,
                    MAX(CONSULTATION_ID) AS CONSULTATION_ID

                FROM SYARMIMI.DIAGNOSIS

                WHERE ADMISSION_ID = ?

                GROUP BY ADMISSION_ID
            ) G
                ON A.ADMISSION_ID = G.ADMISSION_ID

            WHERE
                A.ADMISSION_ID = ?
                AND A.DISCHARGE_DATE IS NOT NULL
        ");

    $admissionStmt->execute([
        $admissionId,
        $admissionId
    ]);

    $admission =
        $admissionStmt->fetch(PDO::FETCH_ASSOC);

    if (!$admission) {
        throw new Exception(
            "Unable to generate the final bill because discharge information is incomplete."
        );
    }


    $patientId =
        (int)$admission['PATIENT_ID'];

    $appointmentId =
        !empty($admission['APPOINTMENT_ID'])
            ? (int)$admission['APPOINTMENT_ID']
            : null;

    $consultationId =
        !empty($admission['CONSULTATION_ID'])
            ? (int)$admission['CONSULTATION_ID']
            : null;


    /* =====================================================
       CREATE BILL HEADER - FIXED

       BILL_TYPE REMOVED because it does not exist in
       SYARMIMI.BILL.
    ===================================================== */

    $insertBill =
        $conn->prepare("
            INSERT INTO SYARMIMI.BILL
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
                ?,
                SYSDATE,
                0,
                'Unpaid'
            )
        ");

    $insertBill->execute([
        $patientId,
        $appointmentId,
        $consultationId,
        $admissionId
    ]);

    $billId =
        (int)$conn
            ->query("
                SELECT SYARMIMI.BILL_SEQ.CURRVAL
                FROM DUAL
            ")
            ->fetchColumn();

    if ($billId <= 0) {
        throw new Exception(
            "Unable to create the final admission bill."
        );
    }


    /* =====================================================
       PREPARE BILL ITEM INSERT
    ===================================================== */

    $insertItem =
        $conn->prepare("
            INSERT INTO SYARMIMI.BILL_ITEM
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

    $total = 0.00;


    /* =====================================================
       CONSULTATION CHARGE

       Admission was not billed when the patient was admitted,
       so the original consultation is charged here.
    ===================================================== */

    if ($appointmentId) {

        $consultationFee = 100.00;

        $insertItem->execute([
            $billId,
            'CONSULTATION',
            'Specialist Appointment Consultation',
            1,
            $consultationFee,
            $consultationFee,
            null
        ]);

        $total += $consultationFee;

    }
    elseif ($consultationId) {

        $consultationFee = 120.00;

        $insertItem->execute([
            $billId,
            'CONSULTATION',
            'Walk-In Specialist Consultation',
            1,
            $consultationFee,
            $consultationFee,
            null
        ]);

        $total += $consultationFee;
    }


    /* =====================================================
       WARD CHARGE

       Inclusive calendar days, minimum one chargeable day.
    ===================================================== */

    $daysStmt =
        $conn->prepare("
            SELECT
                GREATEST(
                    TRUNC(DISCHARGE_DATE)
                    -
                    TRUNC(ADMISSION_DATE)
                    +
                    1,
                    1
                )

            FROM SYARMIMI.ADMISSION

            WHERE ADMISSION_ID = ?
        ");

    $daysStmt->execute([
        $admissionId
    ]);

    $wardDays =
        max(
            1,
            (int)$daysStmt->fetchColumn()
        );

    $wardRate =
        getAdmissionWardRate(
            (string)$admission['WARD_NAME']
        );

    $wardSubtotal =
        $wardDays * $wardRate;

    $insertItem->execute([
        $billId,
        'WARD',
        trim((string)$admission['WARD_NAME'])
            . ' Ward Charge',
        $wardDays,
        $wardRate,
        $wardSubtotal,
        null
    ]);

    $total += $wardSubtotal;


    /* =====================================================
       INPATIENT MEDICATION

       Tablet / capsule / sachet:
       quantity = number of actual administrations.

       Syrup / liquid / injection / powder:
       quantity = 1 dispensed item per medication order.

       If no administration row exists, quantity defaults to 1.
    ===================================================== */

    $inpatientMedStmt =
        $conn->prepare("
            SELECT
                MO.MEDORDER_ID,
                MO.DOSAGE,
                MO.FREQUENCY,
                M.MEDICATION_NAME,
                M.DOSAGE_FORM,
                NVL(M.PRICE, 0) AS PRICE,

                (
                    SELECT COUNT(*)

                    FROM SYARMIMI.MEDICATION_ADMIN MA

                    WHERE
                        MA.MEDORDER_ID =
                        MO.MEDORDER_ID
                ) AS ADMIN_COUNT

            FROM SYARMIMI.MEDICATION_ORDER MO

            JOIN SYARMIMI.MEDICATION M
                ON MO.MEDICATION_ID =
                   M.MEDICATION_ID

            WHERE
                MO.ADMISSION_ID = ?

                AND NVL(
                    UPPER(MO.ORDER_TYPE),
                    'INPATIENT'
                ) = 'INPATIENT'

            ORDER BY
                M.MEDICATION_NAME
        ");

    $inpatientMedStmt->execute([
        $admissionId
    ]);

    $inpatientMedications =
        $inpatientMedStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    foreach (
        $inpatientMedications
        as
        $med
    ) {

        $unitPrice =
            (float)($med['PRICE'] ?? 0);

        $dosageForm =
            strtoupper(
                trim(
                    (string)($med['DOSAGE_FORM'] ?? '')
                )
            );

        $adminCount =
            (int)($med['ADMIN_COUNT'] ?? 0);


        if (
            in_array(
                $dosageForm,
                [
                    'SYRUP',
                    'LIQUID',
                    'INJECTION',
                    'POWDER'
                ],
                true
            )
        ) {

            $quantity = 1;

        }
        else {

            $quantity =
                max(
                    1,
                    $adminCount
                );
        }


        $subtotal =
            $quantity * $unitPrice;


        $description =
            trim(
                (string)(
                    $med['MEDICATION_NAME']
                    ?? 'Medication'
                )
            );


        if (
            !empty(
                $med['DOSAGE']
            )
        ) {

            $description .=
                ' - '
                .
                trim(
                    (string)$med['DOSAGE']
                );
        }


        if (
            !empty(
                $med['FREQUENCY']
            )
        ) {

            $description .=
                ' ('
                .
                trim(
                    (string)$med['FREQUENCY']
                )
                .
                ')';
        }


        $insertItem->execute([
            $billId,
            'MEDICATION',
            $description,
            $quantity,
            $unitPrice,
            $subtotal,
            (int)$med['MEDORDER_ID']
        ]);


        $total += $subtotal;
    }


    /* =====================================================
       DISCHARGE / TAKE-HOME MEDICATION

       These orders have ADMISSION_ID = NULL by design.
       MEDORDER_ID values created during this discharge are
       passed into this billing function.
    ===================================================== */

    if (
        !empty(
            $dischargeMedOrderIds
        )
    ) {

        $dischargeMedStmt =
            $conn->prepare("
                SELECT
                    MO.MEDORDER_ID,
                    MO.DOSAGE,
                    MO.FREQUENCY,
                    M.MEDICATION_NAME,
                    M.DOSAGE_FORM,
                    NVL(M.PRICE, 0) AS PRICE

                FROM SYARMIMI.MEDICATION_ORDER MO

                JOIN SYARMIMI.MEDICATION M
                    ON MO.MEDICATION_ID =
                       M.MEDICATION_ID

                WHERE
                    MO.MEDORDER_ID = ?

                    AND UPPER(
                        TRIM(
                            NVL(
                                MO.ORDER_TYPE,
                                'DISCHARGE'
                            )
                        )
                    ) = 'DISCHARGE'
            ");


        foreach (
            $dischargeMedOrderIds
            as
            $medOrderId
        ) {

            $dischargeMedStmt->execute([
                (int)$medOrderId
            ]);


            $med =
                $dischargeMedStmt->fetch(
                    PDO::FETCH_ASSOC
                );


            if (!$med) {
                continue;
            }


            $unitPrice =
                (float)($med['PRICE'] ?? 0);

            /*
               Current discharge form has no quantity/duration
               field, so each take-home medication prescription
               is billed once.
            */

            $quantity = 1;

            $subtotal =
                $quantity * $unitPrice;


            $description =
                'Discharge Medication: '
                .
                trim(
                    (string)(
                        $med['MEDICATION_NAME']
                        ?? 'Medication'
                    )
                );


            if (
                !empty(
                    $med['DOSAGE']
                )
            ) {

                $description .=
                    ' - '
                    .
                    trim(
                        (string)$med['DOSAGE']
                    );
            }


            if (
                !empty(
                    $med['FREQUENCY']
                )
            ) {

                $description .=
                    ' ('
                    .
                    trim(
                        (string)$med['FREQUENCY']
                    )
                    .
                    ')';
            }


            $insertItem->execute([
                $billId,
                'MEDICATION',
                $description,
                $quantity,
                $unitPrice,
                $subtotal,
                (int)$med['MEDORDER_ID']
            ]);


            $total += $subtotal;
        }
    }


    /* =====================================================
       UPDATE FINAL TOTAL
    ===================================================== */

    $updateTotal =
        $conn->prepare("
            UPDATE SYARMIMI.BILL

            SET TOTAL_AMOUNT = ?

            WHERE BILL_ID = ?
        ");

    $updateTotal->execute([
        round($total, 2),
        $billId
    ]);


    /* =====================================================
       VERIFY BILL

       Oracle ODBC rowCount() is intentionally not used.
    ===================================================== */

    $verifyTotal =
        $conn->prepare("
            SELECT COUNT(*)

            FROM SYARMIMI.BILL

            WHERE
                BILL_ID = ?
                AND TOTAL_AMOUNT = ?
                AND UPPER(TRIM(STATUS)) = 'UNPAID'
        ");

    $verifyTotal->execute([
        $billId,
        round($total, 2)
    ]);

    if (
        (int)$verifyTotal->fetchColumn()
        !== 1
    ) {
        throw new Exception(
            "The final admission bill could not be verified."
        );
    }


    return $billId;
}


/* =========================================================
   GET ADMISSION ID
========================================================= */

$admission_id = (int)(
    $_GET['admission_id']
    ??
    $_POST['admission_id']
    ??
    0
);

if ($admission_id <= 0) {
    die("Invalid admission ID.");
}


/* =========================================================
   GET ACTIVE ADMISSION
========================================================= */

$sql = "
    SELECT
        A.ADMISSION_ID,
        A.PATIENT_ID,
        A.BED_ID,
        A.ACCOUNT_ID,

        P.NAME,
        P.IC_NUMBER,
        P.GENDER,
        P.PHONE,

        B.BED_NUMBER,
        W.WARD_NAME,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        ) AS ADMISSION_DATE,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'DD-MON-YYYY'
        ) AS EXPECTED_DISCHARGE_DATE_DISPLAY,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'YYYY-MM-DD'
        ) AS EXPECTED_DISCHARGE_DATE_VALUE,

        CASE
            WHEN
                A.EXPECTED_DISCHARGE_DATE IS NOT NULL
                AND TRUNC(SYSDATE)
                    < TRUNC(A.EXPECTED_DISCHARGE_DATE)
            THEN 1
            ELSE 0
        END AS IS_EARLY_DISCHARGE

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE
        A.ADMISSION_ID = ?
        AND A.DISCHARGE_DATE IS NULL
";

if ($role === 'doctor') {
    $sql .= "
        AND A.ACCOUNT_ID = ?
    ";
}

$stmt = $conn->prepare($sql);

$params = [$admission_id];

if ($role === 'doctor') {
    $params[] = $user_id;
}

$stmt->execute($params);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$patient) {
    die(
        "Admission record not found, already discharged, " .
        "or you do not have permission to discharge this patient."
    );
}

$isEarlyDischarge =
    (int)($patient['IS_EARLY_DISCHARGE'] ?? 0)
    === 1;


/* =========================================================
   LATEST DIAGNOSIS
========================================================= */

$latestDiagnosisStmt = $conn->prepare("
    SELECT *
    FROM
    (
        SELECT
            D.DIAGNOSIS_ID,
            D.DIAGNOSIS_DETAILS,
            D.ALLERGIES,

            TO_CHAR(
                D.DATE_RECORDED,
                'DD-MON-YYYY HH24:MI'
            ) AS DATE_DISPLAY

        FROM SYARMIMI.DIAGNOSIS D

        WHERE D.PATIENT_ID = ?

        ORDER BY
            D.DATE_RECORDED DESC,
            D.DIAGNOSIS_ID DESC
    )

    FETCH FIRST 1 ROW ONLY
");

$latestDiagnosisStmt->execute([
    $patient['PATIENT_ID']
]);

$latestDiagnosis =
    $latestDiagnosisStmt->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   AVAILABLE MEDICATION
========================================================= */

$medStmt = $conn->query("
    SELECT
        MEDICATION_ID,
        MEDICATION_NAME,
        DOSAGE_FORM,
        STOCK

    FROM SYARMIMI.MEDICATION

    WHERE
        NVL(IS_AVAILABLE, 0) = 1
        AND NVL(STOCK, 0) > 0

    ORDER BY
        MEDICATION_NAME
");

$medications =
    $medStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   CURRENT MEDICATION COUNT
========================================================= */

$medCountStmt = $conn->prepare("
    SELECT COUNT(*)

    FROM SYARMIMI.MEDICATION_ORDER

    WHERE
        ADMISSION_ID = ?
        AND NVL(UPPER(ORDER_TYPE), 'INPATIENT') = 'INPATIENT'
");

$medCountStmt->execute([
    $admission_id
]);

$medicationCount =
    (int)$medCountStmt->fetchColumn();


/* =========================================================
   REMAINING SCHEDULE COUNT
========================================================= */

$remainingScheduleStmt = $conn->prepare("
    SELECT COUNT(*)

    FROM SYARMIMI.MEDICATION_SCHEDULE MS

    JOIN SYARMIMI.MEDICATION_ORDER MO
        ON MS.MEDORDER_ID = MO.MEDORDER_ID

    WHERE
        MO.ADMISSION_ID = ?

        AND UPPER(
            TRIM(
                NVL(
                    MS.STATUS,
                    'PENDING PREPARATION'
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

        AND
        (
            TRUNC(MS.SCHEDULE_DATE) > TRUNC(SYSDATE)

            OR

            (
                TRUNC(MS.SCHEDULE_DATE) = TRUNC(SYSDATE)

                AND TRIM(MS.SCHEDULE_TIME)
                    > TO_CHAR(SYSDATE, 'HH24:MI')
            )
        )
");

$remainingScheduleStmt->execute([
    $admission_id
]);

$remainingScheduleCount =
    (int)$remainingScheduleStmt->fetchColumn();


$errorMessage = '';


/* =========================================================
   PROCESS DISCHARGE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['confirm_discharge'])
) {

    $finalReview =
        trim($_POST['final_review'] ?? '');

    $allergies =
        trim($_POST['allergies'] ?? '');


    /* =====================================================
       DISCHARGE MEDICATION INPUT
    ===================================================== */

    $dischargeMedicationIds =
        $_POST['discharge_medication_id']
        ?? [];

    $dischargeDosages =
        $_POST['discharge_dosage']
        ?? [];

    $dischargeFrequencies =
        $_POST['discharge_frequency']
        ?? [];


    if (
        $role === 'doctor'
        &&
        $finalReview === ''
    ) {

        $errorMessage =
            "Please enter the patient's final clinical review before discharge.";

    } else {

        try {

            $conn->beginTransaction();


            /* =================================================
               1. LOCK ADMISSION
            ================================================= */

            $lockSql = "
                SELECT
                    PATIENT_ID,
                    BED_ID,
                    ACCOUNT_ID,
                    EXPECTED_DISCHARGE_DATE

                FROM SYARMIMI.ADMISSION

                WHERE
                    ADMISSION_ID = ?
                    AND DISCHARGE_DATE IS NULL
            ";

            if ($role === 'doctor') {
                $lockSql .= "
                    AND ACCOUNT_ID = ?
                ";
            }

            $lockSql .= "
                FOR UPDATE
            ";

            $lockStmt =
                $conn->prepare($lockSql);

            $lockParams = [
                $admission_id
            ];

            if ($role === 'doctor') {
                $lockParams[] =
                    $user_id;
            }

            $lockStmt->execute(
                $lockParams
            );

            $lockedAdmission =
                $lockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$lockedAdmission) {
                throw new Exception(
                    "This admission is no longer active."
                );
            }

            $patientId =
                (int)$lockedAdmission['PATIENT_ID'];

            $bedId =
                (int)$lockedAdmission['BED_ID'];


            /* =================================================
               2. FINAL CLINICAL REVIEW
            ================================================= */

            if (
                $role === 'doctor'
                &&
                $finalReview !== ''
            ) {

                $diagnosisId =
                    (int)$conn
                        ->query("
                            SELECT
                                NVL(MAX(DIAGNOSIS_ID), 0) + 1
                            FROM SYARMIMI.DIAGNOSIS
                        ")
                        ->fetchColumn();

                $insertDiagnosis =
                    $conn->prepare("
                        INSERT INTO SYARMIMI.DIAGNOSIS
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

                $insertDiagnosis->execute([
                    $diagnosisId,
                    $patientId,
                    $admission_id,
                    $finalReview,
                    $allergies !== ''
                        ? $allergies
                        : '-',
                    $user_id
                ]);
            }


            /* =================================================
               3. CANCEL FUTURE INPATIENT SCHEDULES
            ================================================= */

            $cancelSchedules =
                $conn->prepare("
                    UPDATE
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    SET
                        MS.STATUS =
                        'Cancelled - Discharged'

                    WHERE
                        MS.MEDORDER_ID IN
                        (
                            SELECT
                                MO.MEDORDER_ID

                            FROM
                                SYARMIMI.MEDICATION_ORDER MO

                            WHERE
                                MO.ADMISSION_ID = ?

                                AND NVL(
                                    UPPER(MO.ORDER_TYPE),
                                    'INPATIENT'
                                ) = 'INPATIENT'
                        )

                    AND UPPER(
                        TRIM(
                            NVL(
                                MS.STATUS,
                                'PENDING PREPARATION'
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

                    AND
                    (
                        TRUNC(MS.SCHEDULE_DATE)
                            > TRUNC(SYSDATE)

                        OR

                        (
                            TRUNC(MS.SCHEDULE_DATE)
                                = TRUNC(SYSDATE)

                            AND TRIM(MS.SCHEDULE_TIME)
                                > TO_CHAR(
                                    SYSDATE,
                                    'HH24:MI'
                                )
                        )
                    )
                ");

            $cancelSchedules->execute([
                $admission_id
            ]);


            /* =================================================
               4. CANCEL RELATED PHARMACY PREPARATION
            ================================================= */

            $cancelPreparation =
                $conn->prepare("
                    UPDATE
                        SYARMIMI.PHARMACY_PREPARATION PP

                    SET
                        PP.STATUS = 'Cancelled'

                    WHERE
                        PP.SCHEDULE_ID IS NOT NULL

                    AND EXISTS
                    (
                        SELECT 1

                        FROM
                            SYARMIMI.MEDICATION_SCHEDULE MS

                        JOIN
                            SYARMIMI.MEDICATION_ORDER MO
                            ON MS.MEDORDER_ID =
                               MO.MEDORDER_ID

                        WHERE
                            MS.SCHEDULE_ID =
                            PP.SCHEDULE_ID

                            AND MO.ADMISSION_ID = ?

                            AND UPPER(
                                TRIM(MS.STATUS)
                            ) =
                            'CANCELLED - DISCHARGED'
                    )

                    AND UPPER(
                        TRIM(
                            NVL(
                                PP.STATUS,
                                'PENDING'
                            )
                        )
                    )
                    NOT IN
                    (
                        'ADMINISTERED',
                        'GIVEN',
                        'DELIVERED',
                        'COMPLETED'
                    )
                ");

            $cancelPreparation->execute([
                $admission_id
            ]);


            /* =================================================
               5. STOP INPATIENT MEDICATION
            ================================================= */

            $stopMedication =
                $conn->prepare("
                    UPDATE
                        SYARMIMI.MEDICATION_ORDER

                    SET
                        MED_END_DATE =
                        TRUNC(SYSDATE)

                    WHERE
                        ADMISSION_ID = ?

                        AND NVL(
                            UPPER(ORDER_TYPE),
                            'INPATIENT'
                        ) = 'INPATIENT'

                        AND
                        (
                            MED_END_DATE IS NULL

                            OR

                            TRUNC(MED_END_DATE)
                                > TRUNC(SYSDATE)
                        )
                ");

            $stopMedication->execute([
                $admission_id
            ]);


            /* =================================================
               6. CREATE DISCHARGE MEDICATION

               ADMISSION_ID = NULL
               ORDER_TYPE   = DISCHARGE
               NO MEDICATION_SCHEDULE
            ================================================= */

            $dischargeCreatedMedOrderIds = [];

            for (
                $i = 0;
                $i < count($dischargeMedicationIds);
                $i++
            ) {

                $medicationId =
                    (int)(
                        $dischargeMedicationIds[$i]
                        ?? 0
                    );

                $dosage =
                    trim(
                        $dischargeDosages[$i]
                        ?? ''
                    );

                $frequency =
                    trim(
                        $dischargeFrequencies[$i]
                        ?? ''
                    );


                /* Completely blank row - ignore */

                if (
                    $medicationId <= 0
                    &&
                    $dosage === ''
                    &&
                    $frequency === ''
                ) {
                    continue;
                }


                if ($medicationId <= 0) {
                    throw new Exception(
                        "Please select a medication for every discharge medication row."
                    );
                }

                if ($dosage === '') {
                    throw new Exception(
                        "Please enter the dosage for every discharge medication."
                    );
                }

                if ($frequency === '') {
                    throw new Exception(
                        "Please enter the frequency/instructions for every discharge medication."
                    );
                }


                /* =============================================
                   VERIFY MEDICATION
                ============================================= */

                $checkMedication =
                    $conn->prepare("
                        SELECT COUNT(*)

                        FROM SYARMIMI.MEDICATION

                        WHERE
                            MEDICATION_ID = ?
                            AND NVL(IS_AVAILABLE, 0) = 1
                    ");

                $checkMedication->execute([
                    $medicationId
                ]);

                if (
                    (int)$checkMedication
                        ->fetchColumn()
                    !== 1
                ) {

                    throw new Exception(
                        "One of the selected discharge medications is unavailable."
                    );
                }


                /* =============================================
                   GENERATE MEDORDER ID
                ============================================= */

                $medOrderId =
                    (int)$conn
                        ->query("
                            SELECT
                                NVL(MAX(MEDORDER_ID), 0) + 1

                            FROM
                                SYARMIMI.MEDICATION_ORDER
                        ")
                        ->fetchColumn();


                /* =============================================
                   INSERT DISCHARGE MEDICATION
                ============================================= */

                $insertDischargeMedication =
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
                            MED_END_DATE,
                            DOSES_PER_DAY,
                            ORDER_TYPE
                        )
                        VALUES
                        (
                            ?,
                            NULL,
                            ?,
                            NULL,
                            NULL,
                            ?,
                            ?,
                            ?,
                            ?,
                            TRUNC(SYSDATE),
                            NULL,
                            NULL,
                            'DISCHARGE'
                        )
                    ");

                $insertDischargeMedication->execute([
                    $medOrderId,
                    $patientId,
                    $medicationId,
                    $dosage,
                    $frequency,
                    $user_id
                ]);


                $dischargeCreatedMedOrderIds[] =
                    $medOrderId;
            }


            /* =================================================
               7. ACTUAL DISCHARGE
            ================================================= */

            $updateAdmission =
                $conn->prepare("
                    UPDATE
                        SYARMIMI.ADMISSION

                    SET
                        DISCHARGE_DATE = SYSDATE

                    WHERE
                        ADMISSION_ID = ?
                        AND DISCHARGE_DATE IS NULL
                ");

            $updateAdmission->execute([
                $admission_id
            ]);


            /* =================================================
               VERIFY DISCHARGE
            ================================================= */

            $verifyDischarge =
                $conn->prepare("
                    SELECT COUNT(*)

                    FROM SYARMIMI.ADMISSION

                    WHERE
                        ADMISSION_ID = ?
                        AND DISCHARGE_DATE IS NOT NULL
                ");

            $verifyDischarge->execute([
                $admission_id
            ]);

            if (
                (int)$verifyDischarge
                    ->fetchColumn()
                !== 1
            ) {

                throw new Exception(
                    "Unable to verify patient discharge."
                );
            }


            /* =================================================
               8. RELEASE BED
            ================================================= */

            if ($bedId > 0) {

                $updateBed =
                    $conn->prepare("
                        UPDATE
                            SYARMIMI.BED

                        SET
                            STATUS = 'Available'

                        WHERE
                            BED_ID = ?
                    ");

                $updateBed->execute([
                    $bedId
                ]);


                $verifyBed =
                    $conn->prepare("
                        SELECT STATUS

                        FROM SYARMIMI.BED

                        WHERE BED_ID = ?
                    ");

                $verifyBed->execute([
                    $bedId
                ]);

                $bedStatus =
                    strtoupper(
                        trim(
                            (string)$verifyBed
                                ->fetchColumn()
                        )
                    );

                if ($bedStatus !== 'AVAILABLE') {

                    throw new Exception(
                        "Unable to release the patient's bed."
                    );
                }
            }


            /* =================================================
               9. GENERATE FINAL ADMISSION BILL
            ================================================= */

            $generatedBillId =
                generateAdmissionBill(
                    $conn,
                    $admission_id,
                    $dischargeCreatedMedOrderIds
                );


            if ($generatedBillId <= 0) {

                throw new Exception(
                    "Unable to generate the patient's final bill."
                );
            }


            /* =================================================
               10. COMMIT
            ================================================= */

            $conn->commit();


            if ($role === 'doctor') {

                $_SESSION['success_title'] =
                    $isEarlyDischarge
                    ? 'Patient Discharged Early'
                    : 'Patient Discharged';

                $_SESSION['success_message'] =
                    $isEarlyDischarge
                    ?
                    'The patient has been discharged early. Future inpatient medication doses were cancelled, any discharge medication was sent to pharmacy for preparation, and the final bill was generated.'
                    :
                    'The patient has been discharged successfully. Any discharge medication was sent to pharmacy for preparation and the final bill was generated.';

                header(
                    "Location: treatment.php"
                );
                exit();

            } else {

                header(
                    "Location: patient_management.php?discharged=1"
                );
                exit();
            }

        }
        catch (Throwable $e) {

            if ($conn->inTransaction()) {
                $conn->rollBack();
            }

            $errorMessage =
                $e->getMessage();
        }
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
<?= $isEarlyDischarge
    ? 'Early Discharge'
    : 'Discharge Patient'
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    background:#f5f7fb;
    color:#1e293b;
    font-family:'Segoe UI',Arial,sans-serif;
}

.sidebar{
    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;
}

.content{
    flex:1;
    min-width:0;
    min-height:100vh;
    padding:25px 30px 50px;
}

.page-wrapper{
    width:100%;
    max-width:1050px;
    margin:0 auto;
}

.back-btn{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:16px;
    padding:8px 12px;
    border:1px solid #e2e8f0;
    border-radius:8px;
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

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:15px;
    margin-bottom:16px;
}

.page-title{
    margin:0;
    color:#0f172a;
    font-size:28px;
    font-weight:800;
}

.page-subtitle{
    margin-top:4px;
    color:#64748b;
    font-size:13px;
}

.discharge-type{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:700;
}

.early-type{
    border:1px solid #fed7aa;
    background:#fff7ed;
    color:#c2410c;
}

.normal-type{
    border:1px solid #bbf7d0;
    background:#f0fdf4;
    color:#15803d;
}

.card-box{
    margin-bottom:14px;
    padding:20px;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:14px;
}

.patient-card{
    border-left:4px solid #ef4444;
}

.patient-name{
    color:#0f172a;
    font-size:22px;
    font-weight:800;
}

.patient-meta{
    margin-top:3px;
    color:#64748b;
    font-size:12px;
}

.info-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:9px;
    margin-top:16px;
}

.info-box{
    min-height:72px;
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
}

.info-value{
    margin-top:5px;
    color:#334155;
    font-size:13px;
    font-weight:650;
}

.warning-box,
.normal-box{
    display:flex;
    align-items:flex-start;
    gap:10px;
    margin-top:16px;
    padding:14px;
    border-radius:10px;
    font-size:12px;
    line-height:1.55;
}

.warning-box{
    border:1px solid #fed7aa;
    background:#fff7ed;
    color:#9a3412;
}

.normal-box{
    border:1px solid #bbf7d0;
    background:#f0fdf4;
    color:#166534;
}

.section-title{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:14px;
    color:#0f172a;
    font-size:16px;
    font-weight:750;
}

.section-icon{
    width:31px;
    height:31px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:#eff6ff;
    color:#2563eb;
}

.latest-box{
    padding:14px;
    background:#f8fafc;
    border:1px solid #edf0f3;
    border-radius:9px;
}

.latest-date{
    margin-top:7px;
    color:#94a3b8;
    font-size:10px;
}

.discharge-summary{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:9px;
}

.summary-box{
    padding:13px;
    border:1px solid #e5e7eb;
    border-radius:9px;
    background:#f8fafc;
}

.summary-number{
    color:#0f172a;
    font-size:22px;
    font-weight:800;
}

.summary-label{
    margin-top:2px;
    color:#64748b;
    font-size:11px;
}

.form-label{
    margin-bottom:5px;
    color:#334155;
    font-size:13px;
    font-weight:650;
}

.form-control,
.form-select{
    border:1px solid #dbe2ea;
    border-radius:9px;
    font-size:13px;
}

.form-control:focus,
.form-select:focus{
    border-color:#3b82f6;
    box-shadow:0 0 0 4px rgba(59,130,246,.08);
}

textarea.form-control{
    min-height:110px;
    resize:vertical;
}


/* =========================================================
   DISCHARGE MEDICATION
========================================================= */

.medication-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:14px;
}

.medication-description{
    color:#64748b;
    font-size:11px;
    line-height:1.5;
}

.add-med-btn{
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:8px 11px;
    border:1px solid #bfdbfe;
    border-radius:8px;
    background:#eff6ff;
    color:#2563eb;
    font-size:11px;
    font-weight:700;
}

.add-med-btn:hover{
    background:#dbeafe;
}

.med-row{
    display:grid;
    grid-template-columns:
        minmax(0,1.5fr)
        minmax(0,1fr)
        minmax(0,1.5fr)
        40px;
    gap:9px;
    align-items:end;
    margin-bottom:10px;
    padding:13px;
    border:1px solid #e5e7eb;
    border-radius:10px;
    background:#fafbfc;
}

.remove-med-btn{
    width:40px;
    height:40px;
    display:grid;
    place-items:center;
    border:1px solid #fecaca;
    border-radius:8px;
    background:#fff;
    color:#dc2626;
}

.remove-med-btn:hover{
    background:#fef2f2;
}

.medication-note{
    margin-top:12px;
    padding:11px 12px;
    border:1px solid #bfdbfe;
    border-radius:9px;
    background:#eff6ff;
    color:#1e40af;
    font-size:11px;
    line-height:1.55;
}

.button-area{
    display:flex;
    justify-content:flex-end;
    gap:9px;
    margin-top:20px;
}

.btn{
    border-radius:9px;
    font-weight:650;
}

.btn-cancel{
    min-width:110px;
}

.btn-discharge{
    min-width:185px;
    border:0;
    background:#dc2626;
    color:#fff;
}

.btn-discharge:hover{
    background:#b91c1c;
    color:#fff;
}

.error-alert{
    border:0;
    border-radius:10px;
}

.swal2-popup{
    border-radius:16px !important;
}

@media(max-width:1100px){

    .info-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media(max-width:850px){

    .med-row{
        grid-template-columns:1fr;
    }

    .remove-med-btn{
        width:100%;
    }
}

@media(max-width:800px){

    .content{
        padding:20px 16px 40px;
    }

    .info-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .page-header{
        flex-direction:column;
    }
}

@media(max-width:600px){

    .info-grid,
    .discharge-summary{
        grid-template-columns:1fr;
    }

    .button-area{
        flex-direction:column-reverse;
    }

    .button-area .btn{
        width:100%;
    }
}

</style>

</head>

<body>

<div class="d-flex">


<?php

if ($role === 'admin') {

    include("../includes/sidebar_admin.php");

} else {

    include("../includes/sidebar_doctor.php");
}

?>


<div class="content">

<div class="page-wrapper">


<a
    href="<?= $role === 'doctor'
        ? 'treatment.php'
        : 'patient_management.php'
    ?>"
    class="back-btn"
>
<i class="bi bi-arrow-left"></i>

<?= $role === 'doctor'
    ? 'Back to Treatment'
    : 'Back to Patient Management'
?>
</a>


<div class="page-header">

<div>

<h1 class="page-title">

<?= $isEarlyDischarge
    ? 'Early Discharge'
    : 'Discharge Patient'
?>

</h1>

<div class="page-subtitle">
Complete the patient's discharge and prepare any medication required after discharge.
</div>

</div>


<?php if ($isEarlyDischarge): ?>

<span class="discharge-type early-type">
<i class="bi bi-exclamation-triangle"></i>
Early Discharge
</span>

<?php else: ?>

<span class="discharge-type normal-type">
<i class="bi bi-check-circle"></i>
Normal Discharge
</span>

<?php endif; ?>

</div>


<?php if ($errorMessage !== ''): ?>

<div class="alert alert-danger error-alert">

<i class="bi bi-exclamation-circle me-2"></i>

<strong>Discharge Failed:</strong>

<?= h($errorMessage) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     PATIENT
===================================================== -->

<div class="card-box patient-card">

<div class="patient-name">
<?= h($patient['NAME']) ?>
</div>

<div class="patient-meta">
IC Number:
<?= h($patient['IC_NUMBER'] ?: '-') ?>
</div>


<div class="info-grid">

<div class="info-box">
<div class="info-label">Admission ID</div>
<div class="info-value">
<?= h($patient['ADMISSION_ID']) ?>
</div>
</div>

<div class="info-box">
<div class="info-label">Ward</div>
<div class="info-value">
<?= h($patient['WARD_NAME']) ?>
</div>
</div>

<div class="info-box">
<div class="info-label">Bed</div>
<div class="info-value">
<?= h($patient['BED_NUMBER']) ?>
</div>
</div>

<div class="info-box">
<div class="info-label">Admission Date</div>
<div class="info-value">
<?= h($patient['ADMISSION_DATE']) ?>
</div>
</div>

<div class="info-box">
<div class="info-label">Expected Discharge</div>
<div class="info-value">
<?= h(
    $patient['EXPECTED_DISCHARGE_DATE_DISPLAY']
    ?: 'Not Set'
) ?>
</div>
</div>

</div>


<?php if ($isEarlyDischarge): ?>

<div class="warning-box">

<i class="bi bi-exclamation-triangle-fill"></i>

<div>

<strong>Early discharge detected.</strong>

<br>

The original expected discharge date will remain unchanged.
Future inpatient medication doses will be cancelled automatically.

</div>

</div>

<?php else: ?>

<div class="normal-box">

<i class="bi bi-check-circle-fill"></i>

<div>
The patient's actual discharge time will be recorded automatically when the discharge is confirmed.
</div>

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

<div class="latest-box">

<div>
<?= nl2br(
    h($latestDiagnosis['DIAGNOSIS_DETAILS'])
) ?>
</div>

<div class="latest-date">

Recorded:
<?= h($latestDiagnosis['DATE_DISPLAY']) ?>

</div>

<div class="latest-date">

Allergies:
<?= h(
    $latestDiagnosis['ALLERGIES']
    ?: '-'
) ?>

</div>

</div>

<?php else: ?>

<div class="text-muted small">
No previous diagnosis or clinical review found.
</div>

<?php endif; ?>

</div>


<!-- =====================================================
     MEDICATION IMPACT
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">
<i class="bi bi-capsule-pill"></i>
</span>

Inpatient Medication Impact

</div>


<div class="discharge-summary">

<div class="summary-box">

<div class="summary-number">
<?= $medicationCount ?>
</div>

<div class="summary-label">
Inpatient medication order(s)
</div>

</div>


<div class="summary-box">

<div class="summary-number">
<?= $remainingScheduleCount ?>
</div>

<div class="summary-label">
Future / upcoming dose(s) that will be cancelled
</div>

</div>

</div>

<div
    class="text-muted mt-3"
    style="font-size:11px;line-height:1.6;"
>

<i class="bi bi-info-circle me-1"></i>

Already administered medication history will remain unchanged.

</div>

</div>


<!-- =====================================================
     FORM
===================================================== -->

<form
    method="POST"
    id="dischargeForm"
>

<input
    type="hidden"
    name="admission_id"
    value="<?= (int)$admission_id ?>"
>

<input
    type="hidden"
    name="confirm_discharge"
    value="1"
>


<!-- =====================================================
     FINAL REVIEW
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">
<i class="bi bi-journal-check"></i>
</span>

<?= $role === 'doctor'
    ? 'Final Clinical Review'
    : 'Discharge Confirmation'
?>

</div>


<?php if ($role === 'doctor'): ?>

<div class="mb-3">

<label class="form-label">
Final Condition / Diagnosis
</label>

<textarea
    name="final_review"
    id="final_review"
    class="form-control"
    placeholder="Example: Patient condition improved, pain is controlled and patient is clinically suitable for discharge."
    required
><?= h($_POST['final_review'] ?? '') ?></textarea>

</div>


<div class="mb-3">

<label class="form-label">
Allergies
</label>

<input
    type="text"
    name="allergies"
    class="form-control"
    placeholder="No known allergies / Penicillin"
    value="<?= h(
        $_POST['allergies']
        ??
        ($latestDiagnosis['ALLERGIES'] ?? '')
    ) ?>"
>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     DISCHARGE MEDICATION
===================================================== -->

<?php if ($role === 'doctor'): ?>

<div class="card-box">

<div class="medication-header">

<div>

<div class="section-title mb-1">

<span class="section-icon">
<i class="bi bi-prescription2"></i>
</span>

Discharge Medication

</div>

<div class="medication-description">
Optional medication for the patient to take home after discharge.
</div>

</div>


<button
    type="button"
    class="add-med-btn"
    id="addMedicationBtn"
>
<i class="bi bi-plus-lg"></i>
Add Medication
</button>

</div>


<div id="medicationContainer">

<div class="med-row">

<div>

<label class="form-label">
Medication
</label>

<select
    name="discharge_medication_id[]"
    class="form-select"
>

<option value="">
Select Medication
</option>

<?php foreach ($medications as $med): ?>

<option value="<?= (int)$med['MEDICATION_ID'] ?>">

<?= h($med['MEDICATION_NAME']) ?>

<?php if (!empty($med['DOSAGE_FORM'])): ?>
—
<?= h($med['DOSAGE_FORM']) ?>
<?php endif; ?>

(Stock: <?= (int)$med['STOCK'] ?>)

</option>

<?php endforeach; ?>

</select>

</div>


<div>

<label class="form-label">
Dosage
</label>

<input
    type="text"
    name="discharge_dosage[]"
    class="form-control"
    placeholder="e.g. 500 mg"
>

</div>


<div>

<label class="form-label">
Frequency / Instructions
</label>

<input
    type="text"
    name="discharge_frequency[]"
    class="form-control"
    placeholder="e.g. Twice daily after meal"
>

</div>


<div>

<button
    type="button"
    class="remove-med-btn"
    title="Remove Medication"
>
<i class="bi bi-trash"></i>
</button>

</div>

</div>

</div>


<div class="medication-note">

<i class="bi bi-info-circle me-1"></i>

Discharge medication will be sent to the pharmacy for patient pickup.
It will <strong>not</strong> generate nurse medication administration schedules.

</div>

</div>

<?php endif; ?>


<!-- =====================================================
     BUTTON
===================================================== -->

<div class="card-box">

<div class="button-area">

<a
    href="<?= $role === 'doctor'
        ? 'treatment.php'
        : 'patient_management.php'
    ?>"
    class="btn btn-outline-secondary btn-cancel"
>

<i class="bi bi-x-lg me-1"></i>
Cancel

</a>


<button
    type="submit"
    class="btn btn-danger btn-discharge"
>

<i class="bi bi-box-arrow-right me-1"></i>

<?= $isEarlyDischarge
    ? 'Confirm Early Discharge'
    : 'Confirm Discharge'
?>

</button>

</div>

</div>


</form>

</div>
</div>
</div>


<script>

/* =========================================================
   MEDICATION TEMPLATE
========================================================= */

const medicationContainer =
    document.getElementById(
        'medicationContainer'
    );

const addMedicationBtn =
    document.getElementById(
        'addMedicationBtn'
    );


if (
    medicationContainer
    &&
    addMedicationBtn
) {

    addMedicationBtn.addEventListener(
        'click',
        function()
        {

            const firstRow =
                medicationContainer
                    .querySelector(
                        '.med-row'
                    );

            if (!firstRow) {
                return;
            }

            const newRow =
                firstRow.cloneNode(true);

            newRow
                .querySelectorAll(
                    'input'
                )
                .forEach(
                    function(input)
                    {
                        input.value = '';
                    }
                );

            newRow
                .querySelectorAll(
                    'select'
                )
                .forEach(
                    function(select)
                    {
                        select.selectedIndex = 0;
                    }
                );

            medicationContainer
                .appendChild(
                    newRow
                );
        }
    );


    medicationContainer.addEventListener(
        'click',
        function(event)
        {

            const removeButton =
                event.target.closest(
                    '.remove-med-btn'
                );

            if (!removeButton) {
                return;
            }

            const rows =
                medicationContainer
                    .querySelectorAll(
                        '.med-row'
                    );

            const row =
                removeButton.closest(
                    '.med-row'
                );

            if (rows.length === 1) {

                row
                    .querySelectorAll(
                        'input'
                    )
                    .forEach(
                        function(input)
                        {
                            input.value = '';
                        }
                    );

                row
                    .querySelectorAll(
                        'select'
                    )
                    .forEach(
                        function(select)
                        {
                            select.selectedIndex = 0;
                        }
                    );

                return;
            }

            row.remove();
        }
    );
}


/* =========================================================
   CONFIRM DISCHARGE
========================================================= */

const dischargeForm =
    document.getElementById(
        'dischargeForm'
    );

const isEarlyDischarge =
    <?= $isEarlyDischarge
        ? 'true'
        : 'false'
    ?>;

const isDoctor =
    <?= $role === 'doctor'
        ? 'true'
        : 'false'
    ?>;


if (dischargeForm) {

    dischargeForm.addEventListener(
        'submit',
        function(event)
        {

            event.preventDefault();


            if (isDoctor) {

                const finalReview =
                    document
                        .getElementById(
                            'final_review'
                        )
                        .value
                        .trim();

                if (finalReview === '') {

                    Swal.fire({

                        icon:'warning',

                        title:
                            'Final Review Required',

                        text:
                            'Please enter the patient final clinical condition before discharge.',

                        confirmButtonColor:
                            '#2563eb'

                    });

                    return;
                }


                /* =========================================
                   VALIDATE PARTIAL MEDICATION ROW
                ========================================= */

                const rows =
                    document.querySelectorAll(
                        '.med-row'
                    );

                for (
                    const row
                    of rows
                ) {

                    const medication =
                        row.querySelector(
                            'select'
                        ).value;

                    const inputs =
                        row.querySelectorAll(
                            'input'
                        );

                    const dosage =
                        inputs[0]
                            .value
                            .trim();

                    const frequency =
                        inputs[1]
                            .value
                            .trim();


                    const rowHasSomething =
                        medication !== ''
                        ||
                        dosage !== ''
                        ||
                        frequency !== '';


                    if (
                        rowHasSomething
                        &&
                        (
                            medication === ''
                            ||
                            dosage === ''
                            ||
                            frequency === ''
                        )
                    ) {

                        Swal.fire({

                            icon:'warning',

                            title:
                                'Incomplete Medication',

                            text:
                                'Please complete the medication, dosage and frequency or remove the incomplete discharge medication row.',

                            confirmButtonColor:
                                '#2563eb'

                        });

                        return;
                    }
                }
            }


            Swal.fire({

                icon:
                    isEarlyDischarge
                    ? 'warning'
                    : 'question',

                title:
                    isEarlyDischarge
                    ? 'Confirm Early Discharge?'
                    : 'Confirm Patient Discharge?',

                html:
                    isEarlyDischarge

                    ?

                    `
                    The patient is being discharged
                    <strong>before the expected discharge date</strong>.

                    <br><br>

                    The expected discharge date will remain unchanged.

                    Future inpatient doses will be cancelled.

                    Any discharge medication entered here will be sent to pharmacy for patient pickup.
                    `

                    :

                    `
                    The patient's active admission will be completed.

                    <br><br>

                    Future inpatient doses will be cancelled.

                    Any discharge medication entered here will be sent to pharmacy for patient pickup.
                    `,

                showCancelButton:true,

                reverseButtons:true,

                confirmButtonText:
                    isEarlyDischarge
                    ? 'Yes, Discharge Early'
                    : 'Yes, Discharge',

                cancelButtonText:'Cancel',

                confirmButtonColor:'#dc2626',

                cancelButtonColor:'#64748b'

            })
            .then(
                function(result)
                {

                    if (result.isConfirmed) {

                        Swal.fire({

                            title:
                                'Processing Discharge',

                            text:
                                'Updating admission, medication and pharmacy workflow...',

                            allowOutsideClick:false,

                            allowEscapeKey:false,

                            didOpen:function()
                            {
                                Swal.showLoading();
                            }

                        });

                        dischargeForm.submit();
                    }
                }
            );
        }
    );
}

</script>

</body>
</html>