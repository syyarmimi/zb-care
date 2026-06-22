<?php
session_start();
include("../config/config.php");

if(!isset($_GET['id'])){
    die("Invalid Admission");
}

$admission_id = intval($_GET['id']);

/* PATIENT INFO */

$stmt = $conn->prepare("

SELECT
P.*,
A.ADMISSION_DATE,
A.DISCHARGE_DATE,
A.ADMISSION_ID,
W.WARD_NAME,
B.BED_NUMBER

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID

JOIN SYARMIMI.BED B
ON A.BED_ID = B.BED_ID

JOIN SYARMIMI.WARD W
ON B.WARD_ID = W.WARD_ID

WHERE A.ADMISSION_ID = :id

");

$stmt->execute([
':id'=>$admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

/* DIAGNOSIS */

$diag = $conn->prepare("

SELECT
DIAGNOSIS_DETAILS,
DATE_RECORDED

FROM SYARMIMI.DIAGNOSIS

WHERE ADMISSION_ID=:id

ORDER BY DATE_RECORDED DESC

");

$diag->execute([
':id'=>$admission_id
]);

$diagnosisList =
$diag->fetchAll(PDO::FETCH_ASSOC);

/* MEDICATION HISTORY */

$medStmt = $conn->prepare("

SELECT
M.MEDICATION_NAME,
DOSAGE,
FREQUENCY

FROM SYARMIMI.MEDICATION_ORDER MO
JOIN SYARMIMI.MEDICATION M
ON MO.MEDICATION_ID = M.MEDICATION_ID

WHERE ADMISSION_ID = :id

ORDER BY MEDORDER_ID DESC

");

$medStmt->execute([
':id'=>$admission_id
]);

$medicationList =
$medStmt->fetchAll(PDO::FETCH_ASSOC);

/* DISCHARGE */

if(isset($_POST['discharge']))
{

    /* GET BED ID */

    $bedStmt = $conn->prepare("
    SELECT BED_ID
    FROM SYARMIMI.ADMISSION
    WHERE ADMISSION_ID = :id
    ");

    $bedStmt->execute([
        ':id' => $admission_id
    ]);

    $bed_id = $bedStmt->fetchColumn();

    /* DISCHARGE */

    $update = $conn->prepare("
    UPDATE SYARMIMI.ADMISSION
    SET DISCHARGE_DATE = SYSDATE
    WHERE ADMISSION_ID = :id
    ");

    $update->execute([
        ':id' => $admission_id
    ]);

    /* FREE BED */

    $conn->prepare("
    UPDATE SYARMIMI.BED
    SET STATUS='Available'
    WHERE BED_ID = :bed
    ")->execute([
        ':bed' => $bed_id
    ]);

    header(
    "Location: patient_details.php?id=".$admission_id
    );
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<title>Patient Details</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body>

<div class="container mt-4">

<h2>
Patient Details
</h2>

<div class="d-flex justify-content-between mb-3">

<a
href="patient_management.php"
class="btn btn-secondary">

← Back

</a>

<a
href="patient_report.php?id=<?= $admission_id ?>"
class="btn btn-success">

📄 Export PDF

</a>

</div>

<div class="card p-4">

<h4>
<?= $patient['NAME'] ?>


</h4>

<hr>

<div class="row">

<div class="col-md-6">

<p>
<b>Ward:</b>
<?= $patient['WARD_NAME'] ?>
</p>

<p>
<b>Bed:</b>
<?= $patient['BED_NUMBER'] ?>
</p>

</div>

<div class="col-md-6">

<p>
<b>Admission Date:</b>
<?= $patient['ADMISSION_DATE'] ?>
</p>

<p>
<b>Discharge Date:</b>
<?= $patient['DISCHARGE_DATE'] ?: 'Still Admitted' ?>
</p>

</div>

</div>

<br>

<?php if(empty($patient['DISCHARGE_DATE'])): ?>

<form method="POST" id="dischargeForm">

<button
type="button"
id="dischargeBtn"
class="btn btn-danger">

Discharge Patient

</button>

<input
type="hidden"
name="discharge"
value="1">

</form>

<?php endif; ?>

<br>

<div class="card p-4">

<h4>
Diagnosis History
</h4>

<table class="table">

<tr>

<th>Date</th>
<th>Diagnosis</th>

</tr>

<?php foreach($diagnosisList as $d): ?>

<tr>

<td>
<?= $d['DATE_RECORDED'] ?>
</td>

<td>
<?= $d['DIAGNOSIS_DETAILS'] ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<div class="card p-4 mt-4">

<h4>
💊 Medication History
</h4>

<table class="table">

<tr>

<th>Medication Name</th>
<th>Dosage</th>
<th>Frequency</th>

</tr>

<?php foreach($medicationList as $m): ?>

<tr>

<td>
<?= $m['MEDICATION_NAME'] ?>
</td>

<td>
<?= $m['DOSAGE'] ?>
</td>

<td>
<?= $m['FREQUENCY'] ?>
</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<div class="card p-4 mt-4">

<h4>
📌 Patient Timeline
</h4>

<ul class="list-group">

<li class="list-group-item">

🏥 Admitted

<br>

<?= $patient['ADMISSION_DATE'] ?>

</li>

<?php foreach($diagnosisList as $d): ?>

<li class="list-group-item">

🩺 Diagnosis Recorded

<br>

<?= $d['DATE_RECORDED'] ?>

</li>

<?php endforeach; ?>

<!-- Medication -->

<?php foreach($medicationList as $m): ?>

<li class="list-group-item">

💊 Medication Prescribed

<br>

Medication Name:
<?= $m['MEDICATION_NAME'] ?>

<br>

Dosage:
<?= $m['DOSAGE'] ?>

<br>

Frequency:
<?= $m['FREQUENCY'] ?>

</li>

<?php endforeach; ?>


<?php if(!empty($patient['DISCHARGE_DATE'])): ?>

<li class="list-group-item">

✅ Discharged

<br>

<?= $patient['DISCHARGE_DATE'] ?>

</li>

<?php endif; ?>

</ul>

</div>

<script>

document
.getElementById('dischargeBtn')
.addEventListener('click', function(){

Swal.fire({

title:'Discharge Patient?',
text:'This patient will be discharged.',
icon:'warning',
showCancelButton:true,
confirmButtonText:'Yes'

}).then((result)=>{

if(result.isConfirmed){

document
.getElementById('dischargeForm')
.submit();

}

});

});

</script>

</body>
</html>