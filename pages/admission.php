<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");

/* ================= INSERT ================= */
if (isset($_POST['submit'])) {

    $patient = $_POST['patient_id'];
    $bed     = $_POST['bed'];
    $doctor  = $_POST['doctor'];

    try {
        $sql = "SELECT NVL(MAX(ADMISSION_ID),0)+1 AS NEW_ID FROM SYARMIMI.ADMISSION";
        $stmt = $conn->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newId = $row['NEW_ID'];

        // 🔥 FIX: ADD IS_SEEN = 0
        $sql = "INSERT INTO SYARMIMI.ADMISSION 
                (ADMISSION_ID, PATIENT_ID, BED_ID, STAFF_ID, ADMISSION_DATE, IS_SEEN)
                VALUES (:id, :patient, :bed, :doctor, SYSDATE, 0)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':id' => $newId,
            ':patient' => $patient,
            ':bed' => $bed,
            ':doctor' => $doctor
        ]);

        $conn->prepare("UPDATE SYARMIMI.BED SET STATUS='Occupied' WHERE BED_ID=:bed")
             ->execute([':bed'=>$bed]);

        echo "<script>alert('Admission Added Successfully');</script>";

    } catch (PDOException $e) {
        die("Insert Error: " . $e->getMessage());
    }
}

/* ================= UPDATE DOCTOR ================= */
if (isset($_POST['update_doctor'])) {

    $admission_id = $_POST['admission_id'];
    $doctor_id    = $_POST['doctor_id'];

    try {
        // 🔥 FIX: RESET IS_SEEN = 0
        $sql = "UPDATE SYARMIMI.ADMISSION 
                SET STAFF_ID = :doctor,
                    IS_SEEN = 0
                WHERE ADMISSION_ID = :id";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':doctor' => $doctor_id,
            ':id' => $admission_id
        ]);

        echo "<script>alert('Doctor Updated'); window.location='admission.php';</script>";

    } catch (PDOException $e) {
        die("Update Error: " . $e->getMessage());
    }
}

