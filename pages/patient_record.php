<?php
session_start();
include("../config/config.php");

$id = $_GET['id'];

/* PATIENT */
$p = $conn->query("
SELECT p.NAME
FROM SYARMIMI.ADMISSION a
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
WHERE a.ADMISSION_ID = $id
")->fetch(PDO::FETCH_ASSOC);

/* DIAGNOSIS */
$diag = $conn->query("
SELECT Diagnosis_Details, Allergies
FROM SYARMIMI.DIAGNOSIS
WHERE ADMISSION_ID = $id
");

/* MEDICATION */
$med = $conn->query("
SELECT m.MEDICATION_NAME, mo.DOSAGE, mo.FREQUENCY
FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID
WHERE mo.ADMISSION_ID = $id
");

?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Record</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background:#eef2f7;
    font-family:'Segoe UI';
}

.header-box {
    background: linear-gradient(135deg, #4f46e5, #3b82f6);
    color:white;
    padding:25px;
    border-radius:15px;
    margin-bottom:20px;
}

.card-box {
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
    margin-bottom:20px;
}

.section-title {
    font-weight:600;
    margin-bottom:15px;
}

.empty {
    color:#888;
    font-style:italic;
}
</style>

</head>

<body class="p-4">

<div class="container-fluid">

<!-- 🔙 BACK BUTTON -->
<a href="admission.php" class="btn btn-secondary mb-3">
← Back to Admission
</a>

<!-- HEADER -->
<div class="header-box">
    <h4>🧾 Patient Record</h4>
    <h2><?= $p['NAME'] ?></h2>
</div>

<div class="row">

<!-- DIAGNOSIS -->
<div class="col-md-4">
<div class="card-box">

<h5 class="section-title">🧠 Diagnosis</h5>

<?php 
$hasDiag = false;
while($d = $diag->fetch(PDO::FETCH_ASSOC)): 
$hasDiag = true;
?>

<div class="mb-3 p-2 border rounded">
   <strong><?= $d['DIAGNOSIS_DETAILS'] ?></strong><br>
<small class="text-muted">Allergies: <?= $d['ALLERGIES'] ?: 'None' ?></small>
</div>

<?php endwhile; ?>

<?php if(!$hasDiag): ?>
<p class="empty">No diagnosis recorded</p>
<?php endif; ?>

</div>
</div>

<!-- MEDICATION -->
<div class="col-md-4">
<div class="card-box">

<h5 class="section-title">💊 Medication</h5>

<?php 
$hasMed = false;
while($m = $med->fetch(PDO::FETCH_ASSOC)): 
$hasMed = true;
?>

<div class="mb-3 p-2 border rounded">
    <strong><?= $m['MEDICATION_NAME'] ?></strong><br>
    <small><?= $m['DOSAGE'] ?> | <?= $m['FREQUENCY'] ?></small>
</div>

<?php endwhile; ?>

<?php if(!$hasMed): ?>
<p class="empty">No medication prescribed</p>
<?php endif; ?>

</div>
</div>

</div>

</div>

</body>
</html>