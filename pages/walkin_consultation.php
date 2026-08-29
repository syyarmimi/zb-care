<?php
session_start();

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] != 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");


/* =========================================================
   SEARCH PATIENT
========================================================= */

$patientData = null;

if (isset($_POST['search_patient'])) {

    $ic = trim(
        $_POST['ic_search']
    );

    $stmt = $conn->prepare("
        SELECT *
        FROM SYARMIMI.PATIENT
        WHERE IC_NUMBER = ?
    ");

    $stmt->execute([
        $ic
    ]);

    $patientData = $stmt->fetch(
        PDO::FETCH_ASSOC
    );
}


/* =========================================================
   QUICK REGISTER
========================================================= */

if (isset($_POST['register_patient'])) {

    $ic =
        trim($_POST['ic'] ?? '');

    $name =
        trim($_POST['name'] ?? '');

    $age =
        trim($_POST['age'] ?? '');

    $gender =
        trim($_POST['gender'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $address =
        trim($_POST['address'] ?? '');


    try {

        $check = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.PATIENT
            WHERE IC_NUMBER = ?
        ");

        $check->execute([
            $ic
        ]);


        if (
            (int)$check->fetchColumn()
            > 0
        ) {

            echo "
            <script>
                window.addEventListener(
                    'DOMContentLoaded',
                    function()
                    {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Patient Already Exists',
                            text: 'A patient with this IC number is already registered.',
                            confirmButtonColor: '#2563eb'
                        });
                    }
                );
            </script>
            ";

        } else {

            $stmt = $conn->query("
                SELECT
                    NVL(
                        MAX(PATIENT_ID),
                        0
                    ) + 1 AS NEW_ID
                FROM SYARMIMI.PATIENT
            ");


            $newId =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                )['NEW_ID'];


            $sql = "
                INSERT INTO SYARMIMI.PATIENT
                (
                    PATIENT_ID,
                    IC_NUMBER,
                    NAME,
                    AGE,
                    GENDER,
                    PHONE,
                    ADDRESS
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )
            ";


            $stmt = $conn->prepare(
                $sql
            );


            $stmt->execute([
                $newId,
                $ic,
                strtoupper($name),
                $age,
                $gender,
                $phone,
                strtoupper($address)
            ]);


            echo "
            <script>
                window.addEventListener(
                    'DOMContentLoaded',
                    function()
                    {
                        Swal.fire({
                            icon: 'success',
                            title: 'Patient Registered',
                            text: 'The patient has been registered successfully.',
                            confirmButtonColor: '#16a34a'
                        }).then(
                            function()
                            {
                                window.location =
                                    'walkin_consultation.php';
                            }
                        );
                    }
                );
            </script>
            ";
        }

    } catch (PDOException $e) {

        die(
            $e->getMessage()
        );
    }
}


/* =========================================================
   CREATE WALK-IN CONSULTATION
========================================================= */

if (isset($_POST['create_consultation'])) {

    $patient_id =
        (int)($_POST['patient_id'] ?? 0);

    $account_id =
        (int)($_POST['doctor'] ?? 0);

    $department =
        trim($_POST['department'] ?? '');

    $notes =
        trim($_POST['notes'] ?? '');


    try {

        $stmt = $conn->query("
            SELECT
                NVL(
                    MAX(CONSULTATION_ID),
                    0
                ) + 1 AS NEW_ID
            FROM SYARMIMI.WALKIN_CONSULTATION
        ");


        $newId =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            )['NEW_ID'];


        $sql = "
            INSERT INTO
                SYARMIMI.WALKIN_CONSULTATION
            (
                CONSULTATION_ID,
                PATIENT_ID,
                ACCOUNT_ID,
                CONSULTATION_DATE,
                STATUS,
                NOTES,
                DEPARTMENT,
                DIAGNOSIS
            )
            VALUES
            (
                :id,
                :patient,
                :doctor,
                SYSDATE,
                'Assigned',
                :notes,
                :department,
                '-'
            )
        ";


        $stmt = $conn->prepare(
            $sql
        );


        $stmt->execute([
            ':id' =>
                $newId,

            ':patient' =>
                $patient_id,

            ':doctor' =>
                $account_id,

            ':notes' =>
                $notes,

            ':department' =>
                $department
        ]);


        echo "
        <script>
            window.addEventListener(
                'DOMContentLoaded',
                function()
                {
                    Swal.fire({
                        icon: 'success',
                        title: 'Consultation Assigned',
                        text: 'Patient has been assigned to the selected doctor.',
                        confirmButtonColor: '#2563eb'
                    }).then(
                        function()
                        {
                            window.location =
                                'walkin_consultation.php';
                        }
                    );
                }
            );
        </script>
        ";

    } catch (PDOException $e) {

        die(
            $e->getMessage()
        );
    }
}


/* =========================================================
   AVAILABLE DOCTORS TODAY
========================================================= */

$countDoctor = (int)$conn->query("
    SELECT COUNT(*)

    FROM SYARMIMI.DOCTOR_AVAILABILITY

    WHERE UPPER(TRIM(STATUS)) = 'AVAILABLE'

    AND TRUNC(AVAILABLE_DATE)
        =
        TRUNC(SYSDATE)
")->fetchColumn();


$doctorStmt = $conn->query("
    SELECT
        H.ACCOUNT_ID,
        H.USERNAME,
        H.DEPARTMENT,
        A.START_TIME,
        A.END_TIME

    FROM SYARMIMI.HOSPITAL_STAFF H

    JOIN SYARMIMI.DOCTOR_AVAILABILITY A
        ON H.ACCOUNT_ID =
           A.ACCOUNT_ID

    WHERE LOWER(H.ROLE) = 'doctor'

    AND UPPER(TRIM(A.STATUS))
        =
        'AVAILABLE'

    AND TRUNC(A.AVAILABLE_DATE)
        =
        TRUNC(SYSDATE)

    ORDER BY
        H.DEPARTMENT,
        H.USERNAME
");


$availableDoctors =
    $doctorStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Walk-In Consultation
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:#f5f7fa;

    color:#1f2937;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;
}


.main-content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:30px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    margin-bottom:25px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:30px;

    font-weight:750;
}


.page-subtitle{

    margin-top:6px;

    color:#64748b;

    font-size:14px;
}


/* =========================================================
   CARD
========================================================= */

.content-card{

    margin-bottom:20px;

    padding:22px;

    background:#ffffff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.card-header-clean{

    display:flex;

    align-items:center;

    gap:11px;

    margin-bottom:18px;
}


.card-icon{

    width:38px;

    height:38px;

    min-width:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:16px;
}


.icon-search{

    background:#eff6ff;

    color:#2563eb;
}


.icon-register{

    background:#fff1f2;

    color:#e11d48;
}


.icon-consult{

    background:#ecfdf5;

    color:#15803d;
}


.icon-schedule{

    background:#f5f3ff;

    color:#7c3aed;
}


.card-title{

    margin:0;

    color:#1f2937;

    font-size:17px;

    font-weight:700;
}


.card-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:12px;
}


