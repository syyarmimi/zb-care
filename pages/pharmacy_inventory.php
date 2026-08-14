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

        header("Location: pharmacy_inventory.php?restock=1");
        exit();
    }
}

/* =========================
   TOGGLE STATUS (AVAILABLE / UNAVAILABLE)
========================= */
if(isset($_GET['toggle_status'])) {
    $med_id = $_GET['toggle_status'];
    
    // Get current status
    $statusStmt = $conn->prepare("
        SELECT NVL(IS_AVAILABLE, 1) AS IS_AVAILABLE
        FROM SYARMIMI.MEDICATION
        WHERE MEDICATION_ID = :id
    ");
    $statusStmt->execute([':id' => $med_id]);
    $currentStatus = $statusStmt->fetch(PDO::FETCH_ASSOC);
    
    // Toggle status (1 = Available, 0 = Unavailable)
    $newStatus = ($currentStatus['IS_AVAILABLE'] == 1) ? 0 : 1;
    
    $updateStmt = $conn->prepare("
        UPDATE SYARMIMI.MEDICATION
        SET IS_AVAILABLE = :status
        WHERE MEDICATION_ID = :id
    ");
    $updateStmt->execute([
        ':status' => $newStatus,
        ':id' => $med_id
    ]);
    
    header("Location: pharmacy_inventory.php?status_toggled=1");
    exit();
}

/* =========================
   LOW STOCK COUNT
========================= */
$lowStock = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEDICATION
WHERE NVL(STOCK,0) < 10
AND NVL(IS_AVAILABLE, 1) = 1
")->fetchColumn();

/* =========================
   FETCH MEDICATION
========================= */
$stmt = $conn->query("
SELECT 
    MEDICATION_ID, 
    MEDICATION_NAME, 
    DESCRIPTION, 
    DOSAGE_FORM, 
    NVL(STOCK,0) AS STOCK,
    NVL(IS_AVAILABLE, 1) AS IS_AVAILABLE
FROM SYARMIMI.MEDICATION
ORDER BY MEDICATION_NAME
");

$totalMedication = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.MEDICATION
")->fetchColumn();

$availableStock = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.MEDICATION
WHERE STOCK >= 10
AND NVL(IS_AVAILABLE, 1) = 1
")->fetchColumn();

$outOfStock = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.MEDICATION
WHERE STOCK <= 0
OR NVL(IS_AVAILABLE, 0) = 0
")->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>

<?php if(isset($_GET['restock'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        icon:'success',
        title:'Stock Updated',
        text:'Medication inventory has been updated successfully.',
        confirmButtonColor:'#198754'
    });
});
</script>
<?php endif; ?>

<?php if(isset($_GET['status_toggled'])): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    Swal.fire({
        icon:'success',
        title:'Status Updated',
        text:'Medication availability status has been changed.',
        confirmButtonColor:'#198754'
    });
});
</script>
<?php endif; ?>

