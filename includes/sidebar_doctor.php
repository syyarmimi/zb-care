<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$current = basename($_SERVER['PHP_SELF']);
?>

<style>
.sidebar {
    width:260px;
    min-height:100vh;
    background:#1e3a8a;
    color:white;
}

.sidebar .nav-link {
    color:white;
    padding:10px;
    margin:5px 0;
    border-radius:8px;
    display:block;
    text-decoration:none;
}

.sidebar .nav-link:hover {
    background:#2563eb;
}

.active {
    background:#2563eb;
}

.logout {
    color:yellow;
}
</style>

<div class="sidebar">

<div class="p-3">

    <div class="text-center mb-4">
        <img src="https://cdn-icons-png.flaticon.com/512/387/387561.png" width="70">
        <h6 class="mt-2">Dr. <?= $_SESSION['username'] ?? 'Doctor' ?></h6>
        <small>Doctor</small>
    </div>

    <a href="doctor_dashboard.php"
       class="nav-link <?= ($current == 'doctor_dashboard.php') ? 'active' : '' ?>">
        🏠 Dashboard
    </a>

    <a href="treatment.php"
       class="nav-link <?= ($current == 'treatment.php') ? 'active' : '' ?>">
        🧠 Treatment
    </a>
    
    </a>

    <a href="../auth/logout.php" class="nav-link logout mt-3">
        🚪 Logout
    </a>

</div>

</div>