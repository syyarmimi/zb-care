<?php

session_start();

include("../config/config.php");

/* =========================================================
   ROLE CHECK
========================================================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    die("Access Denied");
}

$doctor_id = (int)($_SESSION['user_id'] ?? 0);

if ($doctor_id <= 0) {
    die("Invalid doctor account.");
}


/* =========================================================
   BASIC VARIABLES
========================================================= */

$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';

$appointmentPatient = null;
$walkinPatient      = null;
$patientInfo        = null;

$errorMessage = '';



/* =========================================================
   GET DOCTOR INFORMATION
========================================================= */

$doctorName = '';

try {

    $doctorStmt = $conn->prepare("
        SELECT USERNAME
        FROM SYARMIMI.HOSPITAL_STAFF
        WHERE ACCOUNT_ID = ?
    ");

    $doctorStmt->execute([$doctor_id]);

    $doctorName = $doctorStmt->fetchColumn();

} catch (Exception $e) {

    $doctorName = '';

}



/* =========================================================
   GET APPOINTMENT PATIENT
========================================================= */

if ($type === 'appointment' && !empty($id)) {

    $stmt = $conn->prepare("
        SELECT *
        FROM SYARMIMI.APPOINTMENT
        WHERE APPOINTMENT_ID = ?
        AND ACCOUNT_ID = ?
    ");

    $stmt->execute([
        (int)$id,
        $doctor_id
    ]);

    $appointmentPatient = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($appointmentPatient) {

        $patientInfo = [
            'NAME' => $appointmentPatient['PATIENT_NAME'] ?? '',
            'WARD_NAME' => 'Appointment Patient',
            'BED_NUMBER' => '-',
            'ADMISSION_DATE' => !empty($appointmentPatient['APPOINTMENT_DATE'])
                ? strtoupper(date(
                    'd-M-y',
                    strtotime($appointmentPatient['APPOINTMENT_DATE'])
                ))
                : '-'
        ];
    }
}



/* =========================================================
   GET WALK-IN PATIENT
========================================================= */

if ($type === 'walkin' && !empty($id)) {

    $stmt = $conn->prepare("
        SELECT
            W.*,
            P.*
        FROM SYARMIMI.WALKIN_CONSULTATION W
        JOIN SYARMIMI.PATIENT P
            ON W.PATIENT_ID = P.PATIENT_ID
        WHERE W.CONSULTATION_ID = ?
        AND W.ACCOUNT_ID = ?
    ");

    $stmt->execute([
        (int)$id,
        $doctor_id
    ]);

    $walkinPatient = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($walkinPatient) {

        $patientInfo = [
            'NAME' => $walkinPatient['NAME'] ?? '',
            'WARD_NAME' => 'Walk-In Patient',
            'BED_NUMBER' => '-',
            'ADMISSION_DATE' => date('d-M-Y')
        ];
    }
}



/* =========================================================
   GET PATIENT DEPARTMENT
========================================================= */

$patientDept = null;

if ($appointmentPatient) {

    $patientDept = trim(
        $appointmentPatient['DEPARTMENT'] ?? ''
    );

}

elseif ($walkinPatient) {

    $patientDept = trim(
        $walkinPatient['DEPARTMENT'] ?? ''
    );

}



/* =========================================================
   AVAILABLE BEDS
   ONLY SHOW AVAILABLE BEDS FOR PATIENT'S DEPARTMENT

   Handles:
   Paediatric  = Paediatrics
   Orthopaedic = Orthopaedics
========================================================= */

$availableBeds = [];

if (!empty($patientDept)) {

    $bedStmt = $conn->prepare("
        SELECT
            B.BED_ID,
            B.BED_NUMBER,
            W.WARD_NAME
        FROM SYARMIMI.BED B
        JOIN SYARMIMI.WARD W
            ON B.WARD_ID = W.WARD_ID
        WHERE
            REGEXP_REPLACE(
                UPPER(TRIM(W.WARD_NAME)),
                'S$',
                ''
            )
            =
            REGEXP_REPLACE(
                UPPER(TRIM(?)),
                'S$',
                ''
            )
        AND TRIM(UPPER(B.STATUS)) = 'AVAILABLE'
        ORDER BY B.BED_NUMBER
    ");

    $bedStmt->execute([
        $patientDept
    ]);

    $availableBeds = $bedStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================================
   GET MEDICATION
========================================================= */

$medications = [];

try {

    $medStmt = $conn->query("
        SELECT
            MEDICATION_ID,
            MEDICATION_NAME
        FROM SYARMIMI.MEDICATION
        ORDER BY MEDICATION_NAME
    ");

    $medications = $medStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $medications = [];

}



/* =========================================================
   GET TODAY'S PATIENT QUEUE
========================================================= */

$todayAppointments = [];

try {

    $queueStmt = $conn->prepare("

        SELECT
            APPOINTMENT_ID AS RECORD_ID,
            PATIENT_NAME,
            STATUS,
            APPOINTMENT_TIME,
            'APPOINTMENT' AS TYPE

        FROM SYARMIMI.APPOINTMENT

        WHERE ACCOUNT_ID = ?

        AND STATUS = 'Approved'

        AND TRUNC(APPOINTMENT_DATE) = TRUNC(SYSDATE)


        UNION ALL


        SELECT
            W.CONSULTATION_ID AS RECORD_ID,
            P.NAME AS PATIENT_NAME,
            W.STATUS,
            'Walk-In' AS APPOINTMENT_TIME,
            'WALKIN' AS TYPE

        FROM SYARMIMI.WALKIN_CONSULTATION W

        JOIN SYARMIMI.PATIENT P
            ON W.PATIENT_ID = P.PATIENT_ID

        WHERE W.ACCOUNT_ID = ?

        AND TRIM(UPPER(W.STATUS)) = 'ASSIGNED'

        ORDER BY APPOINTMENT_TIME

    ");

    $queueStmt->execute([
        $doctor_id,
        $doctor_id
    ]);

    $todayAppointments = $queueStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $todayAppointments = [];

}



/* =========================================================
   GET DOCTOR'S CURRENT ADMITTED PATIENTS
   THIS ALLOWS DOCTOR TO DISCHARGE THEIR OWN PATIENT
========================================================= */

$myAdmissions = [];

try {

    $admissionStmt = $conn->prepare("

        SELECT

            A.ADMISSION_ID,

            A.PATIENT_ID,

            A.BED_ID,

            A.ADMISSION_DATE,

            A.EXPECTED_DISCHARGE_DATE,

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

        WHERE A.ACCOUNT_ID = ?

        AND A.DISCHARGE_DATE IS NULL

        ORDER BY A.ADMISSION_DATE DESC

    ");

    $admissionStmt->execute([
        $doctor_id
    ]);

    $myAdmissions = $admissionStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $myAdmissions = [];

}



/* =========================================================
   GET PATIENT LIST FOR DIAGNOSIS
========================================================= */

$patients = [];

try {

    $stmt = $conn->prepare("

        SELECT

            A.ADMISSION_ID AS RECORD_ID,

            P.NAME,

            NULL AS APPOINTMENT_TIME,

            'ADMISSION' AS SOURCE_TYPE

        FROM SYARMIMI.ADMISSION A

        JOIN SYARMIMI.PATIENT P
            ON A.PATIENT_ID = P.PATIENT_ID

        WHERE A.ACCOUNT_ID = ?

        AND A.DISCHARGE_DATE IS NULL


        UNION ALL


        SELECT

            APPOINTMENT_ID AS RECORD_ID,

            PATIENT_NAME AS NAME,

            APPOINTMENT_TIME,

            'APPOINTMENT' AS SOURCE_TYPE

        FROM SYARMIMI.APPOINTMENT

        WHERE ACCOUNT_ID = ?

        AND STATUS = 'Approved'

        AND TRUNC(APPOINTMENT_DATE) = TRUNC(SYSDATE)


        UNION ALL


        SELECT

            W.CONSULTATION_ID AS RECORD_ID,

            P.NAME,

            'Walk-In',

            'WALKIN'

        FROM SYARMIMI.WALKIN_CONSULTATION W

        JOIN SYARMIMI.PATIENT P
            ON W.PATIENT_ID = P.PATIENT_ID

        WHERE W.ACCOUNT_ID = ?

        AND TRIM(UPPER(W.STATUS)) = 'ASSIGNED'

    ");

    $stmt->execute([
        $doctor_id,
        $doctor_id,
        $doctor_id
    ]);

    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {

    $patients = [];

}



/* =========================================================
   SELECTED RECORD
========================================================= */

$selected_id = null;

if ($appointmentPatient) {

    $selected_id =
        $appointmentPatient['APPOINTMENT_ID'] ?? null;

}

elseif ($walkinPatient) {

    $selected_id =
        $walkinPatient['CONSULTATION_ID'] ?? null;

}



/* =========================================================
   SAVE TREATMENT
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['save_all'])) {

    try {

        /* =====================================================
           START TRANSACTION
        ===================================================== */

        $conn->beginTransaction();


        $patient_id      = null;
        $appointment_id  = null;
        $consultation_id = null;
        $admission_id    = null;


        /* =====================================================
           GET PATIENT ID
        ===================================================== */

        if ($type === 'appointment' && $appointmentPatient) {

            $patient_id =
                $appointmentPatient['PATIENT_ID'] ?? null;

            $appointment_id =
                $appointmentPatient['APPOINTMENT_ID'] ?? null;


            /*
             * If appointment does not have PATIENT_ID,
             * create patient record.
             */

            if (empty($patient_id)) {

                $insertPatient = $conn->prepare("

                    INSERT INTO SYARMIMI.PATIENT
                    (
                        PATIENT_ID,
                        NAME
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(PATIENT_ID),0) + 1
                            FROM SYARMIMI.PATIENT
                        ),
                        ?
                    )

                ");

                $insertPatient->execute([
                    $appointmentPatient['PATIENT_NAME']
                ]);


                $patient_id = $conn->query("

                    SELECT MAX(PATIENT_ID)
                    FROM SYARMIMI.PATIENT

                ")->fetchColumn();


                $updateAppointment = $conn->prepare("

                    UPDATE SYARMIMI.APPOINTMENT

                    SET PATIENT_ID = ?

                    WHERE APPOINTMENT_ID = ?

                ");

                $updateAppointment->execute([
                    $patient_id,
                    $appointment_id
                ]);
            }
        }


        if ($type === 'walkin' && $walkinPatient) {

            $patient_id =
                $walkinPatient['PATIENT_ID'] ?? null;

            $consultation_id =
                $walkinPatient['CONSULTATION_ID'] ?? null;
        }



        /* =====================================================
           DIAGNOSIS
        ===================================================== */

        if (!empty(trim($_POST['details'] ?? ''))) {

            /*
             * Only check consultation ID if there is one.
             */

            $existsDiagnosis = 0;

            if (!empty($consultation_id)) {

                $checkDiagnosis = $conn->prepare("

                    SELECT COUNT(*)

                    FROM SYARMIMI.DIAGNOSIS

                    WHERE CONSULTATION_ID = ?

                ");

                $checkDiagnosis->execute([
                    $consultation_id
                ]);

                $existsDiagnosis =
                    (int)$checkDiagnosis->fetchColumn();
            }


            if ($existsDiagnosis === 0) {

                $diagnosisStmt = $conn->prepare("

                    INSERT INTO SYARMIMI.DIAGNOSIS
                    (
                        DIAGNOSIS_ID,
                        PATIENT_ID,
                        APPOINTMENT_ID,
                        CONSULTATION_ID,
                        DIAGNOSIS_DETAILS,
                        ALLERGIES,
                        DATE_RECORDED,
                        ACCOUNT_ID
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(DIAGNOSIS_ID),0) + 1
                            FROM SYARMIMI.DIAGNOSIS
                        ),
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        SYSDATE,
                        ?
                    )

                ");

                $diagnosisStmt->execute([
                    $patient_id,
                    $appointment_id,
                    $consultation_id,
                    $_POST['details'],
                    $_POST['allergies'] ?? '',
                    $doctor_id
                ]);
            }
        }



        /* =====================================================
           MEDICATION
        ===================================================== */

        if (!empty($_POST['medication_id'])
            && is_array($_POST['medication_id'])) {

            foreach ($_POST['medication_id'] as $index => $med) {

                if (empty($med)) {
                    continue;
                }


                $medication_id = (int)$med;

                $dosage =
                    trim($_POST['dosage'][$index] ?? '');

                $frequency =
                    trim($_POST['frequency'][$index] ?? '');


                /*
                 * Do not check duplicate medication when
                 * there is no consultation ID.
                 */

                $existsMed = 0;

                if (!empty($consultation_id)) {

                    $checkMed = $conn->prepare("

                        SELECT COUNT(*)

                        FROM SYARMIMI.MEDICATION_ORDER

                        WHERE CONSULTATION_ID = ?

                        AND MEDICATION_ID = ?

                    ");

                    $checkMed->execute([
                        $consultation_id,
                        $medication_id
                    ]);

                    $existsMed =
                        (int)$checkMed->fetchColumn();
                }


                if ($existsMed === 0) {

                    $medStmt = $conn->prepare("

                        INSERT INTO SYARMIMI.MEDICATION_ORDER
                        (
                            MEDORDER_ID,
                            ADMISSION_ID,
                            PATIENT_ID,
                            APPOINTMENT_ID,
                            CONSULTATION_ID,
                            MEDICATION_ID,
                            DOSAGE,
                            FREQUENCY,
                            ACCOUNT_ID
                        )
                        VALUES
                        (
                            (
                                SELECT NVL(MAX(MEDORDER_ID),0) + 1
                                FROM SYARMIMI.MEDICATION_ORDER
                            ),
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

                    $medStmt->execute([
                        $admission_id,
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



        /* =====================================================
           DECISION
        ===================================================== */

        $decision =
            trim($_POST['decision_type'] ?? '');



        /* =====================================================
           APPOINTMENT DECISION
        ===================================================== */

        if ($type === 'appointment') {


            /* =================================================
               COMPLETED
            ================================================= */

            if ($decision === 'Completed') {

                $stmt = $conn->prepare("

                    UPDATE SYARMIMI.APPOINTMENT

                    SET STATUS = 'Completed'

                    WHERE APPOINTMENT_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $stmt->execute([
                    $appointment_id,
                    $doctor_id
                ]);
            }



            /* =================================================
               NEXT APPOINTMENT
            ================================================= */

            elseif ($decision === 'Next Appointment') {

                $nextDate =
                    trim($_POST['next_date'] ?? '');

                $nextTime =
                    trim($_POST['next_time'] ?? '');


                if (empty($nextDate) || empty($nextTime)) {

                    throw new Exception(
                        "Please select the next appointment date and time."
                    );
                }


                /*
                 * Insert appointment.
                 *
                 * APPOINTMENT_DATE is assumed to be DATE.
                 * Explicit TO_DATE prevents Oracle format issues.
                 */

                $appointmentStmt = $conn->prepare("

                    INSERT INTO SYARMIMI.APPOINTMENT
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
                        (
                            SELECT NVL(MAX(APPOINTMENT_ID),0) + 1
                            FROM SYARMIMI.APPOINTMENT
                        ),
                        ?,
                        ?,
                        ?,
                        ?,
                        TO_DATE(?, 'YYYY-MM-DD'),
                        ?,
                        'Approved',
                        ?,
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


                $appointmentStmt->execute([

                    $appointmentPatient['PATIENT_NAME'] ?? '',

                    $appointmentPatient['PHONE'] ?? null,

                    $appointmentPatient['EMAIL'] ?? null,

                    $appointmentPatient['DEPARTMENT'] ?? null,

                    $nextDate,

                    $appointmentPatient['NOTES'] ?? null,

                    $appointmentPatient['DOB'] ?? null,

                    $doctorName,

                    $nextTime,

                    $appointmentPatient['ADDRESS'] ?? null,

                    $appointmentPatient['CITY'] ?? null,

                    $appointmentPatient['STATE'] ?? null,

                    $appointmentPatient['IC_NUMBER'] ?? null,

                    $appointmentPatient['GENDER'] ?? null,

                    $doctor_id,

                    $patient_id
                ]);


                /*
                 * Get new appointment ID.
                 */

                $newAppointmentId = $conn->query("

                    SELECT MAX(APPOINTMENT_ID)
                    FROM SYARMIMI.APPOINTMENT

                ")->fetchColumn();



                /* =============================================
                   DOCTOR SLOT
                ============================================= */

                $slotStmt = $conn->prepare("

                    SELECT
                        SLOT_ID,
                        MAX_PATIENT,
                        CURRENT_PATIENT,
                        STATUS

                    FROM SYARMIMI.DOCTOR_SLOT

                    WHERE ACCOUNT_ID = ?

                    AND TRUNC(SLOT_DATE) =
                        TO_DATE(?, 'YYYY-MM-DD')

                    AND SLOT_TIME = ?

                ");

                $slotStmt->execute([
                    $doctor_id,
                    $nextDate,
                    $nextTime
                ]);

                $slot =
                    $slotStmt->fetch(PDO::FETCH_ASSOC);



                if ($slot) {

                    $maxPatient =
                        (int)($slot['MAX_PATIENT'] ?? 1);

                    $currentPatient =
                        (int)($slot['CURRENT_PATIENT'] ?? 0);


                    if ($currentPatient >= $maxPatient) {

                        throw new Exception(
                            "Selected appointment slot is already full."
                        );
                    }


                    $newCurrentPatient =
                        $currentPatient + 1;


                    $slotStatus =
                        ($newCurrentPatient >= $maxPatient)
                        ? 'Booked'
                        : 'Available';


                    $updateSlot = $conn->prepare("

                        UPDATE SYARMIMI.DOCTOR_SLOT

                        SET
                            CURRENT_PATIENT = ?,
                            STATUS = ?,
                            APPOINTMENT_ID = ?

                        WHERE SLOT_ID = ?

                    ");

                    $updateSlot->execute([
                        $newCurrentPatient,
                        $slotStatus,
                        $newAppointmentId,
                        $slot['SLOT_ID']
                    ]);

                }

                else {

                    $insertSlot = $conn->prepare("

                        INSERT INTO SYARMIMI.DOCTOR_SLOT
                        (
                            SLOT_ID,
                            ACCOUNT_ID,
                            SLOT_DATE,
                            SLOT_TIME,
                            MAX_PATIENT,
                            CURRENT_PATIENT,
                            STATUS,
                            APPOINTMENT_ID
                        )
                        VALUES
                        (
                            (
                                SELECT NVL(MAX(SLOT_ID),0) + 1
                                FROM SYARMIMI.DOCTOR_SLOT
                            ),
                            ?,
                            TO_DATE(?, 'YYYY-MM-DD'),
                            ?,
                            1,
                            1,
                            'Booked',
                            ?
                        )

                    ");

                    $insertSlot->execute([
                        $doctor_id,
                        $nextDate,
                        $nextTime,
                        $newAppointmentId
                    ]);
                }



                /* =============================================
                   COMPLETE CURRENT APPOINTMENT
                ============================================= */

                $completeAppointment = $conn->prepare("

                    UPDATE SYARMIMI.APPOINTMENT

                    SET STATUS = 'Completed'

                    WHERE APPOINTMENT_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $completeAppointment->execute([
                    $appointment_id,
                    $doctor_id
                ]);
            }



            /* =================================================
               ADMIT PATIENT
            ================================================= */

            elseif ($decision === 'Admit Patient') {

                $bed_id =
                    (int)($_POST['bed_id'] ?? 0);

                $expectedDate =
                    trim($_POST['expected_discharge_date'] ?? '');


                if ($bed_id <= 0) {

                    throw new Exception(
                        "Please select a bed."
                    );
                }


                if (empty($expectedDate)) {

                    throw new Exception(
                        "Please select the expected discharge date."
                    );
                }


                /*
                 * Make sure selected bed is actually available
                 * and belongs to the patient's department.
                 */

           $bedCheck = $conn->prepare("

    SELECT
        B.BED_ID,
        B.BED_NUMBER,
        W.WARD_NAME

    FROM SYARMIMI.BED B

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE B.BED_ID = ?

    AND TRIM(UPPER(B.STATUS)) = 'AVAILABLE'

    AND REGEXP_REPLACE(
            UPPER(TRIM(W.WARD_NAME)),
            'S$',
            ''
        )
        =
        REGEXP_REPLACE(
            UPPER(TRIM(?)),
            'S$',
            ''
        )

");

                $bedCheck->execute([
                    $bed_id,
                    $patientDept
                ]);

                $selectedBed =
                    $bedCheck->fetch(PDO::FETCH_ASSOC);


                if (!$selectedBed) {

                    throw new Exception(
                        "The selected bed is no longer available or does not belong to the correct ward."
                    );
                }


                /*
                 * Check existing admission.
                 */

                $checkAdmission = $conn->prepare("

                    SELECT COUNT(*)

                    FROM SYARMIMI.ADMISSION

                    WHERE PATIENT_ID = ?

                    AND DISCHARGE_DATE IS NULL

                ");

                $checkAdmission->execute([
                    $patient_id
                ]);

                $existingAdmission =
                    (int)$checkAdmission->fetchColumn();


                if ($existingAdmission > 0) {

                    throw new Exception(
                        "This patient already has an active admission."
                    );
                }


                /*
                 * IMPORTANT:
                 *
                 * EXPECTED_DISCHARGE_DATE is Oracle DATE.
                 *
                 * We use:
                 *
                 * TO_DATE(?, 'YYYY-MM-DD')
                 *
                 * so Oracle receives the correct DATE.
                 *
                 * Positional ? parameters avoid PDO-ODBC
                 * named parameter datatype problems.
                 */

                $insertAdmission = $conn->prepare("

                    INSERT INTO SYARMIMI.ADMISSION
                    (
                        ADMISSION_ID,
                        ADMISSION_DATE,
                        PATIENT_ID,
                        BED_ID,
                        ACCOUNT_ID,
                        IS_SEEN,
                        EXPECTED_DISCHARGE_DATE
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(ADMISSION_ID),0) + 1
                            FROM SYARMIMI.ADMISSION
                        ),
                        SYSDATE,
                        ?,
                        ?,
                        ?,
                        0,
                        TO_DATE(?, 'YYYY-MM-DD')
                    )

                ");


                /*
                 * VERY IMPORTANT ORDER:
                 *
                 * 1 = PATIENT_ID
                 * 2 = BED_ID
                 * 3 = ACCOUNT_ID
                 * 4 = EXPECTED_DISCHARGE_DATE
                 */

                $insertAdmission->execute([

                    (int)$patient_id,

                    (int)$bed_id,

                    (int)$doctor_id,

                    $expectedDate
                ]);


                /*
                 * Get new admission ID.
                 */

                $newAdmissionId = $conn->query("

                    SELECT MAX(ADMISSION_ID)
                    FROM SYARMIMI.ADMISSION

                ")->fetchColumn();


                if (empty($newAdmissionId)) {

                    throw new Exception(
                        "Admission was not created."
                    );
                }


                /*
                 * Occupy bed.
                 */

                $updateBed = $conn->prepare("

                    UPDATE SYARMIMI.BED

                    SET STATUS = 'Occupied'

                    WHERE BED_ID = ?

                    AND TRIM(UPPER(STATUS)) = 'AVAILABLE'

                ");

                $updateBed->execute([
                    $bed_id
                ]);


                if ($updateBed->rowCount() === 0) {

                    throw new Exception(
                        "Unable to update bed status."
                    );
                }


                /*
                 * Link existing medication orders
                 * to this admission.
                 */

                $updateMedication = $conn->prepare("

                    UPDATE SYARMIMI.MEDICATION_ORDER

                    SET ADMISSION_ID = ?

                    WHERE PATIENT_ID = ?

                    AND ADMISSION_ID IS NULL

                ");

                $updateMedication->execute([
                    $newAdmissionId,
                    $patient_id
                ]);


                /*
                 * Mark appointment as admitted.
                 */

                $updateAppointment = $conn->prepare("

                    UPDATE SYARMIMI.APPOINTMENT

                    SET STATUS = 'Admitted'

                    WHERE APPOINTMENT_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $updateAppointment->execute([
                    $appointment_id,
                    $doctor_id
                ]);
            }
        }



        /* =====================================================
           WALK-IN DECISION
        ===================================================== */

        if ($type === 'walkin') {

            $decision =
                trim($_POST['decision_type'] ?? '');


            /* =================================================
               CHECK ACTIVE ADMISSION
            ================================================= */

            $checkAdmission = $conn->prepare("

                SELECT COUNT(*)

                FROM SYARMIMI.ADMISSION

                WHERE PATIENT_ID = ?

                AND DISCHARGE_DATE IS NULL

            ");

            $checkAdmission->execute([
                $patient_id
            ]);

            $existingAdmission =
                (int)$checkAdmission->fetchColumn();



            /* =================================================
               COMPLETED
            ================================================= */

            if ($decision === 'Completed') {

                $stmt = $conn->prepare("

                    UPDATE SYARMIMI.WALKIN_CONSULTATION

                    SET STATUS = 'Completed'

                    WHERE CONSULTATION_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $stmt->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }



            /* =================================================
               NEXT APPOINTMENT
            ================================================= */

            elseif ($decision === 'Next Appointment') {

                $nextDate =
                    trim($_POST['next_date'] ?? '');

                $nextTime =
                    trim($_POST['next_time'] ?? '');


                if (empty($nextDate) || empty($nextTime)) {

                    throw new Exception(
                        "Please select the next appointment date and time."
                    );
                }


                $appointmentStmt = $conn->prepare("

                    INSERT INTO SYARMIMI.APPOINTMENT
                    (
                        APPOINTMENT_ID,
                        PATIENT_NAME,
                        PHONE,
                        DEPARTMENT,
                        APPOINTMENT_DATE,
                        STATUS,
                        DOCTOR_NAME,
                        APPOINTMENT_TIME,
                        IC_NUMBER,
                        GENDER,
                        ACCOUNT_ID,
                        PATIENT_ID
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(APPOINTMENT_ID),0) + 1
                            FROM SYARMIMI.APPOINTMENT
                        ),
                        ?,
                        ?,
                        ?,
                        TO_DATE(?, 'YYYY-MM-DD'),
                        'Approved',
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )

                ");


                $appointmentStmt->execute([

                    $walkinPatient['NAME'] ?? '',

                    $walkinPatient['PHONE'] ?? null,

                    $walkinPatient['DEPARTMENT'] ?? null,

                    $nextDate,

                    $doctorName,

                    $nextTime,

                    $walkinPatient['IC_NUMBER'] ?? null,

                    $walkinPatient['GENDER'] ?? null,

                    $doctor_id,

                    $patient_id

                ]);


                $newAppointmentId =
                    $conn->query("

                        SELECT MAX(APPOINTMENT_ID)
                        FROM SYARMIMI.APPOINTMENT

                    ")->fetchColumn();


                /*
                 * Create slot.
                 */

                $insertSlot = $conn->prepare("

                    INSERT INTO SYARMIMI.DOCTOR_SLOT
                    (
                        SLOT_ID,
                        ACCOUNT_ID,
                        SLOT_DATE,
                        SLOT_TIME,
                        MAX_PATIENT,
                        CURRENT_PATIENT,
                        STATUS,
                        APPOINTMENT_ID
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(SLOT_ID),0) + 1
                            FROM SYARMIMI.DOCTOR_SLOT
                        ),
                        ?,
                        TO_DATE(?, 'YYYY-MM-DD'),
                        ?,
                        1,
                        1,
                        'Booked',
                        ?
                    )

                ");

                $insertSlot->execute([
                    $doctor_id,
                    $nextDate,
                    $nextTime,
                    $newAppointmentId
                ]);


                /*
                 * Complete walk-in.
                 */

                $updateWalkin = $conn->prepare("

                    UPDATE SYARMIMI.WALKIN_CONSULTATION

                    SET STATUS = 'Completed'

                    WHERE CONSULTATION_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $updateWalkin->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }



            /* =================================================
               ADMIT WALK-IN PATIENT
            ================================================= */

            elseif ($decision === 'Admit Patient') {

                if ($existingAdmission > 0) {

                    throw new Exception(
                        "This patient already has an active admission."
                    );
                }


                $bed_id =
                    (int)($_POST['bed_id'] ?? 0);

                $expectedDate =
                    trim($_POST['expected_discharge_date'] ?? '');


                if ($bed_id <= 0) {

                    throw new Exception(
                        "Please select a bed."
                    );
                }


                if (empty($expectedDate)) {

                    throw new Exception(
                        "Please select the expected discharge date."
                    );
                }


                /*
                 * Verify bed.
                 */

                $bedCheck = $conn->prepare("

                    SELECT
                        B.BED_ID,
                        B.BED_NUMBER,
                        W.WARD_NAME

                    FROM SYARMIMI.BED B

                    JOIN SYARMIMI.WARD W
                        ON B.WARD_ID = W.WARD_ID

                    WHERE B.BED_ID = ?

                    AND TRIM(UPPER(B.STATUS)) = 'AVAILABLE'

                    AND TRIM(UPPER(W.WARD_NAME)) =
                        TRIM(UPPER(?))

                ");

                $bedCheck->execute([
                    $bed_id,
                    $patientDept
                ]);

                $selectedBed =
                    $bedCheck->fetch(PDO::FETCH_ASSOC);


                if (!$selectedBed) {

                    throw new Exception(
                        "The selected bed is no longer available or does not belong to the correct ward."
                    );
                }


                /*
                 * Insert admission.
                 *
                 * DATE conversion is explicit.
                 */

                $insertAdmission = $conn->prepare("

                    INSERT INTO SYARMIMI.ADMISSION
                    (
                        ADMISSION_ID,
                        ADMISSION_DATE,
                        PATIENT_ID,
                        BED_ID,
                        ACCOUNT_ID,
                        IS_SEEN,
                        EXPECTED_DISCHARGE_DATE
                    )
                    VALUES
                    (
                        (
                            SELECT NVL(MAX(ADMISSION_ID),0) + 1
                            FROM SYARMIMI.ADMISSION
                        ),
                        SYSDATE,
                        ?,
                        ?,
                        ?,
                        1,
                        TO_DATE(?, 'YYYY-MM-DD')
                    )

                ");


                $insertAdmission->execute([

                    (int)$patient_id,

                    (int)$bed_id,

                    (int)$doctor_id,

                    $expectedDate

                ]);


                /*
                 * Get admission ID.
                 */

                $newAdmissionId =
                    $conn->query("

                        SELECT MAX(ADMISSION_ID)
                        FROM SYARMIMI.ADMISSION

                    ")->fetchColumn();


                /*
                 * Occupy bed.
                 */

                $updateBed = $conn->prepare("

                    UPDATE SYARMIMI.BED

                    SET STATUS = 'Occupied'

                    WHERE BED_ID = ?

                    AND TRIM(UPPER(STATUS)) = 'AVAILABLE'

                ");

                $updateBed->execute([
                    $bed_id
                ]);


                if ($updateBed->rowCount() === 0) {

                    throw new Exception(
                        "Unable to update bed status."
                    );
                }


                /*
                 * Link medication orders.
                 */

                $updateMedication = $conn->prepare("

                    UPDATE SYARMIMI.MEDICATION_ORDER

                    SET ADMISSION_ID = ?

                    WHERE PATIENT_ID = ?

                    AND ADMISSION_ID IS NULL

                ");

                $updateMedication->execute([
                    $newAdmissionId,
                    $patient_id
                ]);


                /*
                 * Update walk-in status.
                 */

                $updateWalkin = $conn->prepare("

                    UPDATE SYARMIMI.WALKIN_CONSULTATION

                    SET STATUS = 'Admitted'

                    WHERE CONSULTATION_ID = ?

                    AND ACCOUNT_ID = ?

                ");

                $updateWalkin->execute([
                    $consultation_id,
                    $doctor_id
                ]);
            }
        }



        /* =====================================================
           COMMIT
        ===================================================== */

        $conn->commit();


        $_SESSION['success_message'] =
            "Diagnosis and treatment saved successfully.";


        header("Location: treatment.php");

        exit();


    } catch (Exception $e) {

        /*
         * Rollback if transaction is active.
         */

        if ($conn->inTransaction()) {

            $conn->rollBack();

        }


        $errorMessage =
            $e->getMessage();
    }
}

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Treatment</title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >


    <style>

        body {
            background: #eef2f7;
        }

        .content {
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }

        .card-box {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .section-title {
            font-weight: 600;
            margin-bottom: 15px;
        }

        .patient-info {
            background: #dbeafe;
            border-radius: 12px;
            padding: 15px;
        }

        input,
        select,
        textarea {
            border-radius: 10px !important;
        }

        button {
            border-radius: 10px;
        }

        #nextAppointmentBox {
            background: #ffffff;
            border: 1px solid #dbeafe;
            border-left: 5px solid #0d6efd;
        }

        #nextAppointmentBox .section-title {
            color: #0d6efd;
            font-weight: 700;
        }

        #bedBox {
            background: #f8fafc;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        #expectedDischargeBox {
            background: #f8fafc;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            margin-top: 15px;
        }

        .admission-card {
            border-left: 5px solid #198754;
        }

        .stay-badge {
            background: #e8f5e9;
            color: #198754;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #6c757d;
        }

        .table th {
            white-space: nowrap;
        }

    </style>

</head>


<body>


<div class="d-flex">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php include("../includes/sidebar_doctor.php"); ?>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content">


        <h4 class="mb-4">
            <i class="bi bi-clipboard2-pulse me-2"></i>
            Patient Treatment
        </h4>



        <!-- =================================================
             ERROR
        ================================================== -->

        <?php if (!empty($errorMessage)): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <strong>
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    Error:
                </strong>

                <?= htmlspecialchars($errorMessage) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>



        <!-- =================================================
             SUCCESS
        ================================================== -->

        <?php if (isset($_SESSION['success_message'])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <strong>
                    <i class="bi bi-check-circle me-1"></i>
                    Success!
                </strong>

                <?= htmlspecialchars($_SESSION['success_message']) ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

            <?php unset($_SESSION['success_message']); ?>

        <?php endif; ?>



        <!-- =================================================
             MY ADMITTED PATIENTS
        ================================================== -->

        <div class="card-box admission-card">

            <div class="d-flex justify-content-between align-items-center mb-3">

                <div>

                    <h5 class="mb-1">

                        <i class="bi bi-hospital me-2"></i>

                        My Admitted Patients

                    </h5>

                    <small class="text-muted">

                        Patients currently admitted under your care

                    </small>

                </div>

                <span class="badge bg-success">

                    <?= count($myAdmissions) ?> Active

                </span>

            </div>


            <?php if (!empty($myAdmissions)): ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                        <tr>

                            <th>Patient</th>

                            <th>Ward</th>

                            <th>Bed</th>

                            <th>Admission Date</th>

                            <th>Expected Discharge</th>

                            <th>Stay</th>

                            <th class="text-center">Action</th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php foreach ($myAdmissions as $admission): ?>

                            <?php

                            $admissionDate =
                                !empty($admission['ADMISSION_DATE'])
                                ? strtotime($admission['ADMISSION_DATE'])
                                : false;

                            $expectedDate =
                                !empty($admission['EXPECTED_DISCHARGE_DATE'])
                                ? strtotime($admission['EXPECTED_DISCHARGE_DATE'])
                                : false;


                            $stayDays = '-';

                            if ($admissionDate && $expectedDate) {

                                $diff =
                                    $expectedDate - $admissionDate;

                                $stayDays =
                                    max(1, ceil($diff / 86400));
                            }

                            ?>

                            <tr>

                                <td>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $admission['PATIENT_NAME'] ?? ''
                                        ) ?>

                                    </strong>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $admission['WARD_NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <span class="badge bg-secondary">

                                        <?= htmlspecialchars(
                                            $admission['BED_NUMBER'] ?? ''
                                        ) ?>

                                    </span>

                                </td>


                                <td>

                                    <?= $admissionDate
                                        ? strtoupper(
                                            date('d-M-y', $admissionDate)
                                        )
                                        : '-'
                                    ?>

                                </td>


                                <td>

                                    <?= $expectedDate
                                        ? strtoupper(
                                            date('d-M-y', $expectedDate)
                                        )
                                        : '-'
                                    ?>

                                </td>


                                <td>

                                    <span class="stay-badge">

                                        <?= $stayDays === '-'
                                            ? '-'
                                            : $stayDays . ' day(s)'
                                        ?>

                                    </span>

                                </td>


                                <td class="text-center">

                                    <a
                                        href="discharge_patient.php?admission_id=<?= urlencode($admission['ADMISSION_ID']) ?>"
                                        class="btn btn-danger btn-sm"
                                    >

                                        <i class="bi bi-box-arrow-right me-1"></i>

                                        Discharge

                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <i class="bi bi-hospital fs-1 d-block mb-2"></i>

                    You currently have no admitted patients.

                </div>

            <?php endif; ?>

        </div>



        <!-- =================================================
             APPOINTMENT PATIENT
        ================================================== -->

        <?php if ($appointmentPatient && $type === 'appointment'): ?>

            <div class="card-box patient-info">

                <h5>

                    <i class="bi bi-calendar-check me-2"></i>

                    Appointment Patient

                </h5>


                <strong>

                    <?= htmlspecialchars(
                        $appointmentPatient['PATIENT_NAME'] ?? ''
                    ) ?>

                </strong>


                <br>


                Doctor:

                <?= htmlspecialchars(
                    $appointmentPatient['DOCTOR_NAME'] ?? ''
                ) ?>


                <br>


                Date:

                <?= !empty($appointmentPatient['APPOINTMENT_DATE'])

                    ? strtoupper(
                        date(
                            'd-M-y',
                            strtotime(
                                $appointmentPatient['APPOINTMENT_DATE']
                            )
                        )
                    )

                    : '-'
                ?>


                <br>


                Time:

                <?= htmlspecialchars(
                    $appointmentPatient['APPOINTMENT_TIME'] ?? ''
                ) ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             WALK-IN PATIENT
        ================================================== -->

        <?php if ($walkinPatient && $type === 'walkin'): ?>

            <div class="card-box patient-info">

                <h5>

                    <i class="bi bi-person-walking me-2"></i>

                    Walk-In Patient

                </h5>


                <strong>

                    <?= htmlspecialchars(
                        $walkinPatient['NAME'] ?? ''
                    ) ?>

                </strong>


                <br>


                Status:

                <?= htmlspecialchars(
                    $walkinPatient['STATUS'] ?? ''
                ) ?>

            </div>

        <?php endif; ?>



        <!-- =================================================
             TODAY'S QUEUE
        ================================================== -->

        <?php if (!$appointmentPatient && !$walkinPatient): ?>

            <div class="card-box">

                <h5 class="mb-3">

                    <i class="bi bi-list-check me-2"></i>

                    Today's Patient Queue

                </h5>


                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead>

                        <tr>

                            <th class="text-center">
                                Time
                            </th>

                            <th>
                                Patient
                            </th>

                            <th class="text-center">
                                Status
                            </th>

                            <th class="text-center">
                                Action
                            </th>

                        </tr>

                        </thead>


                        <tbody>

                        <?php if (!empty($todayAppointments)): ?>

                            <?php foreach ($todayAppointments as $row): ?>

                                <tr>

                                    <td class="text-center">

                                        <?= htmlspecialchars(
                                            $row['APPOINTMENT_TIME'] ?? ''
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $row['PATIENT_NAME'] ?? ''
                                        ) ?>

                                    </td>


                                    <td class="text-center">

                                        <span class="badge bg-info">

                                            <?= htmlspecialchars(
                                                $row['STATUS'] ?? ''
                                            ) ?>

                                        </span>

                                    </td>


                                    <td class="text-center">

                                        <?php if ($row['TYPE'] === 'APPOINTMENT'): ?>

                                            <a
                                                href="treatment.php?type=appointment&id=<?= urlencode($row['RECORD_ID']) ?>"
                                                class="btn btn-primary btn-sm"
                                            >

                                                <i class="bi bi-clipboard-pulse me-1"></i>

                                                Diagnose

                                            </a>

                                        <?php else: ?>

                                            <a
                                                href="treatment.php?type=walkin&id=<?= urlencode($row['RECORD_ID']) ?>"
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
                                    colspan="4"
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

        <?php endif; ?>



        <!-- =================================================
             TREATMENT FORM
        ================================================== -->

        <?php if ($appointmentPatient || $walkinPatient): ?>

            <form method="POST">


                <!-- =========================================
                     PATIENT INFO
                ========================================== -->

                <?php if ($patientInfo): ?>

                    <div class="card-box patient-info">

                        <strong>

                            <i class="bi bi-person-circle me-1"></i>

                            <?= htmlspecialchars(
                                $patientInfo['NAME'] ?? ''
                            ) ?>

                        </strong>

                        <br>

                        <?= htmlspecialchars(
                            $patientInfo['WARD_NAME'] ?? ''
                        ) ?>

                        |

                        Bed:

                        <?= htmlspecialchars(
                            $patientInfo['BED_NUMBER'] ?? '-'
                        ) ?>

                        <br>

                        Date:

                        <?= htmlspecialchars(
                            $patientInfo['ADMISSION_DATE'] ?? '-'
                        ) ?>

                    </div>

                <?php endif; ?>



                <!-- =========================================
                     DIAGNOSIS
                ========================================== -->

                <div class="card-box">

                    <div class="section-title">

                        <i class="bi bi-clipboard2-pulse me-2"></i>

                        Diagnosis

                    </div>


                    <textarea
                        name="details"
                        class="form-control mb-3"
                        rows="4"
                        placeholder="Enter diagnosis..."
                    ></textarea>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <input
                                name="allergies"
                                class="form-control"
                                placeholder="Allergies"
                            >

                        </div>


                        <div class="col-md-6">

                            <select
                                name="record_id"
                                class="form-control"
                            >

                                <option value="">
                                    Select Patient Record
                                </option>


                                <?php foreach ($patients as $p): ?>

                                    <option
                                        value="<?= htmlspecialchars($p['RECORD_ID']) ?>"
                                        <?= (
                                            $selected_id ==
                                            $p['RECORD_ID']
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >

                                        <?= htmlspecialchars(
                                            $p['NAME']
                                        ) ?>


                                        <?php if (!empty($p['APPOINTMENT_TIME'])): ?>

                                            (
                                            <?= htmlspecialchars(
                                                $p['APPOINTMENT_TIME']
                                            ) ?>
                                            )

                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                    </div>

                </div>



                <!-- =========================================
                     ADMISSION DECISION
                ========================================== -->

                <div class="card-box">

                    <div class="section-title">

                        <i class="bi bi-hospital me-2"></i>

                        Treatment Decision

                    </div>


                    <select
                        name="decision_type"
                        id="decision_type"
                        class="form-control"
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



                    <!-- =====================================
                         ADMISSION DETAILS
                    ====================================== -->

                    <div
                        id="bedBox"
                        style="display:none;"
                    >

                        <label class="mb-2 mt-3 fw-semibold">

                            <i class="bi bi-bed me-1"></i>

                            Select Bed

                        </label>


                        <select
                            name="bed_id"
                            id="bed_id"
                            class="form-control"
                        >

                            <option value="">

                                Select Available Bed

                            </option>


                            <?php foreach ($availableBeds as $bed): ?>

                                <option
                                    value="<?= htmlspecialchars($bed['BED_ID']) ?>"
                                >

                                    Bed
                                    <?= htmlspecialchars(
                                        $bed['BED_NUMBER']
                                    ) ?>

                                    -

                                    <?= htmlspecialchars(
                                        $bed['WARD_NAME']
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>


                        <?php if (empty($availableBeds)): ?>

                            <div class="alert alert-warning mt-3 mb-0">

                                <i class="bi bi-exclamation-triangle me-1"></i>

                                No available beds found for

                                <strong>

                                    <?= htmlspecialchars(
                                        $patientDept ?: 'this department'
                                    ) ?>

                                </strong>.

                            </div>

                        <?php else: ?>

                            <small class="text-muted d-block mt-2">

                                Only available beds from the patient's
                                department are shown.

                            </small>

                        <?php endif; ?>



                        <!-- =================================
                             EXPECTED DISCHARGE DATE
                        ================================== -->

                        <div id="expectedDischargeBox">

                            <label
                                for="expected_discharge_date"
                                class="mb-2 fw-semibold"
                            >

                                <i class="bi bi-calendar-event me-1"></i>

                                Expected Discharge Date

                            </label>


                            <input
                                type="date"
                                name="expected_discharge_date"
                                id="expected_discharge_date"
                                class="form-control"
                                min="<?= date('Y-m-d') ?>"
                            >


                            <small class="text-muted d-block mt-2">

                                This date will be stored in
                                <strong>
                                    EXPECTED_DISCHARGE_DATE
                                </strong>
                                and can be used by Pharmacy to determine
                                the patient's expected medication duration.

                            </small>

                        </div>

                    </div>

                </div>



                <!-- =========================================
                     NEXT APPOINTMENT
                ========================================== -->

                <div
                    id="nextAppointmentBox"
                    class="card-box"
                    style="display:none;"
                >

                    <div class="section-title">

                        <i class="bi bi-calendar-plus me-2"></i>

                        Next Appointment Details

                    </div>


                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">

                                Next Appointment Date

                            </label>


                            <input
                                type="date"
                                id="next_date"
                                name="next_date"
                                class="form-control"
                                min="<?= date(
                                    'Y-m-d',
                                    strtotime('+1 day')
                                ) ?>"
                            >

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">

                                Next Appointment Time

                            </label>


                            <select
                                name="next_time"
                                class="form-control"
                            >

                                <option value="">
                                    Select Time
                                </option>

                                <option value="08:00">
                                    08:00
                                </option>

                                <option value="09:00">
                                    09:00
                                </option>

                                <option value="10:00">
                                    10:00
                                </option>

                                <option value="11:00">
                                    11:00
                                </option>

                                <option value="12:00">
                                    12:00
                                </option>

                                <option value="14:00">
                                    14:00
                                </option>

                                <option value="15:00">
                                    15:00
                                </option>

                                <option value="16:00">
                                    16:00
                                </option>

                            </select>

                        </div>

                    </div>

                </div>



                <!-- =========================================
                     MEDICATION
                ========================================== -->

                <div class="card-box">

                    <div class="section-title">

                        <i class="bi bi-capsule me-2"></i>

                        Medication Prescription

                    </div>


                    <div id="medicationContainer">


                        <div class="row medicationRow mb-3 g-2">


                            <div class="col-md-4">

                                <select
                                    name="medication_id[]"
                                    class="form-control"
                                >

                                    <option value="">

                                        Select Medication

                                    </option>


                                    <?php foreach ($medications as $m): ?>

                                        <option
                                            value="<?= htmlspecialchars($m['MEDICATION_ID']) ?>"
                                        >

                                            <?= htmlspecialchars(
                                                $m['MEDICATION_NAME']
                                            ) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="col-md-4">

                                <input
                                    type="text"
                                    name="dosage[]"
                                    class="form-control"
                                    placeholder="Example: 500mg"
                                >

                            </div>


                            <div class="col-md-4">

                                <input
                                    type="text"
                                    name="frequency[]"
                                    class="form-control"
                                    placeholder="Example: 1 tablet TDS after meal"
                                >

                            </div>

                        </div>

                    </div>


                    <button
                        type="button"
                        id="addMedication"
                        class="btn btn-success btn-sm"
                    >

                        <i class="bi bi-plus-circle me-1"></i>

                        Add Medication

                    </button>

                </div>



                <!-- =========================================
                     SAVE
                ========================================== -->

                <button
                    type="submit"
                    name="save_all"
                    class="btn btn-primary w-100 py-3 mb-4"
                >

                    <i class="bi bi-save me-1"></i>

                    Save Treatment

                </button>


            </form>

        <?php endif; ?>


    </div>

</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<script>

document.addEventListener("DOMContentLoaded", function () {


    /* =====================================================
       DECISION DISPLAY
    ===================================================== */

    const decision =
        document.getElementById("decision_type");

    const nextAppointmentBox =
        document.getElementById("nextAppointmentBox");

    const bedBox =
        document.getElementById("bedBox");


    if (decision) {

        decision.addEventListener("change", function () {

            nextAppointmentBox.style.display = "none";

            bedBox.style.display = "none";


            if (this.value === "Next Appointment") {

                nextAppointmentBox.style.display = "block";

            }


            if (this.value === "Admit Patient") {

                bedBox.style.display = "block";

            }

        });

    }



    /* =====================================================
       NEXT APPOINTMENT DATE
    ===================================================== */

    const nextDate =
        document.getElementById("next_date");


    if (nextDate) {

        const today = new Date();

        today.setDate(
            today.getDate() + 1
        );


        const yyyy =
            today.getFullYear();


        const mm =
            String(
                today.getMonth() + 1
            ).padStart(2, "0");


        const dd =
            String(
                today.getDate()
            ).padStart(2, "0");


        nextDate.min =
            `${yyyy}-${mm}-${dd}`;
    }



    /* =====================================================
       EXPECTED DISCHARGE DATE
    ===================================================== */

    const expectedDate =
        document.getElementById(
            "expected_discharge_date"
        );


    if (expectedDate) {

        const today =
            new Date();


        const yyyy =
            today.getFullYear();


        const mm =
            String(
                today.getMonth() + 1
            ).padStart(2, "0");


        const dd =
            String(
                today.getDate()
            ).padStart(2, "0");


        expectedDate.min =
            `${yyyy}-${mm}-${dd}`;
    }

});



/* =========================================================
   ADD MEDICATION
========================================================= */

const addMedication =
    document.getElementById(
        "addMedication"
    );


if (addMedication) {

    addMedication.addEventListener(
        "click",
        function () {


            const container =
                document.getElementById(
                    "medicationContainer"
                );


            const row =
                document.createElement("div");


            row.className =
                "row medicationRow mb-3 g-2";


            row.innerHTML = `

                <div class="col-md-4">

                    <select
                        name="medication_id[]"
                        class="form-control"
                    >

                        <option value="">
                            Select Medication
                        </option>

                        <?php foreach ($medications as $m): ?>

                            <option
                                value="<?= htmlspecialchars($m['MEDICATION_ID']) ?>"
                            >

                                <?= htmlspecialchars(
                                    $m['MEDICATION_NAME']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="col-md-3">

                    <input
                        type="text"
                        name="dosage[]"
                        class="form-control"
                        placeholder="Example: 500mg"
                    >

                </div>


                <div class="col-md-3">

                    <input
                        type="text"
                        name="frequency[]"
                        class="form-control"
                        placeholder="Example: 1 tablet TDS"
                    >

                </div>


                <div class="col-md-2">

                    <button
                        type="button"
                        class="btn btn-danger removeMedication w-100"
                    >

                        <i class="bi bi-trash"></i>

                    </button>

                </div>

            `;


            container.appendChild(row);

        }
    );

}



/* =========================================================
   REMOVE MEDICATION
========================================================= */

document.addEventListener(
    "click",
    function (e) {

        const button =
            e.target.closest(
                ".removeMedication"
            );


        if (button) {

            const row =
                button.closest(
                    ".medicationRow"
                );


            if (row) {

                row.remove();

            }

        }

    }
);

</script>


</body>

</html>