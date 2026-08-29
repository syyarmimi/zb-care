<?php

session_start();

include("../config/config.php");


// ============================================================
// ROLE CHECK
// ============================================================

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pharmacist'
) {

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

$stmt2 = $conn->query("
    SELECT COUNT(DISTINCT MEDORDER_ID) AS TOTAL
    FROM SYARMIMI.PHARMACY_PREPARATION
");

$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 3. TOTAL DELIVERY
// ============================================================

$stmt3 = $conn->query("
    SELECT COUNT(DISTINCT MEDORDER_ID) AS TOTAL
    FROM SYARMIMI.MEDICATION_DELIVERY
");

$row3 = $stmt3->fetch(PDO::FETCH_ASSOC);


// ============================================================
// 4. TOTAL PENDING PREPARATION
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

<title>
Pharmacist Dashboard
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:#f5f7fa;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;

    color:#1f2937;
}


/* =========================================================
   CONTENT
========================================================= */

.content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:28px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    margin-bottom:24px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:26px;

    font-weight:700;
}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;
}


/* =========================================================
   ALERT
========================================================= */

.dashboard-alert{

    display:flex;

    align-items:flex-start;

    gap:10px;

    padding:13px 15px;

    margin-bottom:12px;

    border-radius:10px;

    font-size:12px;
}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card{

    height:100%;

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;

    transition:.2s;
}


.stat-card:hover{

    border-color:#d7dce3;

    transform:translateY(-2px);
}


.stat-card-content{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:15px;
}


.stat-label{

    color:#8a94a3;

    font-size:12px;

    font-weight:600;
}


.stat-number{

    margin-top:5px;

    color:#111827;

    font-size:27px;

    line-height:1;

    font-weight:700;
}


.stat-description{

    margin-top:7px;

    margin-bottom:0;

    color:#94a3b8;

    font-size:10px;
}


.stat-icon{

    width:39px;

    height:39px;

    min-width:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:17px;
}


.icon-orders{

    background:#eff6ff;

    color:#2563eb;
}


.icon-pending{

    background:#fff7ed;

    color:#ea580c;
}


.icon-prepared{

    background:#f0fdfa;

    color:#0f766e;
}


.icon-delivered{

    background:#ecfdf5;

    color:#15803d;
}


.icon-today{

    background:#f0fdf4;

    color:#16a34a;
}


.icon-stock{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   SECTION CARD
========================================================= */

.section-card{

    margin-top:22px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;
}


.section-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:16px;
}


.section-title{

    margin:0;

    color:#1f2937;

    font-size:16px;

    font-weight:650;
}


.section-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   QUICK ACTIONS
========================================================= */

.quick-grid{

    display:grid;

    grid-template-columns:
        repeat(4,minmax(0,1fr));

    gap:12px;
}


.quick-action{

    min-height:100px;

    display:flex;

    flex-direction:column;

    align-items:flex-start;

    justify-content:space-between;

    padding:16px;

    border:1px solid #e7eaee;

    border-radius:10px;

    background:#f8fafc;

    color:#334155;

    text-decoration:none;

    transition:.2s;
}


.quick-action:hover{

    background:#fff;

    border-color:#cbd5e1;

    color:#111827;

    transform:translateY(-2px);
}


.quick-icon{

    width:35px;

    height:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:8px;

    font-size:15px;
}


.quick-blue{

    background:#eff6ff;

    color:#2563eb;
}


.quick-orange{

    background:#fff7ed;

    color:#ea580c;
}


.quick-green{

    background:#ecfdf5;

    color:#15803d;
}


.quick-red{

    background:#fff1f2;

    color:#dc2626;
}


.quick-title{

    margin-top:14px;

    font-size:12px;

    font-weight:650;
}


