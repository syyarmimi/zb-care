<?php
session_start();
include("../config/config.php");

// ✅ ALLOW ADMIN + DOCTOR
if (!isset($_SESSION['role']) || 
   ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'doctor')) {
    die("Access Denied");
}

$staff_id = $_SESSION['user_id'] ?? 0;

/* ================= HANDLE DELIVERY ================= */
if(isset($_GET['deliver'])){

    $id = $_GET['deliver'];

    $check = $conn->prepare("
        SELECT COUNT(*) FROM SYARMIMI.MEAL_DELIVERY
        WHERE MEALPLAN_ID = :id
    ");
    $check->execute([':id'=>$id]);

    if($check->fetchColumn() == 0){

        $conn->prepare("
            INSERT INTO SYARMIMI.MEAL_DELIVERY
            (MEALDELIVERY_ID, MEALPLAN_ID, DELIVERY_TIME, STATUS, STAFF_ID)
            VALUES (SYARMIMI.MEAL_DELIVERY_SEQ.NEXTVAL, :id, SYSDATE, 'Delivered', :staff)
        ")->execute([
            ':id'=>$id,
            ':staff'=>$staff_id
        ]);

        echo "<script>alert('Meal Delivered'); window.location='meal.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Meal Delivery</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#f1f5f9;
    font-family:'Segoe UI';
}

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

<?php 
if($_SESSION['role'] == 'admin'){
    include("../includes/sidebar_admin.php");
}else{
    include("../includes/sidebar_doctor.php");
}
?>

<div class="p-4 w-100">

<h4 class="mb-4">🍽️ Meal Delivery</h4>

<div class="box">

<table class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Date</th>
<th>Meal Time</th>
<th>Status</th>
<th>Delivered Time</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
$sql = "
SELECT 
MP.MEALPLAN_ID,
P.NAME,
MP.MEAL_DATE,
MP.MEALTIME_SLOT,

CASE 
    WHEN MD.MEALPLAN_ID IS NOT NULL THEN 'Delivered'
    ELSE NVL(MPR.STATUS, 'Pending')
END AS STATUS,

MD.DELIVERY_TIME

FROM SYARMIMI.MEAL_PLAN MP
JOIN SYARMIMI.ADMISSION A ON MP.ADMISSION_ID = A.ADMISSION_ID
JOIN SYARMIMI.PATIENT P ON A.PATIENT_ID = P.PATIENT_ID

LEFT JOIN SYARMIMI.MEAL_PREPARATION MPR 
ON MP.MEALPLAN_ID = MPR.MEALPLAN_ID

LEFT JOIN SYARMIMI.MEAL_DELIVERY MD 
ON MP.MEALPLAN_ID = MD.MEALPLAN_ID

ORDER BY MP.MEALPLAN_ID
";

$stmt = $conn->query($sql);

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

echo "<tr>
<td>{$row['MEALPLAN_ID']}</td>
<td>{$row['NAME']}</td>
<td>{$row['MEAL_DATE']}</td>
<td>{$row['MEALTIME_SLOT']}</td>
<td>";

if($row['STATUS'] == 'Delivered'){
    echo "<span class='badge bg-success'>Delivered</span>";
}else{
    echo "<span class='badge bg-warning text-dark'>Pending</span>";
}

echo "</td>

<td>".($row['DELIVERY_TIME'] ?? '-')."</td>

<td>";

if($row['STATUS'] != 'Delivered'){
    echo "<a href='?deliver={$row['MEALPLAN_ID']}' 
    class='btn btn-success btn-sm'
    onclick=\"return confirm('Deliver this meal?')\">
    Deliver
    </a>";
}else{
    echo "<button class='btn btn-secondary btn-sm' disabled>Done</button>";
}

echo "</td></tr>";
}
?>

</tbody>

</table>

</div>

</div>
</div>

</body>
</html>