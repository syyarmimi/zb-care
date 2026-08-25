<?php

session_start();
include("../config/config.php");

/* =========================================================
   ROLE CHECK
========================================================= */

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

$staff_id = $_SESSION['user_id'] ?? 0;


/* =========================================================
   GIVE MEDICATION
========================================================= */

if (isset($_POST['give_medication'])) {

    $medorder_id = $_POST['medorder_id'] ?? 0;

    if (!is_numeric($medorder_id) || $medorder_id <= 0) {

        header("Location: nurse_medication.php?error=invalid");
        exit();
    }

    try {

        /* -------------------------------------------------
           CHECK MEDICATION ORDER EXISTS
        ------------------------------------------------- */

        $orderCheck = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.MEDICATION_ORDER
            WHERE MEDORDER_ID = :medorder_id
        ");

        $orderCheck->execute([
            ':medorder_id' => $medorder_id
        ]);

        if ($orderCheck->fetchColumn() == 0) {

            header("Location: nurse_medication.php?error=not_found");
            exit();
        }


        /* -------------------------------------------------
           CHECK IF ALREADY GIVEN
        ------------------------------------------------- */

        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.MEDICATION_ADMIN
            WHERE MEDORDER_ID = :medorder_id
        ");

        $check->execute([
            ':medorder_id' => $medorder_id
        ]);

        $alreadyGiven = $check->fetchColumn();


        if ($alreadyGiven > 0) {

            header(
                "Location: nurse_medication.php?error=already_given"
            );

            exit();
        }


        /* -------------------------------------------------
           INSERT MEDICATION ADMINISTRATION
           
           Existing columns:
           MEDORDER_ID
           ADMIN_TIME
           ACCOUNT_ID
        ------------------------------------------------- */

        $insert = $conn->prepare("
            INSERT INTO SYARMIMI.MEDICATION_ADMIN
            (
                MEDORDER_ID,
                ADMIN_TIME,
                ACCOUNT_ID
            )
            VALUES
            (
                :medorder_id,
                SYSDATE,
                :account_id
            )
        ");

        $insert->execute([
            ':medorder_id' => $medorder_id,
            ':account_id' => $staff_id
        ]);


        /* -------------------------------------------------
           SUCCESS
        ------------------------------------------------- */

        header(
            "Location: nurse_medication.php?success=1"
        );

        exit();


    } catch (PDOException $e) {

        header(
            "Location: nurse_medication.php?error=database"
        );

        exit();
    }
}


/* =========================================================
   FILTER VALUES
========================================================= */

$search = trim($_GET['search'] ?? '');

$wardFilter = trim($_GET['ward'] ?? '');

$sort = $_GET['sort'] ?? 'newest';


/* =========================================================
   SORTING
========================================================= */

$orderBy = "mo.MEDORDER_ID DESC";

if ($sort === 'patient') {

    $orderBy = "UPPER(p.NAME) ASC";

}
elseif ($sort === 'ward') {

    $orderBy = "UPPER(w.WARD_NAME) ASC";

}


/* =========================================================
   WHERE
========================================================= */

$where = "
    WHERE ma.MEDORDER_ID IS NULL
";

$params = [];


/* =========================================================
   SEARCH
========================================================= */

if ($search !== '') {

    $where .= "
        AND
        (
            UPPER(p.NAME) LIKE UPPER(:search)
            OR UPPER(m.MEDICATION_NAME) LIKE UPPER(:search)
            OR UPPER(w.WARD_NAME) LIKE UPPER(:search)
            OR TO_CHAR(a.ADMISSION_ID) LIKE :search_id
        )
    ";

    $params[':search'] = '%' . $search . '%';

    $params[':search_id'] = '%' . $search . '%';
}


/* =========================================================
   WARD FILTER
========================================================= */

if ($wardFilter !== '') {

    $where .= "
        AND UPPER(w.WARD_NAME) = UPPER(:ward)
    ";

    $params[':ward'] = $wardFilter;
}


/* =========================================================
   FETCH PENDING MEDICATION
========================================================= */

$sql = "

SELECT

    mo.MEDORDER_ID,

    a.ADMISSION_ID,

    p.NAME,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,

    w.WARD_NAME,

    b.BED_NUMBER

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.ADMISSION a
    ON mo.ADMISSION_ID = a.ADMISSION_ID

JOIN SYARMIMI.PATIENT p
    ON a.PATIENT_ID = p.PATIENT_ID

JOIN SYARMIMI.MEDICATION m
    ON mo.MEDICATION_ID = m.MEDICATION_ID

/* Medication must already be prepared by pharmacy */
JOIN SYARMIMI.PHARMACY_PREPARATION pp
    ON mo.MEDORDER_ID = pp.MEDORDER_ID

LEFT JOIN SYARMIMI.BED b
    ON a.BED_ID = b.BED_ID

LEFT JOIN SYARMIMI.WARD w
    ON b.WARD_ID = w.WARD_ID

/* Remove medication already administered */
LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
    ON mo.MEDORDER_ID = ma.MEDORDER_ID

$where

ORDER BY $orderBy

";

$stmt = $conn->prepare($sql);

$stmt->execute($params);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PENDING MEDICATION COUNT
========================================================= */

$pendingCount = $conn->query("

    SELECT COUNT(mo.MEDORDER_ID)

    FROM SYARMIMI.MEDICATION_ORDER mo

    JOIN SYARMIMI.ADMISSION a
        ON mo.ADMISSION_ID = a.ADMISSION_ID

    JOIN SYARMIMI.PHARMACY_PREPARATION pp
        ON mo.MEDORDER_ID = pp.MEDORDER_ID

    LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
        ON mo.MEDORDER_ID = ma.MEDORDER_ID

    WHERE ma.MEDORDER_ID IS NULL

")->fetchColumn();


/* =========================================================
   ACTIVE WARDS
=========================================================

   Active Ward =
   A ward containing at least ONE patient
   whose DISCHARGE_DATE is NULL.

   Example:

   Ward A -> 2 patients -> Active
   Ward B -> 1 patient  -> Active
   Ward C -> 0 patients -> Not Active

   COUNT(DISTINCT WARD_ID)
   means each ward is counted only once.
========================================================= */

$wardCount = $conn->query("

    SELECT COUNT(DISTINCT w.WARD_ID)

    FROM SYARMIMI.ADMISSION a

    JOIN SYARMIMI.BED b
        ON a.BED_ID = b.BED_ID

    JOIN SYARMIMI.WARD w
        ON b.WARD_ID = w.WARD_ID

    WHERE a.DISCHARGE_DATE IS NULL

")->fetchColumn();


/* =========================================================
   MEDICATION GIVEN TODAY
========================================================= */

$deliveredToday = $conn->query("

    SELECT COUNT(*)

    FROM SYARMIMI.MEDICATION_ADMIN

    WHERE TRUNC(ADMIN_TIME) = TRUNC(SYSDATE)

")->fetchColumn();


/* =========================================================
   GET ALL WARDS
========================================================= */

$wardStmt = $conn->query("

    SELECT DISTINCT WARD_NAME

    FROM SYARMIMI.WARD

    WHERE WARD_NAME IS NOT NULL

    ORDER BY WARD_NAME

");

$wards = $wardStmt->fetchAll(PDO::FETCH_COLUMN);

?>

<!DOCTYPE html>

<html>

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Medication Administration | ZB-CARE</title>


<!-- Bootstrap -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
rel="stylesheet">


<!-- Bootstrap Icons -->

<link
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
rel="stylesheet">


<!-- SweetAlert -->

<script
src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

/* =========================================================
   BODY
========================================================= */

body {

    background: #f5f7fa;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    margin-left: 260px;

    padding: 30px;

    min-height: 100vh;

}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header {

    background:
        linear-gradient(
            135deg,
            #92400e,
            #b45309
        );

    color: white;

    padding: 28px 30px;

    border-radius: 20px;

    margin-bottom: 25px;

    box-shadow:
        0 8px 25px
        rgba(0,0,0,0.10);

}


.page-header h3 {

    margin: 0;

    font-weight: 700;

}


.page-header p {

    margin: 7px 0 0;

    opacity: .9;

}


/* =========================================================
   PDF BUTTON
========================================================= */

.pdf-btn {

    border-radius: 11px;

    padding: 10px 18px;

    font-weight: 600;

    border: none;

}


/* =========================================================
   STAT CARDS
========================================================= */

.stat-card {

    background: white;

    border-radius: 18px;

    padding: 23px;

    border: none;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.06);

    height: 100%;

    transition: .2s;

}


.stat-card:hover {

    transform:
        translateY(-3px);

    box-shadow:
        0 9px 24px
        rgba(0,0,0,.09);

}


.stat-icon {

    width: 52px;

    height: 52px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 23px;

    margin-bottom: 13px;

}


.icon-pending {

    background: #fee2e2;

    color: #dc2626;

}


.icon-delivered {

    background: #dcfce7;

    color: #16a34a;

}


.icon-ward {

    background: #dbeafe;

    color: #2563eb;

}


.stat-card h2 {

    font-weight: 700;

    margin: 3px 0 0;

}


/* =========================================================
   FILTER
========================================================= */

.filter-box {

    background: white;

    padding: 22px;

    border-radius: 18px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.05);

    margin-bottom: 22px;

}


.form-control,
.form-select {

    border-radius: 12px;

    min-height: 45px;

    border-color: #d1d5db;

}


.form-control:focus,
.form-select:focus {

    border-color: #b45309;

    box-shadow:
        0 0 0 .2rem
        rgba(180,83,9,.12);

}


/* =========================================================
   TABLE
========================================================= */

.table-box {

    background: white;

    border-radius: 18px;

    padding: 22px;

    box-shadow:
        0 5px 18px
        rgba(0,0,0,.05);

}


.table {

    margin-bottom: 0;

    vertical-align: middle;

}


.table thead th {

    background: #1f2937;

    color: white;

    border: none;

    padding: 14px;

    white-space: nowrap;

}


.table tbody td {

    padding: 15px 12px;

}


.table tbody tr {

    transition: .15s;

}


.table tbody tr:hover {

    background: #fff7ed;

}


/* =========================================================
   BADGES
========================================================= */

.medication-badge {

    background: #ede9fe;

    color: #6d28d9;

    padding: 7px 12px;

    border-radius: 20px;

    font-weight: 600;

    display: inline-block;

}


.dosage-badge {

    background: #dbeafe;

    color: #1d4ed8;

    padding: 6px 10px;

    border-radius: 10px;

}


.frequency-badge {

    background: #f3f4f6;

    color: #374151;

    padding: 6px 10px;

    border-radius: 10px;

}


/* =========================================================
   GIVE BUTTON
========================================================= */

.give-btn {

    border-radius: 10px;

    padding: 8px 15px;

    font-weight: 600;

    border: none;

    background: #16a34a;

    color: white;

    cursor: pointer;

    transition: .2s;

    white-space: nowrap;

}


.give-btn:hover {

    background: #15803d;

    transform:
        translateY(-1px);

}


.give-btn:active {

    transform:
        translateY(0);

}


.give-btn i {

    margin-right: 4px;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-box {

    text-align: center;

    padding: 60px 20px;

    color: #6b7280;

}


.empty-box i {

    font-size: 55px;

    color: #16a34a;

}


/* =========================================================
   MODAL
========================================================= */

.modal-content {

    border-radius: 18px;

    border: none;

    box-shadow:
        0 15px 45px
        rgba(0,0,0,.15);

}


.modal-header {

    border-bottom:
        1px solid #f1f1f1;

}


.modal-footer {

    border-top:
        1px solid #f1f1f1;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 992px) {

    .main-content {

        margin-left: 0;

        padding: 20px;

    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<?php include("../includes/sidebar_nurse.php"); ?>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

    <div
    class="d-flex
    justify-content-between
    align-items-center
    flex-wrap
    gap-3">


        <div>

            <h3>

                <i class="bi bi-capsule"></i>

                Medication Administration

            </h3>


            <p>

                Manage and record medication given
                to admitted patients.

            </p>

        </div>


        <!-- PDF -->

        <button
        type="button"
        class="btn btn-light pdf-btn"
        data-bs-toggle="modal"
        data-bs-target="#pdfModal">

            <i class="bi bi-file-earmark-pdf text-danger"></i>

            Generate PDF

        </button>


    </div>

</div>


<!-- =====================================================
     STATISTICS
===================================================== -->

<div class="row g-4 mb-4">


    <!-- PENDING -->

    <div class="col-md-4">

        <div class="stat-card">

            <div class="stat-icon icon-pending">

                <i class="bi bi-hourglass-split"></i>

            </div>


            <small class="text-muted">

                Pending Medication

            </small>


            <h2>

                <?= (int)$pendingCount ?>

            </h2>

        </div>

    </div>


    <!-- GIVEN TODAY -->

    <div class="col-md-4">

        <div class="stat-card">

            <div class="stat-icon icon-delivered">

                <i class="bi bi-check-circle"></i>

            </div>


            <small class="text-muted">

                Given Today

            </small>


            <h2>

                <?= (int)$deliveredToday ?>

            </h2>

        </div>

    </div>


    <!-- ACTIVE WARDS -->

    <div class="col-md-4">

        <div class="stat-card">

            <div class="stat-icon icon-ward">

                <i class="bi bi-hospital"></i>

            </div>


            <small class="text-muted">

                Active Wards

            </small>


            <h2>

                <?= (int)$wardCount ?>

            </h2>

        </div>

    </div>


</div>


<!-- =====================================================
     FILTER
===================================================== -->

<div class="filter-box">


<form method="GET">


<div class="row g-3 align-items-end">


    <!-- SEARCH -->

    <div class="col-md-4">

        <label class="form-label fw-semibold">

            Search

        </label>


        <input
        type="text"
        name="search"
        class="form-control"
        placeholder="Search patient, medication or admission..."
        value="<?= htmlspecialchars($search) ?>">

    </div>


    <!-- WARD -->

    <div class="col-md-3">

        <label class="form-label fw-semibold">

            Ward

        </label>


        <select
        name="ward"
        class="form-select">


            <option value="">

                All Wards

            </option>


            <?php foreach($wards as $ward): ?>

                <option
                value="<?= htmlspecialchars($ward) ?>"
                <?= ($wardFilter === $ward)
                    ? 'selected'
                    : '' ?>>

                    <?= htmlspecialchars($ward) ?>

                </option>

            <?php endforeach; ?>


        </select>

    </div>


    <!-- SORT -->

    <div class="col-md-3">

        <label class="form-label fw-semibold">

            Sort By

        </label>


        <select
        name="sort"
        class="form-select">


            <option
            value="newest"
            <?= ($sort === 'newest')
                ? 'selected'
                : '' ?>>

                Newest First

            </option>


            <option
            value="patient"
            <?= ($sort === 'patient')
                ? 'selected'
                : '' ?>>

                Patient Name

            </option>


            <option
            value="ward"
            <?= ($sort === 'ward')
                ? 'selected'
                : '' ?>>

                Ward Name

            </option>


        </select>

    </div>


    <!-- APPLY -->

    <div class="col-md-2">

        <button
        type="submit"
        class="btn btn-dark w-100"
        style="border-radius:12px; min-height:45px;">

            <i class="bi bi-funnel"></i>

            Apply

        </button>

    </div>


</div>


</form>


</div>


<!-- =====================================================
     MEDICATION QUEUE
===================================================== -->

<div class="table-box">


<div
class="d-flex
justify-content-between
align-items-center
mb-3
flex-wrap
gap-2">


    <div>

        <h5 class="fw-bold mb-1">

            Medication Queue

        </h5>


        <small class="text-muted">

            Prepared medications waiting
            to be administered.

        </small>

    </div>


    <span class="badge bg-danger fs-6">

        <?= count($rows) ?>

        Pending

    </span>


</div>


<div class="table-responsive">


<table class="table table-hover">


<thead>

<tr>

    <th>Patient</th>

    <th>Location</th>

    <th>Medication</th>

    <th>Dosage</th>

    <th>Frequency</th>

    <th class="text-center">

        Action

    </th>

</tr>

</thead>


<tbody>


<?php if(count($rows) > 0): ?>


    <?php foreach($rows as $row): ?>


    <tr>


        <!-- PATIENT -->

        <td>

            <div class="fw-bold">

                <?= htmlspecialchars(
                    $row['NAME']
                ) ?>

            </div>


            <small class="text-muted">

                Admission #

                <?= htmlspecialchars(
                    $row['ADMISSION_ID']
                ) ?>

            </small>

        </td>


        <!-- LOCATION -->

        <td>

            <?php if(!empty($row['WARD_NAME'])): ?>

                <strong>

                    <?= htmlspecialchars(
                        $row['WARD_NAME']
                    ) ?>

                </strong>

            <?php else: ?>

                <span class="text-danger">

                    No Ward

                </span>

            <?php endif; ?>


            <br>


            <small class="text-muted">

                Bed

                <?= htmlspecialchars(
                    $row['BED_NUMBER'] ?? '-'
                ) ?>

            </small>

        </td>


        <!-- MEDICATION -->

        <td>

            <span class="medication-badge">

                <i class="bi bi-capsule"></i>

                <?= htmlspecialchars(
                    $row['MEDICATION_NAME']
                ) ?>

            </span>

        </td>


        <!-- DOSAGE -->

        <td>

            <span class="dosage-badge">

                <?= htmlspecialchars(
                    $row['DOSAGE']
                ) ?>

            </span>

        </td>


        <!-- FREQUENCY -->

        <td>

            <span class="frequency-badge">

                <?= htmlspecialchars(
                    $row['FREQUENCY']
                ) ?>

            </span>

        </td>


        <!-- ACTION -->

        <td class="text-center">


            <form
            method="POST"
            class="giveForm d-inline"

            data-patient="<?= htmlspecialchars(
                $row['NAME'],
                ENT_QUOTES
            ) ?>"

            data-medication="<?= htmlspecialchars(
                $row['MEDICATION_NAME'],
                ENT_QUOTES
            ) ?>"

            data-dosage="<?= htmlspecialchars(
                $row['DOSAGE'],
                ENT_QUOTES
            ) ?>"

            data-frequency="<?= htmlspecialchars(
                $row['FREQUENCY'],
                ENT_QUOTES
            ) ?>">


                <input
                type="hidden"
                name="medorder_id"
                value="<?= (int)$row['MEDORDER_ID'] ?>">


                <input
                type="hidden"
                name="give_medication"
                value="1">


                <button
                type="button"
                class="give-btn giveMedicationBtn">

                    <i class="bi bi-capsule"></i>

                    Give Medication

                </button>


            </form>


        </td>


    </tr>


    <?php endforeach; ?>


<?php else: ?>


    <tr>

        <td colspan="6">


            <div class="empty-box">

                <i class="bi bi-check-circle"></i>


                <h5 class="mt-3 fw-bold">

                    No Pending Medication

                </h5>


                <p class="mb-0">

                    All prepared medications
                    have been administered.

                </p>


            </div>


        </td>

    </tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


<!-- =====================================================
     PDF DATE MODAL
===================================================== -->

<div
class="modal fade"
id="pdfModal"
tabindex="-1"
aria-hidden="true">


<div
class="modal-dialog modal-dialog-centered">


<div
class="modal-content">


    <div class="modal-header">

        <h5 class="modal-title fw-bold">

            <i class="bi bi-file-earmark-pdf text-danger"></i>

            Generate Medication Report

        </h5>


        <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal">
        </button>

    </div>


    <form
    method="GET"
    action="nurse_medication_report.php"
    target="_blank">


        <div class="modal-body">


            <div
            class="text-center mb-3"
            style="font-size:45px;">

                📄

            </div>


            <p class="text-muted text-center">

                Select the date you want
                to include in the medication report.

            </p>


            <label class="form-label fw-semibold">

                Report Date

            </label>


            <input
            type="date"
            name="date"
            class="form-control"
            value="<?= date('Y-m-d') ?>"
            required>


            <div class="alert alert-info mt-3 mb-0">

                <i class="bi bi-info-circle"></i>

                The PDF will display all medication
                administrations recorded on the selected date.

            </div>


        </div>


        <div class="modal-footer">


            <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal">

                Cancel

            </button>


            <button
            type="submit"
            class="btn btn-danger">

                <i class="bi bi-file-earmark-pdf"></i>

                Generate PDF

            </button>


        </div>


    </form>


</div>


</div>


</div>


<!-- =====================================================
     BOOTSTRAP JS
===================================================== -->

<script
src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<script>

/* =========================================================
   GIVE MEDICATION CONFIRMATION
========================================================= */

document
.querySelectorAll('.giveMedicationBtn')
.forEach(function(button) {


    button.addEventListener(
        'click',
        function() {


            const form =
                this.closest('.giveForm');


            const patient =
                form.dataset.patient;


            const medication =
                form.dataset.medication;


            const dosage =
                form.dataset.dosage;


            const frequency =
                form.dataset.frequency;


            Swal.fire({

                title:
                    'Give Medication?',


                html:

                    '<div style="' +
                    'font-size:55px;' +
                    'margin-bottom:10px;">' +
                    '💊' +
                    '</div>' +

                    '<div style="' +
                    'background:#f8fafc;' +
                    'padding:15px;' +
                    'border-radius:12px;' +
                    'text-align:left;">' +

                    '<p class="mb-2">' +
                    '<strong>Patient</strong><br>' +
                    patient +
                    '</p>' +

                    '<p class="mb-2">' +
                    '<strong>Medication</strong><br>' +
                    medication +
                    '</p>' +

                    '<p class="mb-2">' +
                    '<strong>Dosage</strong><br>' +
                    dosage +
                    '</p>' +

                    '<p class="mb-0">' +
                    '<strong>Frequency</strong><br>' +
                    frequency +
                    '</p>' +

                    '</div>',


                icon:
                    'warning',


                showCancelButton:
                    true,


                confirmButtonText:
                    '<i class="bi bi-check-circle"></i> Yes, Give Medication',


                cancelButtonText:
                    'Cancel',


                confirmButtonColor:
                    '#16a34a',


                cancelButtonColor:
                    '#6b7280',


                reverseButtons:
                    true,


                focusCancel:
                    true

            })


            .then(function(result) {


                if(result.isConfirmed) {


                    Swal.fire({

                        title:
                            'Recording Medication...',

                        text:
                            'Please wait.',

                        allowOutsideClick:
                            false,

                        allowEscapeKey:
                            false,

                        didOpen:
                            function() {

                                Swal.showLoading();

                            }

                    });


                    form.submit();

                }

            });


        }
    );

});


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

<?php

if (
    isset($_GET['success']) &&
    $_GET['success'] == '1'
):

?>

Swal.fire({

    icon:
        'success',

    title:
        'Medication Given Successfully',

    text:
        'The medication administration has been recorded.',

    confirmButtonText:
        'OK',

    confirmButtonColor:
        '#16a34a',

    timer:
        2500,

    timerProgressBar:
        true

});


<?php endif; ?>


/* =========================================================
   ALREADY GIVEN
========================================================= */

<?php

if (
    isset($_GET['error']) &&
    $_GET['error'] == 'already_given'
):

?>

Swal.fire({

    icon:
        'info',

    title:
        'Already Given',

    text:
        'This medication has already been recorded as given.',

    confirmButtonText:
        'OK',

    confirmButtonColor:
        '#2563eb'

});


<?php endif; ?>


/* =========================================================
   INVALID
========================================================= */

<?php

if (
    isset($_GET['error']) &&
    $_GET['error'] == 'invalid'
):

?>

Swal.fire({

    icon:
        'error',

    title:
        'Invalid Medication',

    text:
        'The medication order could not be processed.',

    confirmButtonText:
        'OK'

});


<?php endif; ?>


/* =========================================================
   NOT FOUND
========================================================= */

<?php

if (
    isset($_GET['error']) &&
    $_GET['error'] == 'not_found'
):

?>

Swal.fire({

    icon:
        'error',

    title:
        'Medication Order Not Found',

    text:
        'The selected medication order does not exist.',

    confirmButtonText:
        'OK'

});


<?php endif; ?>


/* =========================================================
   DATABASE ERROR
========================================================= */

<?php

if (
    isset($_GET['error']) &&
    $_GET['error'] == 'database'
):

?>

Swal.fire({

    icon:
        'error',

    title:
        'Unable to Record Medication',

    text:
        'A database error occurred. Please try again.',

    confirmButtonText:
        'OK',

    confirmButtonColor:
        '#dc2626'

});


<?php endif; ?>

</script>


</body>

</html>