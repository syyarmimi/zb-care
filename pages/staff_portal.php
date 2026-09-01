<?php
session_start();
include("../config/config.php");

/* =========================
   AUTO REDIRECT IF LOGGED IN
========================= */

if (isset($_SESSION['role'])) {

    switch ($_SESSION['role']) {

        case 'admin':
            header("Location: admin_dashboard.php");
            exit();

        case 'doctor':
            header("Location: doctor_dashboard.php");
            exit();

        case 'nurse':
            header("Location: nurse_dashboard.php");
            exit();

        case 'pharmacist':
            header("Location: pharmacist_dashboard.php");
            exit();

        case 'kitchen':
            header("Location: kitchen_dashboard.php");
            exit();
    }
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

<title>ZB-CARE Staff Portal</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html,
body {
    min-height: 100%;
}

body {
    font-family: 'Segoe UI', Arial, sans-serif;
    background:
        radial-gradient(circle at top left, #eef6ff 0%, transparent 35%),
        radial-gradient(circle at bottom right, #effaf7 0%, transparent 35%),
        #f7f9fc;
    color: #0f172a;
}


/* =========================================================
   TOP BAR
========================================================= */

.topbar {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 10;
    padding: 22px 0;
}

.brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #ffffff;
    font-size: 22px;
    font-weight: 800;
    text-decoration: none;
    letter-spacing: -0.4px;
}

.brand:hover {
    color: #ffffff;
}

.brand-icon {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: rgba(255,255,255,.16);
    border: 1px solid rgba(255,255,255,.22);
    backdrop-filter: blur(8px);
    font-size: 20px;
}

.patient-link {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 15px;
    border-radius: 10px;
    border: 1px solid rgba(255,255,255,.22);
    background: rgba(255,255,255,.10);
    color: rgba(255,255,255,.92);
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
    backdrop-filter: blur(8px);
    transition: .2s ease;
}

.patient-link:hover {
    background: #ffffff;
    color: #2563eb;
}


/* =========================================================
   HERO
========================================================= */

.hero {
    position: relative;
    overflow: hidden;
    padding: 145px 20px 115px;
    color: white;
    background:
        linear-gradient(
            135deg,
            #0f4fd6 0%,
            #2563eb 52%,
            #3182f6 100%
        );
    border-radius: 0 0 50px 50px;
}

.hero::before {
    content: "";
    position: absolute;
    width: 420px;
    height: 420px;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
    top: -190px;
    right: -90px;
}

.hero::after {
    content: "";
    position: absolute;
    width: 320px;
    height: 320px;
    border-radius: 50%;
    background: rgba(255,255,255,.06);
    bottom: -190px;
    left: -90px;
}

.hero-content {
    position: relative;
    z-index: 2;
    max-width: 850px;
    margin: auto;
    text-align: center;
}

.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 20px;
    padding: 8px 15px;
    border-radius: 999px;
    background: rgba(255,255,255,.13);
    border: 1px solid rgba(255,255,255,.20);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: .3px;
    backdrop-filter: blur(8px);
}

.hero h1 {
    margin: 0;
    font-size: 54px;
    line-height: 1.1;
    font-weight: 850;
    letter-spacing: -1.4px;
}

.hero h1 span {
    color: #bfdbfe;
}

.hero-description {
    max-width: 650px;
    margin: 20px auto 0;
    color: #dbeafe;
    font-size: 16px;
    line-height: 1.75;
}

.staff-alert {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 28px;
    padding: 11px 18px;
    border-radius: 12px;
    background: rgba(15,23,42,.16);
    border: 1px solid rgba(255,255,255,.18);
    color: #ffffff;
    font-size: 12px;
    font-weight: 650;
    backdrop-filter: blur(8px);
}


/* =========================================================
   DEPARTMENT SECTION
========================================================= */

.department-section {
    padding: 75px 0 90px;
}

.section-heading {
    max-width: 680px;
    margin: 0 auto 45px;
    text-align: center;
}

.section-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;
    color: #2563eb;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .9px;
}

.section-title {
    margin: 0;
    color: #0f172a;
    font-size: 36px;
    font-weight: 800;
    letter-spacing: -0.7px;
}

.section-description {
    margin-top: 12px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.7;
}


