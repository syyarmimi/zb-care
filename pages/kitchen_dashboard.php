<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kitchen') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= KPI ================= */

$menu = $conn->query("SELECT COUNT(*) FROM SYARMIMI.MENU_ITEM")->fetchColumn();

$plans = $conn->query("SELECT COUNT(*) FROM SYARMIMI.MEAL_PLAN")->fetchColumn();

$prepared = $conn->query("SELECT COUNT(*) FROM SYARMIMI.MEAL_PREPARATION")->fetchColumn();

$pending = $conn->query("
SELECT COUNT(*) 
FROM SYARMIMI.MEAL_PLAN mp
LEFT JOIN SYARMIMI.MEAL_PREPARATION mp2
ON mp.MEALPLAN_ID = mp2.MEALPLAN_ID
WHERE mp2.MEALPLAN_ID IS NULL
")->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
<title>Kitchen Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #fff7ed, #ffedd5);
    font-family: 'Segoe UI';
}

/* HEADER */
.title {
    font-weight:600;
}

/* CARDS */
.card-box {
    background:white;
    padding:20px;
    border-radius:20px;
    text-align:center;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
    transition:0.25s;
}

.card-box:hover {
    transform:translateY(-5px);
}

/* ICON */
.icon {
    width:50px;
    height:50px;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
    margin-bottom:10px;
    font-size:22px;
    color:white;
}

/* COLORS */
.orange { background:#fb923c; }
.green { background:#22c55e; }
.red { background:#ef4444; }
.blue { background:#3b82f6; }

/* TABLE BOX */
.table-box {
    background:white;
    border-radius:20px;
    padding:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_kitchen.php"); ?>

<div class="p-4 w-100">

<h3 class="mb-4 title">🍳 Kitchen Dashboard</h3>

<!-- KPI -->
<div class="row g-3">

<div class="col-md-3">
<div class="card-box">
<div class="icon orange"><i class="bi bi-list"></i></div>
<h6>Menu Items</h6>
<h4><?= $menu ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="icon blue"><i class="bi bi-clipboard"></i></div>
<h6>Meal Plans</h6>
<h4><?= $plans ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="icon green"><i class="bi bi-check-circle"></i></div>
<h6>Prepared</h6>
<h4><?= $prepared ?></h4>
</div>
</div>

<div class="col-md-3">
<div class="card-box">
<div class="icon red"><i class="bi bi-clock"></i></div>
<h6>Pending</h6>
<h4><?= $pending ?></h4>
</div>
</div>

</div>

<!-- RECENT -->
<div class="table-box mt-4">

<h5 class="mb-3">🍽️ Recent Meal Requests</h5>

<?php
$sql = "
SELECT p.NAME, m.FOOD_NAME, mp.MEALTIME_SLOT
FROM SYARMIMI.MEAL_PLAN mp
JOIN SYARMIMI.ADMISSION a ON mp.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MENU_ITEM m ON mp.MENUITEM_ID = m.MENUITEM_ID
ORDER BY mp.MEALPLAN_ID DESC
";

$stmt = $conn->query($sql);
?>

<table class="table table-hover">

<thead>
<tr>
<th>👤 Patient</th>
<th>🍲 Meal</th>
<th>⏰ Time</th>
</tr>
</thead>

<tbody>
<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>
<tr>
<td><?= $row['NAME'] ?></td>
<td><?= $row['FOOD_NAME'] ?></td>
<td><?= $row['MEALTIME_SLOT'] ?></td>
</tr>
<?php endwhile; ?>
</tbody>

</table>

</div>

</div>
</div>

</body>
</html>