<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>

.sidebar{
    width:260px;
    min-height:100vh;

    background:linear-gradient(
    180deg,
    #0f172a 0%,
    #1e293b 100%
    );

    color:white;

    padding:20px;

    display:flex;
    flex-direction:column;

    box-shadow:
    5px 0 20px rgba(0,0,0,.15);
}

.sidebar-header{
    text-align:center;
    margin-bottom:30px;
}

.logo-circle{

    width:75px;
    height:75px;

    margin:auto;

    border-radius:50%;

    background:linear-gradient(
    135deg,
    #22c55e,
    #16a34a
    );

    display:flex;
    align-items:center;
    justify-content:center;

    font-size:32px;

    box-shadow:
    0 8px 20px rgba(34,197,94,.35);
}

.sidebar-header h4{
    margin-top:15px;
    margin-bottom:5px;
    font-weight:700;
}

.sidebar-header small{
    color:#94a3b8;
    font-size:13px;
}

.sidebar .nav-link{

    color:#cbd5e1;

    padding:12px 15px;

    border-radius:12px;

    margin-bottom:8px;

    display:flex;
    align-items:center;
    gap:12px;

    transition:all .25s ease;

    font-size:15px;
}

.sidebar .nav-link:hover{

    background:
    rgba(255,255,255,.08);

    color:white;

    transform:translateX(5px);
}

.sidebar .nav-link.active{

    background:linear-gradient(
    135deg,
    #22c55e,
    #16a34a
    );

    color:white;

    font-weight:600;

    box-shadow:
    0 5px 15px rgba(34,197,94,.25);
}

.sidebar .nav-link i{
    font-size:18px;
}

.menu-title{

    color:#94a3b8;

    font-size:12px;

    text-transform:uppercase;

    letter-spacing:1px;

    margin-top:10px;
    margin-bottom:10px;
    padding-left:10px;
}

.logout-section{
    margin-top:auto;
}

.logout-btn{

    width:100%;

    border:none;

    text-decoration:none;

    display:flex;
    justify-content:center;
    align-items:center;

    gap:10px;

    background:#dc3545;

    color:white;

    padding:12px;

    border-radius:12px;

    transition:.3s;
}

.logout-btn:hover{

    background:#bb2d3b;

    color:white;

    transform:translateY(-2px);
}

.version{

    text-align:center;

    color:#94a3b8;

    font-size:12px;

    margin-top:15px;
}

</style>

<div class="sidebar">

    <div class="sidebar-header">

        <div class="logo-circle">
            💊
        </div>

        <h4>Pharmacy</h4>

        <small>
            Medication Management
        </small>

    </div>

    <div class="menu-title">
        Main Menu
    </div>

    <ul class="nav flex-column">

        <li class="nav-item">

            <a href="../pages/pharmacist_dashboard.php"
            class="nav-link <?= ($currentPage=='pharmacist_dashboard.php') ? 'active' : '' ?>">

                <i class="bi bi-speedometer2"></i>

                Dashboard

            </a>

        </li>

        <li class="nav-item">

            <a href="../pages/pharmacy_inventory.php"
            class="nav-link <?= ($currentPage=='pharmacy_inventory.php') ? 'active' : '' ?>">

                <i class="bi bi-capsule"></i>

                Inventory

            </a>

        </li>

        <li class="nav-item">

            <a href="../pages/pharmacy_preparation.php"
            class="nav-link <?= ($currentPage=='pharmacy_preparation.php') ? 'active' : '' ?>">

                <i class="bi bi-box-seam"></i>

                Prepare Medication

            </a>

        </li>

        <li class="nav-item">

            <a href="../pages/med_delivery.php"
            class="nav-link <?= ($currentPage=='med_delivery.php') ? 'active' : '' ?>">

                <i class="bi bi-truck"></i>

                Delivery

            </a>

        </li>

    </ul>

    <div class="logout-section">

        <a href="../auth/logout.php"
        class="logout-btn">

            <i class="bi bi-box-arrow-right"></i>

            Logout

        </a>

        <div class="version">
            ZB-CARE Pharmacy v1.0
        </div>

    </div>

</div>