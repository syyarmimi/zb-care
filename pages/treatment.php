<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'doctor') die("Access Denied");

$doctor_id = $_SESSION['user_id'] ?? 0;

$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';

$appointmentPatient = null;
$walkinPatient = null;
$patientInfo = null;

if($type == 'appointment' && !empty($id))
{
    $stmt = $conn->prepare("
        SELECT *
        FROM SYARMIMI.APPOINTMENT
        WHERE APPOINTMENT_ID = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $appointmentPatient = $stmt->fetch(PDO::FETCH_ASSOC);

    if($appointmentPatient)
    {
        $patientInfo = [
            'NAME' => $appointmentPatient['PATIENT_NAME'],
            'WARD_NAME' => 'Appointment Patient',
            'BED_NUMBER' => '-',
            'ADMISSION_DATE' => $appointmentPatient['APPOINTMENT_DATE']
        ];
    }
}

if($type == 'walkin' && !empty($id))
{
    $stmt = $conn->prepare("
    SELECT
        W.*,
        P.*
    FROM SYARMIMI.WALKIN_CONSULTATION W
    JOIN SYARMIMI.PATIENT P
    ON W.PATIENT_ID = P.PATIENT_ID
    WHERE W.CONSULTATION_ID = :id
    ");

    $stmt->execute([
        ':id' => $id
    ]);

    $walkinPatient = $stmt->fetch(PDO::FETCH_ASSOC);

    if($walkinPatient)
    {
        $patientInfo = [
            'NAME' => $walkinPatient['NAME'],
            'WARD_NAME' => 'Walk-In Patient',
            'BED_NUMBER' => '-',
            'ADMISSION_DATE' => date('d-M-Y')
        ];
    }
}

/* ================= FETCH PATIENT ================= */
/* ================= FETCH ADMISSION + APPOINTMENT ================= */

$stmt = $conn->prepare("
SELECT
    A.ADMISSION_ID AS RECORD_ID,
    P.NAME,
    NULL AS APPOINTMENT_TIME,
    'ADMISSION' AS SOURCE_TYPE
FROM SYARMIMI.ADMISSION A
JOIN SYARMIMI.PATIENT P
ON A.PATIENT_ID = P.PATIENT_ID
WHERE A.ACCOUNT_ID = :doctor1
AND A.DISCHARGE_DATE IS NULL

UNION ALL

SELECT
    APPOINTMENT_ID,
    PATIENT_NAME,
    APPOINTMENT_TIME,
    'APPOINTMENT'
FROM SYARMIMI.APPOINTMENT
WHERE ACCOUNT_ID = :doctor2
AND STATUS='Approved'
AND UPPER(APPOINTMENT_DATE) = UPPER(TO_CHAR(SYSDATE,'DD-MON-RR'))

UNION ALL

SELECT
    W.CONSULTATION_ID,
    P.NAME,
    'Walk-In',
    'WALKIN'
FROM SYARMIMI.WALKIN_CONSULTATION W
JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID
WHERE W.ACCOUNT_ID = :doctor3
AND TRIM(UPPER(W.STATUS))='ASSIGNED'
");

$stmt->execute([
    ':doctor1'=>$doctor_id,
    ':doctor2'=>$doctor_id,
    ':doctor3'=>$doctor_id
]);

$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

$todayAppointments = $conn->prepare("
SELECT
    APPOINTMENT_ID AS RECORD_ID,
    PATIENT_NAME,
    STATUS,
    APPOINTMENT_TIME,
    'APPOINTMENT' AS TYPE
FROM SYARMIMI.APPOINTMENT
WHERE ACCOUNT_ID = :doctor1
AND STATUS = 'Approved'
AND UPPER(APPOINTMENT_DATE) = UPPER(TO_CHAR(SYSDATE,'DD-MON-RR'))

UNION ALL

SELECT
    W.CONSULTATION_ID AS RECORD_ID,
    P.NAME AS PATIENT_NAME,
    W.STATUS,
    'Walk-In' AS APPOINTMENT_TIME,
    'WALKIN' AS TYPE
FROM SYARMIMI.WALKIN_CONSULTATION W
JOIN SYARMIMI.PATIENT P
ON W.PATIENT_ID = P.PATIENT_ID
WHERE W.ACCOUNT_ID = :doctor2
AND W.STATUS = 'Assigned'
");

$todayAppointments->execute([
    ':doctor1' => $doctor_id,
    ':doctor2' => $doctor_id
]);

$todayAppointments = $todayAppointments->fetchAll(PDO::FETCH_ASSOC);

/* ================= FETCH MEDICATION ================= */
$medications = $conn->query("
SELECT MEDICATION_ID, MEDICATION_NAME 
FROM SYARMIMI.MEDICATION
")->fetchAll(PDO::FETCH_ASSOC);

/* ================= AVAILABLE BEDS ================= */
$availableBeds = [];

// Get patient's department if available
$patientDept = null;

if($type == 'appointment' && $appointmentPatient) {
    $patientDept = $appointmentPatient['DEPARTMENT'] ?? null;
} elseif($type == 'walkin' && $walkinPatient) {
    $patientDept = $walkinPatient['DEPARTMENT'] ?? null;
}

// If patient has department, show beds from that ward
if($patientDept) {
    $stmt = $conn->prepare("
    SELECT
        B.BED_ID,
        B.BED_NUMBER,
        W.WARD_NAME
    FROM SYARMIMI.BED B
    JOIN SYARMIMI.WARD W
    ON B.WARD_ID = W.WARD_ID
    WHERE UPPER(W.WARD_NAME) = UPPER(:department)
    AND B.STATUS = 'Available'
    ");
    $stmt->execute([':department' => $patientDept]);
    $availableBeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// If no beds found for patient's department, show all available beds
if(empty($availableBeds)) {
    $fallbackStmt = $conn->prepare("
    SELECT
        B.BED_ID,
        B.BED_NUMBER,
        W.WARD_NAME
    FROM SYARMIMI.BED B
    JOIN SYARMIMI.WARD W
    ON B.WARD_ID = W.WARD_ID
    WHERE B.STATUS = 'Available'
    ");
    $fallbackStmt->execute();
    $availableBeds = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ================= SELECTED PATIENT ================= */
$selected_id = null;

if($type == 'appointment' && $appointmentPatient)
{
    $selected_id = $appointmentPatient['APPOINTMENT_ID'];
}

if($type == 'walkin' && $walkinPatient)
{
    $selected_id = $walkinPatient['CONSULTATION_ID'];
}

/* ================= SAVE ================= */
if(isset($_POST['save_all'])){

    $patient_id = null;
    $appointment_id = null;
    $consultation_id = null;
    $admission_id = null;

    if($type == 'appointment')
    {
        $patient_id = $appointmentPatient['PATIENT_ID'];
        $appointment_id = $appointmentPatient['APPOINTMENT_ID'];

        if(empty($patient_id))
        {
            $stmt = $conn->prepare("
            INSERT INTO SYARMIMI.PATIENT
            (
                PATIENT_ID,
                NAME
            )
            VALUES
            (
                (SELECT NVL(MAX(PATIENT_ID),0)+1
                FROM SYARMIMI.PATIENT),
                :name
            )
            ");

            $stmt->execute([
                ':name' => $appointmentPatient['PATIENT_NAME']
            ]);

            $patient_id = $conn->query("
            SELECT MAX(PATIENT_ID)
            FROM SYARMIMI.PATIENT
            ")->fetchColumn();

            $conn->prepare("
            UPDATE SYARMIMI.APPOINTMENT
            SET PATIENT_ID = :patient
            WHERE APPOINTMENT_ID = :appointment
            ")->execute([
                ':patient' => $patient_id,
                ':appointment' => $appointment_id
            ]);

        }
    }

    if($type == 'walkin')
    {
        $patient_id = $walkinPatient['PATIENT_ID'];
        $consultation_id = $walkinPatient['CONSULTATION_ID'];
    }

    /* ================= DIAGNOSIS ================= */
    if(!empty($_POST['details']))
    {
        $checkDiagnosis = $conn->prepare("
        SELECT COUNT(*)
        FROM SYARMIMI.DIAGNOSIS
        WHERE CONSULTATION_ID = :id
        ");

        $checkDiagnosis->execute([
            ':id' => $consultation_id
        ]);

        $existsDiagnosis = $checkDiagnosis->fetchColumn();

        if($existsDiagnosis == 0)
        {
            $conn->prepare("
            INSERT INTO SYARMIMI.DIAGNOSIS
            (
                DIAGNOSIS_ID,
                PATIENT_ID,
                APPOINTMENT_ID,
                CONSULTATION_ID,
                DIAGNOSIS_DETAILS,
                ALLERGIES,
                DATE_RECORDED,
                ACCOUNT_ID
            )
            VALUES
            (
                (SELECT NVL(MAX(DIAGNOSIS_ID),0)+1
                FROM SYARMIMI.DIAGNOSIS),

                :patient,
                :appointment,
                :consultation,
                :details,
                :allergy,
                SYSDATE,
                :doctor
            )
            ")->execute([
                ':patient' => $patient_id,
                ':appointment' => $appointment_id,
                ':consultation' => $consultation_id,
                ':details' => $_POST['details'],
                ':allergy' => $_POST['allergies'],
                ':doctor' => $doctor_id
            ]);
        }
    }

    /* ================= MEDICATION ================= */
    if(!empty($_POST['medication_id']))
    {
        foreach($_POST['medication_id'] as $index => $med)
        {
            if(empty($med))
            {
                continue;
            }

            $checkMed = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.MEDICATION_ORDER
            WHERE CONSULTATION_ID = :consultation
            AND MEDICATION_ID = :med
            ");

            $checkMed->execute([
                ':consultation' => $consultation_id,
                ':med' => $med
            ]);

            $existsMed = $checkMed->fetchColumn();

            if($existsMed == 0)
            {
                $dosage = $_POST['dosage'][$index];
                $frequency = $_POST['frequency'][$index];

                $conn->prepare("
                INSERT INTO SYARMIMI.MEDICATION_ORDER
                (
                    MEDORDER_ID,
                    ADMISSION_ID,
                    PATIENT_ID,
                    APPOINTMENT_ID,
                    CONSULTATION_ID,
                    MEDICATION_ID,
                    DOSAGE,
                    FREQUENCY,
                    ACCOUNT_ID
                )
                VALUES
                (
                    (SELECT NVL(MAX(MEDORDER_ID),0)+1
                    FROM SYARMIMI.MEDICATION_ORDER),

                    :admission,
                    :patient,
                    :appointment,
                    :consultation,
                    :med,
                    :dosage,
                    :frequency,
                    :doctor
                )
                ")->execute([
                    ':admission' => $admission_id,    
                    ':patient' => $patient_id,
                    ':appointment' => $appointment_id,
                    ':consultation' => $consultation_id,
                    ':med' => $med,
                    ':dosage' => $dosage,
                    ':frequency' => $frequency,
                    ':doctor' => $doctor_id
                ]);
            }
        }
    }

    /* ================= DECISION ================= */

    if($type == 'appointment')
    {
        $decision = $_POST['decision_type'];

        /* COMPLETED */
        if($decision == 'Completed')
        {
            $conn->prepare("
            UPDATE SYARMIMI.APPOINTMENT
            SET STATUS = 'Completed'
            WHERE APPOINTMENT_ID = :id
            ")->execute([
                ':id' => $id
            ]);
        }

        /* NEXT APPOINTMENT */
        elseif($decision == 'Next Appointment')
        {
            $nextDate = $_POST['next_date'];
            $nextTime = $_POST['next_time'];

           try{

    $conn->prepare("
    INSERT INTO SYARMIMI.APPOINTMENT
    (
        APPOINTMENT_ID,
        PATIENT_ID,
        PATIENT_NAME,
        APPOINTMENT_DATE,
        APPOINTMENT_TIME,
        STATUS,
        ACCOUNT_ID,
        DOCTOR_NAME
    )
    VALUES
    (
        (SELECT NVL(MAX(APPOINTMENT_ID),0)+1
        FROM SYARMIMI.APPOINTMENT),

        :patient,
        :name,
        :date,
        :time,
        'Approved',
        :doctor,
        :doctor_name
    )
    ")->execute([
        ':patient' => $patient_id,
        ':name' => $appointmentPatient['PATIENT_NAME'],
        ':date' => $nextDate,
        ':time' => $nextTime,
        ':doctor' => $doctor_id,
        ':doctor_name' => $appointmentPatient['DOCTOR_NAME']
    ]);

}
catch(PDOException $e){

    die($e->getMessage());

}

        }

        /* ADMIT PATIENT */
        elseif($decision == 'Admit Patient')
        {
            $bed_id = $_POST['bed_id'];
            $patientId = $appointmentPatient['PATIENT_ID'];

            $conn->prepare("
            INSERT INTO SYARMIMI.ADMISSION
            (
                ADMISSION_ID,
                ADMISSION_DATE,
                PATIENT_ID,
                BED_ID,
                ACCOUNT_ID,
                IS_SEEN
            )
            VALUES
            (
                (SELECT NVL(MAX(ADMISSION_ID),0)+1
                FROM SYARMIMI.ADMISSION),

                SYSDATE,
                :patient,
                :bed,
                :doctor,
                0
            )
            ")->execute([
                ':patient' => $patientId,
                ':bed' => $bed_id,
                ':doctor' => $doctor_id
            ]);

            $conn->prepare("
            UPDATE SYARMIMI.BED
            SET STATUS='Occupied'
            WHERE BED_ID=:bed
            ")->execute([
                ':bed' => $bed_id
            ]);

            $newAdmissionId = $conn->query("
            SELECT MAX(ADMISSION_ID)
            FROM SYARMIMI.ADMISSION
            ")->fetchColumn();

            $conn->prepare("
            UPDATE SYARMIMI.MEDICATION_ORDER
            SET ADMISSION_ID = :admission
            WHERE PATIENT_ID = :patient
            AND ADMISSION_ID IS NULL
            ")->execute([
                ':admission' => $newAdmissionId,
                ':patient'   => $patientId
            ]);

            $conn->prepare("
            UPDATE SYARMIMI.APPOINTMENT
            SET STATUS='Admitted'
            WHERE APPOINTMENT_ID=:id
            ")->execute([
                ':id' => $id
            ]);
        }
    }

    /* ================= WALKIN DECISION ================= */
    if($type == 'walkin')
    {
        $decision = $_POST['decision_type'];
        $patientId = $walkinPatient['PATIENT_ID'];

        $checkAdmission = $conn->prepare("
        SELECT COUNT(*)
        FROM SYARMIMI.ADMISSION
        WHERE PATIENT_ID = :patient
        AND DISCHARGE_DATE IS NULL
        ");

        $checkAdmission->execute([
            ':patient' => $patientId
        ]);

        $existingAdmission = $checkAdmission->fetchColumn();

        /* ================= COMPLETED ================= */

if($decision == 'Completed')
{
    $conn->prepare("
    UPDATE SYARMIMI.WALKIN_CONSULTATION
    SET STATUS='Completed'
    WHERE CONSULTATION_ID=:id
    ")->execute([
        ':id' => $walkinPatient['CONSULTATION_ID']
    ]);
}

/* ================= NEXT APPOINTMENT ================= */

elseif($decision == 'Next Appointment')
{
    $nextDate = strtoupper(
        date(
            'd-M-y',
            strtotime($_POST['next_date'])
        )
    );

    $nextTime = $_POST['next_time'];

    $doctorStmt = $conn->prepare("
    SELECT USERNAME
    FROM SYARMIMI.HOSPITAL_STAFF
    WHERE ACCOUNT_ID = :doctor
    ");

    $doctorStmt->execute([
        ':doctor' => $doctor_id
    ]);

    $doctorName = $doctorStmt->fetchColumn();

    $conn->prepare("
    INSERT INTO SYARMIMI.APPOINTMENT
    (
        APPOINTMENT_ID,
        PATIENT_NAME,
        PHONE,
        DEPARTMENT,
        APPOINTMENT_DATE,
        STATUS,
        DOCTOR_NAME,
        APPOINTMENT_TIME,
        IC_NUMBER,
        GENDER,
        ACCOUNT_ID,
        PATIENT_ID
    )
    VALUES
    (
        (SELECT NVL(MAX(APPOINTMENT_ID),0)+1
        FROM SYARMIMI.APPOINTMENT),

        :name,
        :phone,
        :department,
        :date,
        'Approved',
        :doctor_name,
        :time,
        :ic,
        :gender,
        :doctor,
        :patient
    )
    ")->execute([
        ':name'        => $walkinPatient['NAME'],
        ':phone'       => $walkinPatient['PHONE'],
        ':department'  => $walkinPatient['DEPARTMENT'],
        ':date'        => $nextDate,
        ':doctor_name' => $doctorName,
        ':time'        => $nextTime,
        ':ic'          => $walkinPatient['IC_NUMBER'],
        ':gender'      => $walkinPatient['GENDER'],
        ':doctor'      => $doctor_id,
        ':patient'     => $patientId
    ]);

    $conn->prepare("
    UPDATE SYARMIMI.WALKIN_CONSULTATION
    SET STATUS='Completed'
    WHERE CONSULTATION_ID=:id
    ")->execute([
        ':id' => $walkinPatient['CONSULTATION_ID']
    ]);
}

        /* ================= NEXT APPOINTMENT ================= */

if($decision == 'Next Appointment')
{
    $nextDate = strtoupper(
        date(
            'd-M-y',
            strtotime($_POST['next_date'])
        )
    );

    $nextTime = $_POST['next_time'];

    $doctorName = $conn->prepare("
        SELECT USERNAME
        FROM SYARMIMI.HOSPITAL_STAFF
        WHERE ACCOUNT_ID = :id
    ");

    $doctorName->execute([
        ':id' => $doctor_id
    ]);

    $doctorName = $doctorName->fetchColumn();

    $conn->prepare("
    INSERT INTO SYARMIMI.APPOINTMENT
    (
        APPOINTMENT_ID,
        PATIENT_ID,
        PATIENT_NAME,
        PHONE,
        IC_NUMBER,
        GENDER,
        APPOINTMENT_DATE,
        APPOINTMENT_TIME,
        STATUS,
        ACCOUNT_ID,
        DOCTOR_NAME
    )
    VALUES
    (
        (SELECT NVL(MAX(APPOINTMENT_ID),0)+1
        FROM SYARMIMI.APPOINTMENT),

        :patient,
        :name,
        :phone,
        :ic,
        :gender,
        :date,
        :time,
        'Approved',
        :doctor,
        :doctor_name
    )
    ")->execute([
        ':patient' => $patientId,
        ':name' => $walkinPatient['NAME'],
        ':phone' => $walkinPatient['PHONE'],
        ':ic' => $walkinPatient['IC_NUMBER'],
        ':gender' => $walkinPatient['GENDER'],
        ':date' => $nextDate,
        ':time' => $nextTime,
        ':doctor' => $doctor_id,
        ':doctor_name' => $doctorName
    ]);

    $conn->prepare("
    UPDATE SYARMIMI.WALKIN_CONSULTATION
    SET STATUS='Completed'
    WHERE CONSULTATION_ID=:id
    ")->execute([
        ':id' => $walkinPatient['CONSULTATION_ID']
    ]);
}

        if($decision == 'Admit Patient' && $existingAdmission == 0)
        {
            $bed_id = $_POST['bed_id'];

            $conn->prepare("
            INSERT INTO SYARMIMI.ADMISSION
            (
                ADMISSION_ID,
                ADMISSION_DATE,
                PATIENT_ID,
                BED_ID,
                ACCOUNT_ID,
                IS_SEEN
            )
            VALUES
            (
                (SELECT NVL(MAX(ADMISSION_ID),0)+1
                FROM SYARMIMI.ADMISSION),

                SYSDATE,
                :patient,
                :bed,
                :doctor,
                1
            )
            ")->execute([
                ':patient' => $patientId,
                ':bed' => $bed_id,
                ':doctor' => $doctor_id
            ]);

            $conn->prepare("
            UPDATE SYARMIMI.BED
            SET STATUS='Occupied'
            WHERE BED_ID=:bed
            ")->execute([
                ':bed' => $bed_id
            ]);

            $newAdmissionId = $conn->query("
            SELECT MAX(ADMISSION_ID)
            FROM SYARMIMI.ADMISSION
            ")->fetchColumn();

            $conn->prepare("
            UPDATE SYARMIMI.MEDICATION_ORDER
            SET ADMISSION_ID = :admission
            WHERE PATIENT_ID = :patient
            AND ADMISSION_ID IS NULL
            ")->execute([
                ':admission' => $newAdmissionId,
                ':patient'   => $patientId
            ]);

            $conn->prepare("
            UPDATE SYARMIMI.WALKIN_CONSULTATION
            SET STATUS='Admitted'
            WHERE CONSULTATION_ID=:id
            ")->execute([
                ':id' => $walkinPatient['CONSULTATION_ID']
            ]);
        }
    }

    $_SESSION['success_message'] = "Diagnosis and treatment saved successfully.";
    header("Location: treatment.php");
    exit;

    $tomorrow = date('Y-m-d', strtotime('+1 day'));

}
?>

