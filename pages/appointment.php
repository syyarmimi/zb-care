<?php
include("../config/config.php");

$message = "";

/* =========================
   FETCH DOCTORS
========================= */

$doctorSql = "
SELECT
       ACCOUNT_ID,
       USERNAME,
       DEPARTMENT
FROM SYARMIMI.HOSPITAL_STAFF
WHERE LOWER(ROLE)='doctor'
AND DEPARTMENT IS NOT NULL
ORDER BY DEPARTMENT, USERNAME
";

$doctorStmt = $conn->prepare($doctorSql);

$doctorStmt->execute();

$doctors = $doctorStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================
   BOOK APPOINTMENT
========================= */

if(isset($_POST['book'])){

    $patient_name     = $_POST['patient_name'];
    $ic_number = $_POST['ic_number'];
    $gender = $_POST['gender'];
    $phone            = $_POST['phone'];
    $email            = $_POST['email'];
    $department       = $_POST['department'];
    $doctorAccountId = $_POST['doctor'];
    $appointment_date = $_POST['appointment_date'];
    if(strtotime($appointment_date) < strtotime(date('Y-m-d'))){

$message =
"Appointment date cannot be earlier than today.";

}else{
    $appointment_time = $_POST['appointment_time'];
    $address          = $_POST['address'];
    $notes            = $_POST['notes'];

    $stmtDoctor = $conn->prepare("
SELECT USERNAME
FROM SYARMIMI.HOSPITAL_STAFF
WHERE ACCOUNT_ID = :id
");

$stmtDoctor->execute([
    ':id' => $doctorAccountId
]);

$doctorData = $stmtDoctor->fetch(PDO::FETCH_ASSOC);

$doctorName = "Dr. " . $doctorData['USERNAME'];

    try{

        $sql = "INSERT INTO SYARMIMI.APPOINTMENT (
    APPOINTMENT_ID,
    PATIENT_NAME,
    IC_NUMBER,
    GENDER,
    PHONE,
    EMAIL,
    DEPARTMENT,
    APPOINTMENT_DATE,
    NOTES,
    STATUS,
    DOCTOR_NAME,
    ACCOUNT_ID,
    APPOINTMENT_TIME,
    ADDRESS
)
VALUES (
    SYARMIMI.APPOINTMENT_SEQ.NEXTVAL,
    :patient_name,
    :ic_number,
    :gender,
    :phone,
    :email,
    :department,
    TO_DATE(:appointment_date,'YYYY-MM-DD'),
    :notes,
    'Pending',
    :doctor_name,
    :account_id,
    :appointment_time,
    :address
)";

        $stmt = $conn->prepare($sql);
$stmt->execute([
    ':patient_name' => $patient_name,
    ':ic_number' => $ic_number,
    ':gender' => $gender,
    ':phone' => $phone,
    ':email' => $email,
    ':department' => $department,
    ':appointment_date' => $appointment_date,
    ':notes' => $notes,
    ':doctor_name' => $doctorName,
    ':account_id' => $doctorAccountId,
    ':appointment_time' => $appointment_time,
    ':address' => $address
]);
        $message = "Appointment submitted successfully!";

  }catch(PDOException $e){

$message = "Error: " . $e->getMessage();

}

} // tutup else

} // tutup if(isset($_POST['book']))

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Make Appointment | ZB-CARE</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

body{
    background:#f3f6fb;
    font-family:'Segoe UI', sans-serif;
}

/* =========================
   NAVBAR
========================= */

.navbar{
    background:white;
    padding:18px 0;
    box-shadow:0 2px 15px rgba(0,0,0,0.05);
}

.navbar-brand{
    font-size:30px;
    font-weight:800;
    color:#0d6efd !important;
}

.home-btn{
    background:#0d6efd;
    color:white;
    padding:10px 24px;
    border-radius:40px;
    text-decoration:none;
    font-weight:600;
}

