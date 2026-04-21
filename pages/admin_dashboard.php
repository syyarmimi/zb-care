<?php
session_start();
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

$patients   = getCount($conn, "SELECT COUNT(*) FROM SYARMIMI.PATIENT");
$admissions = getCount($conn, "SELECT COUNT(*) FROM SYARMIMI.ADMISSION");
$staff      = getCount($conn, "SELECT COUNT(*) FROM SYARMIMI.HOSPITAL_STAFF");
$medOrders  = getCount($conn, "SELECT COUNT(*) FROM SYARMIMI.MEDICATION_ORDER");

/* ================= BED USAGE ================= */
$bed = $conn->query("
SELECT COUNT(*) TOTAL,
SUM(CASE WHEN STATUS='Occupied' THEN 1 ELSE 0 END) USED
FROM SYARMIMI.BED
")->fetch(PDO::FETCH_ASSOC);

$bedUsage = round(($bed['USED'] / $bed['TOTAL']) * 100);

/* ================= GENDER ================= */
$genderData = $conn->query("
SELECT GENDER, COUNT(*) TOTAL
FROM SYARMIMI.PATIENT
GROUP BY GENDER
")->fetchAll(PDO::FETCH_ASSOC);

$genderLabels = [];
$genderValues = [];

foreach($genderData as $g){
    $genderLabels[] = $g['GENDER'];
    $genderValues[] = $g['TOTAL'];
}

/* ================= ADMISSION TREND ================= */
$trend = $conn->query("
SELECT TO_CHAR(ADMISSION_DATE,'DD') DAY, COUNT(*) TOTAL
FROM SYARMIMI.ADMISSION
GROUP BY TO_CHAR(ADMISSION_DATE,'DD')
ORDER BY DAY
")->fetchAll(PDO::FETCH_ASSOC);

$trendLabels = [];
$trendValues = [];

foreach($trend as $t){
    $trendLabels[] = $t['DAY'];
    $trendValues[] = $t['TOTAL'];
}

/* ================= WARD OCCUPANCY ================= */
$ward = $conn->query("
SELECT W.WARD_NAME,
SUM(CASE WHEN B.STATUS='Occupied' THEN 1 ELSE 0 END) OCCUPIED
FROM SYARMIMI.WARD W
LEFT JOIN SYARMIMI.BED B ON W.WARD_ID = B.WARD_ID
GROUP BY W.WARD_NAME
")->fetchAll(PDO::FETCH_ASSOC);

$wardLabels = [];
$wardValues = [];

foreach($ward as $w){
    $wardLabels[] = $w['WARD_NAME'];
    $wardValues[] = $w['OCCUPIED'];
}

/* ================= RECENT ================= */
$sql = "SELECT a.ADMISSION_ID, p.NAME
        FROM SYARMIMI.ADMISSION a
        JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
        ORDER BY a.ADMISSION_ID DESC";

$stmt = $conn->prepare($sql);
$stmt->execute();
?>

<!DOCTYPE html>
<html>
<head>
<title>Admin Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { background:#f1f5f9; }

.topbar {
    background:white;
    padding:15px;
    border-radius:15px;
    display:flex;
    justify-content:space-between;
    box-shadow:0 2px 10px rgba(0,0,0,0.05);
}

.card-box {
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
    text-align:center;
}

.quick-card {
    background:#dc2626;
    color:white;
    padding:20px;
    border-radius:10px;
    text-align:center;
    cursor:pointer;
    transition:0.3s;
}
.quick-card:hover {
    background:#b91c1c;
}

.box {
    background:white;
    padding:20px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<!-- TOP -->
<div class="topbar mb-4">
<input class="form-control w-50" placeholder="Search...">
<div>👤 <?= $_SESSION['user'] ?></div>
</div>

<!-- KPI -->
<div class="row mb-4">
<div class="col-md-2"><div class="card-box">Patients<br><b><?= $patients ?></b></div></div>
<div class="col-md-2"><div class="card-box">Admissions<br><b><?= $admissions ?></b></div></div>
<div class="col-md-2"><div class="card-box">Staff<br><b><?= $staff ?></b></div></div>
<div class="col-md-2"><div class="card-box">Med Orders<br><b><?= $medOrders ?></b></div></div>
<div class="col-md-2"><div class="card-box">Bed Usage<br><b><?= $bedUsage ?>%</b></div></div>
</div>

<!-- 🔥 QUICK ACTION (ADDED BACK) -->
<div class="row mb-4">

<div class="col-md-3">
<div class="quick-card" onclick="location.href='../pages/patient.php'">
🧑‍🤝‍🧑 Add Patient
</div>
</div>

<div class="col-md-3">
<div class="quick-card" onclick="location.href='../pages/staff.php'">
➕ Add Staff
</div>
</div>

<div class="col-md-3">
<div class="quick-card" onclick="location.href='../pages/admission.php'">
🏥 Admission
</div>
</div>

<div class="col-md-3">
<div class="quick-card" onclick="location.href='../pages/med_order.php'">
💊 Medication
</div>
</div>

</div>

<!-- CHARTS -->
<div class="row">

<div class="col-md-8">

<div class="box">
<h6>📊 Admission Trend</h6>
<canvas id="trendChart"></canvas>
</div>

<div class="box mt-3">
<h6>🧾 Recent Admissions</h6>

<table class="table">
<tr><th>ID</th><th>Patient</th></tr>

<?php $c=0; while($row=$stmt->fetch(PDO::FETCH_ASSOC)){ if($c++==5) break; ?>
<tr>
<td><?= $row['ADMISSION_ID'] ?></td>
<td><?= $row['NAME'] ?></td>
</tr>
<?php } ?>

</table>

</div>

</div>

<div class="col-md-4">

<div class="box">
<h6>👤 Gender Distribution</h6>
<canvas id="genderChart"></canvas>
</div>

<div class="box mt-3">
<h6>🏥 Ward Occupancy</h6>
<canvas id="wardChart"></canvas>
</div>

</div>

</div>

</div>
</div>

<script>
new Chart(document.getElementById('trendChart'), {
type: 'line',
data: {
labels: <?= json_encode($trendLabels) ?>,
datasets: [{ label: 'Admissions', data: <?= json_encode($trendValues) ?> }]
}
});

new Chart(document.getElementById('genderChart'), {
type: 'pie',
data: {
labels: <?= json_encode($genderLabels) ?>,
datasets: [{ data: <?= json_encode($genderValues) ?> }]
}
});

new Chart(document.getElementById('wardChart'), {
type: 'bar',
data: {
labels: <?= json_encode($wardLabels) ?>,
datasets: [{ label: 'Occupied Beds', data: <?= json_encode($wardValues) ?> }]
}
});
</script>

</body>
</html>