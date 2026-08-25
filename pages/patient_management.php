<?php

session_start();

if (
    !isset($_SESSION['role']) ||
    !in_array($_SESSION['role'], ['doctor', 'admin'])
) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

/* =========================================================
   ROLE
========================================================= */

$role = $_SESSION['role'];

$doctor_id = $_SESSION['user_id'] ?? 0;


/* =========================================================
   HELPER
   Convert appointment date into YYYY-MM-DD
========================================================= */

function convertAppointmentDate($date)
{
    $date = trim($date);

    if (empty($date)) {
        return '';
    }

    /* DD-MON-RR */
    if (preg_match('/^\d{2}-[A-Za-z]{3}-\d{2}$/', $date)) {

        $dateObj = DateTime::createFromFormat(
            'd-M-y',
            strtoupper($date)
        );

        if ($dateObj) {
            return $dateObj->format('Y-m-d');
        }
    }

    /* YYYY-MM-DD */
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {

        $dateObj = DateTime::createFromFormat(
            'Y-m-d',
            $date
        );

        if ($dateObj) {
            return $dateObj->format('Y-m-d');
        }
    }

    return '';
}


/* =========================================================
   1. CURRENT / ADMITTED PATIENTS
========================================================= */

$sql = "

    SELECT
        A.ADMISSION_ID,
        P.PATIENT_ID,
        P.NAME,
        W.WARD_NAME,
        B.BED_NUMBER,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        ) AS ADMISSION_DATE,

        TO_CHAR(
            A.ADMISSION_DATE,
            'YYYY-MM-DD'
        ) AS ADMISSION_SORT

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE A.DISCHARGE_DATE IS NULL
";


/*
    Doctor only sees patients assigned to that doctor.
    Admin sees all admitted patients.
*/

if ($role === 'doctor') {

    $sql .= "
        AND A.ACCOUNT_ID = :doctor_id
    ";
}


$sql .= "

    ORDER BY
        A.ADMISSION_DATE DESC,
        A.ADMISSION_ID DESC

";


$currentPatients = $conn->prepare($sql);


if ($role === 'doctor') {

    $currentPatients->execute([
        ':doctor_id' => $doctor_id
    ]);

} else {

    $currentPatients->execute();
}


$currentList = $currentPatients->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   2. WALK-IN PATIENTS
========================================================= */

$walkinPatients = $conn->query("

    SELECT
        W.CONSULTATION_ID,
        P.PATIENT_ID,
        P.NAME,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'DD-MON-YYYY'
        ) AS CONSULTATION_DATE,

        TO_CHAR(
            W.CONSULTATION_DATE,
            'YYYY-MM-DD'
        ) AS CONSULTATION_SORT

    FROM SYARMIMI.WALKIN_CONSULTATION W

    JOIN SYARMIMI.PATIENT P
        ON W.PATIENT_ID = P.PATIENT_ID

    ORDER BY
        W.CONSULTATION_DATE DESC,
        W.CONSULTATION_ID DESC

")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   3. APPOINTMENT PATIENTS
========================================================= */

