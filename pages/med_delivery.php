<?php

session_start();
include("../config/config.php");

// ============================================================
// ROLE CHECK
// ============================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}


// ============================================================
// HANDLE NURSE COLLECTED ACTION
// ============================================================
// Workflow:
//
// Pharmacy Preparation
// Ready For Nurse Pickup
//          ↓
// Nurse collects medication
//          ↓
// Pharmacist confirms
//          ↓
// Collected
//
// IMPORTANT:
// We use PREP_ID here, NOT MEDORDER_ID.
// ============================================================

if (isset($_GET['nurse_collected'])) {

    $prepId = (int) $_GET['nurse_collected'];

    $staffId = $_SESSION['user_id'] ?? null;

    try {

        // ====================================================
        // 1. FIND THE PREPARATION RECORD
        // ====================================================
        //
        // Use PREP_ID because every pharmacy preparation
        // record has its own PREP_ID.
        //
        // We also make sure:
        // - Medication belongs to an admission
        // - Status is Ready For Nurse Pickup
        //
        // ====================================================

        $checkStmt = $conn->prepare("
            SELECT
                pp.PREP_ID,
                pp.MEDORDER_ID,
                pp.STATUS,
                pp.ACCOUNT_ID,
                mo.ADMISSION_ID
            FROM SYARMIMI.PHARMACY_PREPARATION pp
            INNER JOIN SYARMIMI.MEDICATION_ORDER mo
                ON pp.MEDORDER_ID = mo.MEDORDER_ID
            WHERE pp.PREP_ID = :prep_id
              AND mo.ADMISSION_ID IS NOT NULL
              AND pp.STATUS = 'Ready For Nurse Pickup'
        ");

        $checkStmt->execute([
            ':prep_id' => $prepId
        ]);

        $preparation = $checkStmt->fetch(PDO::FETCH_ASSOC);


        // ====================================================
        // 2. CHECK WHETHER RECORD EXISTS
        // ====================================================

        if (!$preparation) {

            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Error</title>

                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>

            <body>

            <script>

            Swal.fire({
                icon: 'warning',
                title: 'Medication Not Available',
                text: 'This medication is not available for nurse pickup or has already been collected.',
                confirmButtonColor: '#0dcaf0'
            }).then(function() {
                window.location.href = 'med_delivery.php';
            });

            </script>

            </body>
            </html>
            ";

            exit();
        }


        // ====================================================
        // GET MEDORDER_ID
        // ====================================================

        $medOrderId = $preparation['MEDORDER_ID'];


        // ====================================================
        // START TRANSACTION
        // ====================================================

        $conn->beginTransaction();


        // ====================================================
        // 3. UPDATE PHARMACY PREPARATION
        // ====================================================
        //
        // Ready For Nurse Pickup
        //          ↓
        //       Collected
        //
        // IMPORTANT:
        // Update using PREP_ID.
        // ====================================================

        $updateStmt = $conn->prepare("
            UPDATE SYARMIMI.PHARMACY_PREPARATION
            SET STATUS = 'Collected'
            WHERE PREP_ID = :prep_id
              AND STATUS = 'Ready For Nurse Pickup'
        ");

        $updateStmt->execute([
            ':prep_id' => $prepId
        ]);


        // ====================================================
        // CHECK UPDATE
        // ====================================================

        if ($updateStmt->rowCount() === 0) {

            $conn->rollBack();

            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
            </head>

            <body>

            <script>

            Swal.fire({
                icon: 'warning',
                title: 'Unable to Update',
                text: 'The medication status could not be updated. It may have already been collected.',
                confirmButtonColor: '#0dcaf0'
            }).then(function() {
                window.location.href = 'med_delivery.php';
            });

            </script>

            </body>
            </html>
            ";

            exit();
        }


        // ====================================================
        // 4. CHECK MEDICATION DELIVERY RECORD
        // ====================================================
        //
        // One delivery record per medication order.
        //
        // ====================================================

        $checkDeliveryStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.MEDICATION_DELIVERY
            WHERE MEDORDER_ID = :medorder_id
        ");

        $checkDeliveryStmt->execute([
            ':medorder_id' => $medOrderId
        ]);

        $deliveryExists = (int) $checkDeliveryStmt->fetchColumn();


        // ====================================================
        // 5. INSERT DELIVERY RECORD
        // ====================================================

        if ($deliveryExists === 0) {

            $insertDeliveryStmt = $conn->prepare("
                INSERT INTO SYARMIMI.MEDICATION_DELIVERY
                (
                    MEDDELIVERY_ID,
                    DELIVERY_TIME,
                    STATUS,
                    ACCOUNT_ID,
                    MEDORDER_ID
                )
                VALUES
                (
                    (
                        SELECT NVL(MAX(MEDDELIVERY_ID), 0) + 1
                        FROM SYARMIMI.MEDICATION_DELIVERY
                    ),
                    SYSDATE,
                    'Delivered',
                    :account_id,
                    :medorder_id
                )
            ");

            $insertDeliveryStmt->execute([
                ':account_id'  => $staffId,
                ':medorder_id' => $medOrderId
            ]);
        }


        // ====================================================
        // 6. COMMIT
        // ====================================================

        $conn->commit();


        // ====================================================
        // 7. REDIRECT
        // ====================================================

        header("Location: med_delivery.php?success=1");
        exit();


    } catch (PDOException $e) {

        // ====================================================
        // ROLLBACK
        // ====================================================

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        die("Error updating medication delivery: " . $e->getMessage());
    }
}


// ============================================================
// COUNT READY MEDICATIONS
// ============================================================

$deliveryCountStmt = $conn->query("
    SELECT COUNT(*)
    FROM SYARMIMI.PHARMACY_PREPARATION pp

    INNER JOIN SYARMIMI.MEDICATION_ORDER mo
        ON pp.MEDORDER_ID = mo.MEDORDER_ID

    WHERE mo.ADMISSION_ID IS NOT NULL
      AND pp.STATUS = 'Ready For Nurse Pickup'
");

$deliveryCount = (int) $deliveryCountStmt->fetchColumn();


// ============================================================
// FETCH MEDICATIONS READY FOR NURSE PICKUP
// ============================================================
//
// Only:
// - Admission medication
// - Ready For Nurse Pickup
//
// ============================================================

$sql = "

SELECT

    pp.PREP_ID,

    pp.MEDORDER_ID,

    p.NAME AS PATIENT_NAME,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,

    pp.STATUS,

    TO_CHAR(
        pp.PREPARED_TIME,
        'DD-Mon-YYYY'
    ) AS PREPARED_DATE,

    NVL(
        w.WARD_NAME,
        '-'
    ) AS WARD_NAME,

    NVL(
        b.BED_NUMBER,
        '-'
    ) AS BED_NUMBER

FROM SYARMIMI.PHARMACY_PREPARATION pp

INNER JOIN SYARMIMI.MEDICATION_ORDER mo
    ON pp.MEDORDER_ID = mo.MEDORDER_ID

INNER JOIN SYARMIMI.PATIENT p
    ON mo.PATIENT_ID = p.PATIENT_ID

INNER JOIN SYARMIMI.MEDICATION m
    ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.ADMISSION a
    ON mo.ADMISSION_ID = a.ADMISSION_ID

LEFT JOIN SYARMIMI.BED b
    ON a.BED_ID = b.BED_ID

LEFT JOIN SYARMIMI.WARD w
    ON b.WARD_ID = w.WARD_ID

WHERE mo.ADMISSION_ID IS NOT NULL

  AND pp.STATUS = 'Ready For Nurse Pickup'

ORDER BY pp.PREP_ID DESC

";

$stmt = $conn->query($sql);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Medication Delivery</title>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- =========================================================
     BOOTSTRAP ICONS
========================================================= -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<!-- =========================================================
     DATATABLES
========================================================= -->

<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
>


<!-- =========================================================
     SWEETALERT
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


<style>

body {

    background: #f4f6f9;

}


.main-content {

    min-height: 100vh;

}


.card {

    border-radius: 12px;

}


.table td {

    vertical-align: middle;

}


.table th {

    white-space: nowrap;

}


.badge {

    padding: 8px 12px;

    border-radius: 20px;

    font-size: 12px;

    white-space: nowrap;

}


.page-title {

    color: #172033;

}


.table thead th {

    background: #212529;

    color: white;

    border: none;

}


.dataTables_wrapper
.dataTables_filter {

    display: none;

}


.dataTables_wrapper
.dataTables_length {

    margin-bottom: 10px;

}


</style>


<!-- =========================================================
     SUCCESS MESSAGE
========================================================= -->

<?php if (isset($_GET['success'])): ?>

<script>

document.addEventListener('DOMContentLoaded', function() {

    Swal.fire({

        icon: 'success',

        title: 'Nurse Collected',

        text: 'The medication has been marked as collected by the nurse.',

        confirmButtonColor: '#198754'

    });

});

</script>

<?php endif; ?>


</head>


<body>


<div class="d-flex">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include("../includes/sidebar_pharma.php"); ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content flex-grow-1 p-4">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

    <div>

        <h3 class="fw-bold mb-0 page-title">

            🚚 Medication Delivery

        </h3>

        <small class="text-muted">

            Confirm medication collected by nurse

        </small>

    </div>


    <div>

        <span class="badge bg-info fs-6 p-2">

            Ready:
            <?= htmlspecialchars($deliveryCount) ?>

        </span>

    </div>

</div>


<!-- =========================================================
     INFORMATION ALERT
========================================================= -->

<?php if ($deliveryCount > 0): ?>

<div class="alert alert-info">

    👩‍⚕️

    <strong>
        <?= htmlspecialchars($deliveryCount) ?>
    </strong>

    medication(s) are ready for nurse pickup today.

</div>

<?php else: ?>

<div class="alert alert-secondary">

    No medication is currently waiting for nurse pickup.

</div>

<?php endif; ?>


<!-- =========================================================
     TABLE CARD
========================================================= -->

<div class="card p-3 shadow-sm mt-3">


<h5 class="mb-3">

    Medication Waiting for Nurse Pickup

</h5>


<!-- =========================================================
     FILTERS
========================================================= -->

<div class="row mb-3">


    <!-- SEARCH -->

    <div class="col-md-4">

        <input
            type="text"
            id="searchInput"
            class="form-control"
            placeholder="🔍 Search patient or medication"
        >

    </div>


    <!-- SORT -->

    <div class="col-md-3">

        <select
            id="sortFilter"
            class="form-select"
        >

            <option value="desc">

                Newest First

            </option>

            <option value="asc">

                Oldest First

            </option>

        </select>

    </div>


</div>


<!-- =========================================================
     TABLE
========================================================= -->

<div class="table-responsive">

<table
    id="deliveryTable"
    class="table table-hover align-middle"
>

<thead>

<tr>

    <th>Prep ID</th>

    <th>Patient</th>

    <th>Location</th>

    <th>Medication</th>

    <th>Dosage</th>

    <th>Frequency</th>

    <th>Prepared</th>

    <th>Status</th>

    <th>Action</th>

</tr>

</thead>


<tbody>


<?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>


<tr>


<!-- =====================================================
     PREPARATION ID
===================================================== -->

<td>

    <?= htmlspecialchars($row['PREP_ID']) ?>

</td>


<!-- =====================================================
     PATIENT
===================================================== -->

<td>

    <strong>

        <?= htmlspecialchars($row['PATIENT_NAME']) ?>

    </strong>

</td>


<!-- =====================================================
     LOCATION
===================================================== -->

<td>

    <?= htmlspecialchars($row['WARD_NAME']) ?>

    <?php if ($row['BED_NUMBER'] !== '-'): ?>

        <br>

        <small class="text-muted">

            Bed
            <?= htmlspecialchars($row['BED_NUMBER']) ?>

        </small>

    <?php endif; ?>

</td>


<!-- =====================================================
     MEDICATION
===================================================== -->

<td>

    <?= htmlspecialchars($row['MEDICATION_NAME']) ?>

</td>


<!-- =====================================================
     DOSAGE
===================================================== -->

<td>

    <?= htmlspecialchars($row['DOSAGE']) ?>

</td>


<!-- =====================================================
     FREQUENCY
===================================================== -->

<td>

    <?= htmlspecialchars($row['FREQUENCY']) ?>

</td>


<!-- =====================================================
     PREPARED DATE
===================================================== -->

<td>

    <?= htmlspecialchars($row['PREPARED_DATE']) ?>

</td>


<!-- =====================================================
     STATUS
===================================================== -->

<td>

    <span class="badge bg-info">

        Ready For Nurse Pickup

    </span>

</td>


<!-- =====================================================
     ACTION
===================================================== -->

<td>

    <a
        href="med_delivery.php?nurse_collected=<?= urlencode($row['PREP_ID']) ?>"
        class="btn btn-success btn-sm nurseCollectedBtn"
    >

        <i class="bi bi-check-circle"></i>

        Nurse Collected

    </a>

</td>


</tr>


<?php endwhile; ?>


</tbody>

</table>

</div>


</div>


</div>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"
></script>


<script>


// ============================================================
// DATATABLE
// ============================================================

$(document).ready(function() {


    const table = $('#deliveryTable').DataTable({

        pageLength: 10,

        lengthMenu: [

            [10, 25, 50, 100],

            [10, 25, 50, 100]

        ],

        order: [

            [0, 'desc']

        ],

        dom: 't'

    });


    // ========================================================
    // SEARCH
    // ========================================================

    $('#searchInput').on('keyup', function() {

        table
            .search(this.value)
            .draw();

    });


    // ========================================================
    // SORT
    // ========================================================

    $('#sortFilter').on('change', function() {

        table
            .order([
                [0, this.value]
            ])
            .draw();

    });


    // ========================================================
    // NURSE COLLECTED CONFIRMATION
    // ========================================================

    $(document).on(
        'click',
        '.nurseCollectedBtn',
        function(e) {

            e.preventDefault();


            const url = this.href;


            Swal.fire({

                title: 'Confirm Nurse Collection',

                html: `

                    <div class="text-start">

                        <p>
                            Has the nurse collected this
                            medication from the pharmacy?
                        </p>

                        <p class="mb-0">

                            <strong>
                                The medication will be marked
                                as Collected.
                            </strong>

                        </p>

                    </div>

                `,

                icon: 'question',

                showCancelButton: true,

                confirmButtonColor: '#198754',

                cancelButtonColor: '#6c757d',

                confirmButtonText:
                    'Yes, Nurse Collected',

                cancelButtonText:
                    'Cancel'

            }).then(function(result) {

                if (result.isConfirmed) {

                    window.location.href = url;

                }

            });

        }

    );

});


</script>


</body>

</html>