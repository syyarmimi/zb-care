<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

/* =========================================
   SEARCH PATIENT
========================================= */

$patientData = null;

if(isset($_POST['search_patient'])){

    $ic = trim($_POST['ic_search']);

    $stmt = $conn->prepare("
        SELECT *
        FROM SYARMIMI.PATIENT
        WHERE IC_NUMBER = ?
    ");

    $stmt->execute([$ic]);

    $patientData = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* =========================================
   QUICK REGISTER
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

            echo "
<script>

Swal.fire({
    icon: 'warning',
    title: 'Patient Already Exists',
    text: 'A patient with this IC number is already registered.',
    confirmButtonColor: '#ffc107'
});

</script>
";

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

echo "
<script>

Swal.fire({
    icon: 'success',
    title: 'Patient Registered',
    text: 'The patient has been registered successfully.',
    confirmButtonColor: '#198754',
    confirmButtonText: 'Continue'
}).then(() => {
    window.location='walkin_consultation.php';
});

</script>
";
        }

    }catch(PDOException $e){

        die($e->getMessage());

    }
}

/* =========================================
   CREATE WALK-IN CONSULTATION
========================================= */

if(isset($_POST['create_consultation'])){

    $patient_id = $_POST['patient_id'];
    $account_id = $_POST['doctor']; 
    $department = $_POST['department'];
    $notes      = $_POST['notes'];

    try{

        $stmt = $conn->query("
            SELECT NVL(MAX(CONSULTATION_ID),0)+1 AS NEW_ID
            FROM SYARMIMI.WALKIN_CONSULTATION
        ");

        $newId = $stmt->fetch(PDO::FETCH_ASSOC)['NEW_ID'];

        $sql = "
            INSERT INTO SYARMIMI.WALKIN_CONSULTATION
            (
                CONSULTATION_ID,
                PATIENT_ID,
                ACCOUNT_ID,
                CONSULTATION_DATE,
                STATUS,
                NOTES,
                DEPARTMENT,
                DIAGNOSIS
            )
            VALUES
            (
                :id,
                :patient,
                :doctor,
                SYSDATE,
                'Assigned',
                :notes,
                :department,
                '-'
            )
        ";

        $stmt = $conn->prepare($sql);

        $stmt->execute([
            ':id'         => $newId,
            ':patient'    => $patient_id,
            ':doctor'     => $account_id,
            ':notes'      => $notes,
            ':department' => $department
        ]);

        echo "
<script>

Swal.fire({
    icon: 'success',
    title: 'Consultation Assigned',
    text: 'Patient has been assigned to the selected doctor.',
    confirmButtonColor: '#0d6efd'
});

</script>
";

    }catch(PDOException $e){

        die($e->getMessage());

    }
}

?>

<!DOCTYPE html>
<html>
<head>

<title>Walk-In Consultation</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>

body{
    background:#f4f6f9;
    font-family:'Segoe UI';
}

.form-box{
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 4px 10px rgba(0,0,0,0.08);
    margin-top:20px;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h2>🩺 Walk-In Consultation</h2>

<!-- SEARCH -->
<div class="form-box">

<h5>🔍 Search Patient by IC</h5>

<form method="POST">

<div class="row">

<div class="col-md-10">
<input type="text"
name="ic_search"
class="form-control"
placeholder="Enter IC Number"
required>
</div>

<div class="col-md-2">
<button class="btn btn-primary w-100"
name="search_patient">
Search
</button>
</div>

</div>

</form>

<?php if($patientData): ?>

<div class="alert alert-success mt-4">

Patient found.

</div>

<div class="card border-0 shadow-sm mt-3">

<div class="card-body">

<h5 class="fw-bold text-success">
Patient Information
</h5>

<hr>

<p>
<strong>Name:</strong>
<?= $patientData['NAME'] ?>
</p>

<p>
<strong>IC Number:</strong>
<?= $patientData['IC_NUMBER'] ?>
</p>

<p>
<strong>Phone:</strong>
<?= $patientData['PHONE'] ?>
</p>

</div>

</div>

<?php elseif(isset($_POST['search_patient'])): ?>

<div class="alert alert-danger mt-4">

Patient not found.
Please register patient first.

</div>

<div class="form-box">

<h5>
👤 Register New Patient
</h5>

<form method="POST">

<div class="row">

<div class="col-md-4 mb-3">
<input
type="text"
name="ic"
class="form-control"
placeholder="IC Number"
value="<?= htmlspecialchars($_POST['ic_search']) ?>"
required>
</div>

<div class="col-md-4 mb-3">
<input
type="text"
name="name"
class="form-control"
placeholder="Patient Name"
required>
</div>

<div class="col-md-4 mb-3">
<input
type="number"
name="age"
class="form-control"
placeholder="Age"
required>
</div>

<div class="col-md-4 mb-3">

<select
name="gender"
class="form-control"
required>

<option value="">
Select Gender
</option>

<option value="Male">
Male
</option>

<option value="Female">
Female
</option>

</select>

</div>

<div class="col-md-4 mb-3">

<input
type="text"
name="phone"
class="form-control"
placeholder="Phone Number"
required>

</div>

<div class="col-md-4 mb-3">

<input
type="text"
name="address"
class="form-control"
placeholder="Address"
required>

</div>

<div class="col-md-12">

<button
type="submit"
name="register_patient"
class="btn btn-success">

Register Patient

</button>

</div>

</div>

</form>

</div>

<?php endif; ?>

</div>

<?php if($patientData): ?>

<?php

$countDoctor = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE STATUS='Available'
AND TRUNC(AVAILABLE_DATE)=TRUNC(SYSDATE)
")->fetchColumn();

?>

<div class="card bg-success text-white border-0 mt-3 mb-3">

<div class="card-body text-center">

<h2><?= $countDoctor ?></h2>

<h5>Available Doctors Today</h5>

</div>

</div>

<div class="form-box">

<h5>🩺 Create Consultation</h5>

<form method="POST">
    

<input
type="hidden"
name="patient_id"
value="<?= $patientData['PATIENT_ID'] ?? '' ?>">

<div class="row">

<div class="col-md-6 mb-3">

<label>Department</label>

<select name="department"
class="form-control"
required>

<option value="">Select Department</option>

<option>Orthopaedics</option>
<option>Paediatrics</option>
<option>Dietitian & Nutrition</option>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Doctor</label>

<select name="doctor"
class="form-control"
required>

<option value="">Select Doctor</option>

<?php
$doctors = $conn->query("
SELECT
    H.ACCOUNT_ID,
    H.USERNAME,
    H.DEPARTMENT,
    A.START_TIME,
    A.END_TIME

FROM SYARMIMI.HOSPITAL_STAFF H

JOIN SYARMIMI.DOCTOR_AVAILABILITY A
ON H.ACCOUNT_ID = A.ACCOUNT_ID

WHERE H.ROLE='doctor'
AND A.STATUS='Available'
AND TRUNC(A.AVAILABLE_DATE)=TRUNC(SYSDATE)

ORDER BY H.USERNAME
");

while($d = $doctors->fetch(PDO::FETCH_ASSOC)){

echo "
<option value='{$d['ACCOUNT_ID']}'>
Dr. {$d['USERNAME']} |
{$d['DEPARTMENT']} |
{$d['START_TIME']} - {$d['END_TIME']}
</option>
";

}
?>

</select>

</div>

<!-- ADD HERE -->

<div class="col-md-12">

<div class="alert alert-info">

<h6 class="mb-3">
🩺 Today's Available Doctors
</h6>

<table class="table table-bordered table-sm">

<thead class="table-light">

<tr>
<th>Doctor</th>
<th>Department</th>
<th>Available Time</th>
</tr>

</thead>

<tbody>

<?php

$doctorList = $conn->query("
SELECT
    H.USERNAME,
    H.DEPARTMENT,
    A.START_TIME,
    A.END_TIME

FROM SYARMIMI.HOSPITAL_STAFF H

JOIN SYARMIMI.DOCTOR_AVAILABILITY A
ON H.ACCOUNT_ID=A.ACCOUNT_ID

WHERE H.ROLE='doctor'
AND A.STATUS='Available'
AND TRUNC(A.AVAILABLE_DATE)=TRUNC(SYSDATE)

ORDER BY H.USERNAME
");

while($doc = $doctorList->fetch(PDO::FETCH_ASSOC)){

echo "
<tr>
<td>Dr. {$doc['USERNAME']}</td>
<td>{$doc['DEPARTMENT']}</td>
<td>{$doc['START_TIME']} - {$doc['END_TIME']}</td>
</tr>
";

}

?>

</tbody>

</table>

</div>

</div>

<!-- END ADD HERE -->


<div class="col-md-12 mb-3">

<label>Symptoms / Notes</label>

<textarea name="notes"
class="form-control"
rows="4"></textarea>

</div>

<div class="col-md-12">

<button class="btn btn-primary"
name="create_consultation">

Create Consultation

</button>

</div>

</div>

</form>

</div>

<?php endif; ?>

</div>

</div>

</body>
</html>