$appointmentPatients = $conn->query("

    SELECT
        A.APPOINTMENT_ID,
        P.PATIENT_ID,
        P.NAME,
        A.APPOINTMENT_DATE,
        A.DEPARTMENT,
        A.STATUS

    FROM SYARMIMI.APPOINTMENT A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    ORDER BY

        CASE

            WHEN REGEXP_LIKE(
                A.APPOINTMENT_DATE,
                '^[0-9]{2}-[A-Za-z]{3}-[0-9]{2}$'
            )

            THEN TO_DATE(
                UPPER(A.APPOINTMENT_DATE),
                'DD-MON-RR'
            )

            WHEN REGEXP_LIKE(
                A.APPOINTMENT_DATE,
                '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
            )

            THEN TO_DATE(
                A.APPOINTMENT_DATE,
                'YYYY-MM-DD'
            )

            ELSE NULL

        END DESC,

        A.APPOINTMENT_ID DESC

")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   4. DIAGNOSED PATIENTS
========================================================= */

$sql = "

    SELECT
        D.DIAGNOSIS_ID,
        P.PATIENT_ID,
        P.NAME,
        D.DIAGNOSIS_DETAILS,

        TO_CHAR(
            D.DATE_RECORDED,
            'DD-MON-YYYY'
        ) AS DATE_RECORDED,

        TO_CHAR(
            D.DATE_RECORDED,
            'YYYY-MM-DD'
        ) AS DATE_SORT

    FROM SYARMIMI.DIAGNOSIS D

    JOIN SYARMIMI.PATIENT P
        ON D.PATIENT_ID = P.PATIENT_ID

    WHERE 1 = 1
";


if ($role === 'doctor') {

    $sql .= "
        AND D.ACCOUNT_ID = :doctor_id
    ";
}


$sql .= "

    ORDER BY
        D.DATE_RECORDED DESC,
        D.DIAGNOSIS_ID DESC

";


$diagnosedPatients = $conn->prepare($sql);


if ($role === 'doctor') {

    $diagnosedPatients->execute([
        ':doctor_id' => $doctor_id
    ]);

} else {

    $diagnosedPatients->execute();
}


$diagnosedList = $diagnosedPatients->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   5. DISCHARGED PATIENTS
========================================================= */

$sql = "

    SELECT
        A.ADMISSION_ID,
        P.PATIENT_ID,
        P.NAME,

        TO_CHAR(
            A.ADMISSION_DATE,
            'DD-MON-YYYY'
        ) AS ADMISSION_DATE,

        TO_CHAR(
            A.ADMISSION_DATE,
            'YYYY-MM-DD'
        ) AS ADMISSION_SORT,

        TO_CHAR(
            A.DISCHARGE_DATE,
            'DD-MON-YYYY'
        ) AS DISCHARGE_DATE,

        TO_CHAR(
            A.DISCHARGE_DATE,
            'YYYY-MM-DD'
        ) AS DISCHARGE_SORT

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    WHERE A.DISCHARGE_DATE IS NOT NULL
";


if ($role === 'doctor') {

    $sql .= "
        AND A.ACCOUNT_ID = :doctor_id
    ";
}


$sql .= "

    ORDER BY
        A.DISCHARGE_DATE DESC,
        A.ADMISSION_ID DESC

";


$dischargedPatients = $conn->prepare($sql);


if ($role === 'doctor') {

    $dischargedPatients->execute([
        ':doctor_id' => $doctor_id
    ]);

} else {

    $dischargedPatients->execute();
}


$dischargedList = $dischargedPatients->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   6. STATISTICS
========================================================= */

$totalCurrent    = count($currentList);
$totalDiagnosed  = count($diagnosedList);
$totalDischarged = count($dischargedList);


/* =========================================================
   7. MEDICATION COUNT
========================================================= */

if ($role === 'doctor') {

    $medStmt = $conn->prepare("

        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION_ORDER

        WHERE ACCOUNT_ID = :doctor_id

    ");

    $medStmt->execute([
        ':doctor_id' => $doctor_id
    ]);

} else {

    $medStmt = $conn->prepare("

        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION_ORDER

    ");

    $medStmt->execute();
}


$totalMedication = $medStmt->fetchColumn();

?>

<!DOCTYPE html>

<html>

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Management</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- DataTables -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
    >


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


        /* =====================================================
           PAGE TITLE
        ===================================================== */

        .page-title {

            font-size: 28px;

            font-weight: 700;

            color: #1f2937;

            margin-bottom: 25px;

        }


        /* =====================================================
           CARD
        ===================================================== */

        .card-box {

            background: white;

            border-radius: 15px;

            padding: 20px;

            box-shadow: 0 5px 15px rgba(0,0,0,0.05);

        }


        /* =====================================================
           STATISTICS
        ===================================================== */

        .stats-card {

            text-align: center;

            background: white;

            padding: 20px;

            border-radius: 15px;

            box-shadow: 0 5px 15px rgba(0,0,0,0.05);

        }


        .stats-card h2 {

            font-weight: bold;

            color: #1f2937;

        }


        .stats-card h6 {

            color: #64748b;

            font-weight: 600;

        }


        /* =====================================================
           FORM
        ===================================================== */

        #searchBox {

            height: 45px;

            border-radius: 10px;

        }


        .form-select,
        .form-control {

            height: 45px;

            border-radius: 10px;

        }


        .date-filter-box {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 15px;

            margin-bottom: 15px;

        }


        .date-filter-label {

            font-size: 13px;

            font-weight: 600;

            color: #475569;

            margin-bottom: 6px;

        }


        .date-filter-title {

            font-weight: 700;

            color: #334155;

            margin-bottom: 10px;

        }


        .btn-date {

            height: 45px;

            border-radius: 10px;

        }


        /* =====================================================
           TABLE
        ===================================================== */

        .table th {

            background: #f8fafc;

            color: #334155;

            font-weight: 600;

        }


        .action-buttons {

            white-space: nowrap;

        }


        .btn-view {

            min-width: 75px;

        }


        .btn-discharge {

            min-width: 100px;

        }


        .dataTables_filter {

            display: none;

        }


        /* =====================================================
           CUSTOM DISCHARGE MODAL
        ===================================================== */

        .custom-modal-overlay {

            display: none;

            position: fixed;

            z-index: 99999;

            top: 0;

            left: 0;

            width: 100%;

            height: 100%;

            background: rgba(0, 0, 0, 0.45);

            align-items: center;

            justify-content: center;

            padding: 20px;

        }


        .custom-modal-overlay.show {

            display: flex;

        }


        .custom-modal {

            width: 100%;

            max-width: 450px;

            background: #ffffff;

            border-radius: 8px;

            padding: 32px 30px 28px;

            text-align: center;

            box-shadow: 0 10px 35px rgba(0,0,0,0.20);

            animation: modalFadeIn 0.25s ease;

        }


        @keyframes modalFadeIn {

            from {

                opacity: 0;

                transform: scale(0.92);

            }

            to {

                opacity: 1;

                transform: scale(1);

            }

        }


        .modal-icon {

            width: 76px;

            height: 76px;

            border-radius: 50%;

            margin: 0 auto 20px;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 38px;

        }


        .modal-icon.warning {

            background: #fff7e6;

            color: #f0ad00;

            border: 3px solid #ffe1a3;

        }


        .custom-modal h3 {

            font-size: 27px;

            font-weight: 600;

            color: #3c4652;

            margin-bottom: 12px;

        }


        .custom-modal p {

            font-size: 16px;

            color: #64748b;

            margin-bottom: 25px;

            line-height: 1.5;

        }


        .modal-buttons {

            display: flex;

            justify-content: center;

            gap: 12px;

        }


        .modal-buttons button {

            min-width: 100px;

            padding: 10px 22px;

            border-radius: 5px;

            font-size: 15px;

            border: none;

            cursor: pointer;

            transition: 0.2s;

        }


        .btn-modal-cancel {

            background: #e9ecef;

            color: #495057;

        }


        .btn-modal-cancel:hover {

            background: #d9dde1;

        }


        .btn-modal-confirm {

            background: #198754;

            color: white;

        }


        .btn-modal-confirm:hover {

            background: #157347;

        }


        .btn-modal-confirm i {

            margin-right: 5px;

        }


        /* =====================================================
           NO DATE MESSAGE
        ===================================================== */

        .date-active-message {

            display: none;

            margin-top: 10px;

            color: #475569;

            font-size: 14px;

        }


        .date-active-message.show {

            display: block;

        }


    </style>

</head>


<body>


<div class="d-flex">


    <!-- =====================================================
         SIDEBAR
    ===================================================== -->

    <?php

    if ($role === 'admin') {

        include("../includes/sidebar_admin.php");

    } else {

        include("../includes/sidebar_doctor.php");

    }

    ?>


    <!-- =====================================================
         CONTENT
    ===================================================== -->

    <div class="content">


        <!-- =================================================
             PAGE TITLE
        ================================================= -->

        <div class="page-title">

            <i class="bi bi-people-fill me-2"></i>

            Patient Management

        </div>


        <!-- =================================================
             STATISTICS
        ================================================= -->

        <div class="row g-3 mb-4">


            <div class="col-md-3">

                <div class="stats-card">

                    <h6>
                        Admitted Patients
                    </h6>

                    <h2>
                        <?= $totalCurrent ?>
                    </h2>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stats-card">

                    <h6>
                        Diagnosed
                    </h6>

                    <h2>
                        <?= $totalDiagnosed ?>
                    </h2>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stats-card">

                    <h6>
                        Medication
                    </h6>

                    <h2>
                        <?= $totalMedication ?>
                    </h2>

                </div>

            </div>


            <div class="col-md-3">

                <div class="stats-card">

                    <h6>
                        Discharged
                    </h6>

                    <h2>
                        <?= $totalDischarged ?>
                    </h2>

                </div>

            </div>


        </div>


        <!-- =================================================
             SEARCH + FILTER
        ================================================= -->

        <div class="card-box mb-3">


            <div class="row g-2">


                <!-- SEARCH -->

                <div class="col-md-5">

                    <input
                        type="text"
                        id="searchBox"
                        class="form-control"
                        placeholder="🔍 Search patient or record"
                    >

                </div>


                <!-- TYPE -->

                <div class="col-md-3">

                    <select
                        id="typeFilter"
                        class="form-select"
                    >

                        <option value="">
                            All Types
                        </option>

                        <option value="current">
                            Admitted Patients
                        </option>

                        <option value="walkin">
                            Walk-In
                        </option>

                        <option value="appointment">
                            Appointment
                        </option>

                        <option value="diagnosed">
                            Diagnosed
                        </option>

                        <option value="discharged">
                            Discharged
                        </option>

                    </select>

                </div>


                <!-- SORT -->

                <div class="col-md-4">

                    <select
                        id="sortOrder"
                        class="form-select"
                    >

                        <option value="latest">
                            Newest First
                        </option>

                        <option value="oldest">
                            Oldest First
                        </option>

                        <option value="asc">
                            Name A-Z
                        </option>

                        <option value="desc">
                            Name Z-A
                        </option>

                    </select>

                </div>


            </div>


            <?php if ($role === 'admin'): ?>

                <!-- =================================================
                     ADMIN DATE FILTER
                ================================================= -->

                <div class="date-filter-box mt-3">


                    <div class="date-filter-title">

                        <i class="bi bi-calendar3 me-2"></i>

                        View Patient Records by Date

                    </div>


                    <div class="row g-2 align-items-end">


                        <div class="col-md-5">


                            <div class="date-filter-label">

                                Select Date

                            </div>


                            <input
                                type="date"
                                id="recordDate"
                                class="form-control"
                            >


                        </div>


                        <div class="col-md-2">


                            <button
                                type="button"
                                id="todayBtn"
                                class="btn btn-primary btn-date w-100"
                            >

                                <i class="bi bi-calendar-day me-1"></i>

                                Today

                            </button>


                        </div>


                        <div class="col-md-2">


                            <button
                                type="button"
                                id="clearDateBtn"
                                class="btn btn-outline-secondary btn-date w-100"
                            >

                                <i class="bi bi-x-circle me-1"></i>

                                Clear

                            </button>


                        </div>


                    </div>


                    <div
                        id="dateActiveMessage"
                        class="date-active-message"
                    >

                        <i class="bi bi-funnel-fill me-1"></i>

                        Showing records for
                        <strong id="selectedDateText"></strong>

                    </div>


                </div>

            <?php endif; ?>


        </div>


        <!-- =================================================
             TABLE CARD
        ================================================= -->

        <div class="card-box">


            <!-- TABS -->

            <ul class="nav nav-tabs mb-3">


                <li class="nav-item">

                    <button
                        class="nav-link active"
                        data-bs-toggle="tab"
                        data-bs-target="#current"
                    >

                        Admitted Patients

                    </button>

                </li>


                <li class="nav-item">

                    <button
                        class="nav-link"
                        data-bs-toggle="tab"
                        data-bs-target="#walkin"
                    >

                        Walk-In Patients

                    </button>

                </li>


                <li class="nav-item">

                    <button
                        class="nav-link"
                        data-bs-toggle="tab"
                        data-bs-target="#appointment"
                    >

                        Appointment Patients

                    </button>

                </li>


                <li class="nav-item">

                    <button
                        class="nav-link"
                        data-bs-toggle="tab"
                        data-bs-target="#diagnosed"
                    >

                        Diagnosed Patients

                    </button>

                </li>


                <li class="nav-item">

                    <button
                        class="nav-link"
                        data-bs-toggle="tab"
                        data-bs-target="#discharged"
                    >

                        Discharged Patients

                    </button>

                </li>


            </ul>


            <div class="tab-content">


                <!-- =================================================
                     ADMITTED PATIENTS
                ================================================= -->

                <div
                    class="tab-pane fade show active"
                    id="current"
                >


                    <table
                        class="table table-bordered"
                        id="currentTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Ward
                                </th>

                                <th>
                                    Bed
                                </th>

                                <th>
                                    Admission Date
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($currentList as $p): ?>

                            <tr
                                data-record-date="<?= htmlspecialchars(
                                    $p['ADMISSION_SORT'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >


                                <td>

                                    <?= htmlspecialchars(
                                        $p['NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $p['WARD_NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $p['BED_NUMBER'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $p['ADMISSION_SORT'] ?? ''
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $p['ADMISSION_DATE'] ?? ''
                                    ) ?>

                                </td>


                                <td class="action-buttons">


                                    <!-- VIEW -->

                                    <a
                                        href="patient_details.php?id=<?= urlencode(
                                            $p['ADMISSION_ID']
                                        ) ?>"
                                        class="btn btn-primary btn-sm btn-view me-1"
                                    >

                                        <i class="bi bi-eye"></i>

                                        View

                                    </a>


                                    <!-- DISCHARGE -->

                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm btn-discharge discharge-btn"
                                        data-url="discharge_patient.php?admission_id=<?= urlencode(
                                            $p['ADMISSION_ID']
                                        ) ?>"
                                        data-patient="<?= htmlspecialchars(
                                            $p['NAME'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <i class="bi bi-box-arrow-right"></i>

                                        Discharge

                                    </button>


                                </td>


                            </tr>

                        <?php endforeach; ?>


                        </tbody>

                    </table>


                </div>


                <!-- =================================================
                     WALK-IN
                ================================================= -->

                <div
                    class="tab-pane fade"
                    id="walkin"
                >


                    <table
                        class="table table-bordered"
                        id="walkinTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($walkinPatients as $w): ?>

                            <tr
                                data-record-date="<?= htmlspecialchars(
                                    $w['CONSULTATION_SORT'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <td>

                                    <?= htmlspecialchars(
                                        $w['NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $w['CONSULTATION_SORT'] ?? ''
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $w['CONSULTATION_DATE'] ?? ''
                                    ) ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>


                        </tbody>

                    </table>


                </div>


                <!-- =================================================
                     APPOINTMENTS
                ================================================= -->

                <div
                    class="tab-pane fade"
                    id="appointment"
                >


                    <table
                        class="table table-bordered"
                        id="appointmentTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Date
                                </th>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Status
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($appointmentPatients as $a): ?>


                            <?php

                            $appointmentSortDate =
                                convertAppointmentDate(
                                    $a['APPOINTMENT_DATE'] ?? ''
                                );

                            ?>


                            <tr
                                data-record-date="<?= htmlspecialchars(
                                    $appointmentSortDate,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >


                                <td>

                                    <?= htmlspecialchars(
                                        $a['NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $appointmentSortDate
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $a['APPOINTMENT_DATE'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $a['DEPARTMENT'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $a['STATUS'] ?? ''
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>


                </div>


                <!-- =================================================
                     DIAGNOSED
                ================================================= -->

                <div
                    class="tab-pane fade"
                    id="diagnosed"
                >


                    <table
                        class="table table-bordered"
                        id="diagnosedTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Diagnosis
                                </th>

                                <th>
                                    Date
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($diagnosedList as $d): ?>


                            <tr
                                data-record-date="<?= htmlspecialchars(
                                    $d['DATE_SORT'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >


                                <td>

                                    <?= htmlspecialchars(
                                        $d['NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= htmlspecialchars(
                                        $d['DIAGNOSIS_DETAILS'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $d['DATE_SORT'] ?? ''
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $d['DATE_RECORDED'] ?? ''
                                    ) ?>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>


                </div>


                <!-- =================================================
                     DISCHARGED
                ================================================= -->

                <div
                    class="tab-pane fade"
                    id="discharged"
                >


                    <table
                        class="table table-bordered"
                        id="dischargedTable"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Patient
                                </th>

                                <th>
                                    Admission Date
                                </th>

                                <th>
                                    Discharge Date
                                </th>

                                <th>
                                    Length of Stay
                                </th>

                                <th>
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach ($dischargedList as $d): ?>


                            <?php

                            $admissionDate =
                                !empty($d['ADMISSION_SORT'])
                                    ? new DateTime($d['ADMISSION_SORT'])
                                    : null;

                            $dischargeDate =
                                !empty($d['DISCHARGE_SORT'])
                                    ? new DateTime($d['DISCHARGE_SORT'])
                                    : null;

                            $days = 0;

                            if (
                                $admissionDate &&
                                $dischargeDate
                            ) {

                                $days =
                                    $admissionDate
                                    ->diff($dischargeDate)
                                    ->days + 1;

                            }

                            ?>


                            <tr
                                data-record-date="<?= htmlspecialchars(
                                    $d['DISCHARGE_SORT'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >


                                <td>

                                    <?= htmlspecialchars(
                                        $d['NAME'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $d['ADMISSION_SORT'] ?? ''
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $d['ADMISSION_DATE'] ?? ''
                                    ) ?>

                                </td>


                                <td
                                    data-date="<?= htmlspecialchars(
                                        $d['DISCHARGE_SORT'] ?? ''
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $d['DISCHARGE_DATE'] ?? ''
                                    ) ?>

                                </td>


                                <td>

                                    <?= $days ?> Day(s)

                                </td>


                                <td>

                                    <a
                                        href="patient_details.php?id=<?= urlencode(
                                            $d['ADMISSION_ID']
                                        ) ?>"
                                        class="btn btn-primary btn-sm"
                                    >

                                        <i class="bi bi-eye"></i>

                                        View

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

</div>


<!-- =========================================================
     CUSTOM DISCHARGE CONFIRMATION POPUP
========================================================= -->

<div
    id="dischargeModal"
    class="custom-modal-overlay"
>


    <div class="custom-modal">


        <div class="modal-icon warning">

            <i class="bi bi-exclamation-triangle"></i>

        </div>


        <h3>
            Discharge Patient?
        </h3>


        <p id="dischargeMessage">

            Are you sure you want to discharge this patient?

        </p>


        <div class="modal-buttons">


            <button
                type="button"
                class="btn-modal-cancel"
                id="cancelDischarge"
            >

                Cancel

            </button>


            <button
                type="button"
                class="btn-modal-confirm"
                id="confirmDischarge"
            >

                <i class="bi bi-check-lg"></i>

                Discharge

            </button>


        </div>


    </div>

</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>


<script>

/* =========================================================
   DATATABLE VARIABLES
========================================================= */

let currentTable;
let walkinTable;
let appointmentTable;
let diagnosedTable;
let dischargedTable;


/* =========================================================
   SELECTED DATE
========================================================= */

let selectedRecordDate = '';


/* =========================================================
   DATATABLES
========================================================= */

$(document).ready(function () {


    /* =====================================================
       CURRENT TABLE
    ===================================================== */

    currentTable = $('#currentTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [[3, 'desc']],

        columnDefs: [

            {
                orderable: false,
                targets: 4
            }

        ]

    });


    /* =====================================================
       WALK-IN TABLE
    ===================================================== */

    walkinTable = $('#walkinTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [[1, 'desc']]

    });


    /* =====================================================
       APPOINTMENT TABLE
    ===================================================== */

    appointmentTable = $('#appointmentTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [[1, 'desc']]

    });


    /* =====================================================
       DIAGNOSED TABLE
    ===================================================== */

    diagnosedTable = $('#diagnosedTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [[2, 'desc']]

    });


    /* =====================================================
       DISCHARGED TABLE
    ===================================================== */

    dischargedTable = $('#dischargedTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [[2, 'desc']],

        columnDefs: [

            {
                orderable: false,
                targets: 4
            }

        ]

    });


    /* =====================================================
       DATE FILTER
    ===================================================== */

    $.fn.dataTable.ext.search.push(
        function (
            settings,
            data,
            dataIndex
        ) {

            /*
             * If no date selected,
             * show all records.
             */

            if (!selectedRecordDate) {

                return true;

            }


            /*
             * Get current table row.
             */

            const row =
                settings.aoData[dataIndex].nTr;


            if (!row) {

                return true;

            }


            /*
             * Get record date from row.
             */

            const recordDate =
                row.getAttribute(
                    'data-record-date'
                );


            /*
             * Show only matching date.
             */

            return recordDate === selectedRecordDate;

        }
    );


    /* =====================================================
       DATE INPUT CHANGE
    ===================================================== */

    $('#recordDate').on(
        'change',
        function () {

            selectedRecordDate = this.value;

            updateDateMessage();

            redrawAllTables();

        }
    );


    /* =====================================================
       TODAY BUTTON
    ===================================================== */

    $('#todayBtn').on(
        'click',
        function () {

            const today =
                new Date();


            const year =
                today.getFullYear();


            const month =
                String(
                    today.getMonth() + 1
                ).padStart(2, '0');


            const day =
                String(
                    today.getDate()
                ).padStart(2, '0');


            selectedRecordDate =
                year +
                '-' +
                month +
                '-' +
                day;


            $('#recordDate').val(
                selectedRecordDate
            );


            updateDateMessage();

            redrawAllTables();

        }
    );


    /* =====================================================
       CLEAR DATE BUTTON
    ===================================================== */

    $('#clearDateBtn').on(
        'click',
        function () {

            selectedRecordDate = '';

            $('#recordDate').val('');

            updateDateMessage();

            redrawAllTables();

        }
    );


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#searchBox').on(
        'keyup',
        function () {

            const value =
                this.value;


            const activeTab =
                getActiveTab();


            if (!activeTab) {

                return;

            }


            const tableElement =
                activeTab.querySelector(
                    'table'
                );


            if (!tableElement) {

                return;

            }


            const tableId =
                tableElement.id;


            $('#' + tableId)
                .DataTable()
                .search(value)
                .draw();

        }
    );


    /* =====================================================
       TYPE FILTER
    ===================================================== */

    $('#typeFilter').on(
        'change',
        function () {

            const type =
                this.value;


            if (type === '') {

                return;

            }


            const target =
                document.querySelector(
                    '[data-bs-target="#' +
                    type +
                    '"]'
                );


            if (target) {

                target.click();

            }

        }
    );


    /* =====================================================
       SORT
    ===================================================== */

    $('#sortOrder').on(
        'change',
        function () {

            const mode =
                this.value;


            const activeTab =
                getActiveTab();


            if (!activeTab) {

                return;

            }


            const tableElement =
                activeTab.querySelector(
                    'table'
                );


            if (!tableElement) {

                return;

            }


            const table =
                $('#' + tableElement.id)
                .DataTable();


            if (mode === 'asc') {

                table
                    .order([0, 'asc'])
                    .draw();

            }


            else if (mode === 'desc') {

                table
                    .order([0, 'desc'])
                    .draw();

            }


            else {

                const dateIndex =
                    findDateColumn(
                        table
                    );


                if (dateIndex !== -1) {

                    table
                        .order([
                            dateIndex,
                            mode === 'latest'
                                ? 'desc'
                                : 'asc'
                        ])
                        .draw();

                }

            }

        }
    );


    /* =====================================================
       TAB CHANGE
    ===================================================== */

    document
        .querySelectorAll(
            '[data-bs-toggle="tab"]'
        )
        .forEach(
            function (tabButton) {

                tabButton.addEventListener(
                    'shown.bs.tab',
                    function () {

                        const searchValue =
                            $('#searchBox').val();


                        const activeTab =
                            getActiveTab();


                        if (!activeTab) {

                            return;

                        }


                        const tableElement =
                            activeTab.querySelector(
                                'table'
                            );


                        if (!tableElement) {

                            return;

                        }


                        $('#' + tableElement.id)
                            .DataTable()
                            .search(searchValue)
                            .draw();

                    }
                );

            }
        );


    /* =====================================================
       DISCHARGE BUTTON
    ===================================================== */

    document
        .querySelectorAll(
            '.discharge-btn'
        )
        .forEach(
            function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        selectedDischargeURL =
                            this.getAttribute(
                                'data-url'
                            );


                        const patientName =
                            this.getAttribute(
                                'data-patient'
                            );


                        document.getElementById(
                            'dischargeMessage'
                        ).innerHTML =
                            'Are you sure you want to discharge <strong>' +
                            escapeHtml(
                                patientName
                            ) +
                            '</strong>?';


                        document.getElementById(
                            'dischargeModal'
                        ).classList.add(
                            'show'
                        );

                    }
                );

            }
        );


    /* =====================================================
       CANCEL DISCHARGE
    ===================================================== */

    document
        .getElementById(
            'cancelDischarge'
        )
        .addEventListener(
            'click',
            function () {

                closeDischargeModal();

            }
        );


    /* =====================================================
       CONFIRM DISCHARGE
    ===================================================== */

    document
        .getElementById(
            'confirmDischarge'
        )
        .addEventListener(
            'click',
            function () {

                if (
                    selectedDischargeURL
                ) {

                    window.location.href =
                        selectedDischargeURL;

                }

            }
        );


    /* =====================================================
       CLICK OUTSIDE MODAL
    ===================================================== */

    document
        .getElementById(
            'dischargeModal'
        )
        .addEventListener(
            'click',
            function (event) {

                if (
                    event.target === this
                ) {

                    closeDischargeModal();

                }

            }
        );


    /* =====================================================
       ESCAPE KEY
    ===================================================== */

    document.addEventListener(
        'keydown',
        function (event) {

            if (
                event.key === 'Escape'
            ) {

                closeDischargeModal();

            }

        }
    );

});


/* =========================================================
   REDRAW ALL TABLES
========================================================= */

function redrawAllTables()
{

    if (currentTable) {

        currentTable.draw();

    }


    if (walkinTable) {

        walkinTable.draw();

    }


    if (appointmentTable) {

        appointmentTable.draw();

    }


    if (diagnosedTable) {

        diagnosedTable.draw();

    }


    if (dischargedTable) {

        dischargedTable.draw();

    }

}


/* =========================================================
   UPDATE DATE MESSAGE
========================================================= */

function updateDateMessage()
{

    const message =
        document.getElementById(
            'dateActiveMessage'
        );


    const selectedText =
        document.getElementById(
            'selectedDateText'
        );


    if (
        !selectedRecordDate
    ) {

        message.classList.remove(
            'show'
        );

        selectedText.textContent = '';

        return;

    }


    const parts =
        selectedRecordDate.split('-');


    if (
        parts.length !== 3
    ) {

        return;

    }


    const year =
        parts[0];


    const month =
        parts[1];


    const day =
        parts[2];


    const monthNames = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December'
    ];


    const monthName =
        monthNames[
            parseInt(month, 10) - 1
        ];


    selectedText.textContent =
        day +
        ' ' +
        monthName +
        ' ' +
        year;


    message.classList.add(
        'show'
    );

}


/* =========================================================
   FIND DATE COLUMN
========================================================= */

function findDateColumn(table)
{

    let dateIndex = -1;


    table.columns().every(
        function (index) {

            const header =
                $(this.header())
                .text()
                .trim()
                .toLowerCase();


            if (
                header.includes('date')
            ) {

                dateIndex = index;

            }

        }
    );


    return dateIndex;

}


/* =========================================================
   GET ACTIVE TAB
========================================================= */

function getActiveTab()
{

    return document.querySelector(
        '.tab-pane.active'
    );

}


/* =========================================================
   CLOSE DISCHARGE MODAL
========================================================= */

function closeDischargeModal()
{

    document
        .getElementById(
            'dischargeModal'
        )
        .classList.remove(
            'show'
        );


    selectedDischargeURL = null;

}


/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(text)
{

    const div =
        document.createElement(
            'div'
        );


    div.textContent = text;


    return div.innerHTML;

}

</script>


</body>

</html>