<!DOCTYPE html>
<html>
<head>
<title>Treatment</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

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

#nextAppointmentBox{
    background:#ffffff;
    border:1px solid #dbeafe;
    border-left:5px solid #0d6efd;
}

#nextAppointmentBox .section-title{
    color:#0d6efd;
    font-weight:700;
}
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="content">

<h4 class="mb-4">🧾 Patient Treatment</h4>

<?php if(isset($_SESSION['success_message'])): ?>

<div class="alert alert-success alert-dismissible fade show" role="alert">

<strong>✅ Success!</strong>
<?= $_SESSION['success_message']; ?>

<button
type="button"
class="btn-close"
data-bs-dismiss="alert">
</button>

</div>

<?php unset($_SESSION['success_message']); ?>

<?php endif; ?>

<?php if($appointmentPatient && $type == 'appointment'): ?>

<div class="card-box patient-info">

<h5>
📅 Appointment Patient
</h5>

<b>
<?= $appointmentPatient['PATIENT_NAME'] ?>
</b>

<br>

Doctor:
<?= $appointmentPatient['DOCTOR_NAME'] ?>

<br>

Date:
<?= $appointmentPatient['APPOINTMENT_DATE'] ?>

<br>

Time:
<?= $appointmentPatient['APPOINTMENT_TIME'] ?>

