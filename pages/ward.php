<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function redirectWard($ward = 'All', $message = '', $type = 'success')
{
    $url = 'ward.php?ward=' . urlencode($ward);

    if ($message !== '') {
        $url .= '&msg=' . urlencode($message);
        $url .= '&type=' . urlencode($type);
    }

    header("Location: " . $url);
    exit();
}


/*
|--------------------------------------------------------------------------
| CURRENT WARD FILTER
|--------------------------------------------------------------------------
*/

$ward_id = $_GET['ward'] ?? 'All';


/*
|--------------------------------------------------------------------------
| DELETE BED
|--------------------------------------------------------------------------
| IMPORTANT:
| Delete is now POST instead of GET.
|--------------------------------------------------------------------------
*/

if (isset($_POST['delete_bed'])) {

    $bed_id = trim($_POST['delete_bed']);
    $current_ward = $_POST['current_ward'] ?? 'All';

    if ($bed_id === '') {
        redirectWard(
            $current_ward,
            'Invalid bed selected.',
            'error'
        );
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Check whether bed exists
        |--------------------------------------------------------------------------
        */

        $checkBed = $conn->prepare("
            SELECT
                BED_ID,
                BED_NUMBER,
                STATUS,
                WARD_ID
            FROM SYARMIMI.BED
            WHERE BED_ID = :bed_id
        ");

        $checkBed->execute([
            ':bed_id' => $bed_id
        ]);

        $bed = $checkBed->fetch(PDO::FETCH_ASSOC);

        if (!$bed) {

            redirectWard(
                $current_ward,
                'The selected bed does not exist.',
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Cannot delete occupied bed
        |--------------------------------------------------------------------------
        */

        if (strtolower(trim($bed['STATUS'])) === 'occupied') {

            redirectWard(
                $current_ward,
                'This bed is currently occupied and cannot be deleted.',
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check admission history
        |--------------------------------------------------------------------------
        |
        | Even if the bed is currently Available, an old ADMISSION record
        | may still reference this BED_ID.
        |
        | Deleting it would cause:
        | ORA-02292: integrity constraint violated
        |
        */

        $historyCheck = $conn->prepare("
            SELECT COUNT(*) AS TOTAL_HISTORY
            FROM SYARMIMI.ADMISSION
            WHERE BED_ID = :bed_id
        ");

        $historyCheck->execute([
            ':bed_id' => $bed_id
        ]);

        $history = $historyCheck->fetch(PDO::FETCH_ASSOC);

        $totalHistory = (int)($history['TOTAL_HISTORY'] ?? 0);


        /*
        |--------------------------------------------------------------------------
        | If bed has history, don't delete
        |--------------------------------------------------------------------------
        */

        if ($totalHistory > 0) {

            redirectWard(
                $current_ward,
                'This bed cannot be deleted because it has admission history.',
                'warning'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Delete bed
        |--------------------------------------------------------------------------
        */

        $delete = $conn->prepare("
            DELETE FROM SYARMIMI.BED
            WHERE BED_ID = :bed_id
        ");

        $delete->execute([
            ':bed_id' => $bed_id
        ]);


        /*
        |--------------------------------------------------------------------------
        | Redirect after successful deletion
        |--------------------------------------------------------------------------
        */

        redirectWard(
            $current_ward,
            'Bed ' . $bed['BED_NUMBER'] . ' has been deleted successfully.',
            'success'
        );


    } catch (PDOException $e) {

        redirectWard(
            $current_ward,
            'Unable to delete the bed. Please check the database relationship.',
            'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| TRANSFER PATIENT
|--------------------------------------------------------------------------
*/

if (isset($_POST['transfer'])) {

    $admission_id = trim($_POST['admission_id'] ?? '');
    $new_bed      = trim($_POST['new_bed'] ?? '');
    $current_ward = $_POST['current_ward'] ?? 'All';

    if ($admission_id === '' || $new_bed === '') {

        redirectWard(
            $current_ward,
            'Please select a new bed.',
            'warning'
        );
    }

    try {

        $conn->beginTransaction();


        /*
        |--------------------------------------------------------------------------
        | Get current admission + old bed
        |--------------------------------------------------------------------------
        */

        $stmt = $conn->prepare("
            SELECT
                ADMISSION_ID,
                BED_ID
            FROM SYARMIMI.ADMISSION
            WHERE ADMISSION_ID = :admission_id
              AND DISCHARGE_DATE IS NULL
        ");

        $stmt->execute([
            ':admission_id' => $admission_id
        ]);

        $old = $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$old) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'Active admission could not be found.',
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check new bed
        |--------------------------------------------------------------------------
        */

        $newBedCheck = $conn->prepare("
            SELECT
                BED_ID,
                BED_NUMBER,
                STATUS
            FROM SYARMIMI.BED
            WHERE BED_ID = :bed_id
            FOR UPDATE
        ");

        $newBedCheck->execute([
            ':bed_id' => $new_bed
        ]);

        $newBedData = $newBedCheck->fetch(PDO::FETCH_ASSOC);


        if (!$newBedData) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'Selected bed does not exist.',
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | New bed must be available
        |--------------------------------------------------------------------------
        */

        if ($newBedData['STATUS'] !== 'Available') {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'The selected bed is no longer available.',
                'warning'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Do not transfer to same bed
        |--------------------------------------------------------------------------
        */

        if ((string)$old['BED_ID'] === (string)$new_bed) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'The patient is already assigned to this bed.',
                'warning'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update admission
        |--------------------------------------------------------------------------
        */

        $updateAdmission = $conn->prepare("
            UPDATE SYARMIMI.ADMISSION
            SET BED_ID = :new_bed
            WHERE ADMISSION_ID = :admission_id
        ");

        $updateAdmission->execute([
            ':new_bed'      => $new_bed,
            ':admission_id' => $admission_id
        ]);


        /*
        |--------------------------------------------------------------------------
        | Free old bed
        |--------------------------------------------------------------------------
        */

        $freeOldBed = $conn->prepare("
            UPDATE SYARMIMI.BED
            SET STATUS = 'Available'
            WHERE BED_ID = :bed_id
        ");

        $freeOldBed->execute([
            ':bed_id' => $old['BED_ID']
        ]);


        /*
        |--------------------------------------------------------------------------
        | Occupy new bed
        |--------------------------------------------------------------------------
        */

        $occupyNewBed = $conn->prepare("
            UPDATE SYARMIMI.BED
            SET STATUS = 'Occupied'
            WHERE BED_ID = :bed_id
        ");

        $occupyNewBed->execute([
            ':bed_id' => $new_bed
        ]);


        $conn->commit();


        redirectWard(
            $current_ward,
            'Patient transferred successfully.',
            'success'
        );


    } catch (PDOException $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        redirectWard(
            $current_ward,
            'Transfer failed. Please try again.',
            'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| ADD BED
|--------------------------------------------------------------------------
*/

if (isset($_POST['add_bed'])) {

    $new_ward_id = trim($_POST['ward_id'] ?? '');
    $bed_no      = trim($_POST['bed_number'] ?? '');
    $current_ward = $_POST['current_ward'] ?? 'All';

    if ($new_ward_id === '' || $bed_no === '') {

        redirectWard(
            $current_ward,
            'Please select a ward and enter a bed number.',
            'warning'
        );
    }

    try {

        /*
        |--------------------------------------------------------------------------
        | Check ward exists
        |--------------------------------------------------------------------------
        */

        $wardCheck = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.WARD
            WHERE WARD_ID = :ward_id
        ");

        $wardCheck->execute([
            ':ward_id' => $new_ward_id
        ]);

        if ((int)$wardCheck->fetchColumn() === 0) {

            redirectWard(
                $current_ward,
                'Selected ward does not exist.',
                'error'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check duplicate bed number inside same ward
        |--------------------------------------------------------------------------
        */

        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.BED
            WHERE UPPER(TRIM(BED_NUMBER)) = UPPER(TRIM(:bed_number))
              AND WARD_ID = :ward_id
        ");

        $check->execute([
            ':bed_number' => $bed_no,
            ':ward_id'    => $new_ward_id
        ]);

        if ((int)$check->fetchColumn() > 0) {

            redirectWard(
                $current_ward,
                'Bed number ' . $bed_no . ' already exists in this ward.',
                'warning'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Generate next BED_ID
        |--------------------------------------------------------------------------
        */

        $idStmt = $conn->query("
            SELECT NVL(MAX(BED_ID), 0) + 1 AS NEW_ID
            FROM SYARMIMI.BED
        ");

        $idRow = $idStmt->fetch(PDO::FETCH_ASSOC);

        $newId = $idRow['NEW_ID'];


        /*
        |--------------------------------------------------------------------------
        | Insert bed
        |--------------------------------------------------------------------------
        */

        $insert = $conn->prepare("
            INSERT INTO SYARMIMI.BED
            (
                BED_ID,
                BED_NUMBER,
                STATUS,
                WARD_ID
            )
            VALUES
            (
                :bed_id,
                :bed_number,
                'Available',
                :ward_id
            )
        ");

        $insert->execute([
            ':bed_id'     => $newId,
            ':bed_number' => $bed_no,
            ':ward_id'    => $new_ward_id
        ]);


        redirectWard(
            $current_ward,
            'Bed ' . $bed_no . ' added successfully.',
            'success'
        );


    } catch (PDOException $e) {

        redirectWard(
            $current_ward,
            'Unable to add the bed. Please try again.',
            'error'
        );
    }
}


/*
|--------------------------------------------------------------------------
| WARD SUMMARY
|--------------------------------------------------------------------------
*/

$wardSummaryStmt = $conn->query("
    SELECT
        w.WARD_ID,
        w.WARD_NAME,

        COUNT(b.BED_ID) AS TOTAL_BED,

        SUM(
            CASE
                WHEN b.STATUS = 'Occupied' THEN 1
                ELSE 0
            END
        ) AS OCCUPIED,

        SUM(
            CASE
                WHEN b.STATUS = 'Available' THEN 1
                ELSE 0
            END
        ) AS AVAILABLE_BEDS

    FROM SYARMIMI.WARD w

    LEFT JOIN SYARMIMI.BED b
        ON w.WARD_ID = b.WARD_ID

    GROUP BY
        w.WARD_ID,
        w.WARD_NAME

    ORDER BY
        w.WARD_ID
");

$wardSummary = $wardSummaryStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| BED + PATIENT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.BED_ID,
        b.BED_NUMBER,
        b.STATUS,

        w.WARD_ID,
        w.WARD_NAME,

        a.ADMISSION_ID,

        p.NAME,
        p.AGE,
        p.GENDER,

        (
            SELECT COUNT(*)
            FROM SYARMIMI.ADMISSION a2
            WHERE a2.BED_ID = b.BED_ID
        ) AS TOTAL_HISTORY

    FROM SYARMIMI.BED b

    JOIN SYARMIMI.WARD w
        ON b.WARD_ID = w.WARD_ID

    LEFT JOIN SYARMIMI.ADMISSION a
        ON b.BED_ID = a.BED_ID
        AND a.DISCHARGE_DATE IS NULL

    LEFT JOIN SYARMIMI.PATIENT p
        ON a.PATIENT_ID = p.PATIENT_ID

    WHERE 1 = 1
";


$params = [];

if ($ward_id !== 'All') {

    $sql .= " AND b.WARD_ID = :ward_id";

    $params[':ward_id'] = $ward_id;
}

$sql .= "
    ORDER BY b.BED_ID
";


$bedStmt = $conn->prepare($sql);
$bedStmt->execute($params);

$result = $bedStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| WARD LIST
|--------------------------------------------------------------------------
*/

$wardsStmt = $conn->query("
    SELECT
        WARD_ID,
        WARD_NAME
    FROM SYARMIMI.WARD
    ORDER BY WARD_ID
");

$wards = $wardsStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| ALERT MESSAGE FROM REDIRECT
|--------------------------------------------------------------------------
*/

$message = $_GET['msg'] ?? '';
$messageType = $_GET['type'] ?? 'success';

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ward Layout (Admin)</title>


<!-- Bootstrap -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- Bootstrap Icons -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<!-- SweetAlert -->

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<style>

body {
    background: #eef2f7;
}


/* =========================
   WARD BOX
========================= */

.ward-box {
    background: white;
    padding: 25px;
    border-radius: 18px;
    box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}


/* =========================
   SUMMARY CARD
========================= */

.summary-card {
    border-radius: 14px;
    transition: all 0.3s ease;
    position: relative;
}

.summary-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}


/* =========================
   BED GRID
========================= */

.bed-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}


/* =========================
   BED CARD
========================= */

.bed-card {
    min-height: 170px;
    border-radius: 16px;
    padding: 15px;
    text-align: center;
    transition: 0.2s;
}

.bed-card:hover {
    transform: scale(1.02);
}

.available {
    background: #d1fae5;
}

.occupied {
    background: #fee2e2;
}


/* =========================
   DETAILS
========================= */

.details {
    margin-top: 10px;
    font-size: 14px;
}


/* =========================
   ALERT
========================= */

.low-stock-alert {
    position: relative;
    overflow: hidden;
}

.low-stock-alert::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;

    background: linear-gradient(
        45deg,
        #ff6b6b22,
        #ffd93d22
    );

    border-radius: 14px;

    animation: pulse-alert 2s ease-in-out infinite;
}


@keyframes pulse-alert {

    0%, 100% {
        opacity: 0.3;
    }

    50% {
        opacity: 0.8;
    }
}


/* =========================
   ALERT BADGE
========================= */

.alert-badge {
    position: absolute;
    top: -8px;
    right: -8px;

    animation: bounce 1s ease infinite;
}


@keyframes bounce {

    0%, 100% {
        transform: scale(1);
    }

    50% {
        transform: scale(1.1);
    }
}


/* =========================
   ADD BED BUTTON
========================= */

.add-bed-btn {
    transition: all 0.3s ease;
}

.add-bed-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 992px) {

    .bed-grid {
        grid-template-columns: repeat(3, 1fr);
    }

}

@media (max-width: 768px) {

    .bed-grid {
        grid-template-columns: repeat(2, 1fr);
    }

}

@media (max-width: 576px) {

    .bed-grid {
        grid-template-columns: 1fr;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php include("../includes/sidebar_admin.php"); ?>


<div class="flex-grow-1 p-4">


<!-- =========================================================
     PAGE TITLE
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="mb-1">
            🏥 Ward Management (Admin)
        </h3>

        <p class="text-muted mb-0">
            Manage hospital wards and beds
        </p>

    </div>

</div>


<!-- =========================================================
     ADD BED
========================================================= -->

<div class="card shadow-sm mb-4">

    <div class="card-header bg-primary text-white">

        <i class="bi bi-plus-circle"></i>

        Add New Bed

    </div>


    <div class="card-body">

        <form method="POST">

            <input
                type="hidden"
                name="current_ward"
                value="<?= htmlspecialchars($ward_id) ?>"
            >


            <div class="row">


                <!-- WARD -->

                <div class="col-md-5">

                    <label class="form-label">
                        Ward
                    </label>

                    <select
                        name="ward_id"
                        class="form-control"
                        required
                    >

                        <option value="">
                            Select Ward
                        </option>


                        <?php foreach ($wards as $w): ?>

                            <option
                                value="<?= htmlspecialchars($w['WARD_ID']) ?>"
                            >

                                <?= htmlspecialchars($w['WARD_NAME']) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- BED NUMBER -->

                <div class="col-md-5">

                    <label class="form-label">
                        Bed Number
                    </label>

                    <input
                        type="text"
                        name="bed_number"
                        class="form-control"
                        placeholder="Example: B101"
                        required
                    >

                </div>


                <!-- BUTTON -->

                <div class="col-md-2 d-flex align-items-end">

                    <button
                        type="submit"
                        name="add_bed"
                        class="btn btn-success w-100"
                    >

                        <i class="bi bi-plus-lg"></i>

                        Add Bed

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<!-- =========================================================
     WARD SUMMARY
========================================================= -->

<div class="row mb-4">


<?php foreach ($wardSummary as $w): ?>


<?php

$total = (int)($w['TOTAL_BED'] ?? 0);

$occupied = (int)($w['OCCUPIED'] ?? 0);

$available = (int)($w['AVAILABLE_BEDS'] ?? 0);

$percentage = $total > 0
    ? round(($occupied / $total) * 100)
    : 0;


/* Alert */

$alertLevel = '';

$alertMessage = '';


if ($available <= 1 && $total > 0) {

    $alertLevel = 'danger';

    $alertMessage =
        "⚠️ CRITICAL: Only {$available} bed left!";

}
elseif ($available <= 2 && $total > 0) {

    $alertLevel = 'warning';

    $alertMessage =
        "⚠️ WARNING: Only {$available} beds left!";

}
elseif ($percentage >= 80 && $total > 0) {

    $alertLevel = 'info';

    $alertMessage =
        "ℹ️ Ward is {$percentage}% full";

}

?>


<div class="col-md-3 mb-3">


<div
    class="card summary-card p-3 text-center shadow-sm
    <?= ($alertLevel === 'danger' || $alertLevel === 'warning')
        ? 'low-stock-alert'
        : '' ?>"
    style="
        border:
        <?= $alertLevel === 'danger'
            ? '3px solid #dc3545'
            : ($alertLevel === 'warning'
                ? '3px solid #ffc107'
                : 'none') ?>;
    "
>


<?php if (
    $alertLevel === 'danger' ||
    $alertLevel === 'warning'
): ?>

<div class="alert-badge">

    <span
        class="badge bg-<?= htmlspecialchars($alertLevel) ?> rounded-pill"
        style="font-size:0.8rem;"
    >

        <?= $alertLevel === 'danger'
            ? '🚨'
            : '⚠️' ?>

    </span>

</div>

<?php endif; ?>


<h6>

    🏥 <?= htmlspecialchars($w['WARD_NAME']) ?>

</h6>


<p>

    Total:
    <?= $total ?>

    <br>

    Occupied:
    <?= $occupied ?>

    <br>

    Available:

    <strong
        class="text-<?=
            $available <= 1
                ? 'danger'
                : ($available <= 2
                    ? 'warning'
                    : 'success')
        ?>"
    >

        <?= $available ?>

    </strong>

</p>


<!-- PROGRESS -->

<div
    class="progress mb-2"
    style="height:8px;"
>

    <div
        class="progress-bar bg-<?=
            $percentage >= 90
                ? 'danger'
                : ($percentage >= 70
                    ? 'warning'
                    : 'success')
        ?>"
        role="progressbar"
        style="width:<?= $percentage ?>%;"
        aria-valuenow="<?= $percentage ?>"
        aria-valuemin="0"
        aria-valuemax="100"
    >
    </div>

</div>


<?php if ($available == 0): ?>

<span class="badge bg-danger">
    FULL
</span>

<?php else: ?>

<span class="badge bg-success">
    AVAILABLE
</span>

<?php endif; ?>


<!-- ALERT -->

<?php if (
    $alertLevel === 'danger' ||
    $alertLevel === 'warning'
): ?>

<div
    class="mt-2 p-2 bg-<?= htmlspecialchars($alertLevel) ?> bg-opacity-10 rounded"
>

    <small
        class="text-<?= htmlspecialchars($alertLevel) ?> d-block mb-2"
    >

        <strong>
            <?= htmlspecialchars($alertMessage) ?>
        </strong>

    </small>


    <button
        type="button"
        class="btn btn-<?= htmlspecialchars($alertLevel) ?> btn-sm w-100 add-bed-btn"
        onclick="openAddBedModal(
            '<?= htmlspecialchars($w['WARD_ID'], ENT_QUOTES) ?>',
            '<?= htmlspecialchars($w['WARD_NAME'], ENT_QUOTES) ?>'
        )"
    >

        ➕ Add Bed Now

    </button>

</div>

<?php endif; ?>


</div>

</div>


<?php endforeach; ?>


</div>


<!-- =========================================================
     FILTER
========================================================= -->

<form
    method="GET"
    class="mb-3"
>


<div class="row">

    <div class="col-md-3">

        <label class="form-label fw-semibold">
            Select Ward
        </label>


        <select
            name="ward"
            onchange="this.form.submit()"
            class="form-control"
        >

            <option value="All">
                All Ward
            </option>


            <?php foreach ($wards as $w): ?>

            <option
                value="<?= htmlspecialchars($w['WARD_ID']) ?>"
                <?= ($ward_id == $w['WARD_ID'])
                    ? 'selected'
                    : '' ?>
            >

                <?= htmlspecialchars($w['WARD_NAME']) ?>

            </option>

            <?php endforeach; ?>

        </select>

    </div>

</div>

</form>


<!-- =========================================================
     BED GRID
========================================================= -->

<div class="ward-box">


<h5 class="mb-4">

    <?php if ($ward_id === 'All'): ?>

        🏥 All Wards

    <?php else: ?>

        <?php

        $selectedWardName = '';

        foreach ($wards as $w) {

            if ((string)$w['WARD_ID'] === (string)$ward_id) {

                $selectedWardName = $w['WARD_NAME'];

                break;
            }
        }

        ?>

        🏥 <?= htmlspecialchars($selectedWardName) ?>

    <?php endif; ?>

</h5>


<div class="bed-grid">


<?php if (empty($result)): ?>

<div class="alert alert-info w-100">

    <i class="bi bi-info-circle"></i>

    No beds found for this ward.

</div>

<?php endif; ?>


<?php foreach ($result as $row): ?>


<?php

$statusClass =
    ($row['STATUS'] === 'Available')
        ? 'available'
        : 'occupied';

?>


<div class="bed-card <?= $statusClass ?>">


<!-- BED -->

<div style="font-size:50px;">
    🛏️
</div>


<strong>

    <?= htmlspecialchars($row['BED_NUMBER'] ?? '') ?>

</strong>


<br>


<?php if ($row['STATUS'] === 'Occupied'): ?>

<span class="badge bg-danger">

    Occupied

</span>

<?php else: ?>

<span class="badge bg-success">

    Available

</span>

<?php endif; ?>


<div class="details">


<?php if ($row['STATUS'] === 'Available'): ?>


<div class="text-muted mt-2">

    No patient

</div>


<?php else: ?>


<strong>

    <?= htmlspecialchars($row['NAME'] ?? 'Unknown Patient') ?>

</strong>


<br>


Age:

<?= htmlspecialchars(
    (string)($row['AGE'] ?? 'N/A')
) ?>


<br>


Gender:

<?= htmlspecialchars(
    $row['GENDER'] ?? 'N/A'
) ?>


<hr>


<!-- =====================================================
     TRANSFER FORM
===================================================== -->

<form method="POST">


<input
    type="hidden"
    name="admission_id"
    value="<?= htmlspecialchars(
        (string)($row['ADMISSION_ID'] ?? '')
    ) ?>"
>


<input
    type="hidden"
    name="current_ward"
    value="<?= htmlspecialchars($ward_id) ?>"
>


<label class="form-label small fw-semibold">

    Move Patient To

</label>


<select
    name="new_bed"
    class="form-control form-control-sm mb-2"
    required
>

<option value="">

    Select Available Bed

</option>


<?php

$bedsStmt = $conn->query("
    SELECT
        BED_ID,
        BED_NUMBER,
        WARD_ID
    FROM SYARMIMI.BED
    WHERE STATUS = 'Available'
    ORDER BY WARD_ID, BED_ID
");

$availableBeds = $bedsStmt->fetchAll(PDO::FETCH_ASSOC);

?>


<?php foreach ($availableBeds as $b): ?>

<?php

if ((string)$b['BED_ID'] === (string)$row['BED_ID']) {
    continue;
}

?>


<option
    value="<?= htmlspecialchars($b['BED_ID']) ?>"
>

    <?= htmlspecialchars($b['BED_NUMBER']) ?>

</option>


<?php endforeach; ?>


</select>


<button
    type="submit"
    name="transfer"
    class="btn btn-warning btn-sm w-100"
>

    <i class="bi bi-arrow-left-right"></i>

    Transfer

</button>


</form>


<?php endif; ?>


<hr>


<div class="small text-muted">

    🛏️ Admission History:

    <strong>

        <?= (int)($row['TOTAL_HISTORY'] ?? 0) ?>

    </strong>

</div>


<br>


<!-- =====================================================
     DELETE BED
===================================================== -->

<form
    method="POST"
    class="delete-bed-form"
>


<input
    type="hidden"
    name="delete_bed"
    value="<?= htmlspecialchars($row['BED_ID']) ?>"
>


<input
    type="hidden"
    name="current_ward"
    value="<?= htmlspecialchars($ward_id) ?>"
>


<button
    type="submit"
    class="btn btn-danger btn-sm w-100"
    <?= ($row['STATUS'] === 'Occupied')
        ? 'disabled'
        : '' ?>
>

    <i class="bi bi-trash"></i>

    <?= ($row['STATUS'] === 'Occupied')
        ? 'Bed Occupied'
        : 'Delete Bed' ?>

</button>


</form>


</div>

</div>


<?php endforeach; ?>


</div>

</div>


</div>

</div>


<!-- =========================================================
     ADD BED MODAL
========================================================= -->

<div
    class="modal fade"
    id="addBedModal"
    tabindex="-1"
    aria-hidden="true"
>


<div class="modal-dialog">


<div class="modal-content">


<div
    class="modal-header"
    style="
        background:
        linear-gradient(
            135deg,
            #667eea 0%,
            #764ba2 100%
        );
        color:white;
    "
>


<h5 class="modal-title">

    ➕ Add New Bed

</h5>


<button
    type="button"
    class="btn-close btn-close-white"
    data-bs-dismiss="modal"
></button>


</div>


<div class="modal-body">


<form
    method="POST"
    id="addBedForm"
>


<input
    type="hidden"
    name="current_ward"
    value="<?= htmlspecialchars($ward_id) ?>"
>


<div class="mb-3">

<label class="form-label">
    Ward
</label>


<input
    type="text"
    id="modalWardName"
    class="form-control"
    readonly
    style="background-color:#f8f9fa;"
>


<input
    type="hidden"
    name="ward_id"
    id="modalWardId"
>


</div>


<div class="mb-3">

<label class="form-label">
    Bed Number
</label>


<input
    type="text"
    name="bed_number"
    class="form-control"
    placeholder="Enter bed number"
    required
>


</div>


<button
    type="submit"
    name="add_bed"
    class="btn btn-success w-100"
>

    <i class="bi bi-plus-circle"></i>

    Add Bed

</button>


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


<script>

/*
|--------------------------------------------------------------------------
| ADD BED MODAL
|--------------------------------------------------------------------------
*/

function openAddBedModal(wardId, wardName)
{
    document.getElementById('modalWardId').value = wardId;

    document.getElementById('modalWardName').value = wardName;

    const modalElement =
        document.getElementById('addBedModal');

    const modal =
        new bootstrap.Modal(modalElement);

    modal.show();
}


/*
|--------------------------------------------------------------------------
| DELETE CONFIRMATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        const deleteForms =
            document.querySelectorAll(
                '.delete-bed-form'
            );


        deleteForms.forEach(
            function(form)
            {

                form.addEventListener(
                    'submit',
                    function(event)
                    {

                        event.preventDefault();


                        Swal.fire({

                            title: 'Delete Bed?',

                            text:
                                'This action cannot be undone.',

                            icon: 'warning',

                            showCancelButton: true,

                            confirmButtonColor:
                                '#dc3545',

                            cancelButtonColor:
                                '#6c757d',

                            confirmButtonText:
                                'Yes, Delete Bed',

                            cancelButtonText:
                                'Cancel'

                        }).then(
                            function(result)
                            {

                                if (
                                    result.isConfirmed
                                ) {

                                    form.submit();

                                }

                            }
                        );

                    }
                );

            }
        );


        /*
        |--------------------------------------------------------------------------
        | SHOW REDIRECT MESSAGE
        |--------------------------------------------------------------------------
        */

        <?php if ($message !== ''): ?>

        Swal.fire({

            icon:
                '<?= htmlspecialchars($messageType, ENT_QUOTES) ?>',

            title:
                <?=
                $messageType === 'success'
                    ? "'Success'"
                    : (
                        $messageType === 'warning'
                            ? "'Warning'"
                            : "'Error'"
                    )
                ?>,

            text:
                <?= json_encode($message) ?>,

            confirmButtonColor:
                '<?= $messageType === 'success'
                    ? '#198754'
                    : (
                        $messageType === 'warning'
                            ? '#ffc107'
                            : '#dc3545'
                    ) ?>'

        });

        <?php endif; ?>


        /*
        |--------------------------------------------------------------------------
        | LOW BED STOCK ALERT
        |--------------------------------------------------------------------------
        */

        <?php foreach ($wardSummary as $w): ?>

        <?php

        $availableAlert =
            (int)($w['AVAILABLE_BEDS'] ?? 0);

        $totalAlert =
            (int)($w['TOTAL_BED'] ?? 0);

        ?>

        <?php if (
            $availableAlert <= 1 &&
            $totalAlert > 0
        ): ?>


        setTimeout(
            function()
            {

                Swal.fire({

                    icon: 'warning',

                    title:
                        '⚠️ Low Bed Stock Alert!',

                    html:
                        'Ward <strong>' +
                        <?= json_encode($w['WARD_NAME']) ?> +
                        '</strong> has only <strong>' +
                        <?= $availableAlert ?> +
                        '</strong> available bed left.',

                    confirmButtonText:
                        'Add Bed Now',

                    confirmButtonColor:
                        '#dc3545',

                    showCancelButton:
                        true,

                    cancelButtonText:
                        'Dismiss'

                }).then(
                    function(result)
                    {

                        if (
                            result.isConfirmed
                        ) {

                            openAddBedModal(

                                <?= json_encode(
                                    (string)$w['WARD_ID']
                                ) ?>,

                                <?= json_encode(
                                    $w['WARD_NAME']
                                ) ?>

                            );

                        }

                    }
                );

            },
            1000
        );


        <?php endif; ?>

        <?php endforeach; ?>

    }
);

</script>


</body>

</html>