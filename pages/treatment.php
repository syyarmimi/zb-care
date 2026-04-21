<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'doctor') die("Access Denied");

$doctor_id = $_SESSION['user_id'] ?? 0;

/* ================= FETCH PATIENT ================= */
$stmt = $conn->prepare("
SELECT a.ADMISSION_ID, p.NAME 
FROM SYARMIMI.ADMISSION a
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
WHERE a.STAFF_ID = :doctor
AND a.DISCHARGE_DATE IS NULL
ORDER BY a.ADMISSION_ID DESC
");
$stmt->execute([':doctor'=>$doctor_id]);
$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH MEDICATION ================= */
$medications = $conn->query("
SELECT MEDICATION_ID, MEDICATION_NAME 
FROM SYARMIMI.MEDICATION
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH MENU + DIET ================= */
$menus = $conn->query("
SELECT M.MENUITEM_ID, M.FOOD_NAME, D.DIET_NAME
FROM SYARMIMI.MENU_ITEM M
LEFT JOIN SYARMIMI.DIET_TYPE D ON M.DIET_ID = D.DIET_ID
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH DIET ================= */
$diets = $conn->query("
SELECT DIET_ID, DIET_NAME 
FROM SYARMIMI.DIET_TYPE
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= SELECTED PATIENT ================= */
$selected_id = $_POST['admission_id'] ?? null;
$patientInfo = null;

if($selected_id){
    $stmt = $conn->prepare("
    SELECT P.NAME, W.WARD_NAME, B.BED_NUMBER, A.ADMISSION_DATE
    FROM SYARMIMI.ADMISSION A
    JOIN SYARMIMI.PATIENT P ON A.PATIENT_ID = P.PATIENT_ID
    JOIN SYARMIMI.BED B ON A.BED_ID = B.BED_ID
    JOIN SYARMIMI.WARD W ON B.WARD_ID = W.WARD_ID
    WHERE A.ADMISSION_ID = :id
    ");
    $stmt->execute([':id'=>$selected_id]);
    $patientInfo = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= SAVE ================= */
if(isset($_POST['save_all'])){

    $adm = $_POST['admission_id'];

    /* ================= DIAGNOSIS ================= */
    if(!empty($_POST['details'])){
        $conn->prepare("
        INSERT INTO SYARMIMI.DIAGNOSIS 
        (Diagnosis_ID, Diagnosis_Details, Allergies, Date_Recorded, Diet_ID, Admission_ID, Staff_ID)
        VALUES (
            (SELECT NVL(MAX(DIAGNOSIS_ID),0)+1 FROM SYARMIMI.DIAGNOSIS),
            :d, :a, SYSDATE, :diet, :adm, :staff
        )
        ")->execute([
            ':d'=>$_POST['details'],
            ':a'=>$_POST['allergies'],
            ':diet'=>$_POST['diet_id'],
            ':adm'=>$adm,
            ':staff'=>$doctor_id
        ]);
    }

    /* ================= MEDICATION ================= */
    if(!empty($_POST['medication_id'])){
        $conn->prepare("
        INSERT INTO SYARMIMI.MEDICATION_ORDER
        (MedOrder_ID, Admission_ID, Medication_ID, Dosage, Frequency, Staff_ID)
        VALUES (
            (SELECT NVL(MAX(MEDORDER_ID),0)+1 FROM SYARMIMI.MEDICATION_ORDER),
            :adm, :med, :dos, :freq, :staff
        )
        ")->execute([
            ':adm'=>$adm,
            ':med'=>$_POST['medication_id'],
            ':dos'=>$_POST['dosage'],
            ':freq'=>$_POST['frequency'],
            ':staff'=>$doctor_id
        ]);
    }

    /* ================= AUTO MEAL PLAN ================= */
    if(!empty($_POST['diet_id'])){

        $diet = $_POST['diet_id'];

        $menuStmt = $conn->prepare("
            SELECT MENUITEM_ID 
            FROM SYARMIMI.MENU_ITEM
            WHERE DIET_ID = :diet
        ");
        $menuStmt->execute([':diet'=>$diet]);

        $menuList = $menuStmt->fetchAll(PDO::FETCH_ASSOC);

        $times = ['Breakfast', 'Lunch', 'Dinner'];
        $i = 0;

        foreach($times as $time){

            if(isset($menuList[$i])){

                $conn->prepare("
                INSERT INTO SYARMIMI.MEAL_PLAN
                (MealPlan_ID, Admission_ID, MenuItem_ID, Meal_Date, MealTime_Slot)
                VALUES (
                    (SELECT NVL(MAX(MEALPLAN_ID),0)+1 FROM SYARMIMI.MEAL_PLAN),
                    :adm, :menu, SYSDATE, :time
                )
                ")->execute([
                    ':adm'=>$adm,
                    ':menu'=>$menuList[$i]['MENUITEM_ID'],
                    ':time'=>$time
                ]);

                $i++;
            }
        }
    }

    echo "<script>alert('Treatment Saved!'); window.location='treatment.php';</script>";
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Treatment</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#eef2f7; }
.content { flex:1; padding:30px; }

.card-box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
    margin-bottom:20px;
}

.section-title {
    font-weight:600;
    margin-bottom:15px;
}

.patient-info {
    background:#dbeafe;
    border-radius:12px;
    padding:15px;
}

input, select, textarea {
    border-radius:10px !important;
}

button {
    border-radius:12px;
    padding:12px;
    font-weight:600;
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h4 class="mb-4">🧾 Patient Treatment</h4>

<form method="POST">

<!-- SELECT PATIENT -->
<div class="card-box">
<div class="section-title">Select Patient</div>

<select name="admission_id" class="form-control" onchange="this.form.submit()" required>
<option value="">Select Patient</option>

<?php foreach($patients as $p): ?>
<option value="<?= $p['ADMISSION_ID'] ?>" <?= ($selected_id==$p['ADMISSION_ID'])?'selected':'' ?>>
<?= $p['NAME'] ?> (ID: <?= $p['ADMISSION_ID'] ?>)
</option>
<?php endforeach; ?>

</select>
</div>

<!-- PATIENT INFO -->
<?php if($patientInfo): ?>
<div class="card-box patient-info">
<b>👤 <?= $patientInfo['NAME'] ?></b><br>
🏥 <?= $patientInfo['WARD_NAME'] ?> | Bed <?= $patientInfo['BED_NUMBER'] ?><br>
📅 <?= $patientInfo['ADMISSION_DATE'] ?>
</div>
<?php endif; ?>

<!-- DIAGNOSIS -->
<div class="card-box">
<div class="section-title">🧠 Diagnosis</div>

<textarea name="details" class="form-control mb-3" placeholder="Diagnosis..."></textarea>

<div class="row">
<div class="col-md-6">
<input name="allergies" class="form-control" placeholder="Allergies">
</div>
<div class="col-md-6">
<select name="diet_id" class="form-control">
<option value="">Select Diet</option>
<?php foreach($diets as $d): ?>
<option value="<?= $d['DIET_ID'] ?>">
<?= $d['DIET_NAME'] ?>
</option>
<?php endforeach; ?>
</select>
</div>
</div>
</div>

<!-- MEDICATION -->
<div class="card-box">
<div class="section-title">💊 Medication</div>

<select name="medication_id" class="form-control mb-3">
<option value="">Select Medication</option>
<?php foreach($medications as $m): ?>
<option value="<?= $m['MEDICATION_ID'] ?>">
<?= $m['MEDICATION_NAME'] ?>
</option>
<?php endforeach; ?>
</select>

<div class="row">
<div class="col-md-6">
<input name="dosage" class="form-control" placeholder="Dosage">
</div>
<div class="col-md-6">
<input name="frequency" class="form-control" placeholder="Frequency">
</div>
</div>
</div>

<!-- BUTTON -->
<button name="save_all" class="btn btn-primary w-100">
💾 Save Treatment
</button>

</form>

</div>
</div>

</body>
</html>