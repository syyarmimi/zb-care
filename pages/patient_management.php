<?php
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['doctor', 'admin'])) {
    die("Access Denied");
}

include("../config/config.php");

$doctor_id = $_SESSION['user_id'] ?? 0;
$admin_id = $_SESSION['user_id'] ?? 0;

$role = $_SESSION['role'];

/* ======================================
   CURRENT PATIENTS
====================================== */

$sql = "

SELECT
    A.ADMISSION_ID,
    P.NAME,
    W.WARD_NAME,
    B.BED_NUMBER,
    TO_CHAR(A.ADMISSION_DATE,'DD-MON-YYYY') AS ADMISSION_DATE

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID

JOIN SYARMIMI.BED B
ON A.BED_ID = B.BED_ID

JOIN SYARMIMI.WARD W
ON B.WARD_ID = W.WARD_ID

WHERE A.DISCHARGE_DATE IS NULL

";

if($role == 'doctor'){

    $sql .= "
    AND A.ACCOUNT_ID = :doctor
    ";

}

$sql .= "
ORDER BY A.ADMISSION_ID DESC
";

$currentPatients = $conn->prepare($sql);

if($role == 'doctor'){

    $currentPatients->execute([
        ':doctor'=>$doctor_id
    ]);

}else{

    $currentPatients->execute();

}

$currentList =
$currentPatients->fetchAll(PDO::FETCH_ASSOC);

/* ======================================
   WALK-IN PATIENTS
====================================== */

