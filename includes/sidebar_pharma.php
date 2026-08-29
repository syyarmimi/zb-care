<?php

$currentPage = basename($_SERVER['PHP_SELF']);

?>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

/* =========================================================
   PHARMACY SIDEBAR
========================================================= */

.pharma-sidebar{

    width:260px !important;
    min-width:260px !important;
    max-width:260px !important;

    flex:0 0 260px !important;
    flex-shrink:0 !important;

    height:100vh;
    min-height:100vh;

    position:sticky;
    top:0;

    display:flex;
    flex-direction:column;

    overflow:hidden;

    background:
        linear-gradient(
            180deg,
            #081122 0%,
            #0c1729 48%,
            #101c31 100%
        );

    color:#fff;

    border-right:
        1px solid
        rgba(255,255,255,.05);

    box-shadow:
        8px 0 25px
        rgba(15,23,42,.10);

    box-sizing:border-box;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;
}


/* =========================================================
   DECORATIVE BACKGROUND
========================================================= */

.pharma-sidebar::before{

    content:"";

    position:absolute;

    width:190px;
    height:190px;

    top:-90px;
    right:-80px;

    border-radius:50%;

    background:
        rgba(16,185,129,.10);

    pointer-events:none;
}


.pharma-sidebar::after{

    content:"";

    position:absolute;

    width:140px;
    height:140px;

    bottom:50px;
    left:-80px;

    border-radius:50%;

    background:
        rgba(34,197,94,.05);

    pointer-events:none;
}


/* =========================================================
   BRAND
========================================================= */

.pharma-brand{

    position:relative;
    z-index:2;

    padding:
        23px
        20px
        18px;

    border-bottom:
        1px solid
        rgba(255,255,255,.06);
}


.pharma-brand-row{

    display:flex;

    align-items:center;

    gap:11px;
}


/* =========================================================
   BRAND ICON
========================================================= */

.pharma-brand-icon{

    width:42px;
    height:42px;

    min-width:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    background:
        linear-gradient(
            135deg,
            #10b981,
            #059669
        );

    color:#fff;

    font-size:20px;

    box-shadow:
        0 8px 20px
        rgba(16,185,129,.22);
}


.pharma-brand-name{

    color:#f8fafc;

    font-size:18px;

    font-weight:750;

    letter-spacing:.3px;

    line-height:1.1;
}


.pharma-brand-subtitle{

    margin-top:3px;

    color:#64748b;

    font-size:9px;

    font-weight:600;

    letter-spacing:.7px;

    text-transform:uppercase;
}


/* =========================================================
   PHARMACY PROFILE CARD
========================================================= */

.pharma-profile{

    position:relative;
    z-index:2;

    margin:
        18px
        14px
        16px;

    padding:13px;

    display:flex;

    align-items:center;

    gap:11px;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,.07),
            rgba(255,255,255,.035)
        );

    border:
        1px solid
        rgba(255,255,255,.07);

    border-radius:14px;
}


/* =========================================================
   PROFILE ICON
========================================================= */

.pharma-avatar{

    width:46px;
    height:46px;

    min-width:46px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    background:
        linear-gradient(
            135deg,
            #d1fae5,
            #a7f3d0
        );

    color:#059669;

    font-size:21px;
}


.pharma-profile-info{

    flex:1;

    min-width:0;
}


.pharma-profile-name{

    color:#f8fafc;

    font-size:13px;

    font-weight:650;
}


.pharma-profile-role{

    margin-top:2px;

    color:#94a3b8;

    font-size:10px;
}


/* =========================================================
   ACTIVE SESSION
========================================================= */

.pharma-session{

    margin-top:5px;

    display:flex;

    align-items:center;

    gap:5px;

    color:#64748b;

    font-size:9px;
}


.session-dot{

    width:6px;
    height:6px;

    border-radius:50%;

    background:#22c55e;

    box-shadow:
        0 0 0 3px
        rgba(34,197,94,.10);
}


/* =========================================================
   NAVIGATION AREA
========================================================= */