<title>Pharmacy Inventory</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
body { background:#f4f6f9; }

.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.btn-toggle {
    min-width: 90px;
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="p-4 w-100">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3 class="fw-bold mb-0">
📦 Pharmacy Inventory
</h3>

<small class="text-muted">
Manage medication stock and inventory
</small>

</div>

<div>

<span class="badge bg-danger fs-6 p-2">
Low Stock: <?= $lowStock ?>
</span>

</div>

</div>

<div class="row mb-4">

<div class="col-md-4">

<div class="card shadow border-0">

<div class="card-body">

<h6 class="text-muted">
Total Medication
</h6>

<h2>
<?= $totalMedication ?>
</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card shadow border-0">

<div class="card-body">

<h6 class="text-muted">
Available
</h6>

<h2 class="text-success">
<?= $availableStock ?>
</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card shadow border-0">

<div class="card-body">

<h6 class="text-muted">
Out Of Stock / Unavailable
</h6>

<h2 class="text-danger">
<?= $outOfStock ?>
</h2>

</div>

</div>

</div>

</div>

<!-- 🔔 LOW STOCK ALERT -->
<?php if($lowStock > 0): ?>
<div class="alert alert-danger">
⚠️ <?= $lowStock ?> medication(s) low stock
</div>
<?php endif; ?>

<div class="box">

<div class="row mb-3">

<div class="col-md-4">

<input
type="text"
id="searchInput"
class="form-control"
placeholder="🔍 Search medication">

</div>

<div class="col-md-3">

<select
id="statusFilter"
class="form-select">

<option value="">
All Status
</option>

<option value="Available">
Available
</option>

<option value="Low Stock">
Low Stock
</option>

<option value="Out Of Stock">
Out Of Stock
</option>

<option value="Unavailable">
Unavailable
</option>

</select>

</div>

<div class="col-md-3">

<select
id="sortFilter"
class="form-select">

<option value="asc">
A-Z Medication
</option>

<option value="desc">
Z-A Medication
</option>

</select>

</div>

</div>

<table
id="inventoryTable"
class="table table-hover align-middle">

<thead style="
background:#0f172a;
color:white;
">

<tr>
<th>ID</th>
<th>Medication</th>
<th>Description</th>
<th>Dosage</th>
<th>Stock</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { 

    // Determine status display
    $statusText = '';
    $statusClass = '';
    $statusFilter = '';
    
    if($row['IS_AVAILABLE'] == 0) {
        $statusText = 'Unavailable';
        $statusClass = 'bg-secondary';
        $statusFilter = 'Unavailable';
    } elseif($row['STOCK'] <= 0) {
        $statusText = 'Out Of Stock';
        $statusClass = 'bg-danger';
        $statusFilter = 'Out Of Stock';
    } elseif($row['STOCK'] < 10) {
        $statusText = 'Low Stock';
        $statusClass = 'bg-warning text-dark';
        $statusFilter = 'Low Stock';
    } else {
        $statusText = 'Available';
        $statusClass = 'bg-success';
        $statusFilter = 'Available';
    }
?>

<tr>
<td><?= $row['MEDICATION_ID'] ?></td>

<td><?= $row['MEDICATION_NAME'] ?></td>

<td><?= $row['DESCRIPTION'] ?></td>

<td><?= $row['DOSAGE_FORM'] ?></td>

<td><b><?= $row['STOCK'] ?></b></td>

<td>
<span class="badge <?= $statusClass ?>">
    <?= $statusText ?>
</span>
<span class="d-none">
    <?= $statusFilter ?>
</span>
</td>

<td>
<div class="d-flex gap-2 flex-wrap">
    <!-- Restock Form -->
    <form method="POST" class="d-flex gap-1">
        <input type="hidden" name="med_id" value="<?= $row['MEDICATION_ID'] ?>">
        <input type="number" name="qty" class="form-control form-control-sm" 
               placeholder="Qty" min="1" required style="width:60px;">
        <button name="restock" class="btn btn-success btn-sm">
            Restock
        </button>
    </form>
    
    <!-- Toggle Status Button -->
    <?php if($row['IS_AVAILABLE'] == 1): ?>
        <a href="?toggle_status=<?= $row['MEDICATION_ID'] ?>" 
           class="btn btn-warning btn-sm btn-toggle"
           onclick="return confirm('Are you sure you want to mark this medication as UNAVAILABLE?')">
            <i class="bi bi-x-circle"></i> Unavailable
        </a>
    <?php else: ?>
        <a href="?toggle_status=<?= $row['MEDICATION_ID'] ?>" 
           class="btn btn-info btn-sm btn-toggle"
           onclick="return confirm('Are you sure you want to mark this medication as AVAILABLE?')">
            <i class="bi bi-check-circle"></i> Available
        </a>
    <?php endif; ?>
</div>
</td>

</tr>

<?php } ?>

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

var table = $('#inventoryTable').DataTable({
    pageLength:10,
    lengthMenu:[[10,25,50,100],[10,25,50,100]],
    order:[[1,'asc']],
    dom:'t'
});

$('#searchInput').on('keyup', function(){
    table.search(this.value).draw();
});

$('#statusFilter').on('change', function(){
    table.column(5).search(this.value).draw();
});

$('#sortFilter').on('change', function(){
    if(this.value=='asc') {
        table.order([1,'asc']).draw();
    } else {
        table.order([1,'desc']).draw();
    }
});

});
</script>

</body>
</html>