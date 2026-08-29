<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {

    header("Location: ../auth/login.php");
    exit();

}


/* =========================================================
   CURRENT DOCTOR
========================================================= */

$username =
    $_SESSION['user'] ?? '';


$stmt =
    $conn->prepare("

        SELECT *

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            USERNAME = :username

    ");


$stmt->execute([
    ':username' => $username
]);


$doctor =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );


if (!$doctor) {

    die("Doctor account not found.");

}



/* =========================================================
   PROFILE IMAGE
========================================================= */

if (
    !empty(
        $doctor['PROFILE_PICTURE']
    )
) {

    $profileImage =
        "../" .
        $doctor['PROFILE_PICTURE'];

}
else {

    if (
        strtolower(
            $doctor['GENDER']
            ?? ''
        )
        ===
        'female'
    ) {

        $profileImage =
            "../assets/images/female-doctor.png";

    }
    else {

        $profileImage =
            "../assets/images/male-doctor.png";

    }

}



/* =========================================================
   PROFILE PICTURE UPLOAD
========================================================= */

if (
    isset(
        $_FILES['profile_picture']
    )
) {

    $file =
        $_FILES['profile_picture'];


    if (
        $file['error'] === 0
    ) {

        $extension =
            strtolower(
                pathinfo(
                    $file['name'],
                    PATHINFO_EXTENSION
                )
            );


        $allowed =
            [
                'jpg',
                'jpeg',
                'png',
                'webp'
            ];


        if (
            in_array(
                $extension,
                $allowed,
                true
            )
        ) {

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


            if (
                move_uploaded_file(
                    $file['tmp_name'],
                    $uploadPath
                )
            ) {

                $savePath =
                    "assets/images/" .
                    $fileName;


                $updatePic =
                    $conn->prepare("

                        UPDATE
                            SYARMIMI.HOSPITAL_STAFF

                        SET
                            PROFILE_PICTURE = :picture

                        WHERE
                            ACCOUNT_ID = :id

                    ");


                $updatePic->execute([

                    ':picture' =>
                        $savePath,

                    ':id' =>
                        $doctor['ACCOUNT_ID']

                ]);


                header(
                    "Location: doctor_profile.php"
                );

                exit();

            }

        }

    }

}



/* =========================================================
   MESSAGE
========================================================= */

$message = '';
$error = '';



/* =========================================================
   CHANGE PASSWORD
========================================================= */

if (
    isset(
        $_POST['change_password']
    )
) {

    $currentPassword =
        trim(
            $_POST['current_password']
            ?? ''
        );


    $newPassword =
        trim(
            $_POST['new_password']
            ?? ''
        );


    $confirmPassword =
        trim(
            $_POST['confirm_password']
            ?? ''
        );


    if (
        empty($currentPassword)
        ||
        empty($newPassword)
        ||
        empty($confirmPassword)
    ) {

        $error =
            "All password fields are required.";

    }

    elseif (
        $newPassword !==
        $confirmPassword
    ) {

        $error =
            "New password and confirm password do not match.";

    }

    else {

        $dbPassword =
            $doctor['PASSWORD'];


        $passwordCorrect =

            password_verify(
                $currentPassword,
                $dbPassword
            )

            ||

            (
                $currentPassword
                ===
                $dbPassword
            );


        if (
            !$passwordCorrect
        ) {

            $error =
                "Current password is incorrect.";

        }
        else {

            $hashedPassword =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );


            $update =
                $conn->prepare("

                    UPDATE
                        SYARMIMI.HOSPITAL_STAFF

                    SET
                        PASSWORD = :password

                    WHERE
                        ACCOUNT_ID = :id

                ");


            $update->execute([

                ':password' =>
                    $hashedPassword,

                ':id' =>
                    $doctor['ACCOUNT_ID']

            ]);


            $message =
                "Password updated successfully.";


            $doctor['PASSWORD'] =
                $hashedPassword;

        }

    }

}


/* =========================================================
   DISPLAY NAME
========================================================= */

$displayName =
    trim(
        $doctor['USERNAME']
        ?? 'Doctor'
    );


if (
    stripos(
        $displayName,
        'Dr.'
    ) !== 0
) {

    $displayName =
        'Dr. ' .
        $displayName;

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

<title>
My Profile
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:#f5f7fa;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;

    color:#1f2937;
}


/* =========================================================
   CONTENT
========================================================= */

.content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:28px;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header{

    margin-bottom:24px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:26px;

    font-weight:700;
}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;
}


