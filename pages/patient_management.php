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
    A.ADMISSION_DATE

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
   DIAGNOSED PATIENTS
====================================== */

$sql = "

SELECT
    D.DIAGNOSIS_ID,
    P.NAME,
    D.DIAGNOSIS_DETAILS,
    D.DATE_RECORDED

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
    A.ADMISSION_DATE,
    A.DISCHARGE_DATE

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
placeholder="Search patient name...">

</div>

<div class="col-md-3">

<select
id="monthFilter"
class="form-select">

<option value="">
All Months
</option>

<option value="01">January</option>
<option value="02">February</option>
<option value="03">March</option>
<option value="04">April</option>
<option value="05">May</option>
<option value="06">June</option>
<option value="07">July</option>
<option value="08">August</option>
<option value="09">September</option>
<option value="10">October</option>
<option value="11">November</option>
<option value="12">December</option>

</select>

</div>

<div class="col-md-4">

<select
id="sortOrder"
class="form-select">

<option value="asc">
Name A-Z
</option>

<option value="desc">
Name Z-A
</option>

<option value="latest">
Latest Admission
</option>

<option value="oldest">
Oldest Admission
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

/* MONTH FILTER */

document
.getElementById('monthFilter')
.addEventListener('change', function(){

let month =
this.value;

document
.querySelectorAll('#currentTable tbody tr')
.forEach(function(row){

let dateText =
row.cells[3].innerText;

if(month == ''){

row.style.display = '';

return;

}

let rowMonth =
new Date(dateText)
.getMonth() + 1;

rowMonth =
String(rowMonth)
.padStart(2,'0');

if(rowMonth == month){

row.style.display = '';

}else{

row.style.display = 'none';

}

});

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