.home-btn:hover{
    background:#2563eb;
    color:white;
}

/* =========================
   HERO
========================= */

.hero{

    background:
    linear-gradient(rgba(15,23,42,0.6),
    rgba(15,23,42,0.6)),

    url('https://images.unsplash.com/photo-1586773860418-d37222d8fce3?q=80&w=1600&auto=format&fit=crop');

    background-size:cover;
    background-position:center;

    padding:100px 20px;

    text-align:center;

    color:white;
}

.hero h1{

    font-size:60px;

    font-weight:800;
}

.hero p{

    margin-top:20px;

    font-size:20px;

    color:#e2e8f0;
}

/* =========================
   FORM
========================= */

.form-box{

    background:white;

    padding:45px;

    border-radius:30px;

    margin-top:-60px;

    position:relative;

    z-index:10;

    box-shadow:0 10px 30px rgba(0,0,0,0.08);
}

.form-control,
.form-select{

    height:55px;

    border-radius:12px;
}

textarea.form-control{
    height:auto;
}

label{
    font-weight:600;
    margin-bottom:8px;
}

.btn-submit{

    background:#dc2626;

    color:white;

    border:none;

    padding:15px;

    border-radius:12px;

    font-weight:600;

    font-size:17px;
}

.btn-submit:hover{
    background:#b91c1c;
}

/* =========================
   FOOTER
========================= */

footer{

    background:#0f172a;

    color:white;

    padding:50px 0;

    margin-top:80px;
}

.navbar-brand{
    font-size:30px;
    font-weight:800;
    color:#0d6efd !important;
    cursor:pointer;
    text-decoration:none;
}

</style>

</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar navbar-expand-lg">

<div class="container">

<a href="../index.php"
class="navbar-brand text-decoration-none">

🏥 ZB-CARE

</a>

<div class="ms-auto">

<a href="../index.php" class="home-btn">
← Back Home
</a>

</div>

</div>

</nav>

<!-- =========================
     HERO
========================= -->

<div class="hero">

<h1>
Make An Appointment
</h1>

<p>
Book your specialist consultation online easily.
</p>

</div>

<!-- =========================
     FORM
========================= -->

<div class="container">

<div class="row justify-content-center">

<div class="col-md-10">

<div class="form-box">

<?php if($message != ""): ?>

<div class="alert <?= strpos($message,'Error') !== false || strpos($message,'cannot') !== false
? 'alert-danger'
: 'alert-success' ?>">

<?= $message ?>

</div>

<?php endif; ?>

<form method="POST">

<div class="row">

<!-- NAME -->
<div class="col-md-6 mb-4">

<label>Patient Name *</label>

<input
type="text"
name="patient_name"
class="form-control"
style="text-transform:uppercase"
oninput="this.value=this.value.toUpperCase()"
required>

</div>

<!-- PHONE -->
<div class="col-md-6 mb-4">

<label>Contact Number *</label>

<input
type="text"
id="phone"
name="phone"
class="form-control"
maxlength="12"
required>

</div>

<!-- EMAIL -->
<div class="col-md-6 mb-4">

<label>Email Address *</label>

<input type="email"
name="email"
class="form-control"
required>

</div>

<!-- IC NUMBER -->
<div class="col-md-6 mb-4">

<label>IC Number *</label>

<input
type="text"
id="ic_number"
name="ic_number"
class="form-control"
maxlength="14"
required>

</div>

<!-- GENDER -->
<div class="col-md-6 mb-4">

<label>Gender *</label>

<select
name="gender"
class="form-select"
required>

<option value="">Select Gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>

</select>

</div>

<!-- DEPARTMENT -->
<div class="col-md-6 mb-4">

<label>Specialist Department *</label>

<select name="department"
class="form-select"
required>

<option value="">Select Department</option>

<?php

