<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("../config/config.php");

$current = basename($_SERVER['PHP_SELF']);

/* =========================
   APPOINTMENT COUNT
========================= */

$doctorName = "Dr. " . ($_SESSION['user'] ?? '');

$sqlCount = "
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE DOCTOR_NAME = :doctor
AND STATUS = 'Approved'
";

$stmtCount = $conn->prepare($sqlCount);

$stmtCount->execute([
    ':doctor' => $doctorName
]);

$appointmentCount = $stmtCount->fetchColumn();

/* =========================
   DOCTOR INFO
========================= */

$sqlDoctor = "
SELECT GENDER, PROFILE_PICTURE
FROM SYARMIMI.HOSPITAL_STAFF
WHERE LOWER(USERNAME) = LOWER(:username)
";

$stmtDoctor = $conn->prepare($sqlDoctor);

$stmtDoctor->execute([
    ':username' => $_SESSION['user']
]);

$doctorInfo = $stmtDoctor->fetch(PDO::FETCH_ASSOC);

$gender = $doctorInfo['GENDER'] ?? 'Male';
$uploadedImage = $doctorInfo['PROFILE_PICTURE'] ?? '';

/* =========================
   PROFILE IMAGE LOGIC
========================= */

if (!empty($uploadedImage)) {

    // Doctor uploaded own picture
    $profileImage = "../" . $uploadedImage;

} else {

    // Default image based on gender
    if (strtolower($gender) == 'female') {

        $profileImage = "../assets/images/female-doctor.png";

    } else {

        $profileImage = "../assets/images/male-doctor.png";

    }
}
?>

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>

.sidebar{
    width:270px;
    min-height:100vh;

    background:linear-gradient(
        180deg,
        #0f172a 0%,
        #1e293b 100%
    );

    color:white;

    box-shadow:
    0 0 30px rgba(0,0,0,.15);

    position:sticky;
    top:0;
}

.profile-card{

    background:rgba(255,255,255,.08);

    border:1px solid rgba(255,255,255,.08);

    border-radius:20px;

    padding:20px;

    backdrop-filter:blur(8px);

}

.profile-image{

    width:95px;
    height:95px;

    border-radius:50%;

    object-fit:cover;

    border:4px solid rgba(255,255,255,.2);

    box-shadow:
    0 8px 20px rgba(0,0,0,.25);

    background:white;
}

.profile-name{
    font-weight:700;
    margin-top:12px;
    margin-bottom:2px;
}

.profile-role{
    color:#cbd5e1;
    font-size:13px;
}

.sidebar-title{

    color:#94a3b8;

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:2px;

    margin-top:20px;

    margin-bottom:10px;

    padding-left:8px;
}

.sidebar .nav-link{

    color:#e2e8f0;

    padding:13px 15px;

    margin-bottom:8px;

    border-radius:14px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    text-decoration:none;

    transition:.3s;

    font-size:15px;

    font-weight:500;
}

.sidebar .nav-link:hover{

    background:rgba(59,130,246,.15);

    color:white;

    transform:translateX(4px);
}

.sidebar .nav-link.active{

    background:linear-gradient(
    135deg,
    #2563eb,
    #3b82f6
    );

    color:white;

    box-shadow:
    0 8px 20px rgba(37,99,235,.35);
}

.menu-icon{

    width:20px;
    text-align:center;
    margin-right:10px;
}

.badge-count{

    background:#ef4444;

    color:white;

    border-radius:50px;

    padding:4px 10px;

    font-size:11px;

    font-weight:700;
}

.logout{

    color:#f87171 !important;
}

.logout:hover{

    background:rgba(239,68,68,.15) !important;
}

.system-logo{

    text-align:center;

    font-weight:700;

    font-size:20px;

    margin-bottom:20px;

    color:white;
}

</style>

<div class="sidebar">

<div class="p-3">

    <!-- PROFILE SECTION -->
    <div class="system-logo">
    <i class="bi bi-heart-pulse-fill"></i>
    ZB-CARE
</div>

<div class="profile-card text-center mb-4">

    <img src="<?= $profileImage ?>"
         class="profile-image">

    <div class="profile-name">
        Dr. <?= ucfirst($_SESSION['user'] ?? 'Doctor') ?>
    </div>

    <div class="profile-role">
        <?= ucfirst($gender) ?> Doctor
    </div>

</div>

    <div class="sidebar-title">
        Main Menu
    </div>

    <!-- DASHBOARD -->
 <a href="doctor_dashboard.php"
class="nav-link <?= ($current == 'doctor_dashboard.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-speedometer2 menu-icon"></i>
Dashboard
</span>

</a>

     <!-- PATIENT MANAGEMENT -->
    <a href="patient_management.php"
class="nav-link <?= ($current == 'patient_management.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-people-fill menu-icon"></i>
Patient Management
</span>

</a>
    <!-- TREATMENT -->
 <a href="treatment.php"
class="nav-link <?= ($current == 'treatment.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-clipboard2-pulse-fill menu-icon"></i>
Treatment
</span>

</a>
    <!-- APPOINTMENTS -->
    <a href="doctor_appointments.php"
class="nav-link <?= ($current == 'doctor_appointments.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-calendar-event menu-icon"></i>
Appointments
</span>

<?php if($appointmentCount > 0): ?>
<span class="badge-count">
<?= $appointmentCount ?>
</span>
<?php endif; ?>

</a>

    <!-- AVAILABILITY -->
  <a href="doctor_availability.php"
class="nav-link <?= ($current == 'doctor_availability.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-clock menu-icon"></i>
Availability
</span>

</a>
    <hr style="border-color:rgba(255,255,255,0.2);">

    <!-- FUTURE PROFILE PAGE -->
    <a href="doctor_profile.php"
class="nav-link <?= ($current == 'doctor_profile.php') ? 'active' : '' ?>">

<span>
<i class="bi bi-person-fill menu-icon"></i>
My Profile
</span>

</a>

    <!-- LOGOUT -->
   <a href="../auth/logout.php"
class="nav-link logout">

<span>
<i class="bi bi-box-arrow-right menu-icon"></i>
Logout
</span>

</a>

</div>

</div>