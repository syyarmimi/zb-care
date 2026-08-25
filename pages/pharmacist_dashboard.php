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
// 1. TOTAL MEDICATION ORDERS
// ============================================================
$stmt1 = $conn->query("
    SELECT COUNT(*) AS TOTAL
    FROM SYARMIMI.MEDICATION_ORDER
");

$row1 = $stmt1->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 2. TOTAL PREPARATION
// ============================================================
// Count unique medication orders that have been prepared.
// ============================================================
$stmt2 = $conn->query("
    SELECT COUNT(DISTINCT MEDORDER_ID) AS TOTAL
    FROM SYARMIMI.PHARMACY_PREPARATION
");

$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 3. TOTAL DELIVERY
// ============================================================
// Count unique medication orders that have delivery records.
// ============================================================
$stmt3 = $conn->query("
    SELECT COUNT(DISTINCT MEDORDER_ID) AS TOTAL
    FROM SYARMIMI.MEDICATION_DELIVERY
");

$row3 = $stmt3->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 4. TOTAL PENDING PREPARATION
// ============================================================
// Pending means:
// Medication Order exists
// BUT there is NO Pharmacy Preparation record.
//
// This uses the SAME logic as pharmacy_preparation.php.
// ============================================================
$stmt4 = $conn->query("
    SELECT COUNT(*) AS TOTAL
    FROM SYARMIMI.MEDICATION_ORDER mo

    INNER JOIN SYARMIMI.PATIENT p
        ON mo.PATIENT_ID = p.PATIENT_ID

    INNER JOIN SYARMIMI.MEDICATION m
        ON mo.MEDICATION_ID = m.MEDICATION_ID

    LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp
        ON mo.MEDORDER_ID = pp.MEDORDER_ID

    WHERE pp.MEDORDER_ID IS NULL
");

$row4 = $stmt4->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 5. LOW STOCK
// ============================================================
$stmt5 = $conn->query("
    SELECT COUNT(*) AS TOTAL
    FROM SYARMIMI.MEDICATION
    WHERE STOCK <= 10
");

$row5 = $stmt5->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 6. TODAY DELIVERY
// ============================================================
$stmt6 = $conn->query("
    SELECT COUNT(*) AS TOTAL
    FROM SYARMIMI.MEDICATION_DELIVERY
    WHERE TRUNC(DELIVERY_TIME) = TRUNC(SYSDATE)
");

$row6 = $stmt6->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 7. RECENT MEDICATION ORDERS
// ============================================================
$sql = "
    SELECT
        mo.MEDORDER_ID,
        p.NAME AS PATIENT_NAME,
        mo.ADMISSION_ID,
        m.MEDICATION_NAME,
        mo.DOSAGE,
        mo.FREQUENCY

    FROM SYARMIMI.MEDICATION_ORDER mo

    INNER JOIN SYARMIMI.PATIENT p
        ON mo.PATIENT_ID = p.PATIENT_ID

    INNER JOIN SYARMIMI.MEDICATION m
        ON mo.MEDICATION_ID = m.MEDICATION_ID

    ORDER BY mo.MEDORDER_ID DESC
";

$stmt = $conn->query($sql);


// ============================================================
// 8. PENDING MEDICATION ORDERS
// ============================================================
// EXACT SAME definition as pharmacy_preparation.php.
//
// Pending = medication order WITHOUT a preparation record.
// ============================================================
$pending = $conn->query("
    SELECT
        mo.MEDORDER_ID,
        p.NAME AS PATIENT_NAME,
        m.MEDICATION_NAME,
        mo.DOSAGE,
        mo.FREQUENCY

    FROM SYARMIMI.MEDICATION_ORDER mo

    INNER JOIN SYARMIMI.PATIENT p
        ON mo.PATIENT_ID = p.PATIENT_ID

    INNER JOIN SYARMIMI.MEDICATION m
        ON mo.MEDICATION_ID = m.MEDICATION_ID

    LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp
        ON mo.MEDORDER_ID = pp.MEDORDER_ID

    WHERE pp.MEDORDER_ID IS NULL

    ORDER BY mo.MEDORDER_ID DESC
");


// ============================================================
// 9. LOW STOCK MEDICATION
// ============================================================
$stock = $conn->query("
    SELECT
        MEDICATION_NAME,
        DOSAGE_FORM,
        STOCK

    FROM SYARMIMI.MEDICATION

    WHERE STOCK <= 10

    ORDER BY STOCK ASC
");


// ============================================================
// SAFE VALUES
// ============================================================
$totalOrders    = (int)($row1['TOTAL'] ?? 0);
$totalPrepared  = (int)($row2['TOTAL'] ?? 0);
$totalDelivered = (int)($row3['TOTAL'] ?? 0);
$totalPending   = (int)($row4['TOTAL'] ?? 0);
$totalLowStock  = (int)($row5['TOTAL'] ?? 0);
$todayDelivery  = (int)($row6['TOTAL'] ?? 0);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Pharmacist Dashboard</title>


    <!-- =========================================================
         BOOTSTRAP
    ========================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- =========================================================
         BOOTSTRAP ICONS
    ========================================================== -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- =========================================================
         DATATABLES
    ========================================================== -->

    <link
        rel="stylesheet"
        href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
    >


    <style>

        body {
            background: #f4f6f9;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .quick-action {
            transition: 0.2s;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            opacity: 0.95;
        }

        .stat-card {
            transition: 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .table td {
            vertical-align: middle;
        }

        .table th {
            white-space: nowrap;
        }

    </style>

</head>


<body>


<div class="d-flex">


    <!-- =========================================================
         SIDEBAR
    ========================================================== -->

    <?php include("../includes/sidebar_pharma.php"); ?>


    <!-- =========================================================
         MAIN CONTENT
    ========================================================== -->

    <div class="flex-grow-1 p-4">


        <!-- =====================================================
             HEADER
        ====================================================== -->

        <h3 class="mb-4">
            💊 Pharmacist Dashboard
        </h3>


        <!-- =====================================================
             PENDING ALERT
        ====================================================== -->

        <?php if ($totalPending > 0): ?>

            <div class="alert alert-warning">

                🔔

                <strong>
                    <?= $totalPending ?>
                </strong>

                medication(s) pending preparation.

            </div>

        <?php else: ?>

            <div class="alert alert-success">

                ✅

                No medication is currently pending preparation.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             LOW STOCK ALERT
        ====================================================== -->

        <?php if ($totalLowStock > 0): ?>

            <div class="alert alert-danger">

                ⚠

                <strong>
                    <?= $totalLowStock ?>
                </strong>

                medication(s) low stock.

            </div>

        <?php endif; ?>


        <!-- =====================================================
             DASHBOARD CARDS
        ====================================================== -->

        <div class="row">


            <!-- =================================================
                 TOTAL ORDERS
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-capsule fs-2 text-primary"></i>

                    <h6 class="mt-2">
                        Orders
                    </h6>

                    <h2>
                        <?= $totalOrders ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 PENDING
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-hourglass-split fs-2 text-warning"></i>

                    <h6 class="mt-2">
                        Pending
                    </h6>

                    <h2>
                        <?= $totalPending ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 PREPARED
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-box-seam fs-2 text-info"></i>

                    <h6 class="mt-2">
                        Prepared
                    </h6>

                    <h2>
                        <?= $totalPrepared ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 DELIVERED
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-truck fs-2 text-success"></i>

                    <h6 class="mt-2">
                        Delivered
                    </h6>

                    <h2>
                        <?= $totalDelivered ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 TODAY DELIVERY
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-calendar-check fs-2 text-success"></i>

                    <h6 class="mt-2">
                        Today Delivery
                    </h6>

                    <h2>
                        <?= $todayDelivery ?>
                    </h2>

                </div>

            </div>


            <!-- =================================================
                 LOW STOCK
            ================================================== -->

            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">

                <div class="card stat-card p-3 text-center h-100">

                    <i class="bi bi-exclamation-triangle fs-2 text-danger"></i>

                    <h6 class="mt-2">
                        Low Stock
                    </h6>

                    <h2>
                        <?= $totalLowStock ?>
                    </h2>

                </div>

            </div>

        </div>


        <!-- =====================================================
             QUICK ACTIONS
        ====================================================== -->

        <div class="card shadow p-3 mt-4">


            <div class="card-header bg-primary text-white rounded-top">

                <h5 class="mb-0">

                    <i class="bi bi-lightning-charge"></i>

                    Quick Actions

                </h5>

            </div>


            <div class="card-body">

                <div class="row g-3">


                    <!-- MEDICATION ORDERS -->

                    <div class="col-md-3">

                        <a
                            href="medication_order.php"
                            class="btn btn-primary w-100 py-3 quick-action"
                        >

                            <i class="bi bi-capsule fs-4 d-block"></i>

                            Medication Orders

                        </a>

                    </div>


                    <!-- PREPARATION -->

                    <div class="col-md-3">

                        <a
                            href="pharmacy_preparation.php"
                            class="btn btn-warning w-100 py-3 quick-action"
                        >

                            <i class="bi bi-box-seam fs-4 d-block"></i>

                            Preparation

                        </a>

                    </div>


                    <!-- DELIVERY -->

                    <div class="col-md-3">

                        <a
                            href="medication_delivery.php"
                            class="btn btn-success w-100 py-3 quick-action"
                        >

                            <i class="bi bi-truck fs-4 d-block"></i>

                            Delivery

                        </a>

                    </div>


                    <!-- INVENTORY -->

                    <div class="col-md-3">

                        <a
                            href="pharmacy_inventory.php"
                            class="btn btn-danger w-100 py-3 quick-action"
                        >

                            <i class="bi bi-archive fs-4 d-block"></i>

                            Inventory

                        </a>

                    </div>


                </div>

            </div>

        </div>


        <!-- =====================================================
             RECENT MEDICATION ORDERS
        ====================================================== -->

        <div class="card mt-4 p-3 shadow">


            <h5 class="mb-3">
                Recent Medication Orders
            </h5>


            <div class="table-responsive">

                <table
                    id="recentTable"
                    class="table table-striped table-hover"
                >

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Patient</th>

                            <th>Admission</th>

                            <th>Medication</th>

                            <th>Dosage</th>

                            <th>Frequency</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while ($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $row['MEDORDER_ID']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['PATIENT_NAME']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['ADMISSION_ID'] ?? '-'
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['MEDICATION_NAME']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['DOSAGE']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $row['FREQUENCY']
                                ) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =====================================================
             PENDING MEDICATION ORDERS
        ====================================================== -->

        <div class="card mt-4 p-3 shadow">


            <!-- HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-3">

                <h5 class="mb-0">
                    Pending Medication Orders
                </h5>

                <span class="badge bg-warning text-dark">

                    <?= $totalPending ?>

                    Pending

                </span>

            </div>


            <!-- TABLE -->

            <div class="table-responsive">

                <table
                    id="pendingTable"
                    class="table table-striped table-hover"
                >

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Patient</th>

                            <th>Medication</th>

                            <th>Dosage</th>

                            <th>Frequency</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while ($p = $pending->fetch(PDO::FETCH_ASSOC)): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $p['MEDORDER_ID']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $p['PATIENT_NAME']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $p['MEDICATION_NAME']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $p['DOSAGE']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $p['FREQUENCY']
                                ) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- =====================================================
             LOW STOCK MEDICATION
        ====================================================== -->

        <div class="card mt-4 p-3 shadow">


            <h5 class="mb-3">
                Low Stock Medication
            </h5>


            <div class="table-responsive">

                <table
                    id="stockTable"
                    class="table table-striped table-hover"
                >

                    <thead class="table-dark">

                        <tr>

                            <th>Medication</th>

                            <th>Form</th>

                            <th>Stock</th>

                        </tr>

                    </thead>


                    <tbody>

                    <?php while ($s = $stock->fetch(PDO::FETCH_ASSOC)): ?>

                        <tr>

                            <td>
                                <?= htmlspecialchars(
                                    $s['MEDICATION_NAME']
                                ) ?>
                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $s['DOSAGE_FORM']
                                ) ?>
                            </td>

                            <td>

                                <?php if ((int)$s['STOCK'] <= 5): ?>

                                    <span class="badge bg-danger">

                                        <?= htmlspecialchars(
                                            $s['STOCK']
                                        ) ?>

                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-warning text-dark">

                                        <?= htmlspecialchars(
                                            $s['STOCK']
                                        ) ?>

                                    </span>

                                <?php endif; ?>

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
     JQUERY
========================================================== -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js">
</script>


<!-- =========================================================
     DATATABLES
========================================================== -->

<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>

<script
    src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js">
</script>


<!-- =========================================================
     DATATABLE INITIALIZATION
========================================================== -->

<script>

$(document).ready(function () {


    // ========================================================
    // RECENT ORDERS TABLE
    // ========================================================

    if (!$.fn.DataTable.isDataTable('#recentTable')) {

        $('#recentTable').DataTable({

            pageLength: 10,

            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],

            order: [
                [0, 'desc']
            ]

        });

    }


    // ========================================================
    // PENDING ORDERS TABLE
    // ========================================================

    if (!$.fn.DataTable.isDataTable('#pendingTable')) {

        $('#pendingTable').DataTable({

            pageLength: 10,

            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],

            order: [
                [0, 'desc']
            ]

        });

    }


    // ========================================================
    // LOW STOCK TABLE
    // ========================================================

    if (!$.fn.DataTable.isDataTable('#stockTable')) {

        $('#stockTable').DataTable({

            pageLength: 10,

            lengthMenu: [
                [10, 25, 50, 100],
                [10, 25, 50, 100]
            ],

            order: [
                [2, 'asc']
            ]

        });

    }

});

</script>


</body>

</html>