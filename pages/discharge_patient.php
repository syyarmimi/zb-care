<?php

session_start();

include("../config/config.php");

/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['admin', 'doctor'])
) {
    header("Location: ../auth/login.php");
    exit();
}

$role = $_SESSION['role'];

$user_id = $_SESSION['user_id'] ?? 0;


/* =========================================================
   GET ADMISSION ID
========================================================= */

$admission_id = $_GET['admission_id'] ?? '';

if ($admission_id === '' || !is_numeric($admission_id)) {
    die("Invalid admission ID.");
}

$admission_id = (int) $admission_id;


/* =========================================================
   GET PATIENT INFORMATION
========================================================= */

$sql = "

    SELECT
        A.ADMISSION_ID,
        A.PATIENT_ID,
        A.BED_ID,
        A.ACCOUNT_ID,

        P.NAME,

        B.BED_NUMBER,

        W.WARD_NAME,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        ) AS ADMISSION_DATE

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE A.ADMISSION_ID = :admission_id

      AND A.DISCHARGE_DATE IS NULL
";


/* =========================================================
   DOCTOR PERMISSION
========================================================= */

if ($role === 'doctor') {

    $sql .= "
        AND A.ACCOUNT_ID = :doctor_id
    ";
}


/* =========================================================
   PREPARE
========================================================= */

$stmt = $conn->prepare($sql);


/* =========================================================
   PARAMETERS
========================================================= */

$params = [
    ':admission_id' => $admission_id
];

if ($role === 'doctor') {

    $params[':doctor_id'] = $user_id;

}


/* =========================================================
   EXECUTE
========================================================= */

$stmt->execute($params);


/* =========================================================
   FETCH PATIENT
========================================================= */

$patient = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   CHECK PATIENT
========================================================= */

if (!$patient) {

    die(
        "Admission record not found, already discharged, " .
        "or you do not have permission to discharge this patient."
    );

}


/* =========================================================
   ERROR MESSAGE
========================================================= */

$errorMessage = '';


