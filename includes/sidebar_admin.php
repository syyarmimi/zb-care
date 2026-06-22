<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("../config/config.php");

$current = basename($_SERVER['PHP_SELF']);
?>

<style>
.submenu {
    display: none;
    margin-left: 15px;
}

.submenu a {
    font-size: 14px;
}
</style>

<div style="width:250px; background:#1e3a8a; min-height:100vh; color:white;">

<h4 class="p-3 text-center border-bottom">ZB-CARE</h4>

<ul class="nav flex-column px-2" style="list-style:none;">

<!-- DASHBOARD -->
<li class="mb-1">
<a href="admin_dashboard.php"
class="nav-link text-white <?= ($current == 'admin_dashboard.php') ? 'bg-primary rounded' : '' ?>">
🏠 Dashboard
</a>
</li>

<!-- 🔥 PATIENT MENU -->
<li class="mb-1">

<a href="javascript:void(0)" onclick="togglePatientMenu()"
class="nav-link text-white">
👤 Patient ▼
</a>

<div id="patientMenu" class="submenu"
style="display: <?= in_array($current, ['patient.php','admission.php','ward.php','bed.php']) ? 'block' : 'none' ?>">

  <a href="patient_management.php"
class="nav-link text-white <?= ($current == 'patient_management.php') ? 'bg-primary rounded' : '' ?>">
🏠 Patient Management
</a>

<a href="walkin_consultation.php"
class="nav-link text-white <?= ($current == 'walkin_consultation.php') ? 'bg-primary rounded' : '' ?>">
🩺 Walk-In Consultation
</a>

<a href="admission.php"
class="nav-link text-white <?= ($current == 'admission.php') ? 'bg-primary rounded' : '' ?>">
👤 Register & Admit
</a>

<a href="ward.php"
class="nav-link text-white <?= ($current == 'ward.php') ? 'bg-primary rounded' : '' ?>">
🛏 Ward & Bed
</a>

</div>

</li>

<!-- STAFF -->
<li class="mb-1">
<a href="staff.php"
class="nav-link text-white <?= ($current == 'staff.php') ? 'bg-primary rounded' : '' ?>">
👨‍⚕️ Staff
</a>
</li>

<!-- DOCTOR AVAILABILITY -->
<li class="mb-1">
<a href="doctor_availability_admin.php"
class="nav-link text-white <?= ($current == 'doctor_availability_admin.php') ? 'bg-primary rounded' : '' ?>">
📅 Doctor Availability
</a>
</li>

<!-- 🔥 APPOINTMENT -->
<li class="mb-1">

<a href="admin_appointment.php"
class="nav-link text-white d-flex justify-content-between align-items-center <?= ($current == 'admin_appointment.php') ? 'bg-primary rounded' : '' ?>">

<span>📅 Appointments</span>

<?php
$apptCount = $conn->query("
SELECT COUNT(*)
FROM SYARMIMI.APPOINTMENT
WHERE STATUS='Pending'
")->fetchColumn();

if($apptCount > 0):
?>

<span class="badge bg-danger">
<?= $apptCount ?>
</span>

<?php endif; ?>

</a>

</li>


<!-- MEDICATION -->
<li class="mb-1">
<a href="medication.php"
class="nav-link text-white <?= ($current == 'medication.php') ? 'bg-primary rounded' : '' ?>">
💊 Medication
</a>
</li>

<!-- MEDICATION DELIVERY -->
<li class="mb-1">
<a href="med.php"
class="nav-link text-white <?= ($current == 'med.php') ? 'bg-primary rounded' : '' ?>">
🚚 Medication Delivery
</a>
</li>

<!-- LOGOUT -->
<li class="mt-4">
<a href="../auth/logout.php" class="nav-link text-warning">
🚪 Logout
</a>
</li>

</ul>

</div>

<!-- 🔥 TOGGLE SCRIPT -->
<script>

function togglePatientMenu() {

    var menu = document.getElementById("patientMenu");

    menu.style.display =
    (menu.style.display === "block")
    ? "none"
    : "block";
}

function toggleMedicationMenu() {

    var menu = document.getElementById("medicationMenu");

    if(menu){
        menu.style.display =
        (menu.style.display === "block")
        ? "none"
        : "block";
    }
}

</script>