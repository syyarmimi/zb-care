<?php
session_start();
include("../config/config.php");

$username = trim($_POST['username']);
$password = trim($_POST['password']);

/* =========================
   FIND USER
========================= */

$stmt = $conn->prepare("
SELECT *
FROM SYARMIMI.HOSPITAL_STAFF
WHERE USERNAME = ?
");

$stmt->execute([$username]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   LOGIN CHECK
========================= */

if ($user) {

    // SUPPORT HASHED + OLD PASSWORD
    if (
        password_verify($password, $user['PASSWORD'])
        ||
        $password == $user['PASSWORD']
    ) {

        $_SESSION['user'] = $user['USERNAME'];
        $_SESSION['role'] = $user['ROLE'];
        $_SESSION['user_id'] = $user['ACCOUNT_ID'];

        /* =========================
           REDIRECT ROLE
        ========================= */

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

        exit();

    } else {

        $_SESSION['error'] = "Invalid password!";
        header("Location: login.php");
        exit();

    }

} else {

    $_SESSION['error'] = "User not found!";
    header("Location: login.php");
    exit();

}
?>