$departments = $conn->query("
SELECT DISTINCT DEPARTMENT
FROM SYARMIMI.HOSPITAL_STAFF
WHERE LOWER(ROLE)='doctor'
AND DEPARTMENT IS NOT NULL
ORDER BY DEPARTMENT
");

while($dep = $departments->fetch(PDO::FETCH_ASSOC)){

echo "
<option value='{$dep['DEPARTMENT']}'>
{$dep['DEPARTMENT']}
</option>
";

}

?>

</select>

</div>

<!-- DOCTOR -->
<div class="col-md-6 mb-4">

<label>Doctor *</label>

<select name="doctor"
class="form-select"
required>

<option value="">Select Doctor</option>

<?php foreach($doctors as $doc): ?>

<option value="<?= $doc['ACCOUNT_ID'] ?>">
    Dr. <?= $doc['USERNAME'] ?> - <?= $doc['DEPARTMENT'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

<!-- DATE -->
<div class="col-md-6 mb-4">

<label>Appointment Date *</label>

<input
type="date"
id="appointment_date"
name="appointment_date"
class="form-control"
required>

</div>

<!-- TIME -->
<div class="col-md-6 mb-4">

<label>Appointment Time *</label>

<select
name="appointment_time"
class="form-select"
required>

<option value="">
Select Appointment Time
</option>

<option value="08:00 AM">08:00 AM</option>
<option value="09:00 AM">09:00 AM</option>
<option value="10:00 AM">10:00 AM</option>
<option value="11:00 AM">11:00 AM</option>
<option value="12:00 PM">12:00 PM</option>
<option value="02:00 PM">02:00 PM</option>
<option value="03:00 PM">03:00 PM</option>
<option value="04:00 PM">04:00 PM</option>

</select>

</div>

<!-- ADDRESS -->
<div class="col-md-12 mb-4">

<label>Address *</label>

<textarea
name="address"
rows="3"
class="form-control"
style="text-transform:uppercase"
oninput="this.value=this.value.toUpperCase()"
required></textarea>

</div>

<!-- NOTES -->
<div class="col-md-12 mb-4">

<label>Remarks / Symptoms</label>

<textarea
name="notes"
rows="5"
class="form-control"
style="text-transform:uppercase"
oninput="this.value=this.value.toUpperCase()"
placeholder="Describe symptoms or additional notes"></textarea>

</div>

<!-- NOTICE -->
<div class="col-md-12 mb-4">

<div class="alert alert-warning">

<b>Please Note:</b><br>

Submitting this form does not immediately confirm your appointment.
Hospital staff will contact you for confirmation.

</div>

</div>

<!-- BUTTON -->
<div class="col-md-12">

<button type="submit"
name="book"
class="btn btn-submit w-100">

Submit Appointment

</button>

</div>

</div>

</form>

</div>

</div>

</div>

</div>

<!-- =========================
     FOOTER
========================= -->

<footer>

<div class="container text-center">

<h4>
🏥 ZB-CARE Specialist Hospital
</h4>

<p class="mt-3 text-light">

Orthopaedics • Paediatrics • Dietitian & Nutrition

</p>

<p class="text-secondary">

© 2026 ZB-CARE. All Rights Reserved.

</p>

</div>

</footer>

<script>

document.getElementById('appointment_date').min =
new Date().toISOString().split('T')[0];

</script>

<script>

document.getElementById('ic_number')
.addEventListener('input', function(){

let value = this.value.replace(/\D/g,'');

if(value.length > 6){
value =
value.substring(0,6)
+
'-'
+
value.substring(6);
}

if(value.length > 9){
value =
value.substring(0,9)
+
'-'
+
value.substring(9);
}

this.value = value;

});

</script>

<script>

document.getElementById('phone')
.addEventListener('input', function(){

let value = this.value.replace(/\D/g,'');

if(value.length > 3){
value =
value.substring(0,3) +
'-' +
value.substring(3);
}

if(value.length > 7){
value =
value.substring(0,7) +
'-' +
value.substring(7);
}

this.value = value;

});

</script>

</body>
</html>