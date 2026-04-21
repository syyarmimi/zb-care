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
?>

<!DOCTYPE html>
<html>
<head>
<title>Pharmacist Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="flex-grow-1 p-4">

<h3 class="mb-4">💊 Pharmacist Dashboard</h3>

<!-- 🔔 NEW ALERT (ADDED ONLY) -->
<?php if($row4['TOTAL'] > 0): ?>
<div class="alert alert-warning">
🔔 <?= $row4['TOTAL']; ?> medication(s) pending preparation
</div>
<?php endif; ?>

<div class="row">

<div class="col-md-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-capsule fs-2 text-primary"></i>
<h5 class="mt-2">Medication Orders</h5>
<h2><?= $row1['TOTAL']; ?></h2>
</div>
</div>

<!-- 🆕 PENDING CARD (ADDED ONLY) -->
<div class="col-md-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-hourglass-split fs-2 text-warning"></i>
<h5 class="mt-2">Pending</h5>
<h2><?= $row4['TOTAL']; ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-box-seam fs-2 text-warning"></i>
<h5 class="mt-2">Prepared</h5>
<h2><?= $row2['TOTAL']; ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="card shadow p-3 text-center">
<i class="bi bi-truck fs-2 text-success"></i>
<h5 class="mt-2">Delivered</h5>
<h2><?= $row3['TOTAL']; ?></h2>
</div>
</div>

</div>

<!-- RECENT ORDERS (UNCHANGED) -->
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

<table class="table table-bordered mt-3">
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

</div>

</div>

</body>
</html>