</div>

<?php endif; ?>

<?php if($walkinPatient && $type == 'walkin'): ?>

<div class="card-box patient-info">

<h5>
🚶 Walk-In Patient
</h5>

<b>
<?= $walkinPatient['NAME'] ?>
</b>

<br>

Status:
<?= $walkinPatient['STATUS'] ?>

</div>

<?php endif; ?>

<?php if(!$appointmentPatient && !$walkinPatient): ?>

<div class="card-box">

<h5>
📋 Today's Appointment Queue
</h5>

<div class="table-responsive">

<table class="table table-bordered">

<tr>
<th class="text-center">Time</th>
<th class="text-center">Patient</th>
<th class="text-center">Action</th>
</tr>

<?php foreach($todayAppointments as $row): ?>

<tr>

<td class="text-center">
<?= $row['APPOINTMENT_TIME'] ?>
</td>

<td>
<?= $row['PATIENT_NAME'] ?>
(<?= $row['STATUS'] ?? '' ?>)
</td>

<td class="text-center">

<?php if($row['TYPE']=='APPOINTMENT'): ?>

<a href="treatment.php?type=appointment&id=<?= $row['RECORD_ID'] ?>"
class="btn btn-primary btn-sm">
Diagnose
</a>

<?php else: ?>

<a href="treatment.php?type=walkin&id=<?= $row['RECORD_ID'] ?>"
class="btn btn-warning btn-sm">
Diagnose
</a>