/* =========================================================
   ROLE CARDS
========================================================= */

.role-card {
    position: relative;
    overflow: hidden;
    height: 100%;
    min-height: 260px;
    padding: 32px 24px;
    background: #ffffff;
    border: 1px solid #e7edf5;
    border-radius: 20px;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 8px 30px rgba(15,23,42,.05);
    transition:
        transform .22s ease,
        box-shadow .22s ease,
        border-color .22s ease;
}

.role-card:hover {
    transform: translateY(-7px);
    box-shadow: 0 18px 42px rgba(15,23,42,.10);
    border-color: #d7e3f3;
}

.role-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    opacity: 0;
    transition: .22s ease;
}

.role-card:hover::before {
    opacity: 1;
}

.role-card.admin::before {
    background: #2563eb;
}

.role-card.doctor::before {
    background: #059669;
}

.role-card.pharmacy::before {
    background: #f59e0b;
}

.role-card.nurse::before {
    background: #e11d48;
}


/* ICON */

.role-icon {
    width: 76px;
    height: 76px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 22px;
    border-radius: 20px;
    font-size: 31px;
}

.icon-admin {
    color: #2563eb;
    background: #eff6ff;
}

.icon-doctor {
    color: #059669;
    background: #ecfdf5;
}

.icon-pharmacy {
    color: #d97706;
    background: #fffbeb;
}

.icon-nurse {
    color: #e11d48;
    background: #fff1f2;
}


/* CONTENT */

.role-card h5 {
    margin: 0;
    color: #0f172a;
    font-size: 19px;
    font-weight: 800;
}

.role-card p {
    margin: 9px 0 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
}

.role-access {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    margin-top: 19px;
    color: #2563eb;
    font-size: 11px;
    font-weight: 750;
    opacity: 0;
    transform: translateY(5px);
    transition: .2s ease;
}

.role-card:hover .role-access {
    opacity: 1;
    transform: translateY(0);
}


/* =========================================================
   SECURITY NOTE
========================================================= */

.security-note {
    max-width: 780px;
    margin: 50px auto 0;
    padding: 18px 22px;
    display: flex;
    align-items: flex-start;
    gap: 13px;
    border-radius: 15px;
    border: 1px solid #e1e8f0;
    background: #ffffff;
    box-shadow: 0 5px 20px rgba(15,23,42,.035);
}

.security-note-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    background: #eff6ff;
    color: #2563eb;
    font-size: 16px;
}

.security-note strong {
    display: block;
    color: #334155;
    font-size: 12px;
    font-weight: 750;
}