/* =========================================================
   ALERT
========================================================= */

.profile-alert{

    display:flex;

    align-items:center;

    gap:8px;

    padding:12px 14px;

    border-radius:9px;

    font-size:12px;
}


/* =========================================================
   PROFILE GRID
========================================================= */

.profile-layout{

    display:grid;

    grid-template-columns:
        330px
        minmax(0,1fr);

    gap:20px;

    align-items:start;
}


/* =========================================================
   CARD
========================================================= */

.profile-card{

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:14px;

    overflow:hidden;
}


.card-header-clean{

    padding:18px 20px;

    background:#fff;

    border-bottom:1px solid #eef1f4;
}


.card-title-clean{

    margin:0;

    color:#1f2937;

    font-size:15px;

    font-weight:650;
}


.card-subtitle-clean{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


.card-body-clean{

    padding:20px;
}


/* =========================================================
   PROFILE PANEL
========================================================= */

.profile-summary{

    text-align:center;
}


.profile-picture-wrapper{

    width:145px;

    height:145px;

    margin:4px auto 16px;

    position:relative;
}


.profile-picture{

    width:145px;

    height:145px;

    object-fit:cover;

    border-radius:50%;

    border:4px solid #fff;

    box-shadow:
        0 0 0 1px #e5e7eb,
        0 8px 20px rgba(15,23,42,.10);

    cursor:pointer;

    transition:.2s;
}


.profile-picture:hover{

    opacity:.92;

    transform:scale(1.01);
}


.profile-edit-badge{

    width:36px;

    height:36px;

    position:absolute;

    right:4px;

    bottom:5px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:3px solid #fff;

    border-radius:50%;

    background:#2563eb;

    color:#fff;

    font-size:14px;

    pointer-events:none;
}


.profile-name{

    margin-top:4px;

    color:#111827;

    font-size:18px;

    font-weight:700;
}


.profile-department{

    margin-top:3px;

    color:#64748b;

    font-size:12px;
}


.profile-role-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    margin-top:12px;

    padding:6px 9px;

    background:#eff6ff;

    border-radius:7px;

    color:#2563eb;

    font-size:11px;

    font-weight:650;
}


.upload-hint{

    margin-top:16px;

    color:#94a3b8;

    font-size:10px;

    line-height:1.5;
}


/* =========================================================
   INFO LIST
========================================================= */

.info-list{

    margin-top:20px;

    padding-top:18px;

    border-top:1px solid #eef1f4;
}


.info-item{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    padding:9px 0;

    border-bottom:1px solid #f1f3f5;
}


.info-item:last-child{

    border-bottom:0;
}


.info-label{

    color:#94a3b8;

    font-size:11px;
}


.info-value{

    color:#374151;

    font-size:12px;

    font-weight:600;

    text-align:right;
}


/* =========================================================
   FORM
========================================================= */

.form-label{

    margin-bottom:6px;

    color:#475569;

    font-size:11px;

    font-weight:600;
}


.form-control{

    min-height:43px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:13px;
}


.form-control:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


.form-control[readonly]{

    background:#f8fafc;

    border-color:#e5e7eb;

    color:#475569;

    cursor:default;
}


/* =========================================================
   INFO GRID
========================================================= */

.info-grid{

    display:grid;

    grid-template-columns:
        repeat(2,minmax(0,1fr));

    gap:16px;
}


/* =========================================================
   PASSWORD
========================================================= */

.password-section{

    margin-top:20px;
}


.password-grid{

    max-width:640px;
}


.password-input-wrapper{

    position:relative;
}


