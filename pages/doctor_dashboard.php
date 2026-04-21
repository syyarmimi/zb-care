<?php 
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    die("Access Denied");
}

include("../config/config.php");

// ✅ SAFE SESSION
$doctor_id = $_SESSION['user_id'] ?? 0;

/* ================= NEW PATIENT COUNT 🔔 ================= */
$newCount = $conn->prepare("
SELECT COUNT(*) 
FROM SYARMIMI.ADMISSION
WHERE STAFF_ID = :doctor
AND IS_SEEN = 0
AND DISCHARGE_DATE IS NULL
");
$newCount->execute([':doctor'=>$doctor_id]);
$newPatients = $newCount->fetchColumn();

/* ================= PATIENT + STATUS ================= */
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
        SELECT 1 FROM SYARMIMI.DIAGNOSIS D 
        WHERE D.ADMISSION_ID = A.ADMISSION_ID
        AND D.STAFF_ID = A.STAFF_ID
    ) THEN 'Diagnosed'
    ELSE 'Not Diagnosed'
END AS DIAG_STATUS,

CASE 
    WHEN EXISTS (
        SELECT 1 FROM SYARMIMI.MEDICATION_ORDER MO 
        WHERE MO.ADMISSION_ID = A.ADMISSION_ID
        AND MO.STAFF_ID = A.STAFF_ID
    ) THEN 'Medication Given'
    ELSE 'No Medication'
END AS MED_STATUS,

CASE 
    WHEN EXISTS (
        SELECT 1 FROM SYARMIMI.MEAL_PLAN MP 
        JOIN SYARMIMI.ADMISSION A2 ON MP.ADMISSION_ID = A2.ADMISSION_ID
        WHERE MP.ADMISSION_ID = A.ADMISSION_ID
        AND A2.STAFF_ID = A.STAFF_ID
    ) THEN 'Meal Assigned'
    ELSE 'No Meal'
END AS MEAL_STATUS

FROM SYARMIMI.ADMISSION A
JOIN SYARMIMI.PATIENT P ON A.PATIENT_ID = P.PATIENT_ID
JOIN SYARMIMI.BED B ON A.BED_ID = B.BED_ID
JOIN SYARMIMI.WARD W ON B.WARD_ID = W.WARD_ID

WHERE A.STAFF_ID = :doctor
AND A.DISCHARGE_DATE IS NULL

ORDER BY A.ADMISSION_ID DESC
");

$stmt->execute([':doctor'=>$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= AUTO MARK AS SEEN ================= */
$conn->prepare("
UPDATE SYARMIMI.ADMISSION
SET IS_SEEN = 1
WHERE STAFF_ID = :doctor
AND IS_SEEN = 0
")->execute([':doctor'=>$doctor_id]);

$totalPatients = count($patients);

/* ================= SAFE COUNTS ================= */
function getDoctorCount($conn, $sql, $doctor_id){
    $stmt = $conn->prepare($sql);
    $stmt->execute([':doctor'=>$doctor_id]);
    return $stmt->fetchColumn();
}

$totalDiagnosis = getDoctorCount($conn,
"SELECT COUNT(*) FROM SYARMIMI.DIAGNOSIS WHERE STAFF_ID = :doctor",
$doctor_id);

$totalMedication = getDoctorCount($conn,
"SELECT COUNT(*) FROM SYARMIMI.MEDICATION_ORDER WHERE STAFF_ID = :doctor",
$doctor_id);

$totalMeal = getDoctorCount($conn,
"SELECT COUNT(*) FROM SYARMIMI.MEAL_PLAN MP
 JOIN SYARMIMI.ADMISSION A ON MP.ADMISSION_ID = A.ADMISSION_ID
 WHERE A.STAFF_ID = :doctor",
$doctor_id);
?>

<!DOCTYPE html>
<html>
<head>
<title>Doctor Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#eef2f7; font-family:'Segoe UI'; }
.content { flex:1; padding:30px; }

.card-box {
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.section { margin-top:20px; }
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h4 class="mb-4">Doctor Overview</h4>

<!-- 🔴 ONLY IMPORTANT ALERT -->
<?php if($newPatients > 0): ?>
<div class="alert alert-danger">
🚨 <?= $newPatients ?> NEW patient(s) assigned to you!
</div>
<?php endif; ?>

<!-- KPI -->
<div class="row g-3">

<div class="col-md-3">
<div class="card-box text-center">
Patients<br><b><?= $totalPatients ?></b>
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
Diagnosis<br><b><?= $totalDiagnosis ?></b>
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
Medication<br><b><?= $totalMedication ?></b>
</div>
</div>

<div class="col-md-3">
<div class="card-box text-center">
Meal<br><b><?= $totalMeal ?></b>
</div>
</div>

</div>

<!-- 🧾 PATIENT LIST -->
<div class="card-box mt-4">

<h6>🧾 My Assigned Patients</h6>

<table class="table table-bordered mt-3">

<tr>
<th>Name</th>
<th>Ward</th>
<th>Bed</th>
<th>Date</th>
<th>Diagnosis</th>
<th>Medication</th>
<th>Meal</th>
</tr>

<?php foreach($patients as $p): ?>
<tr class="<?= ($p['IS_SEEN'] == 0) ? 'table-warning' : '' ?>">

<td><?= $p['NAME'] ?></td>
<td><?= $p['WARD_NAME'] ?></td>
<td><?= $p['BED_NUMBER'] ?></td>
<td><?= $p['ADMISSION_DATE'] ?></td>

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
<span class="badge bg-<?= ($p['MEAL_STATUS']=='Meal Assigned') ? 'success' : 'secondary' ?>">
<?= $p['MEAL_STATUS'] ?>
</span>
</td>

</tr>
<?php endforeach; ?>

</table>

</div>

</div>
</div>

</body>
</html>