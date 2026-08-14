<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

/* =========================================
   SEARCH PATIENT BY IC
========================================= */

$patientData = null;

if(isset($_POST['search_ic'])){

    $ic = trim($_POST['search_ic_number']);

    $stmt = $conn->prepare("
        SELECT *
        FROM SYARMIMI.PATIENT
        WHERE IC_NUMBER = ?
    ");

    $stmt->execute([$ic]);

    $patientData = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================================
   REGISTER PATIENT
========================================= */

if(isset($_POST['register_patient'])){

    $ic      = $_POST['ic'];
    $name    = $_POST['name'];
    $age     = $_POST['age'];
    $gender  = $_POST['gender'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];

    try{

        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.PATIENT
            WHERE IC_NUMBER = ?
        ");

        $check->execute([$ic]);

        if($check->fetchColumn() > 0){

            echo "<script>alert('Patient already exists!');</script>";

        }else{

            $stmt = $conn->query("
                SELECT NVL(MAX(PATIENT_ID),0)+1 AS NEW_ID
                FROM SYARMIMI.PATIENT
            ");

            $newId = $stmt->fetch(PDO::FETCH_ASSOC)['NEW_ID'];

            $sql = "
                INSERT INTO SYARMIMI.PATIENT
                (
                    PATIENT_ID,
                    IC_NUMBER,
                    NAME,
                    AGE,
                    GENDER,
                    PHONE,
                    ADDRESS
                )
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                $newId,
                $ic,
                $name,
                $age,
                $gender,
                $phone,
                $address
            ]);

            echo "<script>alert('Patient Registered Successfully');</script>";
        }

    }catch(PDOException $e){

        die($e->getMessage());

    }
}

/* =========================================
   CREATE ADMISSION
========================================= */

if(isset($_POST['submit'])){

    $patient = $_POST['patient_id'];
    $bed     = $_POST['bed'];
    $doctor  = $_POST['doctor'];

    try{

        // CHECK ACTIVE ADMISSION
        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.ADMISSION
            WHERE PATIENT_ID = ?
            AND DISCHARGE_DATE IS NULL
        ");

        $check->execute([$patient]);

        if($check->fetchColumn() > 0){

            echo "<script>alert('Patient still admitted!');</script>";

        }else{

            $stmt = $conn->query("
                SELECT NVL(MAX(ADMISSION_ID),0)+1 AS NEW_ID
                FROM SYARMIMI.ADMISSION
            ");

            $newId = $stmt->fetch(PDO::FETCH_ASSOC)['NEW_ID'];

            $sql = "
                INSERT INTO SYARMIMI.ADMISSION
                (
                    ADMISSION_ID,
                    PATIENT_ID,
                    BED_ID,
                    ACCOUNT_ID,
                    ADMISSION_DATE,
                    IS_SEEN
                )
                VALUES
                (
                    :id,
                    :patient,
                    :bed,
                    :doctor,
                    SYSDATE,
                    0
                )
            ";

            $stmt = $conn->prepare($sql);

            $stmt->execute([
                ':id' => $newId,
                ':patient' => $patient,
                ':bed' => $bed,
                ':doctor' => $doctor
            ]);

            // INSERT DEFAULT DIAGNOSIS RECORD
            $diagSql = "
                INSERT INTO SYARMIMI.DIAGNOSIS
                (
                    DIAGNOSIS_ID,
                    DIAGNOSIS_DETAILS,
                    ALLERGIES,
                    DATE_RECORDED,
                    ADMISSION_ID,
                    ACCOUNT_ID
                )
                VALUES
                (
                    (SELECT NVL(MAX(DIAGNOSIS_ID),0)+1 FROM SYARMIMI.DIAGNOSIS),
                    '-',
                    '-',
                    SYSDATE,
                    :admission,
                    :doctor
                )
            ";

            $diagStmt = $conn->prepare($diagSql);

            $diagStmt->execute([
                ':admission' => $newId,
                ':doctor' => $doctor
            ]);

            // UPDATE BED
            $conn->prepare("
                UPDATE SYARMIMI.BED
                SET STATUS='Occupied'
                WHERE BED_ID=:bed
            ")->execute([
                ':bed' => $bed
            ]);

            echo "<script>alert('Admission Added Successfully');</script>";
        }

    }catch(PDOException $e){

        die($e->getMessage());

    }
}

/* =========================================
   DISCHARGE
========================================= */

if(isset($_GET['discharge'])){

    $id = $_GET['discharge'];

    try{

        $stmt = $conn->prepare("
            SELECT BED_ID
            FROM SYARMIMI.ADMISSION
            WHERE ADMISSION_ID=:id
        ");

        $stmt->execute([
            ':id' => $id
        ]);

        $bed = $stmt->fetch(PDO::FETCH_ASSOC);

        $conn->prepare("
            UPDATE SYARMIMI.ADMISSION
            SET DISCHARGE_DATE = SYSDATE
            WHERE ADMISSION_ID=:id
        ")->execute([
            ':id' => $id
        ]);

        $conn->prepare("
            UPDATE SYARMIMI.BED
            SET STATUS='Available'
            WHERE BED_ID=:bed
        ")->execute([
            ':bed' => $bed['BED_ID']
        ]);

        echo "<script>alert('Patient Discharged');window.location='admission.php';</script>";

    }catch(PDOException $e){

        die($e->getMessage());

    }
}

