<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include("../config/config.php");

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function digits($v){ return preg_replace('/\D+/', '', (string)$v); }

function findPatient(PDO $conn, string $ic){
    $icDigits = digits($ic);
    if ($icDigits === '') return false;

    $stmt = $conn->prepare("\n        SELECT PATIENT_ID, IC_NUMBER, NAME, AGE, GENDER, PHONE, ADDRESS\n        FROM SYARMIMI.PATIENT\n        WHERE REGEXP_REPLACE(NVL(IC_NUMBER,''), '[^0-9]', '') = ?\n        FETCH FIRST 1 ROW ONLY\n    ");
    $stmt->execute([$icDigits]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function nextQueueNumber(PDO $conn): string {
    $stmt = $conn->query("\n        SELECT NVL(MAX(TO_NUMBER(REGEXP_SUBSTR(QUEUE_NUMBER,'[0-9]+'))),0)+1\n        FROM SYARMIMI.WALKIN_CONSULTATION\n        WHERE TRUNC(CONSULTATION_DATE)=TRUNC(SYSDATE)\n          AND REGEXP_LIKE(QUEUE_NUMBER,'^W[0-9]+$')\n    ");
    $next = (int)$stmt->fetchColumn();
    return 'W' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}


function assignAvailableDoctor(PDO $conn, string $department){
    $stmt = $conn->prepare("\n        SELECT
            HS.ACCOUNT_ID,
            HS.USERNAME,
            HS.DEPARTMENT,
            (
                SELECT COUNT(*)
                FROM SYARMIMI.WALKIN_CONSULTATION WC
                WHERE WC.ACCOUNT_ID = HS.ACCOUNT_ID
                  AND TRUNC(WC.CONSULTATION_DATE) = TRUNC(SYSDATE)
                  AND UPPER(TRIM(NVL(WC.STATUS,'-'))) <> 'CANCELLED'
            ) AS TODAY_WALKIN_COUNT
        FROM SYARMIMI.HOSPITAL_STAFF HS
        WHERE UPPER(TRIM(HS.ROLE)) = 'DOCTOR'
          AND REGEXP_REPLACE(UPPER(TRIM(HS.DEPARTMENT)), 'S$', '')
              = REGEXP_REPLACE(UPPER(TRIM(?)), 'S$', '')
          AND EXISTS (
              SELECT 1
              FROM SYARMIMI.DOCTOR_AVAILABILITY DA
              WHERE DA.ACCOUNT_ID = HS.ACCOUNT_ID
                AND TRUNC(DA.AVAILABLE_DATE) = TRUNC(SYSDATE)
                AND UPPER(TRIM(DA.STATUS)) = 'AVAILABLE'
          )
        ORDER BY TODAY_WALKIN_COUNT ASC, HS.ACCOUNT_ID ASC
        FETCH FIRST 1 ROW ONLY
    ");
    $stmt->execute([$department]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$error = '';
$searchedIc = trim($_POST['ic_number'] ?? '');
$patient = null;
$showNew = false;
$success = null;
$departments = ['Orthopaedics','Paediatrics','Neurology'];

if (isset($_POST['find_patient'])) {
    if ($searchedIc === '') {
        $error = 'Please enter your IC number.';
    } else {
        $patient = findPatient($conn, $searchedIc);
        if (!$patient) $showNew = true;
    }
}

if (isset($_POST['join_queue'])) {
    $ic = trim($_POST['ic_number'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $mode = $_POST['patient_mode'] ?? '';

    try {
        if ($ic === '') throw new Exception('IC number is required.');
        if (!in_array($department, $departments, true)) throw new Exception('Please select a valid department.');

        $conn->beginTransaction();
        $patient = findPatient($conn, $ic);

        if (!$patient) {
            if ($mode !== 'new') throw new Exception('Patient record not found. Please complete the new patient form.');

            $name = strtoupper(trim($_POST['name'] ?? ''));
            $age = (int)($_POST['age'] ?? 0);
            $gender = trim($_POST['gender'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = strtoupper(trim($_POST['address'] ?? ''));

            if ($name === '') throw new Exception('Please enter the patient name.');
            if ($age <= 0 || $age > 120) throw new Exception('Please enter a valid age.');
            if (!in_array($gender, ['Male','Female'], true)) throw new Exception('Please select a valid gender.');
            if ($phone === '') throw new Exception('Please enter a phone number.');

            $newPatientId = (int)$conn->query("SELECT NVL(MAX(PATIENT_ID),0)+1 FROM SYARMIMI.PATIENT")->fetchColumn();

            $stmt = $conn->prepare("\n                INSERT INTO SYARMIMI.PATIENT\n                (PATIENT_ID, IC_NUMBER, NAME, AGE, GENDER, PHONE, ADDRESS)\n                VALUES (?, ?, ?, ?, ?, ?, ?)\n            ");
            $stmt->execute([$newPatientId,$ic,$name,$age,$gender,$phone,$address !== '' ? $address : null]);

            $patient = [
                'PATIENT_ID'=>$newPatientId,
                'IC_NUMBER'=>$ic,
                'NAME'=>$name,
                'AGE'=>$age,
                'GENDER'=>$gender,
                'PHONE'=>$phone,
                'ADDRESS'=>$address
            ];
        }

        /* Find and automatically assign an available doctor. */
        $assignedDoctor = assignAvailableDoctor($conn, $department);

        if (!$assignedDoctor) {
            throw new Exception(
                'No doctor is currently available for ' . $department .
                ' today. Please contact the registration counter for assistance.'
            );
        }

        /* Lock before final workload check and queue-number generation. */
        $conn->exec("LOCK TABLE SYARMIMI.WALKIN_CONSULTATION IN EXCLUSIVE MODE");

        $assignedDoctor = assignAvailableDoctor($conn, $department);

        if (!$assignedDoctor) {
            throw new Exception(
                'No doctor is currently available for ' . $department .
                ' today. Please contact the registration counter for assistance.'
            );
        }

        $doctorId = (int)$assignedDoctor['ACCOUNT_ID'];
        $doctorUsername = trim((string)($assignedDoctor['USERNAME'] ?? 'Doctor'));
        $doctorName = stripos($doctorUsername, 'Dr.') === 0
            ? $doctorUsername
            : 'Dr. ' . $doctorUsername;

        $consultationId = (int)$conn->query(
            "SELECT NVL(MAX(CONSULTATION_ID),0)+1 FROM SYARMIMI.WALKIN_CONSULTATION"
        )->fetchColumn();

        $queueNumber = nextQueueNumber($conn);

        $stmt = $conn->prepare("\n            INSERT INTO SYARMIMI.WALKIN_CONSULTATION
            (CONSULTATION_ID, PATIENT_ID, ACCOUNT_ID, CONSULTATION_DATE, STATUS, NOTES, DEPARTMENT, DIAGNOSIS, CONSULTATION_TIME, DECISION, QUEUE_NUMBER)
            VALUES (?, ?, ?, SYSDATE, 'Assigned', ?, ?, '-', TO_CHAR(SYSDATE,'HH24:MI'), NULL, ?)
        ");

        $stmt->execute([
            $consultationId,
            (int)$patient['PATIENT_ID'],
            $doctorId,
            $notes !== '' ? $notes : null,
            $department,
            $queueNumber
        ]);

        $conn->commit();

        $success = [
            'QUEUE_NUMBER'=>$queueNumber,
            'NAME'=>$patient['NAME'],
            'DEPARTMENT'=>$department,
            'DOCTOR_NAME'=>$doctorName,
            'STATUS'=>'Assigned',
            'DATE'=>strtoupper(date('d-M-y')),
            'TIME'=>date('h:i A')
        ];

    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = $e->getMessage();
        $searchedIc = $ic;
        $patient = findPatient($conn, $ic);
        if (!$patient) $showNew = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Walk-In Registration | ZB-CARE</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
*{box-sizing:border-box} body{margin:0;background:#f5f7fb;color:#0f172a;font-family:'Segoe UI',Arial,sans-serif;min-height:100vh}
.topbar{background:#fff;border-bottom:1px solid #e5e7eb}.topbar-inner{width:min(1180px,calc(100% - 32px));min-height:68px;margin:auto;display:flex;align-items:center;justify-content:space-between}
.brand{display:flex;align-items:center;gap:9px;color:#0f172a;text-decoration:none;font-size:18px;font-weight:800}.brand-icon{width:34px;height:34px;display:grid;place-items:center;border-radius:9px;background:#eff6ff;color:#2563eb}
.home-link{color:#475569;text-decoration:none;font-size:12px;font-weight:650}.page-shell{width:min(760px,calc(100% - 28px));margin:42px auto 70px}.hero{text-align:center;margin-bottom:22px}
.hero-badge{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;background:#eff6ff;color:#2563eb;font-size:10px;font-weight:800}.hero h1{margin:12px 0 7px;font-size:34px;font-weight:850}.hero p{max-width:590px;margin:auto;color:#64748b;font-size:13px;line-height:1.65}
.card-box{padding:24px;background:#fff;border:1px solid #e5e7eb;border-radius:15px;box-shadow:0 6px 20px rgba(15,23,42,.035)}.section-title{display:flex;align-items:center;gap:8px;margin-bottom:18px;font-size:16px;font-weight:800}.section-icon{width:32px;height:32px;display:grid;place-items:center;border-radius:9px;background:#eff6ff;color:#2563eb}
.form-label{margin-bottom:6px;color:#475569;font-size:11px;font-weight:700}.form-control,.form-select{min-height:44px;border:1px solid #dbe1e8;border-radius:9px;font-size:12px}.btn-main{min-height:44px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-size:12px;font-weight:750}.btn-main:hover{background:#1d4ed8;color:#fff}
.patient-banner{margin-bottom:18px;padding:16px;border:1px solid #bbf7d0;border-radius:11px;background:#f0fdf4}.patient-name{color:#166534;font-size:15px;font-weight:800}.patient-meta{margin-top:4px;color:#64748b;font-size:10px}.new-note{margin-bottom:17px;padding:12px 14px;border:1px solid #fde68a;border-radius:10px;background:#fffbeb;color:#92400e;font-size:11px}
.queue-card{padding:30px 24px;border:1px solid #bfdbfe;border-radius:16px;background:linear-gradient(180deg,#eff6ff,#fff);text-align:center;box-shadow:0 8px 28px rgba(37,99,235,.07)}.success-icon{width:52px;height:52px;margin:0 auto 13px;display:grid;place-items:center;border-radius:50%;background:#dcfce7;color:#16a34a;font-size:23px}.queue-label{margin-top:20px;color:#64748b;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.08em}.queue-number{margin-top:4px;color:#2563eb;font-size:54px;line-height:1;font-weight:900;letter-spacing:2px}.queue-details{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px;margin-top:24px;text-align:left}.detail-box{padding:12px;border:1px solid #e5e7eb;border-radius:9px;background:#fff}.detail-label{color:#94a3b8;font-size:9px;text-transform:uppercase;font-weight:750}.detail-value{margin-top:3px;color:#334155;font-size:11px;font-weight:700}.queue-note{margin-top:18px;color:#64748b;font-size:11px;line-height:1.6}.alert{border-radius:10px;font-size:11px}
@media(max-width:600px){.page-shell{margin-top:28px}.hero h1{font-size:28px}.card-box{padding:18px}.queue-details{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="topbar"><div class="topbar-inner"><a href="../index.php" class="brand"><span class="brand-icon"><i class="bi bi-heart-pulse-fill"></i></span>ZB-CARE</a><a href="../index.php" class="home-link"><i class="bi bi-arrow-left me-1"></i>Back to Home</a></div></header>
<main class="page-shell">
<div class="hero"><div class="hero-badge"><i class="bi bi-qr-code-scan"></i>Walk-In Queue</div><h1>Walk-In Registration</h1><p>Enter your IC number to join today's specialist walk-in queue. Existing patients reuse their saved patient record, while first-time patients can register before receiving a queue number and automatic doctor assignment.</p></div>
<?php if($error !== ''): ?><div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div><?php endif; ?>

<?php if($success): ?>
<div class="queue-card">
<div class="success-icon"><i class="bi bi-check-lg"></i></div><h4 class="mb-1">Registration Successful</h4><div class="text-muted" style="font-size:11px">Please keep this queue number for reference.</div>
<div class="queue-label">Your Queue Number</div><div class="queue-number"><?= h($success['QUEUE_NUMBER']) ?></div>
<div class="queue-details">
<div class="detail-box"><div class="detail-label">Patient</div><div class="detail-value"><?= h($success['NAME']) ?></div></div>
<div class="detail-box"><div class="detail-label">Department</div><div class="detail-value"><?= h($success['DEPARTMENT']) ?></div></div>
<div class="detail-box"><div class="detail-label">Assigned Doctor</div><div class="detail-value"><?= h($success['DOCTOR_NAME']) ?></div></div>
<div class="detail-box"><div class="detail-label">Status</div><div class="detail-value"><?= h($success['STATUS']) ?></div></div>
<div class="detail-box"><div class="detail-label">Date</div><div class="detail-value"><?= h($success['DATE']) ?></div></div>
<div class="detail-box"><div class="detail-label">Registration Time</div><div class="detail-value"><?= h($success['TIME']) ?></div></div>
</div>
<div class="queue-note"><strong><?= h($success['DOCTOR_NAME']) ?></strong> has been assigned to your walk-in consultation. Please wait until your queue number is called.</div><a href="walkin_register.php" class="btn btn-outline-primary btn-sm mt-3">Register Another Patient</a>
</div>

<?php elseif(!$patient && !$showNew): ?>
<div class="card-box"><div class="section-title"><span class="section-icon"><i class="bi bi-person-vcard"></i></span>Find Patient Record</div><form method="POST"><label class="form-label">IC Number</label><input type="text" name="ic_number" class="form-control" placeholder="e.g. 900101-01-1234" value="<?= h($searchedIc) ?>" required><button type="submit" name="find_patient" value="1" class="btn btn-main w-100 mt-3"><i class="bi bi-search me-1"></i>Continue</button><div class="text-muted mt-2" style="font-size:10px">Hyphens are optional.</div></form></div>

<?php elseif($patient): ?>
<div class="patient-banner"><div class="patient-name"><i class="bi bi-check-circle me-1"></i>Existing Patient Found</div><div class="patient-meta"><strong><?= h($patient['NAME']) ?></strong> &nbsp;•&nbsp; <?= h($patient['IC_NUMBER']) ?> &nbsp;•&nbsp; <?= h($patient['PHONE'] ?: '-') ?></div></div>
<div class="card-box"><div class="section-title"><span class="section-icon"><i class="bi bi-person-walking"></i></span>Join Walk-In Queue</div><form method="POST"><input type="hidden" name="patient_mode" value="existing"><input type="hidden" name="ic_number" value="<?= h($patient['IC_NUMBER']) ?>"><div class="mb-3"><label class="form-label">Department</label><select name="department" class="form-select" required><option value="">Select Department</option><?php foreach($departments as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Symptoms / Notes</label><textarea name="notes" class="form-control" rows="4" placeholder="Briefly describe your symptoms, if applicable."></textarea></div><button type="submit" name="join_queue" value="1" class="btn btn-main w-100"><i class="bi bi-ticket-perforated me-1"></i>Get Queue Number</button></form></div>

<?php else: ?>
<div class="new-note"><i class="bi bi-info-circle me-1"></i>No patient record was found for this IC number. Complete the registration below. The patient will be saved first, then added to the walk-in queue.</div>
<div class="card-box"><div class="section-title"><span class="section-icon"><i class="bi bi-person-plus"></i></span>First-Time Patient Registration</div><form method="POST"><input type="hidden" name="patient_mode" value="new"><div class="row g-3"><div class="col-md-6"><label class="form-label">IC Number</label><input type="text" name="ic_number" class="form-control" value="<?= h($searchedIc) ?>" required></div><div class="col-md-6"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div><div class="col-md-6"><label class="form-label">Age</label><input type="number" name="age" class="form-control" min="1" max="120" required></div><div class="col-md-6"><label class="form-label">Gender</label><select name="gender" class="form-select" required><option value="">Select Gender</option><option value="Male">Male</option><option value="Female">Female</option></select></div><div class="col-md-6"><label class="form-label">Phone Number</label><input type="text" name="phone" class="form-control" required></div><div class="col-md-6"><label class="form-label">Department</label><select name="department" class="form-select" required><option value="">Select Department</option><?php foreach($departments as $d): ?><option value="<?= h($d) ?>"><?= h($d) ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="3"></textarea></div><div class="col-12"><label class="form-label">Symptoms / Notes</label><textarea name="notes" class="form-control" rows="3"></textarea></div></div><button type="submit" name="join_queue" value="1" class="btn btn-main w-100 mt-3"><i class="bi bi-ticket-perforated me-1"></i>Register & Get Queue Number</button></form></div>
<?php endif; ?>
</main>
</body>
</html>