/* =========================================================
   FORMS
========================================================= */

.form-label{

    margin-bottom:7px;

    color:#475569;

    font-size:12px;

    font-weight:650;
}


.form-control,
.form-select{

    min-height:45px;

    border:1px solid #dfe3e8;

    border-radius:9px;

    color:#374151;

    font-size:13px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


textarea.form-control{

    min-height:110px;

    resize:vertical;
}


/* =========================================================
   BUTTONS
========================================================= */

.btn-main{

    min-height:45px;

    border-radius:9px;

    font-size:13px;

    font-weight:650;
}


.btn-search{

    background:#2563eb;

    border-color:#2563eb;

    color:white;
}


.btn-search:hover{

    background:#1d4ed8;

    border-color:#1d4ed8;

    color:white;
}


.btn-register{

    background:#16a34a;

    border-color:#16a34a;

    color:#fff;
}


.btn-register:hover{

    background:#15803d;

    border-color:#15803d;

    color:#fff;
}


.btn-create{

    min-width:180px;

    background:#2563eb;

    border-color:#2563eb;

    color:#fff;
}


.btn-create:hover{

    background:#1d4ed8;

    border-color:#1d4ed8;

    color:#fff;
}


/* =========================================================
   SEARCH PATIENT
========================================================= */

.search-box{

    position:relative;
}


.search-box i{

    position:absolute;

    top:50%;

    left:15px;

    z-index:2;

    color:#94a3b8;

    font-size:14px;

    transform:translateY(-50%);
}


.search-box input{

    padding-left:42px;
}


/* =========================================================
   PATIENT RESULT
========================================================= */

.patient-found{

    margin-top:18px;

    padding:16px;

    background:#f0fdf4;

    border:1px solid #bbf7d0;

    border-radius:10px;
}


.patient-found-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:14px;
}


