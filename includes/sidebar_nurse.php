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
========================================================= */

.sidebar{

    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;

    flex:0 0 260px !important;
    flex-shrink:0 !important;

    height:100vh;
    min-height:100vh;

    position:fixed;

    top:0;
    left:0;

    z-index:1000;

    overflow-y:auto;
    overflow-x:hidden;

    box-sizing:border-box;

    background:
        linear-gradient(
            180deg,
            #0f172a 0%,
            #172033 50%,
            #1e293b 100%
        );

    color:#fff;

    border-right:
        1px solid
        rgba(255,255,255,.05);

    box-shadow:
        8px 0 24px
        rgba(15,23,42,.08);
}


/* =========================================================
   INNER WRAPPER
========================================================= */

.sidebar-inner{

    min-height:100%;

    padding:20px 14px;

    display:flex;

    flex-direction:column;
}


/* =========================================================
   LOGO
========================================================= */

.system-logo{

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    margin-bottom:20px;

    color:#fff;

    font-size:20px;

    font-weight:700;

    letter-spacing:.2px;
}


.system-logo i{

    color:#60a5fa;

    font-size:20px;
}


/* =========================================================
   PROFILE
========================================================= */

.profile-card{

    margin-bottom:22px;

    padding:16px;

    display:flex;

    align-items:center;

    gap:12px;

    background:
        rgba(255,255,255,.05);

    border:
        1px solid
        rgba(255,255,255,.07);

    border-radius:14px;
}


/* =========================================================
   PROFILE ICON
========================================================= */

.profile-icon{

    width:52px;
    height:52px;

    min-width:52px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:
        rgba(59,130,246,.14);

    border:
        1px solid
        rgba(96,165,250,.18);

    border-radius:12px;

    color:#93c5fd;

    font-size:24px;
}


/* =========================================================
   PROFILE INFO
========================================================= */

.profile-info{

    min-width:0;
}


.profile-name{

    margin:0;

    color:#f8fafc;

    font-size:14px;

    font-weight:650;
}


.profile-role{

    margin-top:2px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   MENU TITLE
========================================================= */

.sidebar-title{

    margin-top:3px;

    margin-bottom:9px;

    padding-left:11px;

    color:#64748b;

    font-size:9px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:1.8px;
}


/* =========================================================
   NAV LINKS
========================================================= */

.sidebar .nav-link{

    min-height:43px;

    margin-bottom:5px;

    padding:10px 12px;

    display:flex;

    align-items:center;

    border-radius:9px;

    color:#cbd5e1;

    text-decoration:none;

    font-size:13px;

    font-weight:500;

    transition:
        background .18s ease,
        color .18s ease,
        transform .18s ease;

    white-space:nowrap;
}


/* =========================================================
   NAV CONTENT
========================================================= */

.sidebar .nav-link span{

    display:flex;

    align-items:center;
}


/* =========================================================
   MENU ICON
========================================================= */

.menu-icon{

    width:22px;

    margin-right:9px;

    display:inline-flex;

    align-items:center;

    justify-content:center;

    color:#94a3b8;

    font-size:15px;

    transition:.18s;
}


/* =========================================================
   HOVER
========================================================= */

.sidebar .nav-link:hover{

    background:
        rgba(255,255,255,.06);

    color:#fff;

    transform:
        translateX(2px);
}


.sidebar .nav-link:hover
.menu-icon{

    color:#bfdbfe;
}


/* =========================================================
   ACTIVE
========================================================= */

.sidebar .nav-link.active{

    background:
        rgba(37,99,235,.16);

    border:
        1px solid
        rgba(96,165,250,.16);

    color:#fff;

    font-weight:600;

    box-shadow:none;
}


.sidebar .nav-link.active
.menu-icon{

    color:#60a5fa;
}


/* =========================================================
   DIVIDER
========================================================= */

.sidebar-divider{

    margin:14px 7px;

    border:0;

    border-top:
        1px solid
        rgba(255,255,255,.07);

    opacity:1;
}


/* =========================================================
   LOGOUT SECTION
========================================================= */

.logout-wrapper{

    margin-top:auto;

    padding-top:18px;
}


.sidebar .logout{

    color:#fca5a5;
}


.sidebar .logout
.menu-icon{

    color:#f87171;
}


.sidebar .logout:hover{

    background:
        rgba(239,68,68,.08);

    color:#fecaca;

    transform:none;
}


/* =========================================================
   VERSION
========================================================= */

.sidebar-version{

    margin-top:12px;

    color:#475569;

    font-size:9px;

    text-align:center;
}


/* =========================================================
   SCROLLBAR
========================================================= */

.sidebar::-webkit-scrollbar{

    width:5px;
}


.sidebar::-webkit-scrollbar-track{

    background:transparent;
}


.sidebar::-webkit-scrollbar-thumb{

    background:
        rgba(148,163,184,.20);

    border-radius:10px;
}


.sidebar::-webkit-scrollbar-thumb:hover{

    background:
        rgba(148,163,184,.35);
}


/* =========================================================
   PAGE CONTENT SUPPORT

   If page uses normal layout, this prevents content
   from going underneath the fixed sidebar.
========================================================= */

body > .d-flex > .flex-grow-1,
body > .d-flex > .content,
body > .d-flex > .main-content{

    margin-left:260px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .sidebar{

        width:230px !important;
        min-width:230px !important;
        max-width:230px !important;

        flex-basis:230px !important;
    }


    body > .d-flex > .flex-grow-1,
    body > .d-flex > .content,
    body > .d-flex > .main-content{

        margin-left:230px;
    }

}

</style>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">


<div class="sidebar-inner">


<!-- =====================================================
     LOGO
===================================================== -->

<div class="system-logo">

<i class="bi bi-heart-pulse-fill"></i>

<span>
ZB-CARE
</span>

</div>



<!-- =====================================================
     PROFILE
===================================================== -->

<div class="profile-card">


<div class="profile-icon">

<i class="bi bi-person-heart"></i>

</div>


<div class="profile-info">


<div class="profile-name">

Nurse

</div>


<div class="profile-role">

Patient Care Staff

</div>


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
        <?= (
            $current
            ===
            'nurse_dashboard.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<span>

<i class="bi bi-grid-1x2 menu-icon"></i>

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
        <?= (
            $current
            ===
            'nurse_patients.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<span>

<i class="bi bi-people menu-icon"></i>

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
        <?= (
            $current
            ===
            'nurse_medication.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<span>

<i class="bi bi-capsule menu-icon"></i>

Medication

</span>


</a>



<!-- =====================================================
     DIVIDER
===================================================== -->

<hr class="sidebar-divider">



<!-- =====================================================
     LOGOUT
===================================================== -->

<div class="logout-wrapper">


<a
    href="../auth/logout.php"
    class="nav-link logout"
>


<span>

<i class="bi bi-box-arrow-right menu-icon"></i>

Logout

</span>


</a>


<div class="sidebar-version">

ZB-CARE Nurse Portal

</div>


</div>


</div>


</div>