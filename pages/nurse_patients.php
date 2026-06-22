<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'nurse') {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

$ward_id = $_GET['ward'] ?? 'All';

$sql = "
SELECT b.BED_ID,
       b.BED_NUMBER,
       b.STATUS,
       w.WARD_NAME,
       p.NAME,
       p.AGE,
       p.GENDER,
       a.ADMISSION_ID,

(
SELECT LISTAGG(
m.MEDICATION_NAME || '|' ||
mo.DOSAGE || '|' ||
mo.FREQUENCY,
'~~'
)
WITHIN GROUP (ORDER BY mo.MEDORDER_ID)

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.MEDICATION m
ON mo.MEDICATION_ID = m.MEDICATION_ID

WHERE mo.ADMISSION_ID = a.ADMISSION_ID

AND NOT EXISTS
(
SELECT 1
FROM SYARMIMI.MEDICATION_ADMIN ma
WHERE ma.MEDORDER_ID = mo.MEDORDER_ID
)

) AS MED_LIST,


       (
       SELECT COUNT(*)
       FROM SYARMIMI.MEDICATION_ORDER mo

       JOIN SYARMIMI.PHARMACY_PREPARATION pp
       ON mo.MEDORDER_ID = pp.MEDORDER_ID

       LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
       ON mo.MEDORDER_ID = ma.MEDORDER_ID

       WHERE mo.ADMISSION_ID = a.ADMISSION_ID
       AND ma.MEDORDER_ID IS NULL
       ) AS MED_COUNT

FROM BED b
JOIN WARD w ON b.WARD_ID = w.WARD_ID
LEFT JOIN ADMISSION a
ON a.ADMISSION_ID =
(
    SELECT MAX(a2.ADMISSION_ID)
    FROM ADMISSION a2
    WHERE a2.BED_ID = b.BED_ID
)
LEFT JOIN PATIENT p ON a.PATIENT_ID = p.PATIENT_ID

WHERE 1=1";

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
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

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

.modal-content{
border:none;
border-radius:20px;
overflow:hidden;
}

.modal-header{
background:#0f172a;
color:white;
}

.modal-body{
padding:25px;
}

.alert-primary{
border:none;
background:#eff6ff;
border-radius:15px;
}

.ward-summary-card{
cursor:pointer;
border-radius:20px;
transition:.3s;
}

.ward-summary-card:hover{
transform:translateY(-5px);
box-shadow:0 12px 30px rgba(0,0,0,.15);
}

.btn-success{
border-radius:12px;
font-weight:600;
padding:12px;
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

<?php

$wardSummary = $conn->query("

SELECT
w.WARD_NAME,

COUNT(DISTINCT b.BED_ID) TOTAL_BED,

COUNT(DISTINCT CASE
WHEN b.STATUS='Occupied'
THEN b.BED_ID
END) OCCUPIED_BED,

COUNT(DISTINCT CASE
WHEN ma.MEDORDER_ID IS NULL
THEN mo.MEDORDER_ID
END) PENDING_MED,

COUNT(DISTINCT ma.MEDORDER_ID) DELIVERED_MED

FROM SYARMIMI.WARD w

LEFT JOIN SYARMIMI.BED b
ON w.WARD_ID = b.WARD_ID

LEFT JOIN SYARMIMI.ADMISSION a
ON b.BED_ID = a.BED_ID

LEFT JOIN SYARMIMI.MEDICATION_ORDER mo
ON a.ADMISSION_ID = mo.ADMISSION_ID

LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma
ON mo.MEDORDER_ID = ma.MEDORDER_ID

GROUP BY w.WARD_NAME

")->fetchAll(PDO::FETCH_ASSOC);

?>

<div class="row mb-4">

<?php foreach($wardSummary as $w): ?>

<?php
$pending = $w['PENDING_MED'];
?>

<div class="col-md-4">

<div class="card shadow border-0 ward-summary-card"
onclick="showWardDetails(
'<?= $w['WARD_NAME'] ?>',
'<?= $w['TOTAL_BED'] ?>',
'<?= $w['OCCUPIED_BED'] ?>',
'<?= $pending ?>',
'<?= $w['DELIVERED_MED'] ?>'
)">

<div class="card-body text-center">

<h5>
🏥 <?= $w['WARD_NAME'] ?>
</h5>

<p class="mb-1">
Beds: <?= $w['TOTAL_BED'] ?>
</p>

<p class="mb-1">
Occupied: <?= $w['OCCUPIED_BED'] ?>
</p>

<p class="text-danger fw-bold">
💊 Pending: <?= $pending ?>
</p>

<p class="text-success fw-bold">
✅ Delivered: <?= $w['DELIVERED_MED'] ?>
</p>

</div>

</div>

</div>

<?php endforeach; ?>

</div>

<div class="d-flex gap-3 mb-3">

<div class="d-flex align-items-center">
<div style="
width:18px;
height:18px;
background:#d1fae5;
border-radius:4px;
margin-right:8px;
border:1px solid #10b981;
"></div>

<span class="fw-semibold">
Available Bed
</span>
</div>

<div class="d-flex align-items-center">
<div style="
width:18px;
height:18px;
background:#fee2e2;
border-radius:4px;
margin-right:8px;
border:1px solid #ef4444;
"></div>

<span class="fw-semibold">
Occupied Bed
</span>
</div>

</div>

<h5 class="mb-3">
Ward: <?= ($ward_id == 'All') ? 'All' : $result[0]['WARD_NAME'] ?? '' ?>
</h5>

<div class="ward-box mt-3">

<div class="bed-grid">

<?php foreach($result as $row):

$statusClass = ($row['STATUS'] == 'Available') ? 'available' : 'occupied';
?>

<div class="bed-card <?= $statusClass ?>"
onclick='openModal(
<?= json_encode($row["NAME"]) ?>,
<?= json_encode($row["AGE"]) ?>,
<?= json_encode($row["GENDER"]) ?>,
<?= json_encode($row["STATUS"]) ?>,
<?= json_encode($row["ADMISSION_ID"]) ?>,
<?= json_encode($row["MED_LIST"]) ?>
)'>

<div style="
font-size:60px;
margin-bottom:10px;
">
🛏️
</div>

<h5 class="fw-bold">
<?= $row['BED_NUMBER'] ?>
</h5>

<?php if($row['STATUS']=='Occupied'): ?>

<span class="badge bg-danger">
Occupied
</span>

<?php else: ?>

<span class="badge bg-success">
Available
</span>

<?php endif; ?>

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

<div class="modal fade" id="wardModal">

<div class="modal-dialog">

<div class="modal-content">

<div class="modal-header">

<h5 class="modal-title">
🏥 Ward Summary
</h5>

<button
type="button"
class="btn-close"
data-bs-dismiss="modal">
</button>

</div>

<div
class="modal-body"
id="wardModalContent">
</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>

    function showWardDetails(
ward,
beds,
occupied,
pending,
delivered
){

document.getElementById(
'wardModalContent'
).innerHTML = `

<div class="text-center">

<h3>${ward}</h3>

<hr>

<p>
🛏️ Total Beds:
<b>${beds}</b>
</p>

<p>
🏥 Occupied Beds:
<b>${occupied}</b>
</p>

<p class="text-danger">
💊 Pending Medication:
<b>${pending}</b>
</p>

<p class="text-success">
✅ Delivered Medication:
<b>${delivered}</b>
</p>

</div>

`;

new bootstrap.Modal(
document.getElementById('wardModal')
).show();

}

function openModal(
name,
age,
gender,
status,
admission_id,
medList
){

let medicationHTML = "";

if(medList){

let meds = medList.split("~~");

meds.forEach(function(item){

let data = item.split("|");

medicationHTML += `
<tr>
<td>${data[0]}</td>
<td>${data[1]}</td>
<td>${data[2]}</td>
</tr>
`;

});

}

let content = "";

if(status === "Available"){

content = "<p>No patient in this bed</p>";

}else{

content = `

<div class="text-center mb-3">

<div style="font-size:60px">
🛏️
</div>

<h4 class="fw-bold">
${name}
</h4>

<span class="badge bg-danger">
Occupied Bed
</span>

</div>

<hr>

<div class="row">

<div class="col-6">
<strong>Age</strong><br>
${age}
</div>

<div class="col-6">
<strong>Gender</strong><br>
${gender}
</div>

</div>

<hr>

<div class="alert alert-primary">

<h6 class="fw-bold mb-3">
💊 Medication List
</h6>

<table class="table table-sm">

<thead>
<tr>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
</tr>
</thead>

<tbody>

${medicationHTML}

</tbody>

</table>

</div>

<a href="nurse_action.php?give_med=${admission_id}"
class="btn btn-success btn-lg w-100">

<i class="bi bi-check-circle-fill"></i>
Confirm Medication Delivery

</a>

`;

}

document.getElementById("modalContent").innerHTML = content;

let modal = new bootstrap.Modal(
document.getElementById('bedModal')
);

modal.show();

}
</script>

</body>
</html>