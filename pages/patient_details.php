<?php
session_start();

include("../config/config.php");

/* =========================================================
   ADMIN ACCESS
========================================================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   CHECK ADMISSION ID
========================================================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Admission ID.");
}

$admission_id = (int) $_GET['id'];


/* =========================================================
   DISCHARGE PATIENT
   IMPORTANT:
   Process this BEFORE fetching the patient information
========================================================= */
if (isset($_POST['discharge'])) {

    try {

        /* ---------------------------------------------
           GET BED ID
        --------------------------------------------- */
        $bedStmt = $conn->prepare("
            SELECT BED_ID
            FROM SYARMIMI.ADMISSION
            WHERE ADMISSION_ID = :id
        ");

        $bedStmt->execute([
            ':id' => $admission_id
        ]);

        $bed_id = $bedStmt->fetchColumn();


        /* ---------------------------------------------
           CHECK IF ALREADY DISCHARGED
        --------------------------------------------- */
        $checkStmt = $conn->prepare("
            SELECT DISCHARGE_DATE
            FROM SYARMIMI.ADMISSION
            WHERE ADMISSION_ID = :id
        ");

        $checkStmt->execute([
            ':id' => $admission_id
        ]);

        $existingDischarge = $checkStmt->fetchColumn();


        if (empty($existingDischarge)) {

            /* -----------------------------------------
               UPDATE DISCHARGE DATE
            ----------------------------------------- */
            $update = $conn->prepare("
                UPDATE SYARMIMI.ADMISSION
                SET DISCHARGE_DATE = SYSDATE
                WHERE ADMISSION_ID = :id
            ");

            $update->execute([
                ':id' => $admission_id
            ]);


            /* -----------------------------------------
               FREE BED
            ----------------------------------------- */
            if (!empty($bed_id)) {

                $freeBed = $conn->prepare("
                    UPDATE SYARMIMI.BED
                    SET STATUS = 'Available'
                    WHERE BED_ID = :bed
                ");

                $freeBed->execute([
                    ':bed' => $bed_id
                ]);
            }
        }


        /* ---------------------------------------------
           REDIRECT BACK
        --------------------------------------------- */
        header(
            "Location: patient_details.php?id=" . $admission_id . "&discharged=1"
        );

        exit();

    } catch (PDOException $e) {

        die(
            "Unable to discharge patient: "
            . htmlspecialchars($e->getMessage())
        );
    }
}


/* =========================================================
   FETCH PATIENT INFORMATION
========================================================= */

$stmt = $conn->prepare("
    SELECT
        P.*,
        A.ADMISSION_DATE,
        A.DISCHARGE_DATE,
        A.ADMISSION_ID,
        W.WARD_NAME,
        B.BED_NUMBER

    FROM SYARMIMI.ADMISSION A

    JOIN SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    JOIN SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE A.ADMISSION_ID = :id
");

$stmt->execute([
    ':id' => $admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);


/* =========================================================
   PATIENT NOT FOUND
========================================================= */
if (!$patient) {
    die("Patient record not found.");
}


/* =========================================================
   DIAGNOSIS HISTORY
========================================================= */

$diag = $conn->prepare("
    SELECT
        DIAGNOSIS_DETAILS,
        DATE_RECORDED

    FROM SYARMIMI.DIAGNOSIS

    WHERE ADMISSION_ID = :id

    ORDER BY DATE_RECORDED DESC
");

$diag->execute([
    ':id' => $admission_id
]);

$diagnosisList = $diag->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   MEDICATION HISTORY
========================================================= */

$medStmt = $conn->prepare("
    SELECT
        MO.MEDORDER_ID,
        M.MEDICATION_NAME,
        MO.DOSAGE,
        MO.FREQUENCY

    FROM SYARMIMI.MEDICATION_ORDER MO

    JOIN SYARMIMI.MEDICATION M
        ON MO.MEDICATION_ID = M.MEDICATION_ID

    WHERE MO.ADMISSION_ID = :id

    ORDER BY MO.MEDORDER_ID DESC
");

$medStmt->execute([
    ':id' => $admission_id
]);

$medicationList = $medStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   FORMAT DATE FUNCTION
========================================================= */

function formatDateOnly($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return htmlspecialchars($date);
    }

    return date('d-M-Y', $timestamp);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Patient Details</title>


<!-- BOOTSTRAP -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- BOOTSTRAP ICONS -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">


<!-- SWEET ALERT -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<style>

/* =========================================================
   GENERAL
========================================================= */

body {

    background: #eef2f7;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #172b4d;

}


/* =========================================================
   MAIN CONTAINER
========================================================= */

.page-container {

    max-width: 1250px;

    margin: 0 auto;

    padding: 30px 25px 60px;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.page-title {

    font-size: 28px;

    font-weight: 700;

    margin: 0;

    color: #172b4d;

}


.page-subtitle {

    color: #6b7280;

    margin-top: 5px;

    font-size: 14px;

}


/* =========================================================
   TOP BUTTONS
========================================================= */

.top-actions {

    display: flex;

    gap: 10px;

    flex-wrap: wrap;

}


.btn-back {

    background: #ffffff;

    border: 1px solid #d1d5db;

    color: #374151;

    padding: 10px 18px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: 600;

}


.btn-back:hover {

    background: #f3f4f6;

    color: #111827;

}


.btn-pdf {

    background: #198754;

    color: white;

    border: none;

    padding: 10px 18px;

    border-radius: 8px;

    text-decoration: none;

    font-weight: 600;

}


.btn-pdf:hover {

    background: #157347;

    color: white;

}


/* =========================================================
   PATIENT PROFILE CARD
========================================================= */

.profile-card {

    background: white;

    border-radius: 16px;

    padding: 28px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);

    margin-bottom: 22px;

}


/* =========================================================
   PROFILE HEADER
========================================================= */

.profile-header {

    display: flex;

    align-items: center;

    gap: 18px;

    margin-bottom: 25px;

}


.patient-avatar {

    width: 70px;

    height: 70px;

    border-radius: 50%;

    background: #e8f1ff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 32px;

    color: #0d6efd;

}


.patient-name {

    font-size: 24px;

    font-weight: 700;

    color: #172b4d;

    margin-bottom: 4px;

}


.patient-id {

    color: #6b7280;

    font-size: 14px;

}


/* =========================================================
   INFORMATION BOX
========================================================= */

.info-box {

    background: #f8fafc;

    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 15px 18px;

    height: 100%;

}


.info-label {

    display: block;

    color: #6b7280;

    font-size: 12px;

    font-weight: 600;

    text-transform: uppercase;

    margin-bottom: 5px;

}


.info-value {

    font-size: 16px;

    font-weight: 600;

    color: #172b4d;

}


/* =========================================================
   STATUS
========================================================= */

.status-badge {

    display: inline-flex;

    align-items: center;

    gap: 6px;

    padding: 7px 12px;

    border-radius: 20px;

    font-size: 13px;

    font-weight: 600;

}


.status-admitted {

    background: #fff3cd;

    color: #856404;

}


.status-discharged {

    background: #d1e7dd;

    color: #0f5132;

}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card {

    background: white;

    border-radius: 16px;

    padding: 25px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.06);

    margin-bottom: 22px;

}


.section-header {

    display: flex;

    align-items: center;

    gap: 10px;

    margin-bottom: 20px;

}


.section-icon {

    width: 38px;

    height: 38px;

    border-radius: 9px;

    background: #eef4ff;

    display: flex;

    align-items: center;

    justify-content: center;

    color: #0d6efd;

    font-size: 18px;

}


.section-title {

    font-size: 19px;

    font-weight: 700;

    margin: 0;

    color: #172b4d;

}


/* =========================================================
   TABLE
========================================================= */

.custom-table {

    margin-bottom: 0;

}


.custom-table thead th {

    background: #f8fafc;

    color: #4b5563;

    font-size: 13px;

    font-weight: 700;

    border-bottom: 1px solid #dee2e6;

    padding: 13px;

}


.custom-table tbody td {

    padding: 14px 13px;

    vertical-align: middle;

    color: #374151;

}


.custom-table tbody tr:hover {

    background: #f9fafb;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    text-align: center;

    padding: 35px 20px;

    color: #6b7280;

}


.empty-icon {

    font-size: 38px;

    margin-bottom: 10px;

    opacity: 0.6;

}


/* =========================================================
   TIMELINE
========================================================= */

.timeline {

    position: relative;

    margin-top: 10px;

    padding-left: 25px;

}


.timeline::before {

    content: "";

    position: absolute;

    left: 9px;

    top: 8px;

    bottom: 8px;

    width: 2px;

    background: #dbe3ef;

}


.timeline-item {

    position: relative;

    padding-left: 30px;

    padding-bottom: 25px;

}


.timeline-item:last-child {

    padding-bottom: 0;

}


.timeline-dot {

    position: absolute;

    left: -1px;

    top: 2px;

    width: 20px;

    height: 20px;

    border-radius: 50%;

    background: #0d6efd;

    border: 4px solid #e8f1ff;

}


.timeline-content {

    background: #f8fafc;

    border: 1px solid #e5e7eb;

    border-radius: 10px;

    padding: 14px 16px;

}


.timeline-title {

    font-weight: 700;

    color: #172b4d;

    margin-bottom: 4px;

}


.timeline-date {

    font-size: 13px;

    color: #6b7280;

}


/* =========================================================
   DISCHARGE ACTION
========================================================= */

.discharge-card {

    background: #fff;

    border: 1px solid #fecaca;

    border-radius: 16px;

    padding: 22px 25px;

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 20px;

    box-shadow:
        0 4px 15px rgba(0,0,0,0.04);

}


.discharge-title {

    font-weight: 700;

    color: #991b1b;

    margin-bottom: 4px;

}


.discharge-text {

    color: #6b7280;

    font-size: 14px;

    margin: 0;

}


.btn-discharge {

    background: #dc3545;

    color: white;

    border: none;

    border-radius: 8px;

    padding: 10px 18px;

    font-weight: 600;

    white-space: nowrap;

}


.btn-discharge:hover {

    background: #bb2d3b;

}


/* =========================================================
   DISCHARGED MESSAGE
========================================================= */

.discharged-card {

    background: #f0fdf4;

    border: 1px solid #bbf7d0;

    border-radius: 16px;

    padding: 20px 25px;

    display: flex;

    align-items: center;

    gap: 14px;

}


.discharged-icon {

    width: 42px;

    height: 42px;

    border-radius: 50%;

    background: #dcfce7;

    color: #15803d;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 20px;

}


.discharged-title {

    font-weight: 700;

    color: #166534;

    margin-bottom: 3px;

}


.discharged-text {

    color: #4b5563;

    font-size: 14px;

    margin: 0;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:768px) {

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }


    .top-actions {

        width: 100%;

    }


    .top-actions a {

        flex: 1;

        text-align: center;

    }


    .profile-header {

        align-items: flex-start;

    }


    .discharge-card {

        flex-direction: column;

        align-items: flex-start;

    }


    .btn-discharge {

        width: 100%;

    }

}

</style>

</head>


<body>


<div class="page-container">


<!-- =====================================================
     PAGE HEADER
===================================================== -->

<div class="page-header">

    <div>

        <h1 class="page-title">
            <i class="bi bi-person-vcard"></i>
            Patient Details
        </h1>

        <div class="page-subtitle">
            View patient admission, diagnosis and medication information
        </div>

    </div>


    <div class="top-actions">

        <a
            href="patient_management.php"
            class="btn-back">

            <i class="bi bi-arrow-left"></i>
            Back

        </a>


        <a
            href="patient_report.php?id=<?= $admission_id ?>"
            target="_blank"
            class="btn-pdf">

            <i class="bi bi-file-earmark-pdf"></i>
            Export PDF

        </a>

    </div>

</div>



<!-- =====================================================
     PATIENT PROFILE
===================================================== -->

<div class="profile-card">


    <div class="profile-header">

        <div class="patient-avatar">

            <i class="bi bi-person-fill"></i>

        </div>


        <div>

            <div class="patient-name">

                <?= htmlspecialchars($patient['NAME']) ?>

            </div>


            <div class="patient-id">

                Patient ID:
                <?= htmlspecialchars($patient['PATIENT_ID']) ?>

                &nbsp; • &nbsp;

                Admission ID:
                <?= htmlspecialchars($patient['ADMISSION_ID']) ?>

            </div>

        </div>

    </div>


    <div class="row g-3">


        <!-- WARD -->

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-label">
                    <i class="bi bi-hospital"></i>
                    Ward
                </span>

                <div class="info-value">

                    <?= htmlspecialchars($patient['WARD_NAME']) ?>

                </div>

            </div>

        </div>


        <!-- BED -->

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-label">
                    <i class="bi bi-door-open"></i>
                    Bed
                </span>

                <div class="info-value">

                    <?= htmlspecialchars($patient['BED_NUMBER']) ?>

                </div>

            </div>

        </div>


        <!-- STATUS -->

        <div class="col-md-4">

            <div class="info-box">

                <span class="info-label">
                    <i class="bi bi-activity"></i>
                    Status
                </span>

                <div class="info-value">

                    <?php if (empty($patient['DISCHARGE_DATE'])): ?>

                        <span class="status-badge status-admitted">

                            <i class="bi bi-clock"></i>

                            Currently Admitted

                        </span>

                    <?php else: ?>

                        <span class="status-badge status-discharged">

                            <i class="bi bi-check-circle"></i>

                            Discharged

                        </span>

                    <?php endif; ?>

                </div>

            </div>

        </div>


        <!-- ADMISSION DATE -->

        <div class="col-md-6">

            <div class="info-box">

                <span class="info-label">
                    <i class="bi bi-calendar-check"></i>
                    Admission Date
                </span>

                <div class="info-value">

                    <?= formatDateOnly($patient['ADMISSION_DATE']) ?>

                </div>

            </div>

        </div>


        <!-- DISCHARGE DATE -->

        <div class="col-md-6">

            <div class="info-box">

                <span class="info-label">
                    <i class="bi bi-calendar-x"></i>
                    Discharge Date
                </span>

                <div class="info-value">

                    <?php if (!empty($patient['DISCHARGE_DATE'])): ?>

                        <?= formatDateOnly($patient['DISCHARGE_DATE']) ?>

                    <?php else: ?>

                        Still Admitted

                    <?php endif; ?>

                </div>

            </div>

        </div>


    </div>

</div>



<!-- =====================================================
     DIAGNOSIS HISTORY
===================================================== -->

<div class="content-card">


    <div class="section-header">

        <div class="section-icon">

            <i class="bi bi-clipboard2-pulse"></i>

        </div>

        <h2 class="section-title">
            Diagnosis History
        </h2>

    </div>


    <?php if (count($diagnosisList) > 0): ?>


        <div class="table-responsive">

            <table class="table custom-table">

                <thead>

                    <tr>

                        <th width="25%">
                            Date
                        </th>

                        <th>
                            Diagnosis
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($diagnosisList as $d): ?>

                    <tr>

                        <td>

                            <?= formatDateOnly($d['DATE_RECORDED']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($d['DIAGNOSIS_DETAILS']) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


    <?php else: ?>


        <div class="empty-state">

            <div class="empty-icon">

                <i class="bi bi-clipboard2-x"></i>

            </div>

            <div>
                No diagnosis records available.
            </div>

        </div>


    <?php endif; ?>

</div>



<!-- =====================================================
     MEDICATION HISTORY
===================================================== -->

<div class="content-card">


    <div class="section-header">

        <div class="section-icon">

            <i class="bi bi-capsule"></i>

        </div>

        <h2 class="section-title">
            Medication History
        </h2>

    </div>


    <?php if (count($medicationList) > 0): ?>


        <div class="table-responsive">

            <table class="table custom-table">

                <thead>

                    <tr>

                        <th>
                            Medication
                        </th>

                        <th>
                            Dosage
                        </th>

                        <th>
                            Frequency
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach ($medicationList as $m): ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars($m['MEDICATION_NAME']) ?>
                            </strong>

                        </td>


                        <td>

                            <?= htmlspecialchars($m['DOSAGE']) ?>

                        </td>


                        <td>

                            <?= htmlspecialchars($m['FREQUENCY']) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


    <?php else: ?>


        <div class="empty-state">

            <div class="empty-icon">

                <i class="bi bi-capsule-pill"></i>

            </div>

            <div>
                No medication records available.
            </div>

        </div>


    <?php endif; ?>

</div>



<!-- =====================================================
     PATIENT TIMELINE
===================================================== -->

<div class="content-card">


    <div class="section-header">

        <div class="section-icon">

            <i class="bi bi-clock-history"></i>

        </div>

        <h2 class="section-title">
            Patient Timeline
        </h2>

    </div>


    <div class="timeline">


        <!-- ADMISSION -->

        <div class="timeline-item">

            <div class="timeline-dot"></div>

            <div class="timeline-content">

                <div class="timeline-title">

                    <i class="bi bi-hospital"></i>
                    Patient Admitted

                </div>

                <div class="timeline-date">

                    <?= formatDateOnly($patient['ADMISSION_DATE']) ?>

                </div>

            </div>

        </div>



        <!-- DIAGNOSIS -->

        <?php foreach ($diagnosisList as $d): ?>

        <div class="timeline-item">

            <div class="timeline-dot"></div>

            <div class="timeline-content">

                <div class="timeline-title">

                    <i class="bi bi-clipboard2-pulse"></i>
                    Diagnosis Recorded

                </div>

                <div class="timeline-date">

                    <?= formatDateOnly($d['DATE_RECORDED']) ?>

                </div>

                <div class="mt-2">

                    <?= htmlspecialchars($d['DIAGNOSIS_DETAILS']) ?>

                </div>

            </div>

        </div>

        <?php endforeach; ?>



        <!-- MEDICATION -->

        <?php foreach ($medicationList as $m): ?>

        <div class="timeline-item">

            <div class="timeline-dot"></div>

            <div class="timeline-content">

                <div class="timeline-title">

                    <i class="bi bi-capsule"></i>
                    Medication Prescribed

                </div>


                <div class="mt-2">

                    <strong>
                        <?= htmlspecialchars($m['MEDICATION_NAME']) ?>
                    </strong>

                </div>


                <div class="timeline-date mt-1">

                    Dosage:
                    <?= htmlspecialchars($m['DOSAGE']) ?>

                    &nbsp; | &nbsp;

                    Frequency:
                    <?= htmlspecialchars($m['FREQUENCY']) ?>

                </div>

            </div>

        </div>

        <?php endforeach; ?>



        <!-- DISCHARGE -->

        <?php if (!empty($patient['DISCHARGE_DATE'])): ?>

        <div class="timeline-item">

            <div class="timeline-dot"></div>

            <div class="timeline-content">

                <div class="timeline-title">

                    <i class="bi bi-check-circle"></i>
                    Patient Discharged

                </div>

                <div class="timeline-date">

                    <?= formatDateOnly($patient['DISCHARGE_DATE']) ?>

                </div>

            </div>

        </div>

        <?php endif; ?>


    </div>

</div>



<!-- =====================================================
     DISCHARGE ACTION
===================================================== -->

<?php if (empty($patient['DISCHARGE_DATE'])): ?>


<div class="discharge-card">


    <div>

        <div class="discharge-title">

            <i class="bi bi-exclamation-triangle"></i>

            Patient Discharge

        </div>

        <p class="discharge-text">

            Discharging this patient will release the assigned bed
            and mark the admission as completed.

        </p>

    </div>


    <form
        method="POST"
        id="dischargeForm"
        style="margin:0;">

        <input
            type="hidden"
            name="discharge"
            value="1">


        <button
            type="button"
            id="dischargeBtn"
            class="btn-discharge">

            <i class="bi bi-box-arrow-right"></i>

            Discharge Patient

        </button>

    </form>

</div>


<?php else: ?>


<div class="discharged-card">

    <div class="discharged-icon">

        <i class="bi bi-check-lg"></i>

    </div>


    <div>

        <div class="discharged-title">

            Patient Successfully Discharged

        </div>

        <p class="discharged-text">

            This admission was discharged on
            <?= formatDateOnly($patient['DISCHARGE_DATE']) ?>.

        </p>

    </div>

</div>


<?php endif; ?>


</div>



<!-- =====================================================
     SWEETALERT
===================================================== -->

<script>

const dischargeBtn =
document.getElementById('dischargeBtn');


if (dischargeBtn) {

    dischargeBtn.addEventListener(
        'click',
        function () {

            Swal.fire({

                title: 'Discharge Patient?',

                text:
                'This patient will be discharged and the assigned bed will be released.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonColor: '#dc3545',

                cancelButtonColor: '#6c757d',

                confirmButtonText:
                '<i class="bi bi-check-circle"></i> Yes, Discharge',

                cancelButtonText:
                'Cancel',

                reverseButtons: true

            }).then((result) => {

                if (result.isConfirmed) {

                    document
                    .getElementById('dischargeForm')
                    .submit();

                }

            });

        }
    );

}

</script>


<!-- =====================================================
     DISCHARGE SUCCESS MESSAGE
===================================================== -->

<?php if (isset($_GET['discharged']) && $_GET['discharged'] == '1'): ?>

<script>

Swal.fire({

    title: 'Patient Discharged',

    text: 'The patient has been successfully discharged.',

    icon: 'success',

    confirmButtonColor: '#198754',

    confirmButtonText: 'OK'

});

</script>

<?php endif; ?>


</body>

</html>