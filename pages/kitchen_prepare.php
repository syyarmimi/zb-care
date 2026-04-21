<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kitchen') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= FILTER ================= */
$time = $_GET['time'] ?? 'All';

/* ================= PREPARE ALL ================= */
if(isset($_GET['prepare_all'])){

    $mealTime = $_GET['prepare_all'];
    $staff = $_SESSION['user_id'];

    $sql = "
    SELECT mp.MEALPLAN_ID 
    FROM SYARMIMI.MEAL_PLAN mp
    LEFT JOIN SYARMIMI.MEAL_PREPARATION prep
    ON mp.MEALPLAN_ID = prep.MEALPLAN_ID
    WHERE prep.MEALPLAN_ID IS NULL
    AND mp.MEALTIME_SLOT = :time
    ";

    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':time'=>$mealTime
    ]);

    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

        $conn->prepare("
        INSERT INTO SYARMIMI.MEAL_PREPARATION
        (PREP_ID, STATUS, PREP_TIME, MEALPLAN_ID, STAFF_ID)
        VALUES (SYARMIMI.MEALPREP_SEQ.NEXTVAL, 'Prepared', SYSDATE, :id, :staff)
        ")->execute([
            ':id'=>$row['MEALPLAN_ID'],
            ':staff'=>$staff
        ]);
    }

    echo "<script>alert('All $mealTime meals prepared!'); window.location='kitchen_prepare.php';</script>";
}

/* ================= SINGLE PREPARE ================= */
if(isset($_GET['prepare'])){

    $mealplan = $_GET['prepare'];
    $staff = $_SESSION['user_id'];

    $check = $conn->prepare("
    SELECT COUNT(*) FROM SYARMIMI.MEAL_PREPARATION
    WHERE MEALPLAN_ID = :id
    ");
    $check->execute([':id'=>$mealplan]);

    if($check->fetchColumn() == 0){

        $conn->prepare("
        INSERT INTO SYARMIMI.MEAL_PREPARATION
        (PREP_ID, STATUS, PREP_TIME, MEALPLAN_ID, STAFF_ID)
        VALUES (SYARMIMI.MEALPREP_SEQ.NEXTVAL, 'Prepared', SYSDATE, :id, :staff)
        ")->execute([
            ':id'=>$mealplan,
            ':staff'=>$staff
        ]);

        echo "<script>alert('Meal Prepared!'); window.location='kitchen_prepare.php';</script>";
    }
}

/* ================= COUNT ================= */
$totalPending = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEAL_PLAN mp
LEFT JOIN SYARMIMI.MEAL_PREPARATION prep
ON mp.MEALPLAN_ID = prep.MEALPLAN_ID
WHERE prep.MEALPLAN_ID IS NULL
")->fetchColumn();

/* ================= FETCH ================= */
$sql = "
SELECT mp.MEALPLAN_ID,
       p.NAME,
       m.FOOD_NAME,
       mp.MEALTIME_SLOT,
       w.WARD_NAME,
       b.BED_NUMBER,

CASE 
    WHEN prep.MEALPLAN_ID IS NOT NULL THEN 'Prepared'
    ELSE 'Pending'
END STATUS

FROM SYARMIMI.MEAL_PLAN mp
JOIN SYARMIMI.ADMISSION a ON mp.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MENU_ITEM m ON mp.MENUITEM_ID = m.MENUITEM_ID
JOIN SYARMIMI.BED b ON a.BED_ID = b.BED_ID
JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID

LEFT JOIN SYARMIMI.MEAL_PREPARATION prep
ON mp.MEALPLAN_ID = prep.MEALPLAN_ID

WHERE 1=1
";

if($time != 'All'){
    $sql .= " AND mp.MEALTIME_SLOT = :time";
}

$sql .= " ORDER BY mp.MEALPLAN_ID DESC";

$stmt = $conn->prepare($sql);

$params = [];

if($time != 'All'){
    $params[':time'] = $time;
}

$stmt->execute($params);
?>

<!DOCTYPE html>
<html>
<head>
<title>Prepare Meals</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background: linear-gradient(135deg, #fff7ed, #ffedd5); }

.box {
    background:white;
    padding:25px;
    border-radius:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_kitchen.php"); ?>

<div class="p-4 w-100">

<h3>🔥 Prepare Meals</h3>

<!-- ALERT -->
<div class="alert alert-warning">
🍽️ <?= $totalPending ?> meals still pending
</div>

<!-- FILTER -->
<form method="GET" class="mb-3">

<select name="time" onchange="this.form.submit()" class="form-control w-25">
<option value="All">All</option>
<option value="Breakfast" <?= ($time=='Breakfast')?'selected':'' ?>>Breakfast</option>
<option value="Lunch" <?= ($time=='Lunch')?'selected':'' ?>>Lunch</option>
<option value="Dinner" <?= ($time=='Dinner')?'selected':'' ?>>Dinner</option>
</select>

</form>

<!-- BULK BUTTON -->
<div class="mb-3">
<a href="?prepare_all=Breakfast" class="btn btn-warning btn-sm">Prepare All Breakfast</a>
<a href="?prepare_all=Lunch" class="btn btn-warning btn-sm">Prepare All Lunch</a>
<a href="?prepare_all=Dinner" class="btn btn-warning btn-sm">Prepare All Dinner</a>
</div>

<div class="box">

<table class="table table-hover">

<thead>
<tr>
<th>👤 Patient</th>
<th>📍 Location</th>
<th>🍲 Meal</th>
<th>⏰ Time</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php 
$hasData = false;
while($row = $stmt->fetch(PDO::FETCH_ASSOC)): 
$hasData = true;
?>

<tr class="<?= ($row['STATUS']=='Pending') ? 'table-danger' : '' ?>">

<td><?= $row['NAME'] ?></td>

<td>
<?= $row['WARD_NAME'] ?><br>
<small>Bed <?= $row['BED_NUMBER'] ?></small>
</td>

<td><?= $row['FOOD_NAME'] ?></td>
<td><?= $row['MEALTIME_SLOT'] ?></td>

<td>
<?php if($row['STATUS'] == 'Prepared'): ?>
<span class="badge bg-success">Prepared</span>
<?php else: ?>
<span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td>
<?php if($row['STATUS'] == 'Pending'): ?>
<a href="?prepare=<?= $row['MEALPLAN_ID'] ?>"
class="btn btn-success btn-sm"
onclick="return confirm('Prepare this meal?')">
Prepare
</a>
<?php else: ?>
<button class="btn btn-secondary btn-sm" disabled>Done</button>
<?php endif; ?>
</td>

</tr>

<?php endwhile; ?>

<?php if(!$hasData): ?>
<tr>
<td colspan="6" class="text-center text-muted">
No meals available
</td>
</tr>
<?php endif; ?>

</tbody>

</table>

</div>

</div>
</div>

</body>
</html>