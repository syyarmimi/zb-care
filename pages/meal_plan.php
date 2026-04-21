<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'doctor') die("Access Denied");

// ✅ GET DOCTOR ID
$doctor_id = $_SESSION['user_id'] ?? 0;

/* ================= FETCH PATIENT (FIXED) ================= */
$sql = "SELECT a.ADMISSION_ID, p.NAME 
        FROM SYARMIMI.ADMISSION a
        JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
        WHERE a.STAFF_ID = :doctor
        AND a.DISCHARGE_DATE IS NULL
        ORDER BY a.ADMISSION_ID DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([':doctor'=>$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH MENU ================= */
$sql = "SELECT MENUITEM_ID, FOOD_NAME FROM SYARMIMI.MENU_ITEM";
$menus = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ================= INSERT ================= */
if(isset($_POST['save'])){

    $sql = "INSERT INTO SYARMIMI.MEAL_PLAN
            (MealPlan_ID, Admission_ID, MenuItem_ID, Meal_Date, MealTime_Slot)
            VALUES (SYARMIMI.MEALPLAN_SEQ.NEXTVAL, :adm, :menu, SYSDATE, :time)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':adm' => $_POST['admission_id'],
        ':menu' => $_POST['menu_id'],
        ':time' => $_POST['time']
    ]);

    echo "<script>alert('Meal Plan Assigned!'); window.location='meal_plan.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Meal Plan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }
.content { flex:1; padding:30px; }
.box { background:white; padding:25px; border-radius:15px; box-shadow:0 5px 15px rgba(0,0,0,0.05); }
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h4>🍽️ Meal Plan</h4>

<div class="box">

<form method="POST">

<div class="mb-3">
<label>Select Patient (Admission)</label>
<select name="admission_id" class="form-control" required>

<?php if(count($patients) > 0): ?>
    <?php foreach($patients as $p): ?>
    <option value="<?= $p['ADMISSION_ID'] ?>">
        <?= $p['NAME'] ?> (ID: <?= $p['ADMISSION_ID'] ?>)
    </option>
    <?php endforeach; ?>
<?php else: ?>
    <option>No Assigned Patients</option>
<?php endif; ?>

</select>
</div>

<div class="mb-3">
<label>Menu</label>
<select name="menu_id" class="form-control" required>
<?php foreach($menus as $m): ?>
<option value="<?= $m['MENUITEM_ID'] ?>">
<?= $m['FOOD_NAME'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Meal Time</label>
<select name="time" class="form-control" required>
<option value="">Select Time</option>
<option>Breakfast</option>
<option>Lunch</option>
<option>Dinner</option>
</select>
</div>

<button name="save" class="btn btn-success w-100">
Assign Meal Plan
</button>

</form>

</div>

</div>
</div>

</body>
</html>