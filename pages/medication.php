<?php
session_start();
include("../config/config.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

/* ================= INSERT ================= */
if(isset($_POST['add'])){

    $name   = $_POST['name'] ?? '';
    $desc   = $_POST['desc'] ?? '';
    $dosage = $_POST['dosage'] ?? '';

    if(!empty($name) && !empty($desc) && !empty($dosage)){

        $sql = "INSERT INTO SYARMIMI.MEDICATION 
                (Medication_ID, Medication_Name, Description, Dosage_Form, STOCK)
                VALUES (SYARMIMI.MEDICATION_SEQ.NEXTVAL, :name, :desc, :dosage, 0)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':dosage' => $dosage
        ]);

        echo "<script>alert('Medication Added'); window.location='medication.php';</script>";
        exit(); // Add exit after redirect
    }
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM SYARMIMI.MEDICATION WHERE MEDICATION_ID=:id");
    $stmt->execute([':id'=>$id]);

    echo "<script>alert('Deleted'); window.location='medication.php';</script>";
    exit(); // Add exit after redirect
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){
    $id     = $_POST['id'];
    $name   = $_POST['name'];
    $desc   = $_POST['desc'];
    $dosage = $_POST['dosage'];
    $stock  = $_POST['stock'] ?? 0; // Added default value

    $stmt = $conn->prepare("
        UPDATE SYARMIMI.MEDICATION 
        SET MEDICATION_NAME=:name,
            DESCRIPTION=:desc,
            DOSAGE_FORM=:dosage,
            STOCK=:stock
        WHERE MEDICATION_ID=:id
    ");

    $stmt->execute([
        ':name'=>$name,
        ':desc'=>$desc,
        ':dosage'=>$dosage,
        ':stock'=>$stock,
        ':id'=>$id
    ]);

    echo "<script>alert('Updated'); window.location='medication.php';</script>";
    exit(); // Add exit after redirect
}

/* ================= FETCH ================= */

$search = $_GET['search'] ?? '';
$sort = ($_GET['sort'] ?? 'ASC') == 'DESC' ? 'DESC' : 'ASC';

// Use parameterized query with ORDER BY safely
// Since $sort only contains 'ASC' or 'DESC', it's safe
$stmt = $conn->prepare("
    SELECT *
    FROM SYARMIMI.MEDICATION
    WHERE LOWER(MEDICATION_NAME) LIKE LOWER(:search)
    ORDER BY MEDICATION_NAME $sort
");

$stmt->execute([
    ':search' => '%' . $search . '%'
]);

$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<title>Medication</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<style>
body { background:#f1f5f9; }
.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
.sidebar {
    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;
    height:100vh;
    flex-shrink:0;
}
.dataTables_filter { display:none; }
.dataTables_info { margin-top:15px; }
.dataTables_paginate { margin-top:15px; }
.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background:#2563eb !important;
    color:white !important;
    border:none !important;
}
.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background:#2563eb !important;
    color:white !important;
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-4">💊 Medication Management & Inventory</h4>

<!-- ADD FORM - Fixed: Added form tags -->
<div class="box mb-4">
<form method="POST" action="">
    <input name="name" class="form-control mb-2" placeholder="Medication Name" required>
    <input name="desc" class="form-control mb-2" placeholder="Description" required>
    <input name="dosage" class="form-control mb-2" placeholder="Dosage Form" required>
    <button name="add" class="btn btn-primary w-100">Add Medication</button>
</form>
</div>

<!-- TABLE -->
<div class="box">

<div class="row mb-4">
    <div class="col-md-4">
        <input type="text" id="medicationSearch" class="form-control" placeholder="🔍 Search Medication">
    </div>
    <div class="col-md-4">
        <select id="stockFilter" class="form-select">
            <option value="">All Stock</option>
            <option value="Out of Stock">Out of Stock</option>
            <option value="Low Stock">Low Stock</option>
            <option value="Available">Available</option>
        </select>
    </div>
    <div class="col-md-4">
        <select id="sortFilter" class="form-select">
            <option value="asc">A-Z</option>
            <option value="desc">Z-A</option>
        </select>
    </div>
</div>

<div class="table-responsive">

<table id="medicationTable" class="table table-hover">
<thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Description</th>
        <th>Dosage</th>
        <th>Stock</th>
        <th style="display:none;">Stock Status</th>
        <th>Action</th>
    </tr>
</thead>
<tbody>

<?php foreach($medications as $m): ?>
<tr>
    <form method="POST" action="">
        <td><?= htmlspecialchars($m['MEDICATION_ID']) ?></td>
        
        <td>
            <span style="display:none;"><?= htmlspecialchars($m['MEDICATION_NAME']) ?></span>
            <input name="name" value="<?= htmlspecialchars($m['MEDICATION_NAME']) ?>" class="form-control">
        </td>
        
        <td>
            <span style="display:none;"><?= htmlspecialchars($m['DESCRIPTION']) ?></span>
            <input name="desc" value="<?= htmlspecialchars($m['DESCRIPTION']) ?>" class="form-control">
        </td>
        
        <td>
            <span style="display:none;"><?= htmlspecialchars($m['DOSAGE_FORM']) ?></span>
            <input name="dosage" value="<?= htmlspecialchars($m['DOSAGE_FORM']) ?>" class="form-control">
        </td>
        
        <td>
            <input name="stock" value="<?= htmlspecialchars($m['STOCK']) ?>" class="form-control" type="number">
        </td>
        
        <td style="display:none;">
            <?php
            if($m['STOCK'] <= 0){
                echo "Out of Stock";
            }
            elseif($m['STOCK'] <= 10){
                echo "Low Stock";
            }
            else{
                echo "Available";
            }
            ?>
        </td>
        
        <td>
            <input type="hidden" name="id" value="<?= htmlspecialchars($m['MEDICATION_ID']) ?>">
            <button name="update" class="btn btn-warning btn-sm">Update</button>
            <a href="?delete=<?= htmlspecialchars($m['MEDICATION_ID']) ?>" 
               class="btn btn-danger btn-sm"
               onclick="return confirm('Delete this medication?')">Delete</a>
        </td>
    </form>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div> <!-- table-responsive -->

</div> <!-- box -->

</div> <!-- content -->

</div> <!-- d-flex -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function(){
    var table = $('#medicationTable').DataTable({
        pageLength: 10,
        order: [[1,'asc']],
        lengthMenu: [[10,25,50,100], [10,25,50,100]]
    });

    $('#medicationSearch').on('keyup', function(){
        table.search(this.value).draw();
    });

    $('#stockFilter').on('change', function(){
        table.column(5).search(this.value).draw();
    });

    $('#sortFilter').on('change', function(){
        if(this.value == 'asc') {
            table.order([1,'asc']).draw();
        } else {
            table.order([1,'desc']).draw();
        }
    });
});
</script>

</body>
</html>