<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['user'];

$stmt = $conn->prepare("
SELECT *
FROM SYARMIMI.HOSPITAL_STAFF
WHERE USERNAME = :username
");

$stmt->execute([
    ':username' => $username
]);

$doctor = $stmt->fetch(PDO::FETCH_ASSOC);

/* =========================
   PROFILE IMAGE
========================= */

if (!empty($doctor['PROFILE_PICTURE'])) {

    $profileImage = "../" . $doctor['PROFILE_PICTURE'];

} else {

    if (strtolower($doctor['GENDER']) == 'female') {

        $profileImage = "../assets/images/female-doctor.png";

    } else {

        $profileImage = "../assets/images/male-doctor.png";

    }
}

/* =========================
   PROFILE PICTURE UPLOAD
========================= */

if (isset($_FILES['profile_picture'])) {

    $file = $_FILES['profile_picture'];

    if ($file['error'] == 0) {

        $extension = strtolower(
            pathinfo(
                $file['name'],
                PATHINFO_EXTENSION
            )
        );

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $allowed)) {

            $fileName =
                "doctor_" .
                $doctor['ACCOUNT_ID'] .
                "_" .
                time() .
                "." .
                $extension;

            $uploadPath =
                "../assets/images/" .
                $fileName;

            move_uploaded_file(
                $file['tmp_name'],
                $uploadPath
            );

            $savePath =
                "assets/images/" .
                $fileName;

            $updatePic = $conn->prepare("
                UPDATE SYARMIMI.HOSPITAL_STAFF
                SET PROFILE_PICTURE = :picture
                WHERE ACCOUNT_ID = :id
            ");

            $updatePic->execute([
                ':picture' => $savePath,
                ':id' => $doctor['ACCOUNT_ID']
            ]);

            header("Location: doctor_profile.php");
            exit();
        }
    }
}

$message = "";
$error = "";

/* =========================
   CHANGE PASSWORD
========================= */

if(isset($_POST['change_password'])){

    $currentPassword = trim($_POST['current_password']);
    $newPassword = trim($_POST['new_password']);
    $confirmPassword = trim($_POST['confirm_password']);

    if(empty($currentPassword) || empty($newPassword) || empty($confirmPassword)){

        $error = "All password fields are required.";

    }
    elseif($newPassword != $confirmPassword){

        $error = "New password and Confirm password do not match.";

    }
    else{

        $dbPassword = $doctor['PASSWORD'];

        $passwordCorrect =
            password_verify($currentPassword, $dbPassword)
            ||
            ($currentPassword == $dbPassword);

        if(!$passwordCorrect){

            $error = "Current password is incorrect.";

        }
        else{

            $hashedPassword = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $update = $conn->prepare("
            UPDATE SYARMIMI.HOSPITAL_STAFF
            SET PASSWORD = :password
            WHERE ACCOUNT_ID = :id
            ");

            $update->execute([
                ':password' => $hashedPassword,
                ':id' => $doctor['ACCOUNT_ID']
            ]);

            $message = "Password updated successfully.";

            $doctor['PASSWORD'] = $hashedPassword;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<title>My Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

.profile-card{
    border:none;
    border-radius:15px;
    box-shadow:0 10px 25px rgba(0,0,0,.05);
}

</style>

</head>

<body style="background:#f4f6f9;">

<div class="d-flex">

<?php include("../includes/sidebar_doctor.php"); ?>

<div class="flex-grow-1 p-4">

    <h2 class="mb-4">
        👤 My Profile
    </h2>

    <?php if($message): ?>
        <div class="alert alert-success">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="alert alert-danger">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- PROFILE INFO -->

    <div class="card profile-card mb-4">

        <div class="card-header">
            Profile Information
        </div>

        <div class="card-body">

         <div class="card-body">

    <div class="text-center mb-4">

        <form
            id="uploadForm"
            method="POST"
            enctype="multipart/form-data">

            <input
                type="file"
                id="profilePictureInput"
                name="profile_picture"
                accept="image/*"
                hidden>

            <img
                src="<?= $profileImage ?>"
                id="profilePicturePreview"
                width="150"
                height="150"
                style="
                    border-radius:50%;
                    object-fit:cover;
                    cursor:pointer;
                    border:5px solid #ddd;
                "
                title="Click to change profile picture">

        </form>

        <p class="text-muted mt-2">
            📸 Click image to change profile picture
        </p>

    </div>

        </div>


            <div class="row mb-3">

                <div class="col-md-6">
                    <label>Username</label>
                    <input type="text"
                           class="form-control"
                           value="<?= $doctor['USERNAME'] ?>"
                           readonly>
                </div>

                <div class="col-md-6">
                    <label>Department</label>
                    <input type="text"
                           class="form-control"
                           value="<?= $doctor['DEPARTMENT'] ?>"
                           readonly>
                </div>

            </div>

            <div class="row">

                <div class="col-md-6">
                    <label>Role</label>
                    <input type="text"
                           class="form-control"
                           value="<?= ucfirst($doctor['ROLE']) ?>"
                           readonly>
                </div>

                <div class="col-md-6">
                    <label>Gender</label>
                    <input type="text"
                           class="form-control"
                           value="<?= $doctor['GENDER'] ?>"
                           readonly>
                </div>

            </div>

    </div>

    <!-- CHANGE PASSWORD -->

    <div class="card profile-card">

        <div class="card-header">
            🔒 Change Password
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="mb-3">

                    <label>
                        Current Password
                    </label>

                    <input type="password"
                           name="current_password"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label>
                        New Password
                    </label>

                    <input type="password"
                           name="new_password"
                           class="form-control"
                           required>

                </div>

                <div class="mb-3">

                    <label>
                        Confirm Password
                    </label>

                    <input type="password"
                           name="confirm_password"
                           class="form-control"
                           required>

                </div>

                <button
                    type="submit"
                    name="change_password"
                    class="btn btn-primary">

                    Update Password

                </button>

            </form>

        </div>

    </div>

</div>

</div>

<script>

document
.getElementById('profilePicturePreview')
.addEventListener('click', function(){

    document
    .getElementById('profilePictureInput')
    .click();

});

document
.getElementById('profilePictureInput')
.addEventListener('change', function(){

    document
    .getElementById('uploadForm')
    .submit();

});

</script>

</body>
</html>