<?php endif; ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

</div>

<?php endif; ?>

<?php if($appointmentPatient || $walkinPatient): ?>

<form method="POST">

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
<select name="admission_id" class="form-control">
<option value="">
Select Admission / Appointment
</option>

<?php
$selectedRecord = '';

if($type == 'appointment' && $appointmentPatient)
{
    $selectedRecord = $appointmentPatient['APPOINTMENT_ID'];
}

if($type == 'walkin' && $walkinPatient)
{
    $selectedRecord = $walkinPatient['CONSULTATION_ID'];
}
?>
<?php foreach($patients as $p): ?>

<option
value="<?= $p['RECORD_ID'] ?>"
<?= ($selectedRecord == $p['RECORD_ID']) ? 'selected' : '' ?>
>

<?= $p['NAME'] ?>

<?php if($p['APPOINTMENT_TIME']): ?>
(<?= $p['APPOINTMENT_TIME'] ?>)
<?php endif; ?>

</option>
<?php endforeach; ?>
</select>
</div>
</div>
</div>

<div class="card-box">

<div class="section-title">
🏥 Admission Decision
</div>

<select
name="decision_type"
id="decision_type"
class="form-control">

<option value="">
Select Decision
</option>

<option value="Completed">
Completed
</option>

<option value="Next Appointment">
Next Appointment
</option>

