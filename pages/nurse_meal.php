<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= FETCH MEAL TASK ================= */
$sql = "
SELECT mp.MEALPLAN_ID,
       a.ADMISSION_ID,
       p.NAME,
       m.FOOD_NAME,
       mp.MEALTIME_SLOT,
       w.WARD_NAME,
       b.BED_NUMBER

FROM SYARMIMI.MEAL_PLAN mp
JOIN SYARMIMI.ADMISSION a ON mp.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MENU_ITEM m ON mp.MENUITEM_ID = m.MENUITEM_ID
JOIN SYARMIMI.BED b ON a.BED_ID = b.BED_ID
JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID

-- NOT yet delivered
LEFT JOIN SYARMIMI.MEAL_DELIVERY md
ON mp.MEALPLAN_ID = md.MEALPLAN_ID

WHERE md.MEALPLAN_ID IS NULL
ORDER BY mp.MEALPLAN_ID DESC
";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Nurse Meal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }
.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_nurse.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-4">🍽️ Meal Tasks</h4>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>Patient</th>
<th>Location</th>
<th>Meal</th>
<th>Time</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>
<td><?= $row['NAME'] ?></td>

<td>
<?= $row['WARD_NAME'] ?><br>
<small class="text-muted">Bed <?= $row['BED_NUMBER'] ?></small>
</td>

<td><?= $row['FOOD_NAME'] ?></td>
<td><?= $row['MEALTIME_SLOT'] ?></td>

<td>
<a href="nurse_action.php?give_meal=<?= $row['ADMISSION_ID'] ?>"
class="btn btn-warning btn-sm"
onclick="return confirm('Deliver this meal?')">
🍽️ Deliver
</a>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>
</div>

</body>
</html>