.password-input-wrapper .form-control{

    padding-right:42px;
}


.password-toggle{

    width:36px;

    height:36px;

    position:absolute;

    top:50%;

    right:4px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:0;

    background:transparent;

    color:#94a3b8;

    transform:translateY(-50%);

    cursor:pointer;
}


.password-toggle:hover{

    color:#475569;
}


.password-note{

    margin-top:3px;

    color:#94a3b8;

    font-size:10px;
}


/* =========================================================
   BUTTON
========================================================= */

.update-btn{

    min-height:42px;

    padding:0 16px;

    border:0;

    border-radius:8px;

    background:#2563eb;

    color:#fff;

    font-size:12px;

    font-weight:600;
}


.update-btn:hover{

    background:#1d4ed8;

    color:#fff;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1050px){

    .profile-layout{

        grid-template-columns:
            1fr;
    }

}


@media(max-width:768px){

    .content{

        padding:18px;
    }


    .info-grid{

        grid-template-columns:
            1fr;
    }


    .page-title{

        font-size:23px;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include(
    "../includes/sidebar_doctor.php"
);
?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<h1 class="page-title">

My Profile

</h1>


<div class="page-subtitle">

View your account information, update your profile picture and manage your password.

</div>


</div>



<!-- =====================================================
     MESSAGE
===================================================== -->

<?php if ($message): ?>


<div class="alert alert-success profile-alert">

<i class="bi bi-check-circle-fill"></i>

<?= htmlspecialchars($message) ?>

</div>


<?php endif; ?>



<?php if ($error): ?>


<div class="alert alert-danger profile-alert">

<i class="bi bi-exclamation-circle-fill"></i>

<?= htmlspecialchars($error) ?>

</div>


<?php endif; ?>



<!-- =====================================================
     PROFILE LAYOUT
===================================================== -->

<div class="profile-layout">


<!-- =================================================
     LEFT PROFILE SUMMARY
================================================= -->

<div class="profile-card">


<div class="card-body-clean profile-summary">


<form
    id="uploadForm"
    method="POST"
    enctype="multipart/form-data"
>


<input
    type="file"
    id="profilePictureInput"
    name="profile_picture"
    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
    hidden
>


<div class="profile-picture-wrapper">


<img
    src="<?= htmlspecialchars(
        $profileImage
    ) ?>"
    id="profilePicturePreview"
    class="profile-picture"
    alt="Doctor profile picture"
    title="Click to change profile picture"
>


<div class="profile-edit-badge">

<i class="bi bi-camera-fill"></i>

</div>


</div>


</form>



<div class="profile-name">

<?= htmlspecialchars(
    $displayName
) ?>

</div>


<div class="profile-department">

<?= htmlspecialchars(
    $doctor['DEPARTMENT']
    ?? 'Department not set'
) ?>

</div>


<div class="profile-role-badge">

<i class="bi bi-person-badge"></i>

<?= htmlspecialchars(
    ucfirst(
        $doctor['ROLE']
        ?? 'Doctor'
    )
) ?>

</div>


<div class="upload-hint">

Click the profile photo to upload a new image.<br>

Supported formats: JPG, JPEG, PNG and WEBP.

</div>



<div class="info-list">


<div class="info-item">

<div class="info-label">

Account ID

</div>

<div class="info-value">

#<?= htmlspecialchars(
    $doctor['ACCOUNT_ID']
    ?? '-'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">

Gender

</div>

<div class="info-value">

<?= htmlspecialchars(
    $doctor['GENDER']
    ?? '-'
) ?>

</div>

</div>


<div class="info-item">

<div class="info-label">

Department

</div>

<div class="info-value">

<?= htmlspecialchars(
    $doctor['DEPARTMENT']
    ?? '-'
) ?>

</div>

</div>


</div>


</div>


</div>



<!-- =================================================
     RIGHT CONTENT
================================================= -->

<div>


<!-- =================================================
     ACCOUNT INFORMATION
================================================= -->

<div class="profile-card">


<div class="card-header-clean">


<h5 class="card-title-clean">

Account Information

</h5>


<div class="card-subtitle-clean">

Your registered doctor account details.

</div>


</div>


<div class="card-body-clean">


<div class="info-grid">


<div>


<label class="form-label">

Username

</label>


<input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars(
        $doctor['USERNAME']
        ?? ''
    ) ?>"
    readonly
>


</div>



<div>


<label class="form-label">

Department

</label>


<input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars(
        $doctor['DEPARTMENT']
        ?? ''
    ) ?>"
    readonly
>


</div>



<div>


<label class="form-label">

Role

</label>


<input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars(
        ucfirst(
            $doctor['ROLE']
            ?? ''
        )
    ) ?>"
    readonly
>


</div>



<div>


<label class="form-label">

Gender

</label>


<input
    type="text"
    class="form-control"
    value="<?= htmlspecialchars(
        $doctor['GENDER']
        ?? ''
    ) ?>"
    readonly