<option value="Admit Patient">
Admit Patient
</option>

</select>

<div id="bedBox" style="display:none; margin-top:20px;">

<label class="mb-2">
Select Bed
</label>

<select
name="bed_id"
class="form-control">

<option value="">
Select Bed
</option>

<?php foreach($availableBeds as $bed): ?>

<option value="<?= $bed['BED_ID'] ?>">
Bed <?= $bed['BED_NUMBER'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

</div>

<div
id="nextAppointmentBox"
class="card-box"
style="display:none;">

<div class="section-title">
📅 Next Appointment Details
</div>

<div class="row">

<div class="col-md-6">
<label>Next Appointment Date</label>

<input
type="date"
id="next_date"
name="next_date"
class="form-control"
min="<?= date('Y-m-d') ?>">
</div>

<div class="col-md-6">
<label>Next Appointment Time</label>

<select
name="next_time"
class="form-control">

<option value="">Select Time</option>
<option value="08:00">08:00</option>
<option value="09:00">09:00</option>
<option value="10:00">10:00</option>
<option value="11:00">11:00</option>
<option value="12:00">12:00</option>
<option value="14:00">14:00</option>
<option value="15:00">15:00</option>
<option value="16:00">16:00</option>

</select>

</div>

</div>

</div>

<!-- MEDICATION -->
<div class="card-box">

<div class="section-title">
💊 Medication Prescription
</div>

<div id="medicationContainer">

<div class="row medicationRow mb-3">

<div class="col-md-4">

<select
name="medication_id[]"
class="form-control">

<option value="">
Select Medication
</option>

<?php foreach($medications as $m): ?>

<option value="<?= $m['MEDICATION_ID'] ?>">
<?= $m['MEDICATION_NAME'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-4">

<input
type="text"
name="dosage[]"
class="form-control"
placeholder="Example: 500mg">

</div>

<div class="col-md-4">

<input
type="text"
name="frequency[]"
class="form-control"
placeholder="Example: 1 tablet TDS after meal">

</div>

</div>

</div>

<button
type="button"
id="addMedication"
class="btn btn-success btn-sm">

➕ Add Medication

</button>

</div>
<!-- BUTTON -->
<button name="save_all" class="btn btn-primary w-100">
💾 Save Treatment
</button>

</form>

<?php endif; ?>

</div>
</div>

<script>

document.addEventListener("DOMContentLoaded", function(){

    let decision =
    document.getElementById("decision_type");

    let box =
    document.getElementById("nextAppointmentBox");

    let bedBox =
    document.getElementById("bedBox");

    if(decision) {
        decision.addEventListener("change", function(){

            box.style.display = "none";
            bedBox.style.display = "none";

            if(this.value == "Next Appointment")
            {
                box.style.display = "block";
            }

            if(this.value == "Admit Patient")
            {
                bedBox.style.display = "block";
            }

        });
    }

});

</script>

<script>

document.getElementById("addMedication").addEventListener("click", function(){

let html = `
<div class="row medicationRow mb-3">

<div class="col-md-4">

<select
name="medication_id[]"
class="form-control">

<option value="">
Select Medication
</option>

<?php foreach($medications as $m): ?>

<option value="<?= $m['MEDICATION_ID'] ?>">
<?= $m['MEDICATION_NAME'] ?>
</option>

<?php endforeach; ?>

</select>

</div>

<div class="col-md-3">

<input
type="text"
name="dosage[]"
class="form-control"
placeholder="Example: 500mg">

</div>

<div class="col-md-3">

<input
type="text"
name="frequency[]"
class="form-control"
placeholder="Example: 1 tablet TDS after meal">

</div>

<div class="col-md-2">

<button
type="button"
class="btn btn-danger removeMedication">

❌

</button>

</div>

</div>
`;

document
.getElementById("medicationContainer")
.insertAdjacentHTML("beforeend", html);

});

document.addEventListener("click", function(e){

if(e.target.classList.contains("removeMedication"))
{
e.target.closest(".medicationRow").remove();
}

});

</script>

<script>

document.addEventListener("DOMContentLoaded", function(){

    let dateInput = document.getElementById("next_date");

    if(dateInput){

        let today = new Date();

        today.setDate(today.getDate() + 1);

        let yyyy = today.getFullYear();
        let mm = String(today.getMonth()+1).padStart(2,'0');
        let dd = String(today.getDate()).padStart(2,'0');

        dateInput.min = `${yyyy}-${mm}-${dd}`;
    }

});

</script>

</body>
</html>