$walkinPatients = $conn->query("

SELECT
    W.CONSULTATION_ID,
    P.NAME,
    TO_CHAR(W.CONSULTATION_DATE,'DD-MON-YYYY') AS CONSULTATION_DATE

FROM SYARMIMI.WALKIN_CONSULTATION W

JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID

ORDER BY W.CONSULTATION_ID DESC

")->fetchAll(PDO::FETCH_ASSOC);

/* ======================================
   APPOINTMENT PATIENTS
====================================== */

$appointmentPatients = $conn->query("

SELECT
    A.APPOINTMENT_ID,
    P.NAME,
    A.APPOINTMENT_DATE,
    A.DEPARTMENT,
    A.STATUS

FROM SYARMIMI.APPOINTMENT A

JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID

ORDER BY A.APPOINTMENT_ID DESC

")->fetchAll(PDO::FETCH_ASSOC);

/* ======================================
   DIAGNOSED PATIENTS
====================================== */

$sql = "

SELECT
    D.DIAGNOSIS_ID,
    P.NAME,
    D.DIAGNOSIS_DETAILS,
    TO_CHAR(D.DATE_RECORDED,'DD-MON-YYYY') AS DATE_RECORDED

FROM SYARMIMI.DIAGNOSIS D

JOIN SYARMIMI.PATIENT P
ON D.PATIENT_ID = P.PATIENT_ID

WHERE 1=1

";

if($role == 'doctor'){

    $sql .= "
    AND D.ACCOUNT_ID = :doctor
    ";

}

$sql .= "
ORDER BY D.DIAGNOSIS_ID DESC
";

$diagnosedPatients = $conn->prepare($sql);

if($role == 'doctor'){

    $diagnosedPatients->execute([
        ':doctor'=>$doctor_id
    ]);

}else{

    $diagnosedPatients->execute();

}

$diagnosedList =
$diagnosedPatients->fetchAll(PDO::FETCH_ASSOC);

/* ======================================
   DISCHARGED PATIENTS
====================================== */

$sql = "

SELECT
    P.NAME,
    TO_CHAR(A.ADMISSION_DATE,'DD-MON-YYYY') AS ADMISSION_DATE,
    TO_CHAR(A.DISCHARGE_DATE,'DD-MON-YYYY') AS DISCHARGE_DATE

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID

WHERE A.DISCHARGE_DATE IS NOT NULL

";

if($role == 'doctor'){

    $sql .= "
    AND A.ACCOUNT_ID = :doctor
    ";

}

$sql .= "
ORDER BY A.DISCHARGE_DATE DESC
";

$dischargedPatients = $conn->prepare($sql);

if($role == 'doctor'){

    $dischargedPatients->execute([
        ':doctor'=>$doctor_id
    ]);

}else{

    $dischargedPatients->execute();

}

$dischargedList =
$dischargedPatients->fetchAll(PDO::FETCH_ASSOC);

/* ======================================
   STATISTICS
====================================== */

$totalCurrent = count($currentList);

$totalDiagnosed = count($diagnosedList);

$totalDischarged = count($dischargedList);

if($role == 'doctor'){

    $medStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.MEDICATION_ORDER
    WHERE ACCOUNT_ID = :doctor
    ");

    $medStmt->execute([
        ':doctor'=>$doctor_id
    ]);

}else{

    $medStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.MEDICATION_ORDER
    ");

    $medStmt->execute();
}

$totalMedication = $medStmt->fetchColumn();

if($role == 'doctor'){

    $dischargeStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.ADMISSION
    WHERE ACCOUNT_ID = :doctor
    AND DISCHARGE_DATE IS NOT NULL
    ");

    $dischargeStmt->execute([
        ':doctor'=>$doctor_id
    ]);

}else{

    $dischargeStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.ADMISSION
    WHERE DISCHARGE_DATE IS NOT NULL
    ");

    $dischargeStmt->execute();
}
$totalDischarged =
$dischargeStmt->fetchColumn();

?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">

<title>Patient Management</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
    background:#eef2f7;
    font-family:'Segoe UI';
}

.content{
    flex:1;
    padding:30px;
}

.card-box{
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.stats-card{
    text-align:center;
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.stats-card h2{
    font-weight:bold;
}

.table th{
    background:#f8fafc;
}

#searchBox{
height:45px;
border-radius:10px;
}

.form-select{
height:45px;
border-radius:10px;
}

</style>

</head>

<body>

<div class="d-flex">

<?php

if($role == 'admin'){
    include("../includes/sidebar_admin.php");
}else{
    include("../includes/sidebar_doctor.php");
}

?>

<div class="content">

<h2 class="fw-bold mb-4">
👨‍⚕️ Patient Management
</h2>

<!-- STATISTICS -->

<div class="row g-3 mb-4">

<div class="col-md-3">
<div class="stats-card">
<h6>Current Patients</h6>
<h2><?= $totalCurrent ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="stats-card">
<h6>Diagnosed</h6>
<h2><?= $totalDiagnosed ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="stats-card">
<h6>Medication</h6>
<h2><?= $totalMedication ?></h2>
</div>
</div>

<div class="col-md-3">
<div class="stats-card">
<h6>Discharged</h6>
<h2><?= $totalDischarged ?></h2>
</div>
</div>

</div>

<!-- SEARCH + FILTER -->

<div class="card-box mb-3">

<div class="row g-2">

<div class="col-md-5">

<input
type="text"
id="searchBox"
class="form-control"
placeholder="🔍 Search patient or medication">

</div>

<div class="col-md-3">

<select
id="typeFilter"
class="form-select">

<option value="">
All Types
</option>

<option value="current">
Current Patient
</option>

<option value="walkin">
Walk-In
</option>

<option value="appointment">
Appointment
</option>

<option value="diagnosed">
Diagnosed
</option>

<option value="discharged">
Discharged
</option>

</select>

</div>

<div class="col-md-4">

<select
id="sortOrder"
class="form-select">

<option value="latest">
Newest First
</option>

<option value="oldest">
Oldest First
</option>

<option value="asc">
Name A-Z
</option>

<option value="desc">
Name Z-A
</option>

</select>

</div>

</div>

</div>

<!-- TABS -->

<div class="card-box">

<ul class="nav nav-tabs mb-3">

<li class="nav-item">

<button
class="nav-link active"
data-bs-toggle="tab"
data-bs-target="#current">

Current Patients

</button>

</li>

<li class="nav-item">

<button
class="nav-link"
data-bs-toggle="tab"
data-bs-target="#walkin">

Walk-In Patients

</button>

</li>

<li class="nav-item">

<button
class="nav-link"
data-bs-toggle="tab"
data-bs-target="#appointment">

Appointment Patients

</button>

</li>

<li class="nav-item">
<button
class="nav-link"
data-bs-toggle="tab"
data-bs-target="#diagnosed">
Diagnosed Patients
</button>
</li>

<li class="nav-item">
<button
class="nav-link"
data-bs-toggle="tab"
data-bs-target="#discharged">
Discharged Patients
</button>
</li>

</button>

</li>

</ul>

<div class="tab-content">

<!-- CURRENT -->

<div
class="tab-pane fade show active"
id="current">

<table
class="table table-bordered"
id="currentTable">

<thead>

<tr>

<th>Name</th>
<th>Ward</th>
<th>Bed</th>
<th>Admission Date</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach($currentList as $p): ?>

<tr>

<td><?= htmlspecialchars($p['NAME']) ?></td>

<td><?= htmlspecialchars($p['WARD_NAME']) ?></td>

<td><?= htmlspecialchars($p['BED_NUMBER']) ?></td>

<td><?= htmlspecialchars($p['ADMISSION_DATE']) ?></td>

<td>

<a
href="patient_details.php?id=<?= $p['ADMISSION_ID'] ?>"
class="btn btn-primary btn-sm">

View Details

</a>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="d-flex justify-content-between align-items-center mt-3">

<div>
Showing 1 to <?= count($currentList) ?> entries
</div>

<nav>

<ul class="pagination mb-0">

<li class="page-item disabled">
<a class="page-link">
Previous
</a>
</li>

<li class="page-item active">
<a class="page-link">
1
</a>
</li>

<li class="page-item disabled">
<a class="page-link">
Next
</a>
</li>

</ul>

</nav>

</div>

</div>

<div
class="tab-pane fade"
id="walkin">

<table class="table table-bordered">

<thead>

<tr>
<th>Patient</th>
<th>Date</th>
</tr>

</thead>

<tbody>

<?php foreach($walkinPatients as $w): ?>

<tr>

<td><?= $w['NAME'] ?></td>

<td><?= $w['CONSULTATION_DATE'] ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="d-flex justify-content-between align-items-center mt-3">

<div>
Showing 1 to <?= count($walkinPatients) ?> entries
</div>

<nav>
<ul class="pagination mb-0">

<li class="page-item disabled">
<a class="page-link">Previous</a>
</li>

<li class="page-item active">
<a class="page-link">1</a>
</li>

<li class="page-item disabled">
<a class="page-link">Next</a>
</li>

</ul>
</nav>

</div>

</div>

<div
class="tab-pane fade"
id="appointment">

<table class="table table-bordered">

<thead>

<tr>
<th>Patient</th>
<th>Date</th>
<th>Department</th>
<th>Status</th>
</tr>

</thead>

<tbody>

<?php foreach($appointmentPatients as $a): ?>

<tr>

<td><?= $a['NAME'] ?></td>

<td><?= $a['APPOINTMENT_DATE'] ?></td>

<td><?= $a['DEPARTMENT'] ?></td>

<td><?= $a['STATUS'] ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="d-flex justify-content-between mt-3">



<div>

Showing 1 to <?= count($appointmentPatients) ?> entries

</div>



<nav>

<ul class="pagination mb-0">



<li class="page-item disabled">

<a class="page-link">Previous</a>

</li>



<li class="page-item active">

<a class="page-link">1</a>

</li>



<li class="page-item disabled">

<a class="page-link">Next</a>

</li>



</ul>

</nav>



</div>

</div>


<!-- DIAGNOSED -->

<div
class="tab-pane fade"
id="diagnosed">

<table
class="table table-bordered"
id="diagnosedTable">

<thead>

<tr>

<th>Patient</th>
<th>Diagnosis</th>
<th>Date</th>

</tr>

</thead>

<tbody>

<?php foreach($diagnosedList as $d): ?>

<tr>

<td><?= htmlspecialchars($d['NAME']) ?></td>

<td><?= htmlspecialchars($d['DIAGNOSIS_DETAILS']) ?></td>

<td><?= htmlspecialchars($d['DATE_RECORDED']) ?></td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="d-flex justify-content-between align-items-center mt-3">

<div>
Showing 1 to <?= count($diagnosedList) ?> entries
</div>

<nav>
<ul class="pagination mb-0">

<li class="page-item disabled">
<a class="page-link">Previous</a>
</li>

<li class="page-item active">
<a class="page-link">1</a>
</li>

<li class="page-item disabled">
<a class="page-link">Next</a>
</li>

</ul>
</nav>

</div>

</div>

</div>

<!-- DISCHARGED -->

<div
class="tab-pane fade"
id="discharged">

<table
class="table table-bordered"
id="dischargedTable">

<thead>

<tr>
<th>Patient Name</th>
<th>Admission Date</th>
<th>Discharge Date</th>
<th>Length of Stay</th>
</tr>

</thead>

<tbody>

<?php foreach($dischargedList as $d): ?>

<?php

$days = ceil(
(
strtotime($d['DISCHARGE_DATE']) -
strtotime($d['ADMISSION_DATE'])
)
/
(60*60*24)
);

?>

<tr>

<td><?= htmlspecialchars($d['NAME']) ?></td>

<td><?= htmlspecialchars($d['ADMISSION_DATE']) ?></td>

<td><?= htmlspecialchars($d['DISCHARGE_DATE']) ?></td>

<td><?= $days ?> Day(s)</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

<div class="d-flex justify-content-between align-items-center mt-3">

<div>
Showing 1 to <?= count($dischargedList) ?> entries
</div>

<nav>
<ul class="pagination mb-0">

<li class="page-item disabled">
<a class="page-link">Previous</a>
</li>

<li class="page-item active">
<a class="page-link">1</a>
</li>

<li class="page-item disabled">
<a class="page-link">Next</a>
</li>

</ul>
</nav>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

/* SEARCH */

document
.getElementById('searchBox')
.addEventListener('keyup', function(){

let value =
this.value.toLowerCase();

document
.querySelectorAll('tbody tr')
.forEach(function(row){

row.style.display =
row.innerText
.toLowerCase()
.includes(value)
?
''
:
'none';

});

});

document
.getElementById('typeFilter')
.addEventListener('change', function(){

let type = this.value;

if(type == 'current'){
    document.querySelector('[data-bs-target="#current"]').click();
}

if(type == 'walkin'){
    document.querySelector('[data-bs-target="#walkin"]').click();
}

if(type == 'appointment'){
    document.querySelector('[data-bs-target="#appointment"]').click();
}

if(type == 'diagnosed'){
    document.querySelector('[data-bs-target="#diagnosed"]').click();
}

if(type == 'discharged'){
    document.querySelector('[data-bs-target="#discharged"]').click();
}

});

/* SORT */

document
.getElementById('sortOrder')
.addEventListener('change', function(){

let table =
document.querySelector('#currentTable tbody');

let rows =
Array.from(table.querySelectorAll('tr'));

let mode =
this.value;

rows.sort(function(a,b){

if(mode == 'asc'){

return a.cells[0].innerText
.localeCompare(
b.cells[0].innerText
);

}

if(mode == 'desc'){

return b.cells[0].innerText
.localeCompare(
a.cells[0].innerText
);

}

if(mode == 'latest'){

return new Date(
b.cells[3].innerText
)
-
new Date(
a.cells[3].innerText
);

}

if(mode == 'oldest'){

return new Date(
a.cells[3].innerText
)
-
new Date(
b.cells[3].innerText
);

}

});

rows.forEach(row =>
table.appendChild(row)
);

});

</script>

</body>
</html>