.patient-found-title{

    display:flex;

    align-items:center;

    gap:7px;

    color:#15803d;

    font-size:13px;

    font-weight:700;
}


.patient-info-grid{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:12px;
}


.info-item{

    padding:13px 14px;

    background:#fff;

    border:1px solid #dcfce7;

    border-radius:9px;
}


.info-label{

    color:#94a3b8;

    font-size:10px;

    font-weight:650;

    text-transform:uppercase;
}


.info-value{

    margin-top:4px;

    color:#1f2937;

    font-size:13px;

    font-weight:650;
}


/* =========================================================
   NOT FOUND
========================================================= */

.not-found-box{

    margin-top:18px;

    padding:13px 15px;

    background:#fff7ed;

    border:1px solid #fed7aa;

    border-radius:9px;

    color:#c2410c;

    font-size:12px;
}


/* =========================================================
   DOCTOR AVAILABILITY SUMMARY
========================================================= */

.availability-summary{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:20px;

    padding:20px 22px;

    background:linear-gradient(
        135deg,
        #eff6ff,
        #f8fbff
    );

    border:1px solid #dbeafe;

    border-radius:14px;
}


.availability-left{

    display:flex;

    align-items:center;

    gap:14px;
}


.availability-icon{

    width:48px;

    height:48px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#2563eb;

    border-radius:12px;

    color:white;

    font-size:20px;
}


.availability-label{

    color:#64748b;

    font-size:12px;
}


.availability-count{

    margin-top:2px;

    color:#111827;

    font-size:26px;

    font-weight:750;
}


.availability-badge{

    display:inline-flex;

    align-items:center;

    gap:6px;

    padding:7px 10px;

    background:#ecfdf5;

    border-radius:7px;

    color:#15803d;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   CONSULTATION
========================================================= */

.consultation-grid{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:16px;
}


/* =========================================================
   DOCTOR SCHEDULE TABLE
========================================================= */

.table-responsive{

    overflow-x:auto;

    border:1px solid #edf0f3;

    border-radius:10px;
}


.table{

    margin-bottom:0;

    vertical-align:middle;
}


.table thead th{

    padding:12px 13px;

    background:#f8fafc;

    border-bottom:1px solid #e5e7eb;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.3px;
}


.table tbody td{

    padding:13px;

    border-color:#eef1f4;

    color:#475569;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.doctor-name{

    color:#111827;

    font-weight:650;
}


.department-badge{

    display:inline-flex;

    padding:5px 8px;

    background:#eff6ff;

    border-radius:6px;

    color:#2563eb;

    font-size:10px;

    font-weight:650;
}


.time-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:6px;

    color:#475569;

    font-size:10px;

    font-weight:600;
}


/* =========================================================
   EMPTY
========================================================= */

.empty-doctor{

    padding:30px 15px;

    text-align:center;

    color:#94a3b8;

    font-size:12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .main-content{

        padding:18px;
    }


    .patient-info-grid,
    .consultation-grid{

        grid-template-columns:1fr;
    }


    .availability-summary{

        align-items:flex-start;

        flex-direction:column;
    }

}


