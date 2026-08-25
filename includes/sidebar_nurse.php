<?php

$current = basename($_SERVER['PHP_SELF']);

?>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

/* =========================================================
   SIDEBAR
   STANDARD SIZE = 260PX
========================================================= */

.sidebar{

    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;

    flex:0 0 260px !important;
    flex-shrink:0 !important;

    height:100vh;
    min-height:100vh;

    background:linear-gradient(
        180deg,
        #0f172a 0%,
        #1e293b 100%
    );

    color:white;

    box-shadow:
        0 0 30px rgba(0,0,0,.15);

    position:fixed;

    top:0;
    left:0;

    overflow-y:auto;
    overflow-x:hidden;

    z-index:1000;

    box-sizing:border-box;
}


/* =========================================================
   SYSTEM LOGO
========================================================= */

.system-logo{

    text-align:center;

    font-weight:700;

    font-size:22px;

    margin-bottom:25px;

    color:white;
}


/* =========================================================
   PROFILE CARD
========================================================= */

.profile-card{

    background:
        rgba(255,255,255,.08);

    border:
        1px solid
        rgba(255,255,255,.08);

    border-radius:20px;

    padding:20px;

    text-align:center;

    margin-bottom:25px;
}


/* =========================================================
   PROFILE ICON
========================================================= */

.profile-icon{

    width:90px;
    height:90px;

    margin:auto;

    border-radius:50%;

    background:white;

    display:flex;

    align-items:center;

    justify-content:center;

    font-size:45px;

    color:#3b82f6;

    box-shadow:
        0 8px 20px
        rgba(0,0,0,.25);
}


/* =========================================================
   PROFILE NAME
========================================================= */

.profile-name{

    font-weight:700;

    margin-top:15px;

    margin-bottom:3px;
}


/* =========================================================
   PROFILE ROLE
========================================================= */

.profile-role{

    color:#cbd5e1;

    font-size:13px;
}


/* =========================================================
   SIDEBAR TITLE
========================================================= */

.sidebar-title{

    color:#94a3b8;

    font-size:11px;

    text-transform:uppercase;

    letter-spacing:2px;

    margin-top:15px;

    margin-bottom:10px;

    padding-left:8px;
}


/* =========================================================
   NAVIGATION
========================================================= */

.sidebar .nav-link{

    color:#e2e8f0;

    padding:13px 15px;

    margin-bottom:8px;

    border-radius:14px;

    display:flex;

    align-items:center;

    text-decoration:none;

    transition:.3s;

    font-size:15px;

    font-weight:500;

    white-space:nowrap;
}


/* =========================================================
   NAVIGATION HOVER
========================================================= */

.sidebar .nav-link:hover{

    background:
        rgba(59,130,246,.15);

    color:white;

    transform:translateX(4px);
}


/* =========================================================
   ACTIVE NAVIGATION
========================================================= */

.sidebar .nav-link.active{

    background:linear-gradient(
        135deg,
        #2563eb,
        #3b82f6
    );

    color:white;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,.35);
}


/* =========================================================
   MENU ICON
========================================================= */

.menu-icon{

    width:22px;

    margin-right:12px;

    text-align:center;
}


/* =========================================================
   LOGOUT
========================================================= */

.logout{

    color:#f87171 !important;
}


.logout:hover{

    background:
        rgba(239,68,68,.15)
        !important;
}


/* =========================================================
   SCROLLBAR
========================================================= */

.sidebar::-webkit-scrollbar{

    width:6px;
}


.sidebar::-webkit-scrollbar-track{

    background:
        rgba(255,255,255,.03);
}


.sidebar::-webkit-scrollbar-thumb{

    background:
        rgba(148,163,184,.35);

    border-radius:10px;
}


.sidebar::-webkit-scrollbar-thumb:hover{

    background:
        rgba(148,163,184,.55);
}

</style>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">


<div class="p-3">


<!-- =====================================================
     LOGO
===================================================== -->

<div class="system-logo">

    <i class="bi bi-heart-pulse-fill"></i>

    ZB-CARE

</div>


<!-- =====================================================
     PROFILE
===================================================== -->

<div class="profile-card">


    <div class="profile-icon">

        <i class="bi bi-person-heart"></i>

    </div>


    <div class="profile-name">

        Nurse

    </div>


    <div class="profile-role">

        Patient Care Staff

    </div>


</div>


<!-- =====================================================
     MENU TITLE
===================================================== -->

<div class="sidebar-title">

    Main Menu

</div>


<!-- =====================================================
     DASHBOARD
===================================================== -->

<a
    href="../pages/nurse_dashboard.php"
    class="
        nav-link
        <?= ($current == 'nurse_dashboard.php')
            ? 'active'
            : '' ?>
    "
>

    <span>

        <i class="bi bi-speedometer2 menu-icon"></i>

        Dashboard

    </span>

</a>


<!-- =====================================================
     PATIENTS
===================================================== -->

<a
    href="../pages/nurse_patients.php"
    class="
        nav-link
        <?= ($current == 'nurse_patients.php')
            ? 'active'
            : '' ?>
    "
>

    <span>

        <i class="bi bi-people-fill menu-icon"></i>

        Patients

    </span>

</a>


<!-- =====================================================
     MEDICATION
===================================================== -->

<a
    href="../pages/nurse_medication.php"
    class="
        nav-link
        <?= ($current == 'nurse_medication.php')
            ? 'active'
            : '' ?>
    "
>

    <span>

        <i class="bi bi-capsule-pill menu-icon"></i>

        Medication

    </span>

</a>


<hr
    style="
        border-color:
        rgba(255,255,255,.15);
    "
>


<!-- =====================================================
     LOGOUT
===================================================== -->

<a
    href="../auth/logout.php"
    class="nav-link logout"
>

    <span>

        <i class="bi bi-box-arrow-right menu-icon"></i>

        Logout

    </span>

</a>


</div>


</div>