>


</div>


</div>


</div>


</div>



<!-- =================================================
     CHANGE PASSWORD
================================================= -->

<div class="profile-card password-section">


<div class="card-header-clean">


<h5 class="card-title-clean">

Change Password

</h5>


<div class="card-subtitle-clean">

Update your account password securely.

</div>


</div>


<div class="card-body-clean">


<form
    method="POST"
    class="password-grid"
>


<div class="mb-3">


<label class="form-label">

Current Password

</label>


<div class="password-input-wrapper">


<input
    type="password"
    name="current_password"
    id="currentPassword"
    class="form-control"
    autocomplete="current-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="currentPassword"
>

<i class="bi bi-eye"></i>

</button>


</div>


</div>



<div class="mb-3">


<label class="form-label">

New Password

</label>


<div class="password-input-wrapper">


<input
    type="password"
    name="new_password"
    id="newPassword"
    class="form-control"
    autocomplete="new-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="newPassword"
>

<i class="bi bi-eye"></i>

</button>


</div>


<div class="password-note">

Choose a password that is different from your current password.

</div>


</div>



<div class="mb-4">


<label class="form-label">

Confirm New Password

</label>


<div class="password-input-wrapper">


<input
    type="password"
    name="confirm_password"
    id="confirmPassword"
    class="form-control"
    autocomplete="new-password"
    required
>


<button
    type="button"
    class="password-toggle"
    data-target="confirmPassword"
>

<i class="bi bi-eye"></i>

</button>


</div>


</div>



<button
    type="submit"
    name="change_password"
    class="update-btn"
>

<i class="bi bi-shield-lock me-1"></i>

Update Password

</button>


</form>


</div>


</div>


</div>


</div>


</div>


</div>



<script>

/* =========================================================
   PROFILE PICTURE
========================================================= */

const profilePicture =
    document.getElementById(
        'profilePicturePreview'
    );


const profileInput =
    document.getElementById(
        'profilePictureInput'
    );


const uploadForm =
    document.getElementById(
        'uploadForm'
    );


profilePicture.addEventListener(
    'click',
    function()
    {

        profileInput.click();

    }
);



profileInput.addEventListener(
    'change',
    function()
    {

        if (
            this.files
            &&
            this.files[0]
        ) {

            uploadForm.submit();

        }

    }
);


/* =========================================================
   SHOW / HIDE PASSWORD
========================================================= */

document
.querySelectorAll(
    '.password-toggle'
)
.forEach(
    function(button)
    {

        button.addEventListener(
            'click',
            function()
            {

                const targetId =
                    this.getAttribute(
                        'data-target'
                    );


                const input =
                    document.getElementById(
                        targetId
                    );


                const icon =
                    this.querySelector(
                        'i'
                    );


                if (
                    input.type
                    ===
                    'password'
                ) {

                    input.type =
                        'text';


                    icon.className =
                        'bi bi-eye-slash';

                }
                else {

                    input.type =
                        'password';


                    icon.className =
                        'bi bi-eye';

                }

            }
        );

    }
);

</script>


</body>

</html>