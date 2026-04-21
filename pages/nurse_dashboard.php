<?php
session_start();
include("../config/config.php");

// ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

// TOTAL PATIENTS
$stmt1 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.PATIENT");
$row1 = $stmt1->fetch(PDO::FETCH_ASSOC);

// TOTAL ADMISSION
$stmt2 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.ADMISSION");
$row2 = $stmt2->fetch(PDO::FETCH_ASSOC);

// OCCUPIED BEDS
$stmt3 = $conn->query("SELECT COUNT(*) AS TOTAL FROM SYARMIMI.BED WHERE STATUS='Occupied'");
$row3 = $stmt3->fetch(PDO::FETCH_ASSOC);

// ✅ NEW: PENDING MEDICATION
$pendingMed = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEDICATION_ORDER mo
LEFT JOIN SYARMIMI.MEDICATION_DELIVERY md 
ON mo.MEDORDER_ID = md.MEDORDER_ID
WHERE md.MEDORDER_ID IS NULL
")->fetchColumn();

// ✅ NEW: PENDING MEAL
$pendingMeal = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEAL_PLAN mp
LEFT JOIN SYARMIMI.MEAL_DELIVERY md 
ON mp.MEALPLAN_ID = md.MEALPLAN_ID
WHERE md.MEALPLAN_ID IS NULL
")->fetchColumn();

// RECENT PATIENTS
$sql = "SELECT p.NAME, p.GENDER, a.ADMISSION_ID
        FROM SYARMIMI.PATIENT p
        JOIN SYARMIMI.ADMISSION a ON p.PATIENT_ID = a.PATIENT_ID
        ORDER BY a.ADMISSION_ID DESC";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background:#f4f6f9;
    font-family:'Segoe UI';
}

.card-box {
    height:160px;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    border-radius:15px;
}

.icon {
    width:50px;
    height:50px;
    border-radius:12px;
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    margin-bottom:10px;
    font-size:22px;
}

.blue { background:#3b82f6; }
.green { background:#10b981; }
.red { background:#ef4444; }

.card:hover {
    transform: translateY(-3px);
    transition: 0.2s;
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_nurse.php"); ?>

<div class="flex-grow-1 p-4">

<h3 class="mb-4">🩺 Nurse Dashboard</h3>

<!-- 🔔 NEW NOTIFICATION -->
<?php if($pendingMed > 0 || $pendingMeal > 0): ?>
<div class="alert alert-warning d-flex justify-content-between align-items-center">

<div>
<strong>🚨 Patient Care Reminder</strong><br>

<?php if($pendingMed > 0): ?>
💊 <?= $pendingMed ?> medication(s) waiting for delivery<br>
<?php endif; ?>

<?php if($pendingMeal > 0): ?>
🍽️ <?= $pendingMeal ?> meal(s) ready to be served
<?php endif; ?>

</div>

<div class="d-flex gap-2">

<?php if($pendingMed > 0): ?>
<a href="nurse_medication.php" class="btn btn-success btn-sm">
Go Medication
</a>
<?php endif; ?>

<?php if($pendingMeal > 0): ?>
<a href="nurse_meal.php" class="btn btn-primary btn-sm">
Go Meals
</a>
<?php endif; ?>

</div>

</div>
<?php endif; ?>


<div class="row g-3">

<!-- PATIENT -->
<div class="col-md-4">
<div class="card shadow card-box">

<div class="icon blue">
<i class="bi bi-people-fill"></i>
</div>

<h6>Total Patients</h6>
<h3><?= $row1['TOTAL']; ?></h3>

</div>
</div>

<!-- ADMISSION -->
<div class="col-md-4">
<div class="card shadow card-box">

<div class="icon green">
<i class="bi bi-hospital-fill"></i>
</div>

<h6>Admissions</h6>
<h3><?= $row2['TOTAL']; ?></h3>

</div>
</div>

<!-- BED -->
<div class="col-md-4">
<div class="card shadow card-box">

<div class="icon red">
<i class="bi bi-hospital"></i>
</div>

<h6>Occupied Beds</h6>
<h3><?= $row3['TOTAL']; ?></h3>

</div>
</div>

</div>

<!-- TABLE -->
<div class="card mt-4 p-3 shadow">
<h5>Patient List</h5>

<table class="table table-bordered mt-3">

<thead class="table-dark">
<tr>
<th>Name</th>
<th>Gender</th>
<th>Admission ID</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
<tr>
<td><?= $row['NAME']; ?></td>
<td><?= $row['GENDER']; ?></td>
<td><?= $row['ADMISSION_ID']; ?></td>
</tr>
<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>