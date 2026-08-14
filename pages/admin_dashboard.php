<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: /zb-care/auth/login.php");
    exit();
}

/* ================= KPI ================= */

function getCount($conn, $sql){
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchColumn();
}

$patients = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.PATIENT
");

$staff = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.HOSPITAL_STAFF
");

$outpatients = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
");

$inpatients = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.ADMISSION
WHERE DISCHARGE_DATE IS NULL
");

$appointments = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Pending'
");

/* Approved Appointment */
$approvedAppointments = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Approved'
");

/* Pending Appointment */
$pendingAppointments = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Pending'
");

$walkin = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.WALKIN_CONSULTATION
");

/* DOCTOR AVAILABLE TODAY */

$todayDoctor = $conn->query("
SELECT hs.USERNAME,
       da.START_TIME,
       da.END_TIME
FROM SYARMIMI.DOCTOR_AVAILABILITY da
JOIN SYARMIMI.HOSPITAL_STAFF hs
ON da.ACCOUNT_ID = hs.ACCOUNT_ID
WHERE da.AVAILABLE_DATE = TRUNC(SYSDATE)
AND da.STATUS='Available'
FETCH FIRST 1 ROWS ONLY
")->fetch(PDO::FETCH_ASSOC);

/* TODAY APPOINTMENTS */

$availableDoctors = $conn->query("
SELECT COUNT(DISTINCT ACCOUNT_ID)
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE AVAILABLE_DATE = TRUNC(SYSDATE)
AND STATUS='Available'
")->fetchColumn();

$unavailableDoctors = $conn->query("
SELECT COUNT(DISTINCT ACCOUNT_ID)
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE AVAILABLE_DATE = TRUNC(SYSDATE)
AND STATUS='Unavailable'
")->fetchColumn();

/* ================= BED ================= */

$bed = $conn->query("
SELECT COUNT(*) TOTAL,
SUM(CASE WHEN STATUS='Occupied' THEN 1 ELSE 0 END) USED
FROM SYARMIMI.BED
")->fetch(PDO::FETCH_ASSOC);

$bedUsage = round(($bed['USED'] / $bed['TOTAL']) * 100);

$patientFlowLabels = [
'Walk-In',
'Appointment',
'Admitted'
];

$patientFlowValues = [
$walkin,
$outpatients,
$inpatients
];

/* ================= GENDER ================= */

$genderData = $conn->query("
SELECT GENDER, COUNT(*) TOTAL
FROM SYARMIMI.PATIENT
WHERE GENDER IS NOT NULL
GROUP BY GENDER
")->fetchAll(PDO::FETCH_ASSOC);

$genderLabels = [];
$genderValues = [];

foreach($genderData as $g){

    $genderLabels[] = $g['GENDER'];
    $genderValues[] = $g['TOTAL'];

}

$admittedToday = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.ADMISSION
WHERE TRUNC(ADMISSION_DATE)=TRUNC(SYSDATE)
");

$dischargedToday = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.ADMISSION
WHERE TRUNC(DISCHARGE_DATE)=TRUNC(SYSDATE)
");

$walkinToday = getCount($conn,"
SELECT COUNT(*)
FROM SYARMIMI.WALKIN_CONSULTATION
WHERE TRUNC(CONSULTATION_DATE)=TRUNC(SYSDATE)
");

/* ================= TREND ================= */

$trend = $conn->query("
SELECT
TO_CHAR(ADMISSION_DATE,'DD Mon') DAY,
COUNT(*) TOTAL

FROM SYARMIMI.ADMISSION

WHERE ADMISSION_DATE >= TRUNC(SYSDATE)-7

GROUP BY TO_CHAR(ADMISSION_DATE,'DD Mon')

ORDER BY MIN(ADMISSION_DATE)
")->fetchAll(PDO::FETCH_ASSOC);

$trendLabels = [];
$trendValues = [];

foreach($trend as $t){

    $trendLabels[] = $t['DAY'];
    $trendValues[] = $t['TOTAL'];

}

/* ================= STAFF ROLE ================= */

$staffRole = $conn->query("
SELECT ROLE, COUNT(*) TOTAL
FROM SYARMIMI.HOSPITAL_STAFF
GROUP BY ROLE
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= WARD SUMMARY ================= */

$wardSummary = $conn->query("
SELECT W.WARD_NAME,
COUNT(B.BED_ID) TOTAL_BED,
SUM(CASE WHEN B.STATUS='Available' THEN 1 ELSE 0 END) AVAILABLE
FROM SYARMIMI.WARD W
LEFT JOIN SYARMIMI.BED B
ON W.WARD_ID = B.WARD_ID
GROUP BY W.WARD_NAME
")->fetchAll(PDO::FETCH_ASSOC);

$topMedication = $conn->query("

SELECT
m.MEDICATION_NAME,
COUNT(*) TOTAL

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.MEDICATION m
ON mo.MEDICATION_ID = m.MEDICATION_ID

GROUP BY m.MEDICATION_NAME

ORDER BY TOTAL DESC

FETCH FIRST 5 ROWS ONLY

")->fetchAll(PDO::FETCH_ASSOC);

$medLabels = [];
$medValues = [];

foreach($topMedication as $m){

    $medLabels[] = $m['MEDICATION_NAME'];
    $medValues[] = $m['TOTAL'];

}

/* ================= RECENT ================= */

$sql = "
SELECT a.ADMISSION_ID, p.NAME
FROM SYARMIMI.ADMISSION a
JOIN SYARMIMI.PATIENT p
ON a.PATIENT_ID = p.PATIENT_ID
ORDER BY a.ADMISSION_ID DESC
";

$patientsList = $conn->query("
SELECT
PATIENT_ID,
NAME,
GENDER
FROM SYARMIMI.PATIENT
ORDER BY PATIENT_ID DESC
FETCH FIRST 6 ROWS ONLY
")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare($sql);
$stmt->execute();
$recentAdmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>

<title>Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

body{
    background:#f1f5f9;
    font-family:'Segoe UI';
    min-height:100vh;
}

/* TOPBAR */

.topbar{
    background:white;
    padding:15px 20px;
    border-radius:15px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

/* CARD */

.card-box{
    background:white;
    padding:25px;
    border-radius:18px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);

    min-height:250px;   /* tambah */
    display:flex;
    flex-direction:column;
}

.card-box:hover{
    transform:translateY(-5px);
}

/* QUICK ACTION */

.quick-card{
    color:white;
    padding:20px;
    border-radius:15px;
    text-align:center;
    cursor:pointer;
    transition:0.3s;
    font-weight:600;
    min-height:105px;
}

.quick-card:hover{
    transform:translateY(-5px);
}

/* Register Patient */
.patient-card{
    background:linear-gradient(135deg,#3b82f6,#2563eb);
}

/* Walk In */
.walkin-card{
    background:linear-gradient(135deg,#14b8a6,#0f766e);
}

/* Admission */
.admission-card{
    background:linear-gradient(135deg,#f59e0b,#d97706);
}

/* Staff */
.staff-card{
    background:linear-gradient(135deg,#8b5cf6,#6d28d9);
}

/* Medication */
.medication-card{
    background:linear-gradient(135deg,#ef4444,#dc2626);
}

/* Appointment */
.appointment-card{
    background:linear-gradient(135deg,#06b6d4,#0891b2);
}

/* BOX */

.box{
    background:white;
    padding:25px;
    border-radius:18px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.sidebar{
width:260px !important;
min-width:260px !important;
max-width:260px !important;
height:100vh;
flex-shrink:0;
}

.chart-container{
height:220px;
position:relative;
}

.chart-container canvas{
max-height:220px !important;
}

.chart-box{
height:auto;
}

.chart-box canvas{
max-height:300px !important;
}

</style>

</head>

<body>

<div class="d-flex" style="min-height:100vh;">

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<!-- TOPBAR -->

<div class="topbar mb-4">

<input class="form-control w-50" placeholder="Search...">

<div class="d-flex align-items-center gap-3">

<a href="../pages/admin_appointment.php"
class="btn btn-warning position-relative">

📅 Appointments

<?php if($appointments > 0): ?>

<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">

<?= $appointments ?>

</span>

<?php endif; ?>

</a>

<div>
👤 <?= $_SESSION['user'] ?>
</div>

</div>

</div>

<!-- KPI ROW -->

<div class="row mb-4 g-3 align-items-stretch">

<!-- TOTAL PATIENT -->

<div class="col">

<div class="card-box">

<div class="d-flex justify-content-between align-items-start">

    <!-- LEFT SIDE -->
    <div>

        <small class="text-muted d-block">
            <?= date('d M Y') ?>
        </small>

        <h5 class="mt-2 fw-bold">
            <?= date('h:i A') ?>
        </h5>

    </div>

    <!-- RIGHT SIDE -->
    <div class="text-end">

        <small class="text-muted">
            Total Patients
        </small>

        <h1 class="fw-bold">
            <?= $patients ?>
        </h1>

    </div>

</div>

<hr>

<div>

    <b>Registered hospital patients</b><br>

    <small class="text-muted">
        Real-time patient statistics
    </small>

</div>

</div>

</div>

<!-- APPOINTMENT STATISTICS -->

<div class="col">

<div class="card-box">

<div class="text-center">

<small class="text-muted">
📅 Appointment Statistics
</small>

</div>

<div class="row text-center mt-3">

<div class="col-5">

<small class="text-muted">
Approved
</small>

<h2>
<?= $approvedAppointments ?>
</h2>

</div>

<div class="col-2 border-end"></div>

<div class="col-5">

<small class="text-muted">
Pending
</small>

<h2>
<?= $pendingAppointments ?>
</h2>

</div>

</div>

<hr>

<div class="text-center">

<small class="text-muted">
Appointment Activity
</small>

<?php if($pendingAppointments > 0): ?>

<div class="mt-2">

<span class="badge bg-danger">
<?= $pendingAppointments ?> Need Approval
</span>

</div>

<?php endif; ?>

</div>

</div>

</div>

<!-- INPATIENT / OUTPATIENT -->

<div class="col">

<div class="card-box">

<div class="text-center">

<small class="text-muted">
Total Patients
</small>

</div>


<div class="row text-center">

<div class="col-6 border-end">

<small class="text-muted">
Outpatient
</small>

<h2><?= $outpatients ?></h2>

</div>

<div class="col-6">

<small class="text-muted">
Inpatient
</small>

<h2><?= $inpatients ?></h2>

</div>

</div>

<hr>

<div class="text-center">

<small class="text-muted">
Hospital Activity
</small>

</div>

</div>

</div>

<!-- STAFF -->

<div class="col">

<div class="card-box text-center"
style="cursor:pointer;"
data-bs-toggle="modal"
data-bs-target="#staffModal">

<small class="text-muted">
Hospital Staff
</small>

<h1 class="mt-3">
<?= $staff ?>
</h1>

<p class="text-primary">
Click to View Staff List
</p>

</div>

</div>

<!-- WALK IN -->

<div class="col">

<div class="card-box text-center">

<small class="text-muted">
Walk-In Consultation
</small>

<h1 class="mt-3">
<?= $walkin ?>
</h1>

<p class="text-muted">
Total walk-in patients
</p>

</div>

</div>

<div class="col-md-3">

<div class="card-box text-center"
style="cursor:pointer;"
data-bs-toggle="modal"
data-bs-target="#doctorAvailabilityModal">

<h6 class="text-muted">
📅 Doctor Availability
</h6>

<div class="row mt-3">

<div class="col-6 border-end">

<small class="text-muted">
Available
</small>

<h2 class="text-success">
<?= $availableDoctors ?>
</h2>

</div>

<div class="col-6">

<small class="text-muted">
Unavailable
</small>

<h2 class="text-danger">
<?= $unavailableDoctors ?>
</h2>

</div>

</div>

<hr>

<p class="text-primary">
Click to View Availability
</p>


</div>

</div>
<!-- QUICK ACTION -->

<div class="row g-3 mb-4">

<!-- REGISTER PATIENT -->

<div class="col-md-2">

<div class="quick-card patient-card"
onclick="location.href='../pages/patient.php'">

🧑‍🤝‍🧑<br><br>

Register Patient

</div>

</div>

<!-- WALK IN -->

<div class="col-md-2">

<div class="quick-card walkin-card"
onclick="location.href='../pages/walkin_consultation.php'">

🩺<br><br>

Walk-In Consultation

</div>

</div>

<!-- ADMISSION -->

<div class="col-md-2">

<div class="quick-card admission-card"
onclick="location.href='../pages/admission.php'">

🏥<br><br>

Admission

</div>

</div>

<!-- STAFF -->

<div class="col-md-2">

<div class="quick-card staff-card"
onclick="location.href='../pages/staff.php'">

👨‍⚕️<br><br>

Add Staff

</div>

</div>

<!-- MEDICATION -->

<div class="col-md-2">

<div class="quick-card medication-card"
onclick="location.href='../pages/med_order.php'">

💊<br><br>

Medication

</div>

</div>

<!-- APPOINTMENT -->

<div class="col-md-2">

<div class="quick-card appointment-card"
onclick="location.href='../pages/admin_appointment.php'">

📅<br><br>

Appointments

<?php if($appointments > 0): ?>

<div class="mt-2">

<span class="badge bg-warning text-dark">

<?= $appointments ?> Pending

</span>

</div>

<?php endif; ?>

</div>

</div>

</div>

<!-- ADMISSION TREND -->

<div class="box mb-4">

    <h6>📊 Admission Trend</h6>

    <div class="chart-container">
        <canvas id="trendChart"></canvas>
    </div>

</div>

<!-- 3 CHART SEBARIS -->

<div class="row mb-4">

    <!-- Gender -->

    <div class="col-md-4">

        <div class="box">

            <h6>👤 Gender Distribution</h6>

            <div class="chart-container">
                <canvas id="genderChart"></canvas>
            </div>

        </div>

    </div>

    <!-- Patient Flow -->

    <div class="col-md-4">

        <div class="box">

            <h6>🚶 Patient Flow</h6>

            <div class="chart-container">
                <canvas id="flowChart"></canvas>
            </div>

        </div>

    </div>

    <!-- Medication -->

    <div class="col-md-4">

        <div class="box">

            <h6>💊 Top 5 Medications</h6>

            <div class="chart-container">
                <canvas id="medChart"></canvas>
            </div>

        </div>

    </div>

</div>

<div class="box mt-4">

<div class="d-flex justify-content-between mb-4">

<h3>
🏥 Recent Admissions
</h3>

<a href="admission.php" class="btn btn-primary">
Latest 5 Admissions
</a>

</div>

<div class="row">

<?php foreach(array_slice($recentAdmissions,0,5) as $row): ?>

<div class="col-md-6 mb-3">

<div class="card shadow-sm">

<div class="card-body">

<h5><?= $row['NAME'] ?></h5>

<p class="text-muted">
Admission #<?= $row['ADMISSION_ID'] ?>
</p>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<div class="box mt-4">

<div class="d-flex justify-content-between mb-4">

<h3>
👤 Patient Records
</h3>

<a href="patient.php" class="btn btn-primary">
View All Patients
</a>

</div>

<div class="row">

<?php foreach($patientsList as $p): ?>

<div class="col-md-4 mb-4">

<div class="card shadow-sm text-center">

<div class="card-body">

<div style="
width:80px;
height:80px;
background:#dbeafe;
border-radius:50%;
margin:auto;
display:flex;
align-items:center;
justify-content:center;
font-size:35px;
">

👤

</div>

<h5 class="mt-3">
<?= $p['NAME'] ?>
</h5>

<span class="badge bg-secondary">
<?= $p['GENDER'] ?>
</span>

<hr>

Patient ID:
<b>#<?= $p['PATIENT_ID'] ?></b>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

<!-- STAFF MODAL -->

<div class="modal fade" id="staffModal">

<div class="modal-dialog modal-dialog-centered">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
👨‍⚕️ Staff Department List
</h5>

<button type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<table class="table">

<tr>
<th>Department</th>
<th>Total</th>
</tr>

<?php foreach($staffRole as $s): ?>

<tr>

<td><?= $s['ROLE'] ?></td>

<td><?= $s['TOTAL'] ?></td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

new Chart(document.getElementById('trendChart'), {

type:'line',

data:{
labels: <?= json_encode($trendLabels) ?>,

datasets:[{
label:'Admissions',
data: <?= json_encode($trendValues) ?>,
borderWidth:3
}]
}

});

new Chart(document.getElementById('genderChart'), {

type:'pie',

data:{
labels: <?= json_encode($genderLabels) ?>,

datasets:[{
data: <?= json_encode($genderValues) ?>
}]
},

options:{
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
position:'bottom'
}
}
}

});

new Chart(document.getElementById('flowChart'),{

type:'pie',

data:{
labels: <?= json_encode($patientFlowLabels) ?>,

datasets:[{
data: <?= json_encode($patientFlowValues) ?>
}]
},

options:{
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
position:'bottom'
}
}
}

});

new Chart(document.getElementById('medChart'),{

type:'doughnut',

data:{
labels: <?= json_encode($medLabels) ?>,

datasets:[{
data: <?= json_encode($medValues) ?>
}]
},

options:{
responsive:true,
maintainAspectRatio:false,

plugins:{
legend:{
position:'bottom'
}
}
}

});

</script>

<div class="modal fade" id="doctorAvailabilityModal">

<div class="modal-dialog modal-lg">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">

📅 Doctor Availability Today
</h5>

<button type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div class="modal-body">

<table class="table">

<tr>
<th>Doctor</th>
<th>Status</th>
<th>Time Slot</th>
</tr>

<?php

$doctorList = $conn->query("
SELECT
    H.USERNAME,
    D.STATUS,
    D.START_TIME,
    D.END_TIME
FROM SYARMIMI.DOCTOR_AVAILABILITY D
JOIN SYARMIMI.HOSPITAL_STAFF H
ON D.ACCOUNT_ID = H.ACCOUNT_ID
WHERE D.AVAILABLE_DATE = TRUNC(SYSDATE)
ORDER BY H.USERNAME
");

while($doc = $doctorList->fetch(PDO::FETCH_ASSOC)){

echo "
<tr>
<td>Dr. {$doc['USERNAME']}</td>
<td>{$doc['STATUS']}</td>
<td>{$doc['START_TIME']} - {$doc['END_TIME']}</td>
</tr>
";
}
?>

</table>

</div>

</div>

</div>

</div>

</body>
</html>