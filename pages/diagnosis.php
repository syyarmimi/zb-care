<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'doctor') die("Access Denied");

// ✅ GET DOCTOR ID
$doctor_id = $_SESSION['user_id'] ?? 0;

// SHOW ERROR (for debug)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// =======================
// INSERT DIAGNOSIS
// =======================
if(isset($_POST['save'])){

    $details = $_POST['details'];
    $allergies = $_POST['allergies'];
    $diet = $_POST['diet_id'];
    $admission = $_POST['admission_id'];
    $staff = $doctor_id;

    $sql = "INSERT INTO SYARMIMI.DIAGNOSIS 
            (Diagnosis_ID, Diagnosis_Details, Allergies, Date_Recorded, Diet_ID, Admission_ID, Staff_ID)
            VALUES (SYARMIMI.DIAG_SEQ.NEXTVAL, :d, :a, SYSDATE, :diet, :adm, :staff)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        ':d' => $details,
        ':a' => $allergies,
        ':diet' => $diet,
        ':adm' => $admission,
        ':staff' => $staff
    ]);

    echo "<script>alert('Diagnosis Saved!'); window.location='diagnosis.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Diagnosis</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }

.content {
    flex:1;
    padding:30px;
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

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h4>🧠 Patient Diagnosis</h4>

<div class="box">

<form method="POST">

<!-- ✅ FIXED: ONLY DOCTOR PATIENTS -->
<div class="mb-3">
<label>Select Patient (Admission)</label>

<select name="admission_id" class="form-control" required>

<?php
$sql = "SELECT a.ADMISSION_ID, p.NAME 
        FROM SYARMIMI.ADMISSION a
        JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
        WHERE a.STAFF_ID = :doctor
        AND a.DISCHARGE_DATE IS NULL
        ORDER BY a.ADMISSION_ID DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([':doctor'=>$doctor_id]);

$found = false;

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $found = true;
?>
    <option value="<?= $row['ADMISSION_ID'] ?>">
        <?= $row['NAME'] ?> (ID: <?= $row['ADMISSION_ID'] ?>)
    </option>
<?php } ?>

<?php if(!$found): ?>
    <option>No Assigned Patients</option>
<?php endif; ?>

</select>

</div>

<div class="mb-3">
<label>Diagnosis</label>
<textarea name="details" class="form-control" required></textarea>
</div>

<div class="mb-3">
<label>Allergies</label>
<input name="allergies" class="form-control">
</div>

<div class="mb-3">
<label>Diet ID</label>
<input name="diet_id" class="form-control" placeholder="1 / 2 / 3">
</div>

<button name="save" class="btn btn-primary w-100">
Save Diagnosis
</button>

</form>

</div>

</div>

</div>

</body>
</html>