/* ================= DISCHARGE ================= */
if (isset($_GET['discharge'])) {
    $id = $_GET['discharge'];

    try {
        $stmt = $conn->prepare("SELECT BED_ID FROM SYARMIMI.ADMISSION WHERE ADMISSION_ID=:id");
        $stmt->execute([':id'=>$id]);
        $bed = $stmt->fetch(PDO::FETCH_ASSOC);

        $conn->prepare("
            UPDATE SYARMIMI.ADMISSION 
            SET DISCHARGE_DATE = SYSDATE 
            WHERE ADMISSION_ID=:id
        ")->execute([':id'=>$id]);

        $conn->prepare("
            UPDATE SYARMIMI.BED 
            SET STATUS='Available' 
            WHERE BED_ID=:bed
        ")->execute([':bed'=>$bed['BED_ID']]);

        echo "<script>alert('Patient Discharged'); window.location='admission.php';</script>";

    } catch (PDOException $e) {
        die("Discharge Error: " . $e->getMessage());
    }
}

/* ================= COUNT ================= */
$count = $conn->query("
    SELECT COUNT(*) AS TOTAL
    FROM SYARMIMI.PATIENT
    WHERE PATIENT_ID NOT IN (
        SELECT PATIENT_ID FROM SYARMIMI.ADMISSION WHERE DISCHARGE_DATE IS NULL
    )
")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Admission</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }

.form-box, .table-box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0px 4px 10px rgba(0,0,0,0.1);
    margin-top:20px;
}
</style>
</head>

<body>

<?php include("../includes/header.php"); ?>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h3>🏥 Patient Admission</h3>

<?php if($count['TOTAL'] > 0): ?>
<div class="alert alert-danger">
⚠️ <?= $count['TOTAL'] ?> patient(s) pending admission
</div>
<?php endif; ?>

<!-- ================= FORM ================= -->
<div class="form-box">
<form method="POST">
<div class="row">

<div class="col-md-3">
<label>Patient</label>
<select name="patient_id" class="form-control" required>
<option value="">Select Patient</option>
<?php
$patients = $conn->query("
SELECT PATIENT_ID, NAME 
FROM SYARMIMI.PATIENT
WHERE PATIENT_ID NOT IN (
SELECT PATIENT_ID FROM SYARMIMI.ADMISSION WHERE DISCHARGE_DATE IS NULL
)");
while($p = $patients->fetch(PDO::FETCH_ASSOC)){
echo "<option value='{$p['PATIENT_ID']}'>{$p['NAME']}</option>";
}
?>
</select>
</div>

<div class="col-md-3">
<label>Bed</label>
<select name="bed" class="form-control" required>
<option value="">Select Bed</option>
<?php
$beds = $conn->query("
SELECT B.BED_ID, B.BED_NUMBER, W.WARD_NAME
FROM SYARMIMI.BED B
JOIN SYARMIMI.WARD W ON B.WARD_ID = W.WARD_ID
WHERE B.STATUS = 'Available'
");
while($b = $beds->fetch(PDO::FETCH_ASSOC)){
echo "<option value='{$b['BED_ID']}'>{$b['BED_NUMBER']} ({$b['WARD_NAME']})</option>";
}
?>
</select>
</div>

<div class="col-md-3">
<label>Doctor</label>
<select name="doctor" class="form-control" required>
<option value="">Select Doctor</option>
<?php
$doctors = $conn->query("
SELECT ACCOUNT_ID, USERNAME 
FROM SYARMIMI.HOSPITAL_STAFF
WHERE ROLE = 'doctor'
");
while($d = $doctors->fetch(PDO::FETCH_ASSOC)){
echo "<option value='{$d['ACCOUNT_ID']}'>{$d['USERNAME']}</option>";
}
?>
</select>
</div>

<div class="col-md-3 d-flex align-items-end">
<button class="btn btn-primary w-100" name="submit">Add Admission</button>
</div>

</div>
</form>
</div>

<!-- ================= ADMISSION LIST ================= -->
<div class="table-box">

<h5>📋 Admission List</h5>

<table class="table table-bordered mt-3">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Ward</th>
<th>Bed</th>
<th>Doctor</th>
<th>Admission Date</th>
<th>Discharge Date</th>
<th>Action</th>
<th>Record</th> <!-- ✅ ADDED -->
</tr>
</thead>

<tbody>

<?php
$stmt = $conn->query("
SELECT 
A.ADMISSION_ID,
P.NAME AS PATIENT_NAME,
W.WARD_NAME,
B.BED_NUMBER,
A.STAFF_ID,
S.USERNAME AS DOCTOR_NAME,
A.ADMISSION_DATE,
A.DISCHARGE_DATE
FROM SYARMIMI.ADMISSION A
JOIN SYARMIMI.PATIENT P ON A.PATIENT_ID = P.PATIENT_ID
JOIN SYARMIMI.BED B ON A.BED_ID = B.BED_ID
JOIN SYARMIMI.WARD W ON B.WARD_ID = W.WARD_ID
LEFT JOIN SYARMIMI.HOSPITAL_STAFF S ON A.STAFF_ID = S.ACCOUNT_ID
ORDER BY A.ADMISSION_ID DESC
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

echo "<tr>
<td>{$row['ADMISSION_ID']}</td>
<td>{$row['PATIENT_NAME']}</td>
<td>{$row['WARD_NAME']}</td>
<td>{$row['BED_NUMBER']}</td>
<td>{$row['DOCTOR_NAME']}</td>

<td>{$row['ADMISSION_DATE']}</td>
<td>".($row['DISCHARGE_DATE'] ?? '-')."</td>
<td>";

if ($row['DISCHARGE_DATE'] == NULL) {
echo "<a href='?discharge={$row['ADMISSION_ID']}' 
class='btn btn-danger btn-sm'
onclick=\"return confirm('Discharge this patient?')\">
Discharge
</a>";
} else {
echo "<span class='badge bg-success'>Done</span>";
}

echo "</td>

<td> <!-- ✅ ADDED -->
<a href='patient_record.php?id={$row['ADMISSION_ID']}' 
class='btn btn-info btn-sm'>
View
</a>
</td>

</tr>";
}
?>

</tbody>
</table>

</div>

</div>
</div>

</body>
</html>