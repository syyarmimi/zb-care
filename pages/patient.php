<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= SORT ================= */
$sort = $_GET['sort'] ?? 'desc';
$order = ($sort === 'asc') ? 'ASC' : 'DESC';

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM SYARMIMI.PATIENT WHERE PATIENT_ID = ?");
    $stmt->execute([$id]);

    header("Location: patient.php");
    exit();
}

/* ================= EDIT FETCH ================= */
$editData = null;

if(isset($_GET['edit'])){
    $id = $_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM SYARMIMI.PATIENT WHERE PATIENT_ID = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){
    $id      = $_POST['id'];
    $ic      = $_POST['ic'];
    $name    = $_POST['name'];
    $age     = $_POST['age'];
    $gender  = $_POST['gender'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];

    $sql = "UPDATE SYARMIMI.PATIENT 
            SET IC_NUMBER=?, NAME=?, AGE=?, GENDER=?, PHONE=?, ADDRESS=?
            WHERE PATIENT_ID=?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$ic,$name,$age,$gender,$phone,$address,$id]);

    header("Location: patient.php");
    exit();
}

/* ================= ADD ================= */
if(isset($_POST['add'])){
    $ic      = $_POST['ic'];
    $name    = $_POST['name'];
    $age     = $_POST['age'];
    $gender  = $_POST['gender'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];

    try {
        // manual ID (no sequence)
        $sql = "SELECT NVL(MAX(PATIENT_ID),0)+1 AS NEW_ID FROM SYARMIMI.PATIENT";
        $stmt = $conn->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newId = $row['NEW_ID'];

        $sql = "INSERT INTO SYARMIMI.PATIENT 
                (PATIENT_ID, IC_NUMBER, NAME, AGE, GENDER, PHONE, ADDRESS)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $newId, $ic, $name, $age, $gender, $phone, $address
        ]);

        header("Location: patient.php");
        exit();

    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<!-- Add jQuery and DataTables JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
body{
    background:#eef2f7;
}

.card{
    border:none;
    border-radius:18px;
    box-shadow:0 4px 15px rgba(0,0,0,.05);
}

.filter-box{
    background:#fff;
    padding:18px;
    border-radius:18px;
    box-shadow:0 4px 15px rgba(0,0,0,.05);
}

.dataTables_filter{
    display:none;
}

.dataTables_info{
    margin-top:15px;
}

.dataTables_paginate{
    margin-top:15px;
}

.table thead{
    background:#0f172a;
    color:white;
}

.table th{
    border:none;
}

.table td{
    vertical-align:middle;
}
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<h3 class="mb-3">👤 Patient Management</h3>

<!-- FORM -->
<div class="card p-3 mb-3 shadow-sm">

<form method="POST">

<input type="hidden" name="id" value="<?= $editData['PATIENT_ID'] ?? '' ?>">

<div class="row mb-2">
<div class="col-md-4">
<input type="text" name="ic" class="form-control"
value="<?= $editData['IC_NUMBER'] ?? '' ?>" placeholder="IC Number" required>
</div>

<div class="col-md-4">
<input type="text" name="name" class="form-control"
value="<?= $editData['NAME'] ?? '' ?>" placeholder="Name" required>
</div>

<div class="col-md-2">
<input type="number" name="age" class="form-control"
value="<?= $editData['AGE'] ?? '' ?>" placeholder="Age" required>
</div>

<div class="col-md-2">
<select name="gender" class="form-control" required>
    <option disabled <?= !$editData ? 'selected' : '' ?>>Gender</option>
    <option value="Male" <?= (($editData['GENDER'] ?? '')=='Male')?'selected':'' ?>>Male</option>
    <option value="Female" <?= (($editData['GENDER'] ?? '')=='Female')?'selected':'' ?>>Female</option>
</select>
</div>
</div>

<div class="row mb-2">
<div class="col-md-4">
<input type="text" name="phone" class="form-control"
value="<?= $editData['PHONE'] ?? '' ?>" placeholder="Phone" required>
</div>

<div class="col-md-8">
<input type="text" name="address" class="form-control"
value="<?= $editData['ADDRESS'] ?? '' ?>" placeholder="Address" required>
</div>
</div>

<?php if($editData){ ?>
    <button class="btn btn-warning" name="update">Update Patient</button>
    <a href="patient.php" class="btn btn-secondary">Cancel</a>
<?php } else { ?>
    <button class="btn btn-primary" name="add">Add Patient</button>
<?php } ?>

</form>

</div>

<!-- TABLE -->
<div class="card p-3 shadow-sm">

<h5>📋 Patient List</h5>

<div class="filter-box mb-4">

<div class="row g-3">

<div class="col-md-4">

<input
type="text"
id="patientSearch"
class="form-control"
placeholder="🔍 Search patient">

</div>

<div class="col-md-4">

<select
id="genderFilter"
class="form-select">

<option value="">
All Gender
</option>

<option value="Male">
Male
</option>

<option value="Female">
Female
</option>

</select>

</div>

<div class="col-md-4">

<select
id="sortFilter"
class="form-select">

<option value="desc">
Newest First
</option>

<option value="asc">
Oldest First
</option>

</select>

</div>

</div>

</div>

<table
id="patientTable"
class="table table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>IC</th>
<th>Name</th>
<th>Age</th>
<th>Gender</th>
<th>Phone</th>
<th>Address</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
$stmt = $conn->query("SELECT * FROM SYARMIMI.PATIENT ORDER BY PATIENT_ID $order");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
?>
<tr>
<td><?= $row['PATIENT_ID'] ?></td>
<td><?= $row['IC_NUMBER'] ?></td>
<td><?= $row['NAME'] ?></td>
<td><?= $row['AGE'] ?></td>
<td><?= $row['GENDER'] ?></td>
<td><?= $row['PHONE'] ?></td>
<td><?= $row['ADDRESS'] ?></td>

<td>
    <a href="?edit=<?= $row['PATIENT_ID'] ?>" class="btn btn-warning btn-sm">Edit</a>
    <a href="?delete=<?= $row['PATIENT_ID'] ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('Delete this patient?')">
       Delete
    </a>
</td>
</tr>
<?php } ?>

</tbody>

</table>

</div>

</div>
</div>

<script>
$(document).ready(function(){

    var table = $('#patientTable').DataTable({

        pageLength:10,

        order:[[0,'desc']],

        lengthMenu:[
            [10,25,50,100],
            [10,25,50,100]
        ]

    });

    $('#patientSearch').on('keyup', function(){

        table.search(this.value).draw();

    });

    $('#genderFilter').on('change', function(){

        // Gender is now column index 4 (0-indexed)
        table.column(4)
             .search(this.value)
             .draw();

    });

    $('#sortFilter').on('change', function(){

        if(this.value == 'asc')
        {
            table.order([0,'asc']).draw();
        }
        else
        {
            table.order([0,'desc']).draw();
        }

    });

});

</script>

</body>
</html>