<?php 
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    die("Access Denied");
}

include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');

$currentDate = date('d M Y');
$currentTime = date('h:i A');

/* ================= SAFE SESSION ================= */

$doctor_id = $_SESSION['user_id'] ?? 0;
$doctor_name = $_SESSION['user'] ?? '';

$notificationStmt = $conn->prepare("
SELECT *
FROM SYARMIMI.APPOINTMENT_NOTIFICATION
WHERE ACCOUNT_ID = :doctor
AND IS_READ = 0
ORDER BY NOTIFICATION_ID DESC
");

$conn->prepare("
UPDATE SYARMIMI.APPOINTMENT_NOTIFICATION
SET IS_READ = 1
WHERE ACCOUNT_ID = :doctor
")->execute([
    ':doctor'=>$doctor_id
]);


$notificationStmt->execute([
':doctor'=>$doctor_id
]);

$notifications =
$notificationStmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= TODAY AVAILABILITY ================= */

$availabilityStmt = $conn->prepare("
SELECT STATUS
FROM SYARMIMI.DOCTOR_AVAILABILITY
WHERE ACCOUNT_ID = :doctor
AND TRUNC(AVAILABLE_DATE) = TRUNC(SYSDATE)
");

$availabilityStmt->execute([
    ':doctor' => $doctor_id
]);

$todayAvailability = $availabilityStmt->fetchColumn();

if(!$todayAvailability){
    $todayAvailability = 'Not Set';
}

/* ================= NEW PATIENT COUNT ================= */

$newCount = $conn->prepare("
SELECT COUNT(*) 
FROM SYARMIMI.ADMISSION
WHERE ACCOUNT_ID = :doctor
AND IS_SEEN = 0
AND DISCHARGE_DATE IS NULL
");

$newCount->execute([
    ':doctor'=>$doctor_id
]);

$newPatients = $newCount->fetchColumn();


/* ================= ADMISSION PATIENT ================= */

$stmt = $conn->prepare("

SELECT 
A.ADMISSION_ID,
A.IS_SEEN,
P.NAME,
W.WARD_NAME,
B.BED_NUMBER,
A.ADMISSION_DATE,

CASE 
    WHEN EXISTS (
        SELECT 1 
        FROM SYARMIMI.DIAGNOSIS D 
        WHERE D.ADMISSION_ID = A.ADMISSION_ID
        AND D.ACCOUNT_ID = A.ACCOUNT_ID
    ) THEN 'Diagnosed'
    ELSE 'Not Diagnosed'
END AS DIAG_STATUS,

CASE 
    WHEN EXISTS (
        SELECT 1 
        FROM SYARMIMI.MEDICATION_ORDER MO 
        WHERE MO.ADMISSION_ID = A.ADMISSION_ID
        AND MO.ACCOUNT_ID = A.ACCOUNT_ID
    ) THEN 'Medication Given'
    ELSE 'No Medication'
END AS MED_STATUS

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P 
ON A.PATIENT_ID = P.PATIENT_ID

JOIN SYARMIMI.BED B 
ON A.BED_ID = B.BED_ID

JOIN SYARMIMI.WARD W 
ON B.WARD_ID = W.WARD_ID

WHERE ACCOUNT_ID = :doctor
AND A.DISCHARGE_DATE IS NULL

AND NOT EXISTS
(
    SELECT 1
    FROM SYARMIMI.DIAGNOSIS D
    WHERE D.PATIENT_ID = A.PATIENT_ID
)

ORDER BY A.ADMISSION_ID DESC

");

$stmt->execute([
    ':doctor'=>$doctor_id
]);

$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ================= APPOINTMENT PATIENT ================= */

$appStmt = $conn->prepare("

SELECT *
FROM SYARMIMI.APPOINTMENT
WHERE LOWER(DOCTOR_NAME) LIKE LOWER(:doctor_name)
AND STATUS='Approved'
AND TO_DATE(APPOINTMENT_DATE,'YYYY-MM-DD') >= TRUNC(SYSDATE)
ORDER BY TO_DATE(APPOINTMENT_DATE,'YYYY-MM-DD')

");

$appStmt->execute([
    ':doctor_name'=>'%'.$doctor_name.'%'
]);

$appointments = $appStmt->fetchAll(PDO::FETCH_ASSOC);

$totalAppointmentPatients = count($appointments);

/* ================= TODAY APPOINTMENTS ================= */

$todayAppointments = $conn->prepare("
SELECT
    PATIENT_NAME,
    APPOINTMENT_TIME
FROM SYARMIMI.APPOINTMENT
WHERE ACCOUNT_ID = :doctor1

UNION ALL

SELECT
    P.NAME,
    'Walk-In'
FROM SYARMIMI.WALKIN_CONSULTATION W
JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID
WHERE W.ACCOUNT_ID = :doctor2
AND UPPER(W.STATUS)='ASSIGNED'
");

$todayAppointments->execute([
    ':doctor1' => $doctor_id,
    ':doctor2' => $doctor_id
]);

$todayList = $todayAppointments->fetchAll(PDO::FETCH_ASSOC);


/* ================= AUTO MARK AS SEEN ================= */

$conn->prepare("
UPDATE SYARMIMI.ADMISSION
SET IS_SEEN = 1
WHERE ACCOUNT_ID = :doctor
AND IS_SEEN = 0
")->execute([
    ':doctor'=>$doctor_id
]);


$stmt = $conn->prepare("
SELECT COUNT(*)
FROM SYARMIMI.ADMISSION
WHERE ACCOUNT_ID = :doctor
AND DISCHARGE_DATE IS NULL
");

$stmt->execute([
    ':doctor'=>$doctor_id
]);

$inpatientCount = $stmt->fetchColumn();

$inpatientCount = count($patients);

$outpatientCount = count($appointments);

/* ================= MONTHLY PATIENTS ================= */

$currentMonth = date('m');
$currentYear = date('Y');

$monthAdmissionSql = "
SELECT COUNT(*)
FROM SYARMIMI.ADMISSION
WHERE ACCOUNT_ID = :doctor
AND TO_CHAR(ADMISSION_DATE,'MM') = :month
AND TO_CHAR(ADMISSION_DATE,'YYYY') = :year
";

$stmtMonthAdmission = $conn->prepare($monthAdmissionSql);

$currentMonth = date('m');
$currentYear  = date('Y');

$stmtMonthAdmission->execute([
    ':doctor' => $doctor_id,
    ':month'  => $currentMonth,
    ':year'   => $currentYear
]);

$monthAdmissions = $stmtMonthAdmission->fetchColumn();

$monthAppointmentSql = "
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE LOWER(DOCTOR_NAME) LIKE LOWER(:doctor_name)
AND STATUS = 'Approved'
AND TO_CHAR(
        TO_DATE(APPOINTMENT_DATE,'YYYY-MM-DD'),
        'MM'
    ) = :month
AND TO_CHAR(
        TO_DATE(APPOINTMENT_DATE,'YYYY-MM-DD'),
        'YYYY'
    ) = :year
";

$stmtMonthAppointment = $conn->prepare($monthAppointmentSql);

$stmtMonthAppointment->execute([
    ':doctor_name' => '%' . $doctor_name . '%',
    ':month' => $currentMonth,
    ':year' => $currentYear
]);

$monthAppointments = $stmtMonthAppointment->fetchColumn();

$monthlyPatients =
    $monthAdmissions +
    $monthAppointments;

/* ================= SAFE COUNTS ================= */

function getDoctorCount($conn, $sql, $doctor_id){
    
    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':doctor'=>$doctor_id
    ]);

    return $stmt->fetchColumn();
}

/* ================= COUNTS ================= */

$totalDiagnosis = getDoctorCount(
    $conn,
    "SELECT COUNT(*) 
     FROM SYARMIMI.DIAGNOSIS 
     WHERE ACCOUNT_ID = :doctor",
    $doctor_id
);

$totalMedication = getDoctorCount(
    $conn,
    "SELECT COUNT(*) 
     FROM SYARMIMI.MEDICATION_ORDER 
     WHERE ACCOUNT_ID = :doctor",
    $doctor_id
);

$totalAppointments = count($appointments);

/* ================= WALKIN QUEUE ================= */

$walkinStmt = $conn->prepare("
SELECT
    W.CONSULTATION_ID,
    P.NAME
FROM SYARMIMI.WALKIN_CONSULTATION W
JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID
WHERE W.ACCOUNT_ID = :doctor
AND UPPER(W.STATUS)='ASSIGNED'
");

$walkinStmt->execute([
    ':doctor'=>$doctor_id
]);

$walkinPatients = $walkinStmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>

<title>Doctor Dashboard</title>

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

/* CARD */

.card-box{
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
    height:100%;
    transition:0.3s;
}

.card-box:hover{
    transform:translateY(-5px);
}

/* SECTION */

.section{
    margin-top:20px;
}

/* BADGE */

.badge{
    padding:8px 12px;
    border-radius:10px;
}

/* TABLE */

table th{
    background:#f8fafc;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h2 class="fw-bold mb-4">
🩺 Doctor Dashboard
</h2>

<?php foreach($notifications as $n): ?>

<div class="alert alert-warning">

🔔 <?= $n['MESSAGE'] ?>

</div>

<?php endforeach; ?>

<!-- ALERT -->

<?php if($newPatients > 0): ?>

<div class="alert alert-danger">

🚨 <?= $newPatients ?> NEW patient(s) assigned to you!

</div>

<?php endif; ?>

<!-- KPI -->

<div class="row g-3">

<!-- AVAILABILITY -->

<div class="col-md">

<a href="doctor_availability.php"
style="text-decoration:none;color:inherit;">

<div class="card-box">

<div class="d-flex justify-content-between align-items-start">

<div>

<small class="text-muted">

<?= $currentDate ?>

</small>

<h3 class="fw-bold mt-2">

<?= $currentTime ?>

</h3>

</div>

<div class="text-end">

<?php if($todayAvailability == 'Available'): ?>

<span class="badge bg-success">
🟢 Available
</span>

<?php elseif($todayAvailability == 'Unavailable'): ?>

<span class="badge bg-danger">
🔴 Unavailable
</span>

<?php else: ?>

<span class="badge bg-secondary">
⚪ Not Set
</span>

<?php endif; ?>

</div>

</div>

<hr>

<h5 class="fw-bold">
📅 Doctor Availability
</h5>

<p class="text-muted mb-0">
Click to manage your schedule
</p>

</div>

</a>

</div>

<!-- PATIENT STATISTICS -->

<div class="col-md">

<div class="card-box">

<div class="text-center mb-3">

<h6>
👨‍⚕️ Patient Statistics
</h6>

</div>

<div class="row text-center">

<div class="col-6">

<small class="text-muted">
Inpatient
</small>

<h2 class="fw-bold">
<?= $inpatientCount ?>
</h2>

</div>

<div class="col-6 border-start">

<small class="text-muted">
Outpatient
</small>

<h2 class="fw-bold">
<?= $outpatientCount ?>
</h2>

</div>

</div>

<hr>

<p class="text-center text-muted mb-0">

Patient Activity

</p>

</div>

</div>

<!-- APPOINTMENT STATISTICS -->

<!-- APPOINTMENT STATISTICS -->

<div class="col-md">

<div class="card-box">

<div class="text-center mb-3">

<h6>
📅 Appointment Statistics
</h6>

</div>

<div class="text-center">

<h1 class="fw-bold">
<?= $totalAppointmentPatients ?>
</h1>

<p class="text-muted">
Total Appointment Patients
</p>

</div>

</div>

</div>

<!-- DIAGNOSIS -->

<div class="col-md">

<div class="card-box text-center">

<h6>
🩺 Diagnosis
</h6>

<h2 class="fw-bold">

<?= $totalDiagnosis ?>

</h2>

<p class="text-muted">
Completed Diagnosis
</p>

</div>

</div>

<!-- MEDICATION -->
<div class="col-md">

<div class="card-box text-center">

<h6>💊 Medication</h6>

<h2 class="fw-bold">
<?= $totalMedication ?>
</h2>

<p class="text-muted">
Medication Orders
</p>

</div>

</div>

<!-- TODAY APPOINTMENTS -->

<div class="card-box mt-4">

<h5 class="mb-3">
📅 Today's Appointments
</h5>

<?php if(count($todayList) > 0): ?>

    <table class="table table-sm">

        <tr>
            <th>Time</th>
            <th>Patient</th>
        </tr>

        <?php foreach($todayList as $t): ?>

        <tr>

            <td>
                <?= $t['APPOINTMENT_TIME'] ?>
            </td>

            <td>
                <?= $t['PATIENT_NAME'] ?>
            </td>

        </tr>

        <?php endforeach; ?>

    </table>

<?php else: ?>

    <div class="alert alert-info mb-0">

        No appointments scheduled today.

    </div>

<?php endif; ?>

</div>

<div class="card-box mt-4">

<h5 class="mb-4">
🚶 Walk-In Patients Waiting
</h5>

<?php if(count($walkinPatients) > 0): ?>

<table class="table table-bordered">

<tr>
<th>Patient</th>
<th>Action</th>
</tr>

<?php foreach($walkinPatients as $w): ?>

<tr>

<td>
<?= $w['NAME'] ?>
</td>

<td>

<a
href="treatment.php?type=walkin&id=<?= $w['CONSULTATION_ID'] ?>"
class="btn btn-warning btn-sm">

Diagnose

</a>

</td>

</tr>

<?php endforeach; ?>

</table>

<?php else: ?>

<div class="alert alert-success">
No walk-in patients waiting.
</div>

<?php endif; ?>

</div>

<!-- ADMISSION PATIENT -->

<div class="card-box mt-4">

<h5 class="mb-4">
🧾 My Assigned Patients
</h5>

<table class="table table-bordered align-middle">

<tr>

<th>Name</th>
<th>Ward</th>
<th>Bed</th>
<th>Date</th>
<th>Diagnosis</th>
<th>Medication</th>
<th>Action</th>

</tr>

<?php foreach($patients as $p): ?>

<tr class="<?= ($p['IS_SEEN'] == 0) ? 'table-warning' : '' ?>">

<td>

<b><?= $p['NAME'] ?></b>

</td>

<td>

<?= $p['WARD_NAME'] ?>

</td>

<td>

<?= $p['BED_NUMBER'] ?>

</td>

<td>

<?= $p['ADMISSION_DATE'] ?>

</td>

<td>

<span class="badge bg-<?= ($p['DIAG_STATUS']=='Diagnosed') ? 'success' : 'danger' ?>">

<?= $p['DIAG_STATUS'] ?>

</span>

</td>

<td>

<span class="badge bg-<?= ($p['MED_STATUS']=='Medication Given') ? 'success' : 'secondary' ?>">

<?= $p['MED_STATUS'] ?>

</span>

</td>

<td>

<a href="treatment.php?id=<?= $p['ADMISSION_ID'] ?>"
class="btn btn-primary btn-sm">

Diagnose

</a>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<!-- APPOINTMENT TABLE -->

<div class="card-box mt-4">

<h5 class="mb-4">
📅 Upcoming Appointments
</h5>

<table class="table table-bordered align-middle">

<tr>

<th>Patient</th>
<th>Date</th>
<th>Time</th>
<th>Action</th>

</tr>

<?php foreach($appointments as $a): ?>

<tr>

<td>

<b><?= $a['PATIENT_NAME'] ?></b>

</td>

<td>
<?= $a['APPOINTMENT_DATE'] ?>
</td>

<td>
<?= $a['APPOINTMENT_TIME'] ?>
</td>

<td>

<a href="treatment.php?id=<?= $a['APPOINTMENT_ID'] ?>&type=appointment"
class="btn btn-primary btn-sm">

Diagnose

</a>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

</div>

</body>
</html>