@media(max-width:576px){

    .page-title{

        font-size:24px;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include("../includes/sidebar_admin.php");
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<h1 class="page-title">

Walk-In Consultation

</h1>


<div class="page-subtitle">

Search or register a patient, then assign the walk-in consultation to an available doctor.

</div>


</div>



<!-- =====================================================
     SEARCH PATIENT
===================================================== -->

<div class="content-card">


<div class="card-header-clean">


<div class="card-icon icon-search">

<i class="bi bi-search"></i>

</div>


<div>

<h5 class="card-title">

Search Patient

</h5>


<div class="card-subtitle">

Search using the patient's IC number.

</div>

</div>


</div>


<form method="POST">


<div class="row g-2">


<div class="col-lg-10">


<div class="search-box">


<i class="bi bi-person-vcard"></i>


<input
    type="text"
    name="ic_search"
    class="form-control ic-format"
    placeholder="Enter IC number • xxxxxx-xx-xxxx"
    maxlength="14"
    required
    value="<?= h(
        $_POST['ic_search']
        ?? ''
    ) ?>"
>


</div>


</div>


<div class="col-lg-2">


<button
    type="submit"
    name="search_patient"
    class="btn btn-search btn-main w-100"
>

<i class="bi bi-search me-1"></i>

Search

</button>


</div>


</div>


</form>



<!-- =================================================
     PATIENT FOUND
================================================= -->

<?php if ($patientData): ?>


<div class="patient-found">


<div class="patient-found-header">


<div class="patient-found-title">

<i class="bi bi-check-circle-fill"></i>

Patient found in system

</div>


<a
    href="walkin_consultation.php"
    class="btn btn-sm btn-outline-secondary"
>

<i class="bi bi-arrow-counterclockwise me-1"></i>

Reset

</a>


</div>


<div class="patient-info-grid">


<div class="info-item">

<div class="info-label">
Patient Name
</div>

<div class="info-value">
<?= h($patientData['NAME']) ?>
</div>

</div>


<div class="info-item">

<div class="info-label">
IC Number
</div>

<div class="info-value">
<?= h($patientData['IC_NUMBER']) ?>
</div>

</div>


<div class="info-item">

<div class="info-label">
Phone Number
</div>

<div class="info-value">
<?= h(
    $patientData['PHONE']
    ?? '-'
) ?>
</div>

</div>


</div>


</div>


<!-- =================================================
     NOT FOUND
================================================= -->

<?php elseif (
    isset(
        $_POST[
            'search_patient'
        ]
    )
): ?>


<div class="not-found-box">

<i class="bi bi-exclamation-circle me-1"></i>

Patient not found. Complete the form below to register a new patient.

</div>


<?php endif; ?>


</div>



<!-- =====================================================
     REGISTER NEW PATIENT
===================================================== -->

<?php if (
    !$patientData
    &&
    isset(
        $_POST[
            'search_patient'
        ]
    )
): ?>


<div class="content-card">


<div class="card-header-clean">


<div class="card-icon icon-register">

<i class="bi bi-person-plus"></i>

</div>


<div>

<h5 class="card-title">

Register New Patient

</h5>


<div class="card-subtitle">

Create a new patient record before proceeding with the consultation.

</div>

</div>


</div>


<form method="POST">


<div class="row g-3">


<div class="col-md-4">


<label class="form-label">

IC Number

</label>


<input
    type="text"
    id="ic"
    name="ic"
    class="form-control ic-format"
    value="<?= h(
        $_POST[
            'ic_search'
        ]
        ?? ''
    ) ?>"
    maxlength="14"
    required
>


</div>



<div class="col-md-8">


<label class="form-label">

Full Name

</label>


<input
    type="text"
    id="name"
    name="name"
    class="form-control"
    placeholder="PATIENT FULL NAME"
    required
>


</div>



<div class="col-md-4">


<label class="form-label">

Age

</label>


<input
    type="number"
    name="age"
    class="form-control"
    min="0"
    max="120"
    placeholder="Age"
    required
>


</div>



<div class="col-md-4">


<label class="form-label">

Gender

</label>


<select
    name="gender"
    class="form-select"
    required