.pharma-navigation{

    position:relative;
    z-index:2;

    flex:1;

    padding:
        5px
        12px
        15px;

    overflow-y:auto;
    overflow-x:hidden;
}


/* =========================================================
   MENU LABEL
========================================================= */

.pharma-menu-label{

    margin:
        8px
        10px
        9px;

    color:#475569;

    font-size:9px;

    font-weight:700;

    letter-spacing:1.6px;

    text-transform:uppercase;
}


/* =========================================================
   NAVIGATION LINK
========================================================= */

.pharma-sidebar .nav-link{

    position:relative;

    min-height:48px;

    margin-bottom:6px;

    padding:
        7px
        10px;

    display:flex;

    align-items:center;

    gap:10px;

    border:
        1px solid
        transparent;

    border-radius:11px;

    color:#94a3b8;

    text-decoration:none;

    font-size:12px;

    font-weight:500;

    transition:
        background .18s ease,
        border-color .18s ease,
        color .18s ease,
        transform .18s ease;

    white-space:nowrap;
}


/* =========================================================
   ICON BOX
========================================================= */

.pharma-nav-icon{

    width:34px;
    height:34px;

    min-width:34px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    background:
        rgba(255,255,255,.04);

    color:#64748b;

    font-size:15px;

    transition:.18s ease;
}


.pharma-nav-text{

    flex:1;
}


/* =========================================================
   HOVER
========================================================= */

.pharma-sidebar .nav-link:hover{

    background:
        rgba(255,255,255,.045);

    border-color:
        rgba(255,255,255,.04);

    color:#e2e8f0;

    transform:
        translateX(2px);
}


.pharma-sidebar
.nav-link:hover
.pharma-nav-icon{

    background:
        rgba(16,185,129,.10);

    color:#6ee7b7;
}


/* =========================================================
   ACTIVE NAVIGATION
========================================================= */

.pharma-sidebar .nav-link.active{

    background:
        linear-gradient(
            90deg,
            rgba(16,185,129,.18),
            rgba(16,185,129,.055)
        );

    border-color:
        rgba(52,211,153,.12);

    color:#fff;

    font-weight:600;
}


/* =========================================================
   ACTIVE LEFT LINE
========================================================= */

.pharma-sidebar
.nav-link.active::before{

    content:"";

    position:absolute;

    left:-12px;

    top:9px;

    width:3px;
    height:30px;

    border-radius:
        0
        4px
        4px
        0;

    background:#10b981;

    box-shadow:
        0 0 10px
        rgba(16,185,129,.45);
}


/* =========================================================
   ACTIVE ICON
========================================================= */

.pharma-sidebar
.nav-link.active
.pharma-nav-icon{

    background:
        linear-gradient(
            135deg,
            #10b981,
            #059669
        );

    color:#fff;

    box-shadow:
        0 5px 13px
        rgba(16,185,129,.18);
}


/* =========================================================
   ACTIVE DOT
========================================================= */

.pharma-active-dot{

    display:none;

    width:5px;
    height:5px;

    flex-shrink:0;

    border-radius:50%;

    background:#34d399;
}


.pharma-sidebar
.nav-link.active
.pharma-active-dot{

    display:block;
}


/* =========================================================
   FOOTER
========================================================= */

.pharma-footer{

    position:relative;
    z-index:2;

    padding:
        13px
        12px
        18px;

    border-top:
        1px solid
        rgba(255,255,255,.05);
}


/* =========================================================
   LOGOUT
========================================================= */

.pharma-sidebar
.logout-link{

    margin-bottom:0;

    color:#f87171;
}


.pharma-sidebar
.logout-link
.pharma-nav-icon{

    background:
        rgba(239,68,68,.07);

    color:#f87171;
}


.pharma-sidebar
.logout-link:hover{

    background:
        rgba(239,68,68,.08);

    border-color:
        rgba(239,68,68,.08);

    color:#fca5a5;

    transform:none;
}


