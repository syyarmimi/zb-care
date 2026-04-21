<?php
session_start();
include("../config/config.php");

$username = $_POST['username'];
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT * FROM hospital_staff WHERE username = ? AND password = ?");
$stmt->execute([$username, $password]);

$user = $stmt->fetch();

if ($user) {

    $_SESSION['user'] = $user['USERNAME'];
    $_SESSION['role'] = $user['ROLE'];

    // ✅ 🔥 ADD THIS LINE (IMPORTANT FIX)
    $_SESSION['user_id'] = $user['ACCOUNT_ID'];

    // 🔥 REDIRECT BASED ON ROLE
    if ($user['ROLE'] == 'admin') {
        header("Location: ../pages/admin_dashboard.php");
    }
    elseif ($user['ROLE'] == 'doctor') {
        header("Location: ../pages/doctor_dashboard.php");
    }
    elseif ($user['ROLE'] == 'nurse') {
        header("Location: ../pages/nurse_dashboard.php");
    }
    elseif ($user['ROLE'] == 'pharmacist') {
        header("Location: ../pages/pharmacist_dashboard.php");
    }
     elseif ($user['ROLE'] == 'kitchen') {
        header("Location: ../pages/kitchen_dashboard.php");
    }
    else {
        echo "Role not recognized";
    }

} else {
    echo "Login failed!";
}
?>