>


<option value="">

Select Gender

</option>


<option value="Male">

Male

</option>


<option value="Female">

Female

</option>


</select>


</div>



<div class="col-md-4">


<label class="form-label">

Phone Number

</label>


<input
    type="text"
    id="phone"
    name="phone"
    class="form-control"
    placeholder="01x-xxx-xxxx"
    maxlength="13"
    required
>


</div>



<div class="col-12">


<label class="form-label">

Address

</label>


<textarea
    id="address"
    name="address"
    class="form-control"
    rows="3"
    placeholder="PATIENT ADDRESS"
    required
></textarea>


</div>



<div class="col-12">


<button
    type="submit"
    name="register_patient"
    class="btn btn-register btn-main px-4"
>

<i class="bi bi-person-plus me-1"></i>

Register Patient

</button>


</div>


</div>


</form>


</div>


<?php endif; ?>



<!-- =====================================================
     CONSULTATION
===================================================== -->

<?php if ($patientData): ?>


<!-- DOCTOR SUMMARY -->

<div class="availability-summary">


<div class="availability-left">


<div class="availability-icon">

<i class="bi bi-person-check"></i>

</div>


<div>


<div class="availability-label">

Doctors Available Today

</div>


<div class="availability-count">

<?= $countDoctor ?>

</div>


</div>


</div>


<div class="availability-badge">

<i class="bi bi-circle-fill" style="font-size:7px;"></i>

Live Availability

</div>


</div>



<!-- CREATE CONSULTATION -->

<div class="content-card">


<div class="card-header-clean">


<div class="card-icon icon-consult">

<i class="bi bi-clipboard2-pulse"></i>

</div>


<div>

<h5 class="card-title">

Create Consultation

</h5>


<div class="card-subtitle">

Select the department and assign an available doctor.

</div>

</div>


</div>


<form method="POST">


<input
    type="hidden"
    name="patient_id"
    value="<?= h(
        $patientData[
            'PATIENT_ID'
        ]
    ) ?>"
>


<div class="consultation-grid">


<div>


<label class="form-label">

Department

</label>


<select
    name="department"
    id="deptSelect"
    class="form-select"
    required
    onchange="filterDoctors()"
>


<option value="">

Select Department

</option>


<option value="Orthopaedics">

Orthopaedics

</option>


<option value="Paediatrics">

Paediatrics

</option>


<option value="Dietitian & Nutrition">

Dietitian & Nutrition

</option>


</select>


</div>



<div>


<label class="form-label">

Assign Doctor

</label>


<select
    name="doctor"
    id="doctorSelect"
    class="form-select"
    required
    disabled
>


<option value="">

Select Doctor

</option>


<?php foreach (
    $availableDoctors
    as
    $doctor
): ?>


<option
    value="<?= h(
        $doctor[
            'ACCOUNT_ID'
        ]
    ) ?>"

    data-dept="<?= h(
        $doctor[
            'DEPARTMENT'
        ]
    ) ?>"
>

Dr.
<?= h(
    $doctor[
        'USERNAME'
    ]
) ?>

(
<?= h(
    $doctor[
        'START_TIME'
    ]
) ?>

-

<?= h(
    $doctor[
        'END_TIME'
    ]
) ?>
)

</option>


<?php endforeach; ?>


</select>


</div>


</div>



<div class="mt-3">


<label class="form-label">

Symptoms / Notes

</label>


<textarea
    name="notes"
    class="form-control"
    rows="4"
    placeholder="Enter patient complaints, symptoms or relevant notes..."
></textarea>


</div>



<div class="mt-3">


<button
    type="submit"
    name="create_consultation"
    class="btn btn-create btn-main px-4"
>

<i class="bi bi-check-circle me-1"></i>

Create Consultation

</button>


</div>


</form>


</div>



<!-- =====================================================
     DOCTOR SCHEDULE
===================================================== -->

<div class="content-card">


<div class="card-header-clean">


<div class="card-icon icon-schedule">

<i class="bi bi-calendar-check"></i>

</div>


