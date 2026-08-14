<?php
session_start();
include("../config/config.php");

// 🔐 SESSION CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   ADD STAFF
========================= */
if(isset($_POST['add'])){

    $username   = $_POST['username'];
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role       = $_POST['role'];
    $department = $_POST['department'];

    try {

        $sql = "INSERT INTO SYARMIMI.HOSPITAL_STAFF
                (USERNAME, PASSWORD, ROLE, DEPARTMENT)
                VALUES (?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            $username,
            $password,
            $role,
            $department
        ]);

    } catch(PDOException $e){

        die("Insert Error: " . $e->getMessage());

    }
}

/* =========================
   DELETE STAFF
========================= */
if(isset($_GET['delete'])){

    $id = $_GET['delete'];

    $sql = "DELETE FROM SYARMIMI.HOSPITAL_STAFF
            WHERE ACCOUNT_ID = ?";

    $stmt = $conn->prepare($sql);

    $stmt->execute([$id]);
}

/* =========================
   FETCH STAFF
========================= */

$sql = "SELECT *
        FROM SYARMIMI.HOSPITAL_STAFF
        ORDER BY ACCOUNT_ID DESC";

$stmt = $conn->query($sql);

?>

<!DOCTYPE html>
<html>
<head>
<title>Manage Staff</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<style>

body{
    background:#f4f6f9;
    font-family:'Segoe UI';
}

.page-title{
    font-size:38px;
    font-weight:700;
}

.card-box{
    background:white;
    padding:30px;
    border-radius:25px;
    box-shadow:0 10px 25px rgba(0,0,0,0.05);
}

.table th{
    background:#1e293b;
    color:white;
}

.badge-role{
    padding:8px 15px;
    border-radius:20px;
    font-size:13px;
}

.role-admin{
    background:#dc2626;
    color:white;
}

.role-doctor{
    background:#2563eb;
    color:white;
}

.role-nurse{
    background:#16a34a;
    color:white;
}

.role-pharmacist{
    background:#d97706;
    color:white;
}

.role-kitchen{
    background:#7c3aed;
    color:white;
}

.sidebar{
width:260px !important;
min-width:260px !important;
max-width:260px !important;
height:100vh;
flex-shrink:0;
}

.dataTables_filter{
    display:none;
}

.dataTables_length{
    display:block;
}

.dataTables_info{
    margin-top:15px;
}

.dataTables_paginate{
    margin-top:15px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current{
    background:#2563eb !important;
    color:white !important;
    border:none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover{
    background:#2563eb !important;
    color:white !important;
}
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<h2 class="page-title mb-4">
👥 Manage Staff
</h2>

<!-- =========================
     ADD STAFF
========================= -->

<div class="card-box mb-4">

<h4 class="mb-4">
Add New Staff
</h4>

<form method="POST">

<div class="row g-3">

<div class="col-md-3">

<input type="text"
name="username"
class="form-control"
placeholder="Username"
required>

</div>

<div class="col-md-3">

<input type="password"
name="password"
class="form-control"
placeholder="Password"
required>

</div>

<div class="col-md-3">

<select name="role"
id="role"
class="form-control"
required>

<option value="">Select Role</option>

<option value="admin">Admin</option>

<option value="doctor">Doctor</option>

<option value="nurse">Nurse</option>

<option value="pharmacist">Pharmacist</option>

<option value="kitchen">Kitchen</option>

</select>

</div>

<div class="col-md-3">

<select name="department"
id="department"
class="form-control">

<option value="">Select Department</option>

<option value="Orthopaedics">
Orthopaedics
</option>

<option value="Paediatrics">
Paediatrics
</option>

<option value="Dietitian & Nutrition">
Dietitian & Nutrition
</option>

</select>

</div>

<div class="col-md-12">

<button type="submit"
name="add"
class="btn btn-primary w-100">

Add Staff

</button>

</div>

</div>

</form>

</div>

<!-- =========================
     STAFF LIST
========================= -->

<div class="card-box">

<h4 class="mb-4">
Staff List
</h4>

<div class="row mb-4">

<div class="col-md-4">

<input type="text"
id="searchBox"
class="form-control"
placeholder="🔍 Search staff">

</div>

<div class="col-md-4">

<select id="roleFilter"
class="form-select">

<option value="">
All Roles
</option>

<option value="ADMIN">
Admin
</option>

<option value="DOCTOR">
Doctor
</option>

<option value="NURSE">
Nurse
</option>

<option value="PHARMACIST">
Pharmacist
</option>

<option value="KITCHEN">
Kitchen
</option>

</select>

</div>

<div class="col-md-4">

<select id="sortFilter"
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

<div class="table-responsive">

<table id="staffTable"
class="table table-bordered table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Username</th>
<th>Role</th>
<th>Department</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>

<td>
<?= $row['ACCOUNT_ID']; ?>
</td>

<td>

<?= ($row['ROLE'] == 'doctor')
? 'Dr. '.$row['USERNAME']
: $row['USERNAME']; ?>

</td>

<td>

<?php

$role = $row['ROLE'];

if($role == 'admin'){
    echo "<span class='badge-role role-admin'>ADMIN</span>";
}
elseif($role == 'doctor'){
    echo "<span class='badge-role role-doctor'>DOCTOR</span>";
}
elseif($role == 'nurse'){
    echo "<span class='badge-role role-nurse'>NURSE</span>";
}
elseif($role == 'pharmacist'){
    echo "<span class='badge-role role-pharmacist'>PHARMACIST</span>";
}
else{
    echo "<span class='badge-role role-kitchen'>KITCHEN</span>";
}

?>

</td>

<td>

<?= $row['DEPARTMENT'] ?: '-' ?>

</td>

<td>

<a href="staff.php?delete=<?= $row['ACCOUNT_ID']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this staff?')">

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

</div>

<script>

/* =========================
   HIDE DEPARTMENT IF NOT DOCTOR
========================= */

const role = document.getElementById('role');
const department = document.getElementById('department');

role.addEventListener('change', function(){

    if(this.value == 'doctor'){

        department.disabled = false;

    }else{

        department.value = '';
        department.disabled = true;

    }

});

department.disabled = true;

</script>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

    var table = $('#staffTable').DataTable({

        pageLength: 10,

        order: [[0, 'desc']],

        lengthMenu: [
            [10,25,50,100],
            [10,25,50,100]
        ]

    });

    /* SEARCH */

    $('#searchBox').on('keyup', function(){

        table.search(this.value).draw();

    });

    /* ROLE FILTER */

    $('#roleFilter').on('change', function(){

        table.column(2)
             .search(this.value)
             .draw();

    });

    /* SORT */

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