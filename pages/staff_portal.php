<?php
session_start();
include("../config/config.php");

/* =========================
   AUTO REDIRECT IF LOGGED IN
========================= */

if(isset($_SESSION['role']))
{
    switch($_SESSION['role'])
    {
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

<title>ZB-CARE Staff Portal</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>

body{
    background:#eef4fb;
    font-family:'Segoe UI', sans-serif;
}

/* HERO */

.hero{
    background:linear-gradient(135deg,#0d6efd,#2563eb);
    color:white;
    padding:80px 20px;
    border-radius:0 0 40px 40px;
}

.hero h1{
    font-size:50px;
    font-weight:700;
}

/* CARD */

.role-card{
    background:white;
    border-radius:25px;
    padding:35px 25px;
    text-align:center;
    transition:0.3s;
    cursor:pointer;
    height:100%;
    box-shadow:0 10px 20px rgba(0,0,0,0.05);
}

.role-card:hover{
    transform:translateY(-12px);
    box-shadow:0 15px 30px rgba(0,0,0,0.1);
}

.role-icon{
    font-size:50px;
    margin-bottom:20px;
}

.section-title{
    font-size:38px;
    font-weight:700;
    color:#0f172a;
}

.staff-alert{
    background:rgba(255,255,255,0.15);
    border:1px solid rgba(255,255,255,0.3);
    display:inline-block;
    padding:12px 25px;
    border-radius:30px;
    margin-top:20px;
    font-weight:500;
}

</style>

</head>

<body>

<!-- HERO -->

<div class="hero text-center">

    <h1>
        🏥 ZB-CARE Staff Portal
    </h1>

    <p class="mt-3">
        Select your department to continue
    </p>

    <div class="staff-alert">

        <i class="bi bi-shield-lock-fill"></i>

        Authorized Hospital Personnel Only

    </div>

</div>

<!-- ROLES -->

<div class="container py-5">

    <div class="text-center mb-5">

        <h2 class="section-title">
            Hospital Departments
        </h2>

        <p class="text-muted">
            Choose your department and proceed to login.
        </p>

    </div>

    <div class="row g-4 justify-content-center">

        <!-- ADMIN -->

        <div class="col-md-2">

            <div class="role-card" onclick="go('admin')">

                <div class="role-icon text-primary">
                    <i class="bi bi-person-gear"></i>
                </div>

                <h5>Admin</h5>

                <p class="text-muted">
                    System Control
                </p>

            </div>

        </div>

        <!-- DOCTOR -->

        <div class="col-md-2">

            <div class="role-card" onclick="go('doctor')">

                <div class="role-icon text-success">
                    <i class="bi bi-heart-pulse"></i>
                </div>

                <h5>Doctor</h5>

                <p class="text-muted">
                    Diagnosis & Treatment
                </p>

            </div>

        </div>

        <!-- PHARMACY -->

        <div class="col-md-2">

            <div class="role-card" onclick="go('pharmacist')">

                <div class="role-icon text-warning">
                    <i class="bi bi-capsule"></i>
                </div>

                <h5>Pharmacy</h5>

                <p class="text-muted">
                    Medication Services
                </p>

            </div>

        </div>

        <!-- NURSE -->

        <div class="col-md-2">

            <div class="role-card" onclick="go('nurse')">

                <div class="role-icon text-danger">
                    <i class="bi bi-hospital"></i>
                </div>

                <h5>Nurse</h5>

                <p class="text-muted">
                    Patient Care
                </p>

            </div>

        </div>

    </div>

</div>

<script>

function go(role)
{
    window.location.href =
    "../auth/login.php?role=" + role;
}

</script>

</body>
</html>