/* =========================================
   SEARCH + SORT
========================================= */

$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'DESC';

?>

<!DOCTYPE html>
<html>
<head>

<title>Admission Workflow</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<style>

body{
    background:#f4f6f9;
    font-family:'Segoe UI';
}

.form-box,
.table-box{
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0px 4px 10px rgba(0,0,0,0.08);
    margin-top:20px;
}

.sidebar{
width:260px !important;
min-width:260px !important;
max-width:260px !important;
height:100vh;
flex-shrink:0;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h2>🏥 Patient Admission Workflow</h2>

<!-- SEARCH -->
<div class="form-box">

<h5>🔍 Search Patient by IC</h5>

<form method="POST">

<div class="row">

<div class="col-md-10">
<input type="text"
name="search_ic_number"
class="form-control"
placeholder="Enter IC Number"
required>
</div>

<div class="col-md-2">
<button class="btn btn-primary w-100"
name="search_ic">
Search
</button>
</div>

</div>

</form>

<?php if($patientData): ?>

<div class="alert alert-success mt-4">

<b>Patient Found:</b><br><br>

<?= $patientData['NAME'] ?><br>
IC: <?= $patientData['IC_NUMBER'] ?><br>
Phone: <?= $patientData['PHONE'] ?>

</div>

<?php endif; ?>

</div>

<!-- REGISTER -->
<div class="form-box">

<h5>👤 Register New Patient</h5>

<form method="POST">

<div class="row">

<div class="col-md-4 mb-3">
<input type="text" name="ic"
class="form-control"
placeholder="IC Number" required>
</div>

<div class="col-md-4 mb-3">
<input type="text" name="name"
class="form-control"
placeholder="Patient Name" required>
</div>

<div class="col-md-4 mb-3">
<input type="number" name="age"
class="form-control"
placeholder="Age" required>
</div>

<div class="col-md-4 mb-3">
<select name="gender"
class="form-control" required>

<option value="">Gender</option>
<option>Male</option>
<option>Female</option>

</select>
</div>

<div class="col-md-4 mb-3">
<input type="text" name="phone"
class="form-control"
placeholder="Phone" required>
</div>

<div class="col-md-4 mb-3">
<input type="text" name="address"
class="form-control"
placeholder="Address" required>
</div>

<div class="col-md-12">
<button class="btn btn-success"
name="register_patient">
Register Patient
</button>
</div>

</div>

</form>

</div>

<!-- CREATE ADMISSION -->
<div class="form-box">

<h5>🛏️ Create Admission</h5>

<form method="POST">

<div class="row">

<div class="col-md-3">

<label>Patient</label>

<select name="patient_id"
class="form-control" required>

<option value="">Select Patient</option>

<?php

$patients = $conn->query("
SELECT *
FROM SYARMIMI.PATIENT
ORDER BY NAME
");

while($p = $patients->fetch(PDO::FETCH_ASSOC)){

echo "
<option value='{$p['PATIENT_ID']}'>
{$p['NAME']} ({$p['IC_NUMBER']})
</option>
";

}

?>

</select>

</div>

<div class="col-md-3">

<label>Bed</label>

<select name="bed"
class="form-control" required>

<option value="">Select Bed</option>

<?php

$beds = $conn->query("
SELECT B.BED_ID,
       B.BED_NUMBER,
       W.WARD_NAME
FROM SYARMIMI.BED B
JOIN SYARMIMI.WARD W
ON B.WARD_ID = W.WARD_ID
WHERE B.STATUS='Available'
");

while($b = $beds->fetch(PDO::FETCH_ASSOC)){

echo "
<option value='{$b['BED_ID']}'>
Bed {$b['BED_NUMBER']} ({$b['WARD_NAME']})
</option>
";

}

?>

</select>

</div>

<div class="col-md-5">

<label>Doctor</label>

<select name="doctor"
class="form-control" required>

<option value="">Select Doctor</option>

<?php

$doctors = $conn->query("
SELECT *
FROM SYARMIMI.HOSPITAL_STAFF
WHERE ROLE='doctor'
ORDER BY USERNAME
");

while($d = $doctors->fetch(PDO::FETCH_ASSOC)){

echo "
<option value='{$d['ACCOUNT_ID']}'>
Dr. {$d['USERNAME']} ({$d['DEPARTMENT']})
</option>
";

}

?>

</select>

</div>


<div class="col-md-1 d-flex align-items-end">

<button class="btn btn-primary w-100"
name="submit">
Add
</button>

</div>

</div>

</form>

</div>

<!-- FILTER -->
<div class="table-box" id="inpatientBox">

</div>

<!-- INPATIENT -->
<div class="table-box">

<div class="row mb-3">

<div class="col-md-4">

<div class="input-group">

<span class="input-group-text">
🔍
</span>

<input
type="text"
id="globalSearch"
class="form-control"
placeholder="Search patient">

</div>

</div>

<div class="col-md-4">

<select
id="typeFilter"
class="form-select">

<option value="">
All Types
</option>

<option value="Inpatient">
Inpatient
</option>

<option value="Appointment">
Appointment
</option>

<option value="Walk-In">
Walk-In
</option>

</select>

</div>

<div class="col-md-4">

<select
id="sortFilter"
class="form-select">


<option value="latest">
Newest First
</option>

<option value="oldest">
Oldest First
</option>

</select>

</div>

</div>

<h4>🛏️ List Of Inpatients and Outpatients</h4>

<table
id="patientTable"
class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Patient</th>
<th>Type</th>
<th>Doctor</th>
<th>Ward / Department</th>
<th>Date</th>
<th>Status</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php

$sql = "

SELECT
A.ADMISSION_ID AS ID,
P.NAME AS PATIENT_NAME,
P.IC_NUMBER,
'Inpatient' AS TYPE,
S.USERNAME AS DOCTOR_NAME,
W.WARD_NAME AS LOCATION,
A.ADMISSION_DATE AS RECORD_DATE,
'Admitted' AS STATUS

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID

JOIN SYARMIMI.BED B
ON A.BED_ID = B.BED_ID

JOIN SYARMIMI.WARD W
ON B.WARD_ID = W.WARD_ID

LEFT JOIN SYARMIMI.HOSPITAL_STAFF S
ON A.ACCOUNT_ID = S.ACCOUNT_ID

WHERE A.DISCHARGE_DATE IS NULL

";

$stmt = $conn->query($sql);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

?>

<tr>

<td><?= $row['ID'] ?></td>

<td>
<?= $row['PATIENT_NAME'] ?><br>
<small><?= $row['IC_NUMBER'] ?></small>
</td>

<td>
<span class="badge bg-primary">
Inpatient
</span>
</td>

<td>
Dr. <?= $row['DOCTOR_NAME'] ?>
</td>

<td><?= $row['LOCATION'] ?></td>

<td><?= $row['RECORD_DATE'] ?></td>

<td>
<span class="badge bg-success">
Admitted
</span>
</td>

<td>

<a
href="?discharge=<?= $row['ID'] ?>"
class="btn btn-danger btn-sm">

Discharge

</a>

</td>

</tr>

<?php } ?>

<?php

$sql = "

SELECT
APPOINTMENT_ID AS ID,
PATIENT_NAME,
IC_NUMBER,
'Appointment' AS TYPE,
DOCTOR_NAME,
DEPARTMENT AS LOCATION,
APPOINTMENT_DATE AS RECORD_DATE,
STATUS

FROM SYARMIMI.APPOINTMENT

";

$stmt = $conn->query($sql);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

?>

<tr>

<td><?= $row['ID'] ?></td>

<td>
<?= $row['PATIENT_NAME'] ?><br>
<small><?= $row['IC_NUMBER'] ?></small>
</td>

<td>
<span class="badge bg-warning">
Appointment
</span>
</td>

<td>
<?= $row['DOCTOR_NAME'] ?>
</td>

<td><?= $row['LOCATION'] ?></td>

<td><?= $row['RECORD_DATE'] ?></td>

<td>

<?php

if($row['STATUS']=='Approved'){

echo "<span class='badge bg-success'>Approved</span>";

}
elseif($row['STATUS']=='Pending'){

echo "<span class='badge bg-warning'>Pending</span>";

}
else{

echo "<span class='badge bg-danger'>Rejected</span>";

}

?>

</td>

<td>-</td>

</tr>



<?php } ?>

<?php

$sql = "

SELECT
W.CONSULTATION_ID AS ID,
P.NAME AS PATIENT_NAME,
P.IC_NUMBER,
'Walk-In' AS TYPE,
S.USERNAME AS DOCTOR_NAME,
W.DEPARTMENT AS LOCATION,
W.CONSULTATION_DATE AS RECORD_DATE,
W.STATUS

FROM SYARMIMI.WALKIN_CONSULTATION W

LEFT JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID

LEFT JOIN SYARMIMI.HOSPITAL_STAFF S
ON W.ACCOUNT_ID = S.ACCOUNT_ID

";

$stmt = $conn->query($sql);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

?>

<tr>

<td><?= $row['ID'] ?></td>

<td>
<?= $row['PATIENT_NAME'] ?><br>
<small><?= $row['IC_NUMBER'] ?></small>
</td>

<td>
<span class="badge bg-info">
Walk-In
</span>
</td>

<td>
Dr. <?= $row['DOCTOR_NAME'] ?>
</td>

<td><?= $row['LOCATION'] ?></td>

<td><?= $row['RECORD_DATE'] ?></td>

<td>

<?php

if($row['STATUS']=='Completed'){

echo "<span class='badge bg-success'>Completed</span>";

}
elseif($row['STATUS']=='Pending'){

echo "<span class='badge bg-warning'>Pending</span>";

}
else{

echo "<span class='badge bg-secondary'>{$row['STATUS']}</span>";

}

?>

</td>

<td>-</td>

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

let patientTable = $('#patientTable').DataTable({

searching:true,
info:true,
pageLength:10,

order:[[0,'desc']],

lengthMenu:[
[10,25,50,100],
[10,25,50,100]
],

dom:'frtip'

});

$('#globalSearch').on('keyup', function(){

patientTable.search($(this).val()).draw();

});

$('#typeFilter').on('change', function(){

let type = $(this).val();

if(type == ''){

patientTable.column(2).search('').draw();

}else{

patientTable.column(2)
.search(type, true, false)
.draw();

}

});

$('#sortFilter').on('change', function(){

if($(this).val() == 'latest'){

patientTable.order([0,'desc']).draw();

}else{

patientTable.order([0,'asc']).draw();

}

});

});

</script>

</body>
</html>