/* =========================================================
   PROCESS DISCHARGE
========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {

        /* =====================================================
           START TRANSACTION
        ===================================================== */

        $conn->beginTransaction();


        /* =====================================================
           1. UPDATE ADMISSION
        ===================================================== */

        $updateAdmission = $conn->prepare("

            UPDATE SYARMIMI.ADMISSION

            SET DISCHARGE_DATE = SYSDATE

            WHERE ADMISSION_ID = :admission_id

              AND DISCHARGE_DATE IS NULL

        ");

        $updateAdmission->execute([
            ':admission_id' => $admission_id
        ]);


        /* =====================================================
           CHECK UPDATE
        ===================================================== */

        if ($updateAdmission->rowCount() === 0) {

            throw new Exception(
                "Patient has already been discharged."
            );

        }


        /* =====================================================
           2. MAKE BED AVAILABLE
        ===================================================== */

        $updateBed = $conn->prepare("

            UPDATE SYARMIMI.BED

            SET STATUS = 'Available'

            WHERE BED_ID = :bed_id

        ");

        $updateBed->execute([
            ':bed_id' => $patient['BED_ID']
        ]);


        /* =====================================================
           CHECK BED UPDATE
        ===================================================== */

        if ($updateBed->rowCount() === 0) {

            throw new Exception(
                "Unable to update bed status."
            );

        }


        /* =====================================================
           3. COMMIT
        ===================================================== */

        $conn->commit();


        /* =====================================================
           4. REDIRECT
        ===================================================== */

        header(
            "Location: patient_management.php?discharged=1"
        );

        exit();


    } catch (Exception $e) {

        /* =====================================================
           ROLLBACK
        ===================================================== */

        if ($conn->inTransaction()) {

            $conn->rollBack();

        }

        $errorMessage = $e->getMessage();

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

    <title>Discharge Patient</title>


    <!-- =====================================================
         BOOTSTRAP
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         BOOTSTRAP ICONS
    ====================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- =====================================================
         STYLE
    ====================================================== -->

    <style>

        * {
            box-sizing: border-box;
        }


        body {

            background: #eef2f7;

            font-family: 'Segoe UI', sans-serif;

            margin: 0;

            min-height: 100vh;

        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .content {

            flex: 1;

            min-height: 100vh;

            padding: 35px 30px 50px;

            display: flex;

            flex-direction: column;

            align-items: center;

        }


        /* =====================================================
           PAGE WRAPPER
        ===================================================== */

        .page-wrapper {

            width: 100%;

            max-width: 950px;

        }


        /* =====================================================
           PAGE TITLE
        ===================================================== */

        .page-title {

            font-size: 28px;

            font-weight: 700;

            color: #1f2937;

            margin-bottom: 25px;

        }


        .page-title i {

            color: #1d4ed8;

        }


        /* =====================================================
           MAIN CARD
        ===================================================== */

        .card-box {

            width: 100%;

            background: #ffffff;

            border-radius: 16px;

            padding: 32px;

            box-shadow:
                0 8px 25px rgba(0, 0, 0, 0.07);

        }


        /* =====================================================
           PATIENT SECTION
        ===================================================== */

        .patient-section {

            border-bottom: 1px solid #e5e7eb;

            padding-bottom: 24px;

            margin-bottom: 24px;

        }


        .info-label {

            font-size: 13px;

            color: #64748b;

            margin-bottom: 6px;

            font-weight: 500;

        }


        .patient-name {

            font-size: 26px;

            font-weight: 700;

            color: #111827;

        }


        /* =====================================================
           INFORMATION BOX
        ===================================================== */

        .info-box {

            background: #f8fafc;

            border-radius: 11px;

            padding: 16px;

            height: 100%;

            border: 1px solid #f1f5f9;

        }


        .info-value {

            font-size: 17px;

            font-weight: 600;

            color: #1f2937;

            word-break: break-word;

        }


        /* =====================================================
           WARNING BOX
        ===================================================== */

        .warning-box {

            background: #fff7ed;

            border: 1px solid #fed7aa;

            border-radius: 12px;

            padding: 20px;

            color: #9a3412;

            margin-top: 25px;

            margin-bottom: 25px;

        }


        .warning-title {

            font-size: 17px;

            font-weight: 700;

            margin-bottom: 10px;

        }


        .warning-title i {

            font-size: 18px;

        }


        .warning-box ul {

            margin-top: 12px;

            margin-bottom: 0;

            padding-left: 22px;

        }


        .warning-box li {

            margin-bottom: 7px;

        }


        .warning-box li:last-child {

            margin-bottom: 0;

        }


        /* =====================================================
           BUTTON AREA
        ===================================================== */

        .button-area {

            display: flex;

            justify-content: flex-end;

            align-items: center;

            gap: 10px;

            margin-top: 25px;

        }


        .btn-cancel {

            min-width: 110px;

            padding: 10px 18px;

        }


        .btn-discharge {

            min-width: 165px;

            padding: 10px 18px;

        }


        /* =====================================================
           ERROR ALERT
        ===================================================== */

        .error-alert {

            border-radius: 10px;

            margin-bottom: 20px;

        }


        /* =====================================================
           CONFIRMATION MODAL
        ===================================================== */

        .modal-content {

            border: none;

            border-radius: 16px;

            box-shadow:
                0 15px 50px rgba(0, 0, 0, 0.20);

            overflow: hidden;

        }


        .modal-body {

            padding: 35px 30px !important;

        }


        /* =====================================================
           MODAL ICON
        ===================================================== */

        .modal-icon {

            width: 76px;

            height: 76px;

            border-radius: 50%;

            background: #fff7e6;

            border: 3px solid #ffe1a3;

            color: #f0ad00;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 36px;

            margin: 0 auto 20px;

        }


        /* =====================================================
           MODAL TITLE
        ===================================================== */

        .modal-title {

            font-size: 24px;

            font-weight: 700;

            color: #1f2937;

        }


        /* =====================================================
           MODAL TEXT
        ===================================================== */

        .modal-text {

            color: #64748b;

            font-size: 15px;

            line-height: 1.6;

        }


        /* =====================================================
           MODAL BUTTON
        ===================================================== */

        .modal .btn {

            min-width: 110px;

            padding: 9px 18px;

            border-radius: 7px;

        }


        /* =====================================================
           RESPONSIVE
        ===================================================== */

        @media (max-width: 992px) {

            .content {

                padding: 25px 20px 40px;

            }

            .page-wrapper {

                max-width: 100%;

            }

        }


        @media (max-width: 768px) {

            .content {

                padding: 20px 15px 35px;

            }


            .page-title {

                font-size: 24px;

            }


            .card-box {

                padding: 22px;

                border-radius: 14px;

            }


            .patient-name {

                font-size: 23px;

            }


            .button-area {

                justify-content: stretch;

                flex-direction: column-reverse;

            }


            .button-area .btn {

                width: 100%;

            }

        }


        @media (max-width: 576px) {

            .content {

                padding: 15px 10px 30px;

            }


            .card-box {

                padding: 18px;

            }


            .page-title {

                font-size: 22px;

                margin-bottom: 18px;

            }


            .warning-box {

                padding: 16px;

            }


            .modal-body {

                padding: 28px 20px !important;

            }

        }

    </style>

</head>


<body>


<div class="d-flex">


    <!-- =====================================================
         SIDEBAR
    ====================================================== -->

    <?php

    if ($role === 'admin') {

        include("../includes/sidebar_admin.php");

    } else {

        include("../includes/sidebar_doctor.php");

    }

    ?>


    <!-- =====================================================
         CONTENT
    ====================================================== -->

    <div class="content">


        <div class="page-wrapper">


            <!-- =================================================
                 PAGE TITLE
            ================================================== -->

            <div class="page-title">

                <i class="bi bi-box-arrow-right me-2"></i>

                Discharge Patient

            </div>


            <!-- =================================================
                 MAIN CARD
            ================================================== -->

            <div class="card-box">


                <!-- =============================================
                     ERROR MESSAGE
                ============================================== -->

                <?php if (!empty($errorMessage)): ?>

                    <div
                        class="alert alert-danger error-alert"
                        role="alert"
                    >

                        <i class="bi bi-exclamation-circle me-2"></i>

                        <strong>Discharge Failed:</strong>

                        <?= htmlspecialchars($errorMessage) ?>

                    </div>

                <?php endif; ?>


                <!-- =============================================
                     PATIENT INFORMATION
                ============================================== -->

                <div class="patient-section">

                    <div class="info-label">

                        Patient

                    </div>


                    <div class="patient-name">

                        <?= htmlspecialchars(
                            $patient['NAME'] ?? ''
                        ) ?>

                    </div>

                </div>


                <!-- =============================================
                     PATIENT DETAILS
                ============================================== -->

                <div class="row g-3">


                    <!-- ADMISSION ID -->

                    <div class="col-md-3">

                        <div class="info-box">

                            <div class="info-label">

                                Admission ID

                            </div>

                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $patient['ADMISSION_ID'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- WARD -->

                    <div class="col-md-3">

                        <div class="info-box">

                            <div class="info-label">

                                Ward

                            </div>

                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $patient['WARD_NAME'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- BED -->

                    <div class="col-md-3">

                        <div class="info-box">

                            <div class="info-label">

                                Bed

                            </div>

                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $patient['BED_NUMBER'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>


                    <!-- ADMISSION DATE -->

                    <div class="col-md-3">

                        <div class="info-box">

                            <div class="info-label">

                                Admission Date

                            </div>

                            <div class="info-value">

                                <?= htmlspecialchars(
                                    $patient['ADMISSION_DATE'] ?? ''
                                ) ?>

                            </div>

                        </div>

                    </div>


                </div>


                <!-- =============================================
                     WARNING
                ============================================== -->

                <div class="warning-box">


                    <div class="warning-title">

                        <i class="bi bi-exclamation-triangle me-2"></i>

                        Confirm Discharge

                    </div>


                    <div>

                        You are about to discharge

                        <strong>
                            <?= htmlspecialchars(
                                $patient['NAME'] ?? ''
                            ) ?>
                        </strong>.

                    </div>


                    <ul>

                        <li>

                            The patient's admission will be marked as
                            discharged.

                        </li>


                        <li>

                            The discharge date will be recorded
                            automatically.

                        </li>


                        <li>

                            Bed

                            <strong>
                                <?= htmlspecialchars(
                                    $patient['BED_NUMBER'] ?? ''
                                ) ?>
                            </strong>

                            will become available.

                        </li>


                        <li>

                            The patient will appear under the
                            <strong>Discharged Patients</strong>
                            tab.

                        </li>

                    </ul>


                </div>


                <!-- =============================================
                     BUTTONS
                ============================================== -->

                <div class="button-area">


                    <!-- CANCEL -->

                    <a
                        href="patient_management.php"
                        class="btn btn-secondary btn-cancel"
                    >

                        <i class="bi bi-arrow-left me-1"></i>

                        Cancel

                    </a>


                    <!-- DISCHARGE -->

                    <button
                        type="button"
                        class="btn btn-danger btn-discharge"
                        data-bs-toggle="modal"
                        data-bs-target="#confirmDischargeModal"
                    >

                        <i class="bi bi-box-arrow-right me-1"></i>

                        Confirm Discharge

                    </button>


                </div>


            </div>


        </div>


    </div>


</div>


<!-- =========================================================
     CONFIRM DISCHARGE MODAL
========================================================= -->

<div
    class="modal fade"
    id="confirmDischargeModal"
    tabindex="-1"
    aria-labelledby="confirmDischargeModalLabel"
    aria-hidden="true"
>


    <div class="modal-dialog modal-dialog-centered">


        <div class="modal-content">


            <div class="modal-body text-center">


                <!-- =============================================
                     ICON
                ============================================== -->

                <div class="modal-icon">

                    <i class="bi bi-exclamation-triangle"></i>

                </div>


                <!-- =============================================
                     TITLE
                ============================================== -->

                <div
                    class="modal-title mb-2"
                    id="confirmDischargeModalLabel"
                >

                    Discharge Patient?

                </div>


                <!-- =============================================
                     MESSAGE
                ============================================== -->

                <div class="modal-text mb-4">

                    Are you sure you want to discharge

                    <strong>
                        <?= htmlspecialchars(
                            $patient['NAME'] ?? ''
                        ) ?>
                    </strong>?

                    <br><br>

                    This action will mark the patient as discharged
                    and make the assigned bed available.

                </div>


                <!-- =============================================
                     FORM
                ============================================== -->

                <form method="POST">


                    <div class="d-flex justify-content-center gap-2">


                        <!-- CANCEL -->

                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >

                            <i class="bi bi-x-lg me-1"></i>

                            Cancel

                        </button>


                        <!-- CONFIRM -->

                        <button
                            type="submit"
                            class="btn btn-danger"
                        >

                            <i class="bi bi-check-lg me-1"></i>

                            Yes, Discharge

                        </button>


                    </div>


                </form>


            </div>


        </div>


    </div>


</div>


<!-- =========================================================
     BOOTSTRAP JS
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>