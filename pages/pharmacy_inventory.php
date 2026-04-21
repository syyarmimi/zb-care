<?php
session_start();
include("../config/config.php");

// ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   RESTOCK
========================= */
if(isset($_POST['restock'])){

    $med_id = $_POST['med_id'];
    $qty    = $_POST['qty'];

    if($qty > 0){
        $stmt = $conn->prepare("
            UPDATE SYARMIMI.MEDICATION
            SET STOCK = NVL(STOCK,0) + :qty
            WHERE MEDICATION_ID = :id
        ");
        $stmt->execute([
            ':qty'=>$qty,
            ':id'=>$med_id
        ]);

        echo "<script>alert('Stock Updated!'); window.location='pharmacy_inventory.php';</script>";
    }
}

/* =========================
   LOW STOCK COUNT
========================= */
$lowStock = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEDICATION
WHERE NVL(STOCK,0) < 10
")->fetchColumn();

/* =========================
   FETCH MEDICATION
========================= */
$stmt = $conn->query("
SELECT MEDICATION_ID, MEDICATION_NAME, DESCRIPTION, DOSAGE_FORM, NVL(STOCK,0) AS STOCK
FROM SYARMIMI.MEDICATION
ORDER BY MEDICATION_NAME
");
?>

<!DOCTYPE html>
<html>
<head>
<title>Pharmacy Inventory</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }

.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-3">📦 Pharmacy Inventory</h4>

<!-- 🔔 LOW STOCK ALERT -->
<?php if($lowStock > 0): ?>
<div class="alert alert-danger">
⚠️ <?= $lowStock ?> medication(s) low stock
</div>
<?php endif; ?>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Medication</th>
<th>Description</th>
<th>Dosage</th>
<th>Stock</th>
<th>Status</th>
<th>Restock</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>
<td><?= $row['MEDICATION_ID'] ?></td>

<td><?= $row['MEDICATION_NAME'] ?></td>

<td><?= $row['DESCRIPTION'] ?></td>

<td><?= $row['DOSAGE_FORM'] ?></td>

<td><b><?= $row['STOCK'] ?></b></td>

<td>
<?php if($row['STOCK'] <= 0): ?>
<span class="badge bg-danger">Out of Stock</span>
<?php elseif($row['STOCK'] < 10): ?>
<span class="badge bg-warning text-dark">Low</span>
<?php else: ?>
<span class="badge bg-success">Available</span>
<?php endif; ?>
</td>

<td>
<form method="POST" class="d-flex gap-2">
<input type="hidden" name="med_id" value="<?= $row['MEDICATION_ID'] ?>">

<input type="number" name="qty" class="form-control form-control-sm" 
placeholder="Qty" min="1" required>

<button name="restock" class="btn btn-primary btn-sm">
Add
</button>
</form>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>