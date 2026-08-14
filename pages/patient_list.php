<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

$sql = "
SELECT
p.PATIENT_ID,
p.NAME,
p.IC_NUMBER,
p.GENDER,
p.AGE,
p.PHONE,

a.ADMISSION_ID,
a.ADMISSION_DATE,
a.DISCHARGE_DATE

FROM SYARMIMI.PATIENT p

LEFT JOIN SYARMIMI.ADMISSION a
ON a.ADMISSION_ID =
(
    SELECT MAX(a2.ADMISSION_ID)
    FROM SYARMIMI.ADMISSION a2
    WHERE a2.PATIENT_ID = p.PATIENT_ID
)

ORDER BY p.NAME
";

$patients = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>

<title>Patient List</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css">

<style>

body{
background:#eef2f7;
}

.card-box{
background:white;
padding:25px;
border-radius:20px;
box-shadow:0 8px 20px rgba(0,0,0,.08);
}

.dataTables_filter{
display:none;
}

.dataTables_length{
margin-bottom:20px;
}

.dataTables_info{
margin-top:15px;
}

.dataTables_paginate{
margin-top:15px;
}

</style>

</head>

<body>

<div class="container-fluid p-4">

<a href="nurse_dashboard.php"
class="btn btn-secondary">

← Back

</a>

<div class="mt-3 mb-4">

<h3 class="mb-0">
👤 Patient List
</h3>

</div>

<div class="card-box">

<div class="row mb-3">

<div class="col-md-4">

<input
type="text"
id="patientSearch"
class="form-control"
placeholder="🔍 Search Patient">

</div>

</div>

<table
id="patientTable"
class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Name</th>
<th>IC Number</th>
<th>Gender</th>
<th>Age</th>
<th>Phone</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach($patients as $p): ?>

<tr>

<td>
<?= $p['PATIENT_ID'] ?>
</td>

<td>
<?= htmlspecialchars($p['NAME']) ?>
</td>

<td>
<?= $p['IC_NUMBER'] ?>
</td>

<td>
<?= $p['GENDER'] ?>
</td>

<td>
<?= $p['AGE'] ?>
</td>

<td>
<?= $p['PHONE'] ?>
</td>

<td>

<?php if(empty($p['DISCHARGE_DATE'])): ?>

<span class="badge bg-success">
Admitted
</span>

<?php else: ?>

<span class="badge bg-secondary">
Discharged
</span>

<?php endif; ?>

</td>

<td>

<?php if(!empty($p['ADMISSION_ID'])): ?>

<a
href="patient_record_details.php?admission_id=<?= $p['ADMISSION_ID'] ?>"
class="btn btn-primary btn-sm">

<i class="bi bi-eye"></i>
View Record

</a>

<?php else: ?>

<span class="badge bg-danger">

No Admission Record

</span>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script>

$(document).ready(function(){

var table = $('#patientTable').DataTable({

pageLength:10,
order:[[1,'asc']]

});

$('#patientSearch').on('keyup', function(){

table.search(this.value).draw();

});

});

</script>

</body>
</html>