<div>

<h5 class="card-title">

Today's Doctor Schedule

</h5>


<div class="card-subtitle">

Doctors currently marked as available for walk-in consultations.

</div>

</div>


</div>


<?php if (
    !empty(
        $availableDoctors
    )
): ?>


<div class="table-responsive">


<table class="table table-hover">


<thead>


<tr>

<th>Doctor</th>

<th>Department</th>

<th>Duty Hours</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $availableDoctors
    as
    $doc
): ?>


<tr>


<td>

<span class="doctor-name">

Dr.
<?= h(
    $doc[
        'USERNAME'
    ]
) ?>

</span>

</td>


<td>

<span class="department-badge">

<?= h(
    $doc[
        'DEPARTMENT'
    ]
) ?>

</span>

</td>


<td>

<span class="time-badge">

<i class="bi bi-clock"></i>

<?= h(
    $doc[
        'START_TIME'
    ]
) ?>

-

<?= h(
    $doc[
        'END_TIME'
    ]
) ?>

</span>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-doctor">

<i class="bi bi-calendar-x fs-3 d-block mb-2"></i>

No doctors are marked as available today.

</div>


<?php endif; ?>


</div>


<?php endif; ?>


</div>


</div>



<script>

/* =========================================================
   FILTER DOCTORS
========================================================= */

function filterDoctors()
{

    const dept =
        document.getElementById(
            'deptSelect'
        ).value;


    const doctorSelect =
        document.getElementById(
            'doctorSelect'
        );


    const options =
        doctorSelect.options;


    doctorSelect.disabled =
        dept === '';


    doctorSelect.value =
        '';


    for (
        let i = 1;
        i < options.length;
        i++
    ) {

        const doctorDept =
            options[i]
            .getAttribute(
                'data-dept'
            );


        /*
         <option> display:none is inconsistent
         in some browsers.

         Disable mismatched doctors instead.
        */

        if (
            doctorDept === dept
        ) {

            options[i].disabled =
                false;

            options[i].hidden =
                false;

        } else {

            options[i].disabled =
                true;

            options[i].hidden =
                true;

        }

    }

}


/* =========================================================
   IC FORMAT
========================================================= */

document
.querySelectorAll(
    '.ic-format'
)
.forEach(
function(element)
{

    element.addEventListener(
        'input',
        function()
        {

            let value =
                this.value
                .replace(
                    /\D/g,
                    ''
                )
                .substring(
                    0,
                    12
                );


            let formatted =
                '';


            if (
                value.length > 0
            ) {

                formatted +=
                    value.substring(
                        0,
                        6
                    );
            }


            if (
                value.length > 6
            ) {

                formatted +=
                    '-'
                    +
                    value.substring(
                        6,
                        8
                    );
            }


            if (
                value.length > 8
            ) {

                formatted +=
                    '-'
                    +
                    value.substring(
                        8,
                        12
                    );
            }


            this.value =
                formatted;

        }
    );

}
);


/* =========================================================
   PHONE FORMAT
========================================================= */

document
.getElementById(
    'phone'
)
?.addEventListener(
'input',
function()
{

    let value =
        this.value
        .replace(
            /\D/g,
            ''
        )
        .substring(
            0,
            10
        );


    let formatted =
        '';


    if (
        value.length > 0
    ) {

        formatted +=
            value.substring(
                0,
                3
            );
    }


    if (
        value.length > 3
    ) {

        formatted +=
            '-'
            +
            value.substring(
                3,
                6
            );
    }


    if (
        value.length > 6
    ) {

        formatted +=
            '-'
            +
            value.substring(
                6,
                10
            );
    }


    this.value =
        formatted;

}
);


/* =========================================================
   UPPERCASE
========================================================= */

document
.getElementById(
    'name'
)
?.addEventListener(
'input',
function()
{

    this.value =
        this.value.toUpperCase();

}
);


document
.getElementById(
    'address'
)
?.addEventListener(
'input',
function()
{

    this.value =
        this.value.toUpperCase();

}
);

</script>


</body>

</html>