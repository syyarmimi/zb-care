<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

include("../config/config.php");

$current = basename($_SERVER['PHP_SELF']);

?>

<style>

/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 260px !important;

    min-width: 260px !important;

    max-width: 260px !important;

    flex: 0 0 260px !important;

    flex-shrink: 0 !important;

    min-height: 100vh;

    height: 100vh;

    background: linear-gradient(
        180deg,
        #081028 0%,
        #0f172a 50%,
        #13203f 100%
    );

    color: white;

    position: sticky;

    top: 0;

    overflow-y: auto;

    overflow-x: hidden;

    box-sizing: border-box;

}


/* =========================================================
   LOGO
========================================================= */

.logo-area {

    padding: 25px;

    text-align: center;

    border-bottom:
        1px solid
        rgba(255,255,255,.08);

}


.logo-area h4 {

    font-weight: 700;

    margin: 0;

    letter-spacing: .5px;

}


/* =========================================================
   MENU TITLE
========================================================= */

.menu-title {

    padding:
        20px
        25px
        10px;

    font-size: 11px;

    letter-spacing: 3px;

    color: #94a3b8;

    text-transform: uppercase;

}


/* =========================================================
   NAV LINK
========================================================= */

.sidebar .nav-link {

    color: #dbeafe;

    padding:
        12px
        18px;

    margin:
        4px
        12px;

    border-radius: 14px;

    display: flex;

    align-items: center;

    gap: 10px;

    font-weight: 500;

    transition: .25s;

    white-space: nowrap;

}


/* =========================================================
   NAV LINK HOVER
========================================================= */

.sidebar .nav-link:hover {

    background:
        rgba(
            59,
            130,
            246,
            .15
        );

    transform: translateX(5px);

    color: white;

}


/* =========================================================
   ACTIVE MENU
========================================================= */

.sidebar .nav-link.active {

    background: linear-gradient(
        135deg,
        #3b82f6,
        #2563eb
    );

    box-shadow:
        0 8px 20px
        rgba(
            37,
            99,
            235,
            .4
        );

    color: white;

}


/* =========================================================
   SUBMENU
========================================================= */

.submenu {

    display: none;

    margin-left: 18px;

}


.submenu .nav-link {

    font-size: 14px;

    padding:
        10px
        15px;

}


/* =========================================================
   LOGOUT
========================================================= */

.logout-btn {

    margin-top: 30px;

    color: #f87171 !important;

}


.logout-btn:hover {

    background:
        rgba(
            239,
            68,
            68,
            .15
        )
        !important;

}


/* =========================================================
   BADGE
========================================================= */

.badge-count {

    background: #ef4444;

    font-size: 11px;

}


/* =========================================================
   SCROLLBAR
========================================================= */

.sidebar::-webkit-scrollbar {

    width: 6px;

}


.sidebar::-webkit-scrollbar-track {

    background:
        rgba(
            255,
            255,
            255,
            .03
        );

}


.sidebar::-webkit-scrollbar-thumb {

    background:
        rgba(
            148,
            163,
            184,
            .35
        );

    border-radius: 10px;

}


.sidebar::-webkit-scrollbar-thumb:hover {

    background:
        rgba(
            148,
            163,
            184,
            .55
        );

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 768px) {

    .sidebar {

        width: 260px !important;

        min-width: 260px !important;

        max-width: 260px !important;

        flex: 0 0 260px !important;

    }

}

</style>


<div class="sidebar">


<!-- =====================================================
     LOGO
===================================================== -->

<div class="logo-area">

<h4>

<i class="bi bi-heart-pulse-fill"></i>

ZB-CARE

</h4>

</div>


<!-- =====================================================
     MENU TITLE
===================================================== -->

<div class="menu-title">

Main Menu

</div>


<ul
    class="nav flex-column px-2"
    style="list-style:none;"
>


<!-- =====================================================
     DASHBOARD
===================================================== -->

<li class="mb-1">

<a
    href="admin_dashboard.php"
    class="
        nav-link
        <?= ($current == 'admin_dashboard.php')
            ? 'active'
            : '' ?>
    "
>

<i class="bi bi-speedometer2"></i>

Dashboard

</a>

</li>


<!-- =====================================================
     PATIENT MENU
===================================================== -->

<li class="mb-1">

