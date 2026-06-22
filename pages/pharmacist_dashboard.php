<?php
session_start();
include("../config/config.php");

// ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   TOTAL MED ORDERS
========================= */
$stmt1 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.MEDICATION_ORDER");
$row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

/* =========================
   TOTAL PREPARATION
========================= */
$stmt2 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.PHARMACY_PREPARATION");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

/* =========================
   TOTAL DELIVERY
========================= */
$stmt3 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.MEDICATION_DELIVERY");
$row3 = $stmt3->fetch(PDO::FETCH_ASSOC);

/* =========================
   🆕 TOTAL PENDING (ADDED ONLY)
========================= */
$stmt4 = $conn->query("
SELECT COUNT(*) AS TOTAL 
FROM SYARMIMI.MEDICATION_ORDER mo
LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp 
ON mo.MEDORDER_ID = pp.MEDORDER_ID
WHERE pp.MEDORDER_ID IS NULL
");
$row4 = $stmt4->fetch(PDO::FETCH_ASSOC);

/* =========================
   LOW STOCK
========================= */

$stmt5 = $conn->query("
SELECT COUNT(*) AS TOTAL
FROM SYARMIMI.MEDICATION
WHERE STOCK <= 10
");

$row5 = $stmt5->fetch(PDO::FETCH_ASSOC);

/* =========================
   TODAY DELIVERY
========================= */

$stmt6 = $conn->query("
SELECT COUNT(*) AS TOTAL
FROM SYARMIMI.MEDICATION_DELIVERY
WHERE TRUNC(DELIVERY_TIME)=TRUNC(SYSDATE)
");

$row6 = $stmt6->fetch(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<title>Pharmacist Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>
body { background:#f4f6f9; }
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="flex-grow-1 p-4">

<h3 class="mb-4">💊 Pharmacist Dashboard</h3>

<!-- PENDING ALERT -->
<?php if($row4['TOTAL'] > 0): ?>
<div class="alert alert-warning">
🔔 <?= $row4['TOTAL']; ?> medication(s) pending preparation
</div>
<?php endif; ?>

<!-- LOW STOCK ALERT -->
<?php if($row5['TOTAL'] > 0): ?>
<div class="alert alert-danger">
⚠ <?= $row5['TOTAL']; ?> medication(s) low stock
</div>
<?php endif; ?>

<div class="row">

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-capsule fs-2 text-primary"></i>
<h6>Orders</h6>
<h2><?= $row1['TOTAL']; ?></h2>
</div>
</div>

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-hourglass-split fs-2 text-warning"></i>
<h6>Pending</h6>
<h2><?= $row4['TOTAL']; ?></h2>
</div>
</div>

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-box-seam fs-2 text-info"></i>
<h6>Prepared</h6>
<h2><?= $row2['TOTAL']; ?></h2>
</div>
</div>

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-truck fs-2 text-success"></i>
<h6>Delivered</h6>
<h2><?= $row3['TOTAL']; ?></h2>
</div>
</div>

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-calendar-check fs-2 text-success"></i>
<h6>Today Delivery</h6>
<h2><?= $row6['TOTAL']; ?></h2>
</div>
</div>

<div class="col-lg-2 col-md-4 col-sm-6 mb-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-exclamation-triangle fs-2 text-danger"></i>
<h6>Low Stock</h6>
<h2><?= $row5['TOTAL']; ?></h2>
</div>
</div>

</div>

<!-- QUICK ACTION -->

<div class="card shadow p-3 mt-4">

<h5>Quick Actions</h5>

<div class="card shadow border-0 mt-4">

<div class="card-header bg-primary text-white">
<h5 class="mb-0">
<i class="bi bi-lightning-charge"></i>
 Quick Actions
</h5>
</div>

<div class="card-body">

<div class="row g-3">

<div class="col-md-3">

<a href="medication_order.php"
class="btn btn-primary w-100 py-3">

<i class="bi bi-capsule fs-4 d-block"></i>

Medication Orders

</a>

</div>

<div class="col-md-3">

<a href="pharmacy_preparation.php"
class="btn btn-warning w-100 py-3">

<i class="bi bi-box-seam fs-4 d-block"></i>

Preparation

</a>

</div>

<div class="col-md-3">

<a href="medication_delivery.php"
class="btn btn-success w-100 py-3">

<i class="bi bi-truck fs-4 d-block"></i>

Delivery

</a>

</div>

<div class="col-md-3">

<a href="pharmacy_inventory.php"
class="btn btn-danger w-100 py-3">

<i class="bi bi-archive fs-4 d-block"></i>

Inventory

</a>

</div>

</div> <!-- row -->

</div> <!-- card-body -->

</div> <!-- inner card -->

</div> <!-- QUICK ACTION CARD -->

<!-- RECENT ORDERS -->

<div class="card mt-4 p-3 shadow">
<h5>Recent Medication Orders</h5>

<?php
$sql = "SELECT mo.MEDORDER_ID,
               mo.ADMISSION_ID,
               m.MEDICATION_NAME,
               mo.DOSAGE,
               mo.FREQUENCY
        FROM SYARMIMI.MEDICATION_ORDER mo
        JOIN SYARMIMI.MEDICATION m 
        ON mo.MEDICATION_ID = m.MEDICATION_ID
        ORDER BY mo.MEDORDER_ID DESC";

$stmt = $conn->query($sql);
?>

<table id="recentTable"
class="table table-striped table-hover mt-3">
<thead class="table-dark">
<tr>
<th>ID</th>
<th>Admission</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
</tr>
</thead>

<tbody>
<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
<tr>
<td><?= $row['MEDORDER_ID']; ?></td>
<td><?= $row['ADMISSION_ID']; ?></td>
<td><?= $row['MEDICATION_NAME']; ?></td>
<td><?= $row['DOSAGE']; ?></td>
<td><?= $row['FREQUENCY']; ?></td>
</tr>
<?php } ?>
</tbody>

</table>

</div>

<div class="card mt-4 p-3 shadow">

<h5>Pending Medication Orders</h5>

<?php

$pending = $conn->query("

SELECT
mo.MEDORDER_ID,
m.MEDICATION_NAME,
mo.DOSAGE,
mo.FREQUENCY

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.MEDICATION m
ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp
ON mo.MEDORDER_ID = pp.MEDORDER_ID

WHERE pp.MEDORDER_ID IS NULL

ORDER BY mo.MEDORDER_ID DESC

");

?>

<table id="pendingTable"
class="table table-striped table-hover">

<thead>
<tr>
<th>ID</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
</tr>
</thead>

<tbody>

<?php while($p = $pending->fetch(PDO::FETCH_ASSOC)): ?>

<tr>
<td><?= $p['MEDORDER_ID'] ?></td>
<td><?= $p['MEDICATION_NAME'] ?></td>
<td><?= $p['DOSAGE'] ?></td>
<td><?= $p['FREQUENCY'] ?></td>
</tr>

<?php endwhile; ?>

</tbody>

</table>
</div>

<div class="card mt-4 p-3 shadow">

<h5>Low Stock Medication</h5>

<?php

$stock = $conn->query("

SELECT
MEDICATION_NAME,
DOSAGE_FORM,
STOCK

FROM SYARMIMI.MEDICATION

WHERE STOCK <= 10

ORDER BY STOCK ASC

");

?>

<table id="stockTable"
class="table table-striped table-hover">

<thead>
<tr>
<th>Medication</th>
<th>Form</th>
<th>Stock</th>
</tr>
</thead>

<tbody>

<?php while($s = $stock->fetch(PDO::FETCH_ASSOC)): ?>

<tr>

<td><?= $s['MEDICATION_NAME'] ?></td>

<td><?= $s['DOSAGE_FORM'] ?></td>

<td>

<?php if($s['STOCK'] <= 5): ?>

<span class="badge bg-danger">
<?= $s['STOCK'] ?>
</span>

<?php else: ?>

<span class="badge bg-warning">
<?= $s['STOCK'] ?>
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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

if (!$.fn.DataTable.isDataTable('#recentTable')) {

    $('#recentTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]]
    });

}

if (!$.fn.DataTable.isDataTable('#pendingTable')) {

    $('#pendingTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]]
    });

}

if (!$.fn.DataTable.isDataTable('#stockTable')) {

    $('#stockTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]]
    });

}

});

</script>

</body>
</html>