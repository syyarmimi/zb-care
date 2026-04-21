<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

$ward_id = $_GET['ward'] ?? 'All';

$sql = "
SELECT b.BED_ID, b.BED_NUMBER, b.STATUS,
       w.WARD_NAME,
       p.NAME, p.AGE, p.GENDER,
       m.FOOD_NAME,
       a.ADMISSION_ID   -- ✅ ADDED

FROM BED b
JOIN WARD w ON b.WARD_ID = w.WARD_ID
LEFT JOIN ADMISSION a ON b.BED_ID = a.BED_ID
LEFT JOIN PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
LEFT JOIN MEAL_PLAN mp ON a.ADMISSION_ID = mp.ADMISSION_ID
LEFT JOIN MENU_ITEM m ON mp.MENUITEM_ID = m.MENUITEM_ID
WHERE 1=1
";

if($ward_id != 'All'){
    $sql .= " AND b.WARD_ID = '$ward_id'";
}

$sql .= " ORDER BY b.BED_ID";

$result = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$wards = $conn->query("SELECT * FROM WARD")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Ward Layout</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }

.page-content { margin-top:20px; }

.ward-box {
    background:white;
    padding:30px;
    border-radius:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}

.bed-grid {
    display:grid;
    grid-template-columns: repeat(4, 1fr);
    gap:25px;
}

.bed-card {
    height:150px;
    border-radius:18px;
    padding:18px;
    text-align:center;
    position:relative;
    cursor:pointer;
    transition:0.25s ease;
}

.bed-card:hover {
    transform:translateY(-5px) scale(1.03);
}

.available { background:#d1fae5; }
.occupied { background:#fee2e2; }

.tooltip-box {
    display:none;
    position:absolute;
    bottom:170px;
    left:0;
    background:white;
    padding:12px;
    border-radius:12px;
    width:220px;
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    z-index:100;
    font-size:14px;
}

.bed-card:hover .tooltip-box {
    display:block;
}
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_nurse.php"); ?>

<div class="flex-grow-1 p-4 page-content">

<h3 class="mb-4">🏥 Ward Layout</h3>

<form method="GET" class="mb-4">
<label class="me-2">Select Ward:</label>

<select name="ward" onchange="this.form.submit()" class="form-control w-25 d-inline-block">
<option value="All">All</option>

<?php foreach($wards as $w): ?>
<option value="<?= $w['WARD_ID'] ?>" <?= ($ward_id == $w['WARD_ID']) ? 'selected' : '' ?>>
<?= $w['WARD_NAME'] ?>
</option>
<?php endforeach; ?>

</select>
</form>

<h5 class="mb-3">
Ward: <?= ($ward_id == 'All') ? 'All' : $result[0]['WARD_NAME'] ?? '' ?>
</h5>

<div class="ward-box mt-3">

<div class="bed-grid">

<?php foreach($result as $row):

$statusClass = ($row['STATUS'] == 'Available') ? 'available' : 'occupied';
?>

<div class="bed-card <?= $statusClass ?>"
onclick="openModal(
'<?= $row['NAME'] ?>',
'<?= $row['AGE'] ?>',
'<?= $row['GENDER'] ?>',
'<?= $row['FOOD_NAME'] ?? 'None' ?>',
'<?= $row['STATUS'] ?>',
'<?= $row['ADMISSION_ID'] ?>'  <!-- ✅ PASS THIS -->
)">

<strong><?= $row['BED_NUMBER'] ?></strong><br>
<small><?= $row['STATUS'] ?></small>

<div class="tooltip-box">

<?php if($row['STATUS'] == 'Available'): ?>
No patient
<?php else: ?>

<strong><?= $row['NAME'] ?></strong><br>
Age: <?= $row['AGE'] ?><br>
Sex: <?= $row['GENDER'] ?><br>
Diet: <?= $row['FOOD_NAME'] ?? 'None' ?>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>

</div>

</div>

</div>

</div>

<!-- MODAL -->
<div class="modal fade" id="bedModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Patient Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body" id="modalContent"></div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
function openModal(name, age, gender, meal, status, admission_id){

    let content = "";

    if(status === "Available"){
        content = "<p>No patient in this bed</p>";
    } else {
        content = `
            <p><strong>Name:</strong> ${name}</p>
            <p><strong>Age:</strong> ${age}</p>
            <p><strong>Gender:</strong> ${gender}</p>

            <hr>

            <p><strong>Meal:</strong> ${meal}</p>

            <hr>

            <a href="nurse_action.php?give_med=${admission_id}" 
               class="btn btn-success w-100 mb-2">
                💊 Give Medication
            </a>

            <a href="nurse_action.php?give_meal=${admission_id}" 
               class="btn btn-warning w-100">
                🍽️ Meal Received
            </a>
        `;
    }

    document.getElementById("modalContent").innerHTML = content;

    let modal = new bootstrap.Modal(document.getElementById('bedModal'));
    modal.show();
}
</script>

</body>
</html>