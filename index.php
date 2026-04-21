<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ZB-CARE Hospital System</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        /* Navbar */
        .navbar {
            background-color: #0d6efd;
        }

        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }

        /* Hero */
        .hero {
            background: linear-gradient(to right, #0d6efd, #4facfe);
            color: white;
            padding: 60px 20px;
            border-radius: 0 0 30px 30px;
        }

        /* Cards */
        .card-role {
            border: none;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            transition: 0.3s;
            background: white;
            box-shadow: 0px 4px 10px rgba(0,0,0,0.1);
            cursor: pointer;
        }

        .card-role:hover {
            transform: translateY(-8px);
            box-shadow: 0px 10px 25px rgba(0,0,0,0.15);
        }

        .icon {
            font-size: 35px;
            margin-bottom: 10px;
        }

        .section-title {
            margin-top: 40px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        footer {
            margin-top: 50px;
            text-align: center;
            color: gray;
        }
    </style>
</head>

<body>

<!-- NAVBAR -->
<nav class="navbar">
    <div class="container">
        <span class="navbar-brand">🏥 ZB-CARE Hospital System</span>
    </div>
</nav>

<!-- HERO -->
<div class="hero text-center">
    <h1>Hospital Diet & Medication Management</h1>
    <p>Manage patient care, medication, and meal distribution efficiently</p>
</div>

<!-- ROLE SELECTION -->
<div class="container">

    <h4 class="text-center section-title">Select Your Role</h4>

    <div class="row justify-content-center g-4">

        <!-- ADMIN -->
        <div class="col-md-2">
            <div class="card-role" onclick="go('admin')">
                <div class="icon text-primary"><i class="bi bi-person-gear"></i></div>
                <h6>Admin</h6>
                <small class="text-muted">System Control</small>
            </div>
        </div>

        <!-- DOCTOR -->
        <div class="col-md-2">
            <div class="card-role" onclick="go('doctor')">
                <div class="icon text-success"><i class="bi bi-heart-pulse"></i></div>
                <h6>Doctor</h6>
                <small class="text-muted">Diagnosis & Orders</small>
            </div>
        </div>

        <!-- PHARMACIST -->
        <div class="col-md-2">
            <div class="card-role" onclick="go('pharmacist')">
                <div class="icon text-warning"><i class="bi bi-capsule"></i></div>
                <h6>Pharmacist</h6>
                <small class="text-muted">Prepare Medication</small>
            </div>
        </div>

        <!-- NURSE -->
        <div class="col-md-2">
            <div class="card-role" onclick="go('nurse')">
                <div class="icon text-danger"><i class="bi bi-hospital"></i></div>
                <h6>Nurse</h6>
                <small class="text-muted">Deliver Care</small>
            </div>
        </div>

        <!-- KITCHEN -->
        <div class="col-md-2">
            <div class="card-role" onclick="go('kitchen')">
                <div class="icon text-secondary"><i class="bi bi-egg-fried"></i></div>
                <h6>Kitchen</h6>
                <small class="text-muted">Meal Preparation</small>
            </div>
        </div>

    </div>

</div>

<!-- FOOTER -->
<footer>
    <small>© 2026 ZB-CARE Hospital System</small>
</footer>

<!-- ✅ FIXED SCRIPT -->
<script>
function go(role){
    // 🔥 IMPORTANT FIX: use absolute path
    window.location.href = "../auth/login.php?role=" + role;
}
</script>

</body>
</html>