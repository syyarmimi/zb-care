<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    header("Location: ../auth/login.php");
    exit();
}

$doctor_id = $_SESSION['user_id'] ?? 0;

if (!$doctor_id) {
    die("Invalid doctor session.");
}


/* =========================================================
   FETCH DOCTOR'S ACTIVE PATIENTS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        A.ADMISSION_ID,
        A.PATIENT_ID,

        P.NAME AS PATIENT_NAME,

        W.WARD_NAME,

        B.BED_NUMBER,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        ) AS ADMISSION_DATE,

        TO_CHAR(
            A.EXPECTED_DISCHARGE_DATE,
            'DD-MON-YYYY'
        ) AS EXPECTED_DISCHARGE_DATE,

        TRUNC(
            A.EXPECTED_DISCHARGE_DATE
            - A.ADMISSION_DATE
        ) AS DURATION_DAYS

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE A.ACCOUNT_ID = :doctor_id

    AND A.DISCHARGE_DATE IS NULL

    ORDER BY
        A.ADMISSION_DATE DESC
");

$stmt->execute([
    ':doctor_id' => $doctor_id
]);

$patients =
    $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>My Admitted Patients</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>

        body {
            background: #eef2f7;
            font-family: 'Segoe UI', sans-serif;
        }

        .content {
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }

        .card-box {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }

        .page-title {
            font-size: 26px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 25px;
        }

        .patient-name {
            font-weight: 700;
        }

        .badge-stay {
            background: #dbeafe;
            color: #1d4ed8;
            padding: 7px 10px;
            border-radius: 8px;
        }

    </style>

</head>

<body>

<div class="d-flex">

    <?php include("../includes/sidebar_doctor.php"); ?>

    <div class="content">

        <div class="page-title">

            <i class="bi bi-hospital me-2"></i>

            My Admitted Patients

        </div>


        <div class="card-box">

            <div class="table-responsive">

                <table class="table table-hover align-middle">

                    <thead>

                    <tr>

                        <th>Patient</th>

                        <th>Ward</th>

                        <th>Bed</th>

                        <th>Admission Date</th>

                        <th>Expected Discharge</th>

                        <th>Duration</th>

                        <th class="text-center">
                            Action
                        </th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php if (
                        empty($patients)
                    ): ?>

                        <tr>

                            <td
                                colspan="7"
                                class="text-center text-muted py-4">

                                <i class="bi bi-info-circle me-1"></i>

                                You currently have no admitted patients.

                            </td>

                        </tr>

                    <?php endif; ?>


                    <?php foreach (
                        $patients
                        as $patient
                    ): ?>

                        <tr>

                            <td>

                                <div class="patient-name">

                                    <?= htmlspecialchars(
                                        $patient[
                                            'PATIENT_NAME'
                                        ]
                                    ) ?>

                                </div>

                                <small class="text-muted">

                                    Admission ID:
                                    <?= htmlspecialchars(
                                        $patient[
                                            'ADMISSION_ID'
                                        ]
                                    ) ?>

                                </small>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $patient[
                                        'WARD_NAME'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $patient[
                                        'BED_NUMBER'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $patient[
                                        'ADMISSION_DATE'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $patient[
                                        'EXPECTED_DISCHARGE_DATE'
                                    ]
                                ) ?>

                            </td>


                            <td>

                                <span
                                    class="badge-stay">

                                    <?= htmlspecialchars(
                                        $patient[
                                            'DURATION_DAYS'
                                        ]
                                    ) ?>

                                    days

                                </span>

                            </td>


                            <td class="text-center">

                                <a
                                    href="discharge_patient.php?admission_id=<?= urlencode(
                                        $patient[
                                            'ADMISSION_ID'
                                        ]
                                    ) ?>"
                                    class="btn btn-danger btn-sm">

                                    <i class="bi bi-box-arrow-right me-1"></i>

                                    Discharge

                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>