.security-note span {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 11px;
    line-height: 1.6;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {
    padding: 25px 15px 35px;
    text-align: center;
    color: #94a3b8;
    font-size: 11px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 991px) {

    .hero {
        padding-top: 130px;
    }

    .hero h1 {
        font-size: 46px;
    }

    .role-card {
        min-height: 240px;
    }
}

@media (max-width: 767px) {

    .topbar {
        padding: 16px 0;
    }

    .brand {
        font-size: 19px;
    }

    .brand-icon {
        width: 37px;
        height: 37px;
    }

    .patient-link span {
        display: none;
    }

    .patient-link {
        padding: 9px 11px;
    }

    .hero {
        padding: 120px 20px 85px;
        border-radius: 0 0 32px 32px;
    }

    .hero h1 {
        font-size: 37px;
        letter-spacing: -.8px;
    }

    .hero-description {
        font-size: 14px;
    }

    .department-section {
        padding: 60px 0 70px;
    }

    .section-title {
        font-size: 30px;
    }

    .role-card {
        min-height: auto;
        padding: 28px 22px;
    }

    .role-access {
        opacity: 1;
        transform: none;
    }

    .security-note {
        margin-top: 35px;
    }
}

</style>

</head>

<body>


<!-- =========================================================
     TOP BAR
========================================================= -->

<div class="topbar">

    <div class="container">

        <div class="d-flex justify-content-between align-items-center">

            <a href="../index.php" class="brand">

                <span class="brand-icon">
                    <i class="bi bi-hospital"></i>
                </span>

                ZB-CARE

            </a>


            <a href="../index.php" class="patient-link">

                <i class="bi bi-arrow-left"></i>

                <span>
                    Patient Website
                </span>

            </a>

        </div>

    </div>

</div>


<!-- =========================================================
     HERO
========================================================= -->

<section class="hero">

    <div class="hero-content">

        <div class="hero-badge">

            <i class="bi bi-shield-check"></i>

            Secure Staff Access

        </div>


        <h1>

            ZB-CARE
            <span>Staff Portal</span>

        </h1>


        <p class="hero-description">

            Secure access portal for authorized healthcare personnel.
            Select your department below to continue to your staff account.

        </p>


        <div class="staff-alert">

            <i class="bi bi-lock-fill"></i>

            Authorized Hospital Personnel Only

        </div>

    </div>

</section>


<!-- =========================================================
     DEPARTMENTS
========================================================= -->

<section class="department-section">

    <div class="container">


        <div class="section-heading">

            <div class="section-label">

                <i class="bi bi-grid"></i>

                Staff Departments

            </div>


            <h2 class="section-title">

                Select Your Department

            </h2>


            <p class="section-description">

                Choose your assigned department to proceed to the secure
                ZB-CARE staff login page.

            </p>

        </div>


        <div class="row g-4 justify-content-center">


            <!-- ADMIN -->

            <div class="col-xl-3 col-md-6">

                <div
                    class="role-card admin"
                    onclick="go('admin')"
                >

                    <div class="role-icon icon-admin">

                        <i class="bi bi-person-gear"></i>

                    </div>


                    <h5>
                        Administrator
                    </h5>


                    <p>
                        Manage patients, appointments,
                        staff and hospital operations.
                    </p>


                    <div class="role-access">

                        Continue to Login

                        <i class="bi bi-arrow-right"></i>

                    </div>

                </div>

            </div>


            <!-- DOCTOR -->

            <div class="col-xl-3 col-md-6">

                <div
                    class="role-card doctor"
                    onclick="go('doctor')"
                >

                    <div class="role-icon icon-doctor">

                        <i class="bi bi-heart-pulse"></i>

                    </div>


                    <h5>
                        Doctor
                    </h5>


                    <p>
                        Access consultations, diagnoses,
                        treatments and prescriptions.
                    </p>


                    <div class="role-access">

                        Continue to Login

                        <i class="bi bi-arrow-right"></i>

                    </div>

                </div>

            </div>


            <!-- PHARMACY -->

            <div class="col-xl-3 col-md-6">

                <div
                    class="role-card pharmacy"
                    onclick="go('pharmacist')"
                >

                    <div class="role-icon icon-pharmacy">

                        <i class="bi bi-capsule-pill"></i>

                    </div>


                    <h5>
                        Pharmacy
                    </h5>


                    <p>
                        Manage medication orders,
                        preparation and inventory.
                    </p>


                    <div class="role-access">

                        Continue to Login

                        <i class="bi bi-arrow-right"></i>

                    </div>

                </div>

            </div>


            <!-- NURSE -->

            <div class="col-xl-3 col-md-6">

                <div
                    class="role-card nurse"
                    onclick="go('nurse')"
                >

                    <div class="role-icon icon-nurse">

                        <i class="bi bi-hospital"></i>

                    </div>


                    <h5>
                        Nurse
                    </h5>


                    <p>
                        Support admitted patients and
                        manage medication administration.
                    </p>


                    <div class="role-access">

                        Continue to Login

                        <i class="bi bi-arrow-right"></i>

                    </div>

                </div>

            </div>


        </div>


        <!-- SECURITY INFO -->

        <div class="security-note">

            <div class="security-note-icon">

                <i class="bi bi-shield-lock"></i>

            </div>


            <div>

                <strong>
                    Protected Staff Access
                </strong>

                <span>
                    This portal is intended for authorized ZB-CARE hospital staff only.
                    Access is controlled according to each staff member's assigned role.
                </span>

            </div>

        </div>


    </div>

</section>


<!-- =========================================================
     FOOTER
========================================================= -->

<div class="footer">

    © 2026 ZB-CARE Specialist Hospital.
    Authorized Staff Portal.

</div>


<script>

function go(role)
{
    window.location.href =
        "../auth/login.php?role=" + encodeURIComponent(role);
}

</script>

</body>

</html>