.pharma-sidebar
.logout-link:hover
.pharma-nav-icon{

    background:
        rgba(239,68,68,.12);

    color:#fca5a5;
}


/* =========================================================
   VERSION
========================================================= */

.pharma-version{

    margin-top:11px;

    color:#334155;

    font-size:8px;

    text-align:center;

    letter-spacing:.5px;
}


/* =========================================================
   SCROLLBAR
========================================================= */

.pharma-navigation::-webkit-scrollbar{

    width:4px;
}


.pharma-navigation::-webkit-scrollbar-track{

    background:transparent;
}


.pharma-navigation::-webkit-scrollbar-thumb{

    background:
        rgba(148,163,184,.15);

    border-radius:10px;
}

</style>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="pharma-sidebar">


<!-- =====================================================
     BRAND
===================================================== -->

<div class="pharma-brand">


<div class="pharma-brand-row">


<div class="pharma-brand-icon">

<i class="bi bi-heart-pulse-fill"></i>

</div>


<div>


<div class="pharma-brand-name">

ZB-CARE

</div>


<div class="pharma-brand-subtitle">

Specialist Hospital

</div>


</div>


</div>


</div>



<!-- =====================================================
     PHARMACY PROFILE
===================================================== -->

<div class="pharma-profile">


<div class="pharma-avatar">

<i class="bi bi-capsule-pill"></i>

</div>


<div class="pharma-profile-info">


<div class="pharma-profile-name">

Pharmacy

</div>


<div class="pharma-profile-role">

Medication Management

</div>


<div class="pharma-session">

<span class="session-dot"></span>

Active Session

</div>


</div>


</div>



<!-- =====================================================
     NAVIGATION
===================================================== -->

<div class="pharma-navigation">


<div class="pharma-menu-label">

Workspace

</div>



<!-- =====================================================
     DASHBOARD
===================================================== -->

<a
    href="../pages/pharmacist_dashboard.php"

    class="
        nav-link
        <?= (
            $currentPage
            ===
            'pharmacist_dashboard.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<div class="pharma-nav-icon">

<i class="bi bi-grid"></i>

</div>


<span class="pharma-nav-text">

Dashboard

</span>


<span class="pharma-active-dot"></span>


</a>



<!-- =====================================================
     INVENTORY
===================================================== -->

<a
    href="../pages/pharmacy_inventory.php"

    class="
        nav-link
        <?= (
            $currentPage
            ===
            'pharmacy_inventory.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<div class="pharma-nav-icon">

<i class="bi bi-capsule"></i>

</div>


<span class="pharma-nav-text">

Inventory

</span>


<span class="pharma-active-dot"></span>


</a>



<!-- =====================================================
     PREPARE MEDICATION
===================================================== -->

<a
    href="../pages/pharmacy_preparation.php"

    class="
        nav-link
        <?= (
            $currentPage
            ===
            'pharmacy_preparation.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<div class="pharma-nav-icon">

<i class="bi bi-box-seam"></i>

</div>


<span class="pharma-nav-text">

Prepare Medication

</span>


<span class="pharma-active-dot"></span>


</a>



<!-- =====================================================
     DELIVERY
===================================================== -->

<a
    href="../pages/med_delivery.php"

    class="
        nav-link
        <?= (
            $currentPage
            ===
            'med_delivery.php'
        )
        ?
        'active'
        :
        ''
        ?>
    "
>


<div class="pharma-nav-icon">

<i class="bi bi-truck"></i>

</div>


<span class="pharma-nav-text">

Delivery

</span>


<span class="pharma-active-dot"></span>


</a>


</div>



<!-- =====================================================
     FOOTER / LOGOUT
===================================================== -->

<div class="pharma-footer">


<a
    href="../auth/logout.php"
    class="nav-link logout-link"
>


<div class="pharma-nav-icon">

<i class="bi bi-box-arrow-right"></i>

</div>


<span class="pharma-nav-text">

Logout

</span>


</a>


<div class="pharma-version">

ZB-CARE • PHARMACY PORTAL

</div>


</div>


</div>