.quick-text{

    margin-top:3px;

    color:#94a3b8;

    font-size:10px;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{

    overflow-x:auto;

    border:1px solid #edf0f3;

    border-radius:9px;
}


.table{

    width:100% !important;

    margin-bottom:0 !important;

    vertical-align:middle;
}


.table thead th{

    padding:11px 12px !important;

    background:#f8fafc !important;

    border-bottom:1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:650;

    letter-spacing:.3px;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:12px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.patient-name{

    color:#1f2937;

    font-weight:650;
}


/* =========================================================
   BADGES
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:5px 8px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;
}


.badge-pending{

    background:#fff7ed;

    color:#c2410c;
}


.badge-danger-stock{

    background:#fff1f2;

    color:#dc2626;
}


.badge-warning-stock{

    background:#fff7ed;

    color:#c2410c;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_wrapper .dataTables_filter{

    margin-bottom:12px;

    color:#64748b;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_filter
input{

    min-height:36px;

    margin-left:7px;

    padding:6px 10px;

    border:1px solid #dfe3e8;

    border-radius:7px;

    outline:none;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_filter
input:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.06);
}


.dataTables_wrapper
.dataTables_length{

    margin-bottom:12px;

    color:#64748b;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_length
select{

    min-height:32px;

    padding:4px 25px 4px 7px;

    border:1px solid #dfe3e8;

    border-radius:6px;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_info{

    padding-top:16px !important;

    color:#94a3b8 !important;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_paginate{

    padding-top:10px !important;
}


.page-link{

    min-width:33px;

    height:33px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:1px solid #e2e8f0;

    border-radius:6px !important;

    color:#64748b;

    font-size:11px;
}


.page-item.active .page-link{

    background:#2563eb;

    border-color:#2563eb;

    color:#fff;
}


.page-link:focus{

    box-shadow:none;
}


/* =========================================================
   EMPTY
========================================================= */

.dataTables_empty{

    padding:32px !important;

    color:#94a3b8 !important;

    text-align:center;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .quick-grid{

        grid-template-columns:
            repeat(2,minmax(0,1fr));
    }

}


@media(max-width:768px){

    .content{

        padding:18px;
    }


    .page-title{

        font-size:23px;
    }


    .quick-grid{

        grid-template-columns:
            1fr;
    }


    .section-header{

        align-items:flex-start;

        flex-direction:column;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include(
    "../includes/sidebar_pharma.php"
);
?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<h1 class="page-title">

Pharmacist Dashboard

</h1>


<div class="page-subtitle">

Monitor medication orders, preparation, delivery and inventory status.

</div>


</div>



<!-- =====================================================
     ALERTS
===================================================== -->

<?php if ($totalPending > 0): ?>


<div class="alert alert-warning dashboard-alert">

<i class="bi bi-hourglass-split"></i>

<div>

<strong>

<?= $totalPending ?>

medication(s)

</strong>

pending preparation.

</div>

</div>


<?php else: ?>


<div class="alert alert-success dashboard-alert">

<i class="bi bi-check-circle"></i>

<div>

No medication is currently pending preparation.

</div>

</div>


<?php endif; ?>



<?php if ($totalLowStock > 0): ?>


<div class="alert alert-danger dashboard-alert">

<i class="bi bi-exclamation-triangle"></i>

<div>

<strong>

<?= $totalLowStock ?>

medication(s)

</strong>

are currently low in stock.

</div>

</div>


<?php endif; ?>



<!-- =====================================================
     KPI
===================================================== -->

<div class="row g-3">


<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Orders

</div>


<div class="stat-number">

<?= $totalOrders ?>

</div>


<p class="stat-description">

Total medication orders

</p>


</div>


<div class="stat-icon icon-orders">

<i class="bi bi-capsule"></i>

</div>


</div>


</div>


</div>



<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Pending

</div>


<div class="stat-number">

<?= $totalPending ?>

</div>


<p class="stat-description">

Waiting preparation

</p>


</div>


<div class="stat-icon icon-pending">

<i class="bi bi-hourglass-split"></i>

</div>


</div>


</div>


</div>



<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Prepared

</div>


<div class="stat-number">

<?= $totalPrepared ?>

</div>


<p class="stat-description">

Prepared orders

</p>


</div>


<div class="stat-icon icon-prepared">

<i class="bi bi-box-seam"></i>

</div>


</div>


</div>


</div>



<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Delivered

</div>


<div class="stat-number">

<?= $totalDelivered ?>

</div>


<p class="stat-description">

Completed deliveries

</p>


</div>


<div class="stat-icon icon-delivered">

<i class="bi bi-truck"></i>

</div>


</div>


</div>


</div>



<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Today Delivery

</div>


<div class="stat-number">

<?= $todayDelivery ?>

</div>


<p class="stat-description">

Delivered today

</p>


</div>


<div class="stat-icon icon-today">

<i class="bi bi-calendar-check"></i>

</div>


</div>


</div>


</div>



<div class="col-xl-2 col-md-4 col-sm-6">


<div class="stat-card">


<div class="stat-card-content">


<div>


<div class="stat-label">

Low Stock

</div>


<div class="stat-number">

<?= $totalLowStock ?>

</div>


<p class="stat-description">

Need attention

</p>


</div>


<div class="stat-icon icon-stock">

<i class="bi bi-exclamation-triangle"></i>

</div>


</div>


</div>


</div>


</div>



<!-- =====================================================
     QUICK ACTIONS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Quick Actions

</h5>


<div class="section-subtitle">

Access the main pharmacy management modules.

</div>


</div>


</div>


<div class="quick-grid">


<a
    href="medication_order.php"
    class="quick-action"
>


<div class="quick-icon quick-blue">

<i class="bi bi-capsule"></i>

</div>


<div>


<div class="quick-title">

Medication Orders

</div>


<div class="quick-text">

View prescribed medication orders

</div>


</div>


</a>



<a
    href="pharmacy_preparation.php"
    class="quick-action"
>


<div class="quick-icon quick-orange">

<i class="bi bi-box-seam"></i>

</div>


<div>


<div class="quick-title">

Preparation

</div>


<div class="quick-text">

Prepare medication for patients

</div>


</div>


</a>



<a
    href="medication_delivery.php"
    class="quick-action"
>


<div class="quick-icon quick-green">

<i class="bi bi-truck"></i>

</div>


<div>


<div class="quick-title">

Delivery

</div>


<div class="quick-text">

Review medication delivery records

</div>


</div>


</a>



<a
    href="pharmacy_inventory.php"
    class="quick-action"
>


<div class="quick-icon quick-red">

<i class="bi bi-archive"></i>

</div>


<div>


<div class="quick-title">

Inventory

</div>


<div class="quick-text">

Manage medication stock levels

</div>


</div>


</a>


</div>


</div>



<!-- =====================================================
     RECENT ORDERS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Recent Medication Orders

</h5>


<div class="section-subtitle">

Latest prescribed medication orders in the system.

</div>


</div>


<span class="badge text-bg-light">

<?= $totalOrders ?>

order(s)

</span>


</div>


<div class="table-responsive">


<table
    id="recentTable"
    class="table"
>


<thead>


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


<?php while (
    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        )
): ?>


<tr>


<td>

<strong>

#<?= htmlspecialchars(
    $row['MEDORDER_ID']
) ?>

</strong>

</td>


<td>

<span class="patient-name">

<?= htmlspecialchars(
    $row['PATIENT_NAME']
) ?>

</span>

</td>


<td>

<?= htmlspecialchars(
    $row['ADMISSION_ID']
    ?? '-'
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
     PENDING ORDERS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Pending Medication Orders

</h5>


<div class="section-subtitle">

Medication orders that have not entered preparation yet.

</div>


</div>


<span class="status-badge badge-pending">

<i class="bi bi-hourglass-split"></i>

<?= $totalPending ?>

Pending

</span>


</div>


<div class="table-responsive">


<table
    id="pendingTable"
    class="table"
>


<thead>


<tr>

<th>ID</th>

<th>Patient</th>

<th>Medication</th>

<th>Dosage</th>

<th>Frequency</th>

</tr>


</thead>


<tbody>


<?php while (
    $p =
        $pending->fetch(
            PDO::FETCH_ASSOC
        )
): ?>


<tr>


<td>

<strong>

#<?= htmlspecialchars(
    $p['MEDORDER_ID']
) ?>

</strong>

</td>


<td>

<span class="patient-name">

<?= htmlspecialchars(
    $p['PATIENT_NAME']
) ?>

</span>

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
     LOW STOCK
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Low Stock Medication

</h5>


<div class="section-subtitle">

Medication with stock level of 10 units or below.

</div>


</div>


<span class="badge text-bg-light">

<?= $totalLowStock ?>

item(s)

</span>


</div>


<div class="table-responsive">


<table
    id="stockTable"
    class="table"
>


<thead>


<tr>

<th>Medication</th>

<th>Form</th>

<th>Stock</th>

</tr>


</thead>


<tbody>


<?php while (
    $s =
        $stock->fetch(
            PDO::FETCH_ASSOC
        )
): ?>


<tr>


<td>

<span class="patient-name">

<?= htmlspecialchars(
    $s['MEDICATION_NAME']
) ?>

</span>

</td>


<td>

<?= htmlspecialchars(
    $s['DOSAGE_FORM']
) ?>

</td>


<td>


<?php if (
    (int)$s['STOCK']
    <=
    5
): ?>


<span class="status-badge badge-danger-stock">

<i class="bi bi-exclamation-circle"></i>

<?= htmlspecialchars(
    $s['STOCK']
) ?>

</span>


<?php else: ?>


<span class="status-badge badge-warning-stock">

<i class="bi bi-exclamation-triangle"></i>

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

$(document).ready(
function()
{


/* =========================================================
   RECENT
========================================================= */

if (
    !$.fn.DataTable
        .isDataTable(
            '#recentTable'
        )
) {

    $('#recentTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [0,'desc']
            ]

        });

}


/* =========================================================
   PENDING
========================================================= */

if (
    !$.fn.DataTable
        .isDataTable(
            '#pendingTable'
        )
) {

    $('#pendingTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [0,'desc']
            ]

        });

}


/* =========================================================
   LOW STOCK
========================================================= */

if (
    !$.fn.DataTable
        .isDataTable(
            '#stockTable'
        )
) {

    $('#stockTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            order:[
                [2,'asc']
            ]

        });

}


}
);

</script>


</body>

</html>