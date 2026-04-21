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

/* ================= FETCH MEDICATION ================= */
$sql = "SELECT MEDICATION_ID, MEDICATION_NAME FROM SYARMIMI.MEDICATION";
$medications = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

/* ================= INSERT ================= */
if(isset($_POST['save'])){

    $sql = "INSERT INTO SYARMIMI.MEDICATION_ORDER
            (MedOrder_ID, Admission_ID, Medication_ID, Dosage, Frequency, Staff_ID)
            VALUES (SYARMIMI.MEDORDER_SEQ.NEXTVAL, :adm, :med, :dos, :freq, :staff)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':adm' => $_POST['admission_id'],
        ':med' => $_POST['medication_id'],
        ':dos' => $_POST['dosage'],
        ':freq' => $_POST['frequency'],
        ':staff' => $doctor_id
    ]);

    echo "<script>alert('Medication Ordered!'); window.location='med_order.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Order</title>
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

<h4>💊 Medication Order</h4>

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
<label>Medication</label>
<select name="medication_id" class="form-control" required>
<?php foreach($medications as $m): ?>
<option value="<?= $m['MEDICATION_ID'] ?>">
<?= $m['MEDICATION_NAME'] ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label>Dosage</label>
<input name="dosage" class="form-control" required>
</div>

<div class="mb-3">
<label>Frequency</label>
<input name="frequency" class="form-control" required>
</div>

<button name="save" class="btn btn-primary w-100">
Prescribe Medication
</button>

</form>

</div>

</div>
</div>

</body>
</html>