<a
    href="javascript:void(0)"
    onclick="togglePatientMenu()"
    class="nav-link"
>

<i class="bi bi-people-fill"></i>

Patient

<i class="bi bi-chevron-down ms-auto"></i>

</a>


<div
    id="patientMenu"
    class="submenu"
    style="
        display:
        <?= in_array(
            $current,
            [
                'patient_management.php',
                'walkin_consultation.php',
                'admission.php',
                'ward.php',
                'bed.php'
            ]
        )
        ? 'block'
        : 'none'
        ?>
    "
>


<a
    href="patient_management.php"
    class="
        nav-link
        <?= ($current == 'patient_management.php')
            ? 'active'
            : '' ?>
    "
>

🏠 Patient Management

</a>


<a
    href="walkin_consultation.php"
    class="
        nav-link
        <?= ($current == 'walkin_consultation.php')
            ? 'active'
            : '' ?>
    "
>

🩺 Walk-In Consultation

</a>


<a
    href="admission.php"
    class="
        nav-link
        <?= ($current == 'admission.php')
            ? 'active'
            : '' ?>
    "
>

👤 Register & Admit

</a>


<a
    href="ward.php"
    class="
        nav-link
        <?= ($current == 'ward.php')
            ? 'active'
            : '' ?>
    "
>

🛏 Ward & Bed

</a>


</div>

</li>


<!-- =====================================================
     STAFF
===================================================== -->

<li class="mb-1">

<a
    href="staff.php"
    class="
        nav-link
        <?= ($current == 'staff.php')
            ? 'active'
            : '' ?>
    "
>

<i class="bi bi-person-badge-fill"></i>

Staff

</a>

</li>


<!-- =====================================================
     DOCTOR AVAILABILITY
===================================================== -->

<li class="mb-1">

<a
    href="doctor_availability_admin.php"
    class="
        nav-link
        <?= ($current == 'doctor_availability_admin.php')
            ? 'active'
            : '' ?>
    "
>

<i class="bi bi-calendar-check"></i>

Doctor Availability

</a>

</li>


<!-- =====================================================
     APPOINTMENTS
===================================================== -->

<li class="mb-1">

<a
    href="admin_appointment.php"
    class="
        nav-link
        d-flex
        justify-content-between
        align-items-center
        <?= ($current == 'admin_appointment.php')
            ? 'active'
            : '' ?>
    "
>

<span>

📅 Appointments

</span>


<?php

$apptCount =
    $conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.APPOINTMENT

        WHERE UPPER(TRIM(STATUS)) = 'PENDING'

    ")->fetchColumn();

?>


<?php if ($apptCount > 0): ?>

<span class="badge bg-danger">

<?= $apptCount ?>

</span>

<?php endif; ?>


</a>

</li>


<!-- =====================================================
     MEDICATION
===================================================== -->

<li class="mb-1">

<a
    href="medication.php"
    class="
        nav-link
        <?= ($current == 'medication.php')
            ? 'active'
            : '' ?>
    "
>

<i class="bi bi-capsule-pill"></i>

Medication

</a>

</li>


<!-- =====================================================
     MEDICATION DELIVERY
===================================================== -->

<li class="mb-1">

<a
    href="med.php"
    class="
        nav-link
        <?= ($current == 'med.php')
            ? 'active'
            : '' ?>
    "
>

<i class="bi bi-truck"></i>

Medication Delivery

</a>

</li>


<!-- =====================================================
     LOGOUT
===================================================== -->

<li class="mt-4">

<a
    href="../auth/logout.php"
    class="nav-link logout-btn"
>

<i class="bi bi-box-arrow-right"></i>

Logout

</a>

</li>


</ul>


</div>


<script>

/* =========================================================
   PATIENT MENU
========================================================= */

function togglePatientMenu()
{

    var menu =
        document.getElementById(
            "patientMenu"
        );


    if (!menu) {
        return;
    }


    menu.style.display =
        (
            menu.style.display
            ===
            "block"
        )
        ?
        "none"
        :
        "block";

}


/* =========================================================
   MEDICATION MENU
========================================================= */

function toggleMedicationMenu()
{

    var menu =
        document.getElementById(
            "medicationMenu"
        );


    if (!menu) {
        return;
    }


    menu.style.display =
        (
            menu.style.display
            ===
            "block"
        )
        ?
        "none"
        :
        "block";

}

</script>