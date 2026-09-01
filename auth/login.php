<?php
session_start();

$role = $_GET['role'] ?? '';

/* =========================
   ROLE DISPLAY SETTINGS
========================= */

$roleName = 'Staff';
$roleIcon = 'bi-person-badge';
$roleClass = 'role-default';

switch ($role) {

    case 'admin':
        $roleName = 'Administrator';
        $roleIcon = 'bi-person-gear';
        $roleClass = 'role-admin';
        break;

    case 'doctor':
        $roleName = 'Doctor';
        $roleIcon = 'bi-heart-pulse';
        $roleClass = 'role-doctor';
        break;

    case 'pharmacist':
        $roleName = 'Pharmacy';
        $roleIcon = 'bi-capsule-pill';
        $roleClass = 'role-pharmacy';
        break;

    case 'nurse':
        $roleName = 'Nurse';
        $roleIcon = 'bi-hospital';
        $roleClass = 'role-nurse';
        break;
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

<title>ZB-CARE Staff Login</title>

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

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

html,
body{
    min-height:100%;
}

body{
    font-family:'Segoe UI', Arial, sans-serif;
    background:
        radial-gradient(circle at top left,#dbeafe 0%,transparent 35%),
        radial-gradient(circle at bottom right,#dcfce7 0%,transparent 30%),
        #f6f9fd;
    color:#0f172a;
}


/* =========================================================
   PAGE
========================================================= */

.login-page{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:40px 20px;
}


/* =========================================================
   LOGIN CONTAINER
========================================================= */

.login-wrapper{
    width:100%;
    max-width:980px;
    overflow:hidden;
    border:1px solid #e2e8f0;
    border-radius:26px;
    background:white;
    box-shadow:0 25px 60px rgba(15,23,42,.10);
}

.login-row{
    min-height:590px;
}


/* =========================================================
   LEFT SIDE
========================================================= */

.login-left{
    position:relative;
    overflow:hidden;
    height:100%;
    padding:60px;
    color:white;
    background:
        linear-gradient(
            145deg,
            #0f4fd6,
            #2563eb 55%,
            #3b82f6
        );
}

.login-left::before{
    content:'';
    position:absolute;
    width:330px;
    height:330px;
    top:-150px;
    right:-130px;
    border-radius:50%;
    background:rgba(255,255,255,.08);
}

.login-left::after{
    content:'';
    position:absolute;
    width:250px;
    height:250px;
    bottom:-130px;
    left:-100px;
    border-radius:50%;
    background:rgba(255,255,255,.07);
}

.left-content{
    position:relative;
    z-index:2;
}

.brand{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:70px;
    color:white;
    font-size:25px;
    font-weight:850;
}

.brand-icon{
    width:45px;
    height:45px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:13px;
    background:rgba(255,255,255,.15);
    border:1px solid rgba(255,255,255,.20);
    font-size:21px;
}

.login-left h1{
    max-width:400px;
    margin:0;
    font-size:42px;
    line-height:1.15;
    font-weight:850;
    letter-spacing:-1px;
}

.login-left p{
    max-width:430px;
    margin-top:20px;
    color:#dbeafe;
    font-size:15px;
    line-height:1.8;
}

.security-box{
    display:flex;
    align-items:center;
    gap:11px;
    margin-top:35px;
    padding:14px 16px;
    max-width:390px;
    border-radius:13px;
    border:1px solid rgba(255,255,255,.18);
    background:rgba(15,23,42,.14);
    backdrop-filter:blur(8px);
}

.security-icon{
    width:37px;
    height:37px;
    display:flex;
    align-items:center;
    justify-content:center;
    flex:0 0 37px;
    border-radius:10px;
    background:rgba(255,255,255,.13);
}

.security-box strong{
    display:block;
    font-size:12px;
}

.security-box span{
    display:block;
    margin-top:2px;
    color:#bfdbfe;
    font-size:10px;
}


/* =========================================================
   RIGHT SIDE
========================================================= */

.login-right{
    height:100%;
    display:flex;
    align-items:center;
    padding:55px;
    background:#ffffff;
}

.login-form{
    width:100%;
    max-width:390px;
    margin:auto;
}

.back-link{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-bottom:34px;
    color:#64748b;
    font-size:12px;
    font-weight:650;
    text-decoration:none;
    transition:.2s ease;
}

.back-link:hover{
    color:#2563eb;
}


/* =========================================================
   ROLE ICON
========================================================= */

.role-icon{
    width:60px;
    height:60px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin-bottom:20px;
    border-radius:16px;
    font-size:25px;
}

.role-admin{
    background:#eff6ff;
    color:#2563eb;
}

.role-doctor{
    background:#ecfdf5;
    color:#059669;
}

.role-pharmacy{
    background:#fffbeb;
    color:#d97706;
}

.role-nurse{
    background:#fff1f2;
    color:#e11d48;
}

.role-default{
    background:#f1f5f9;
    color:#475569;
}


/* =========================================================
   TEXT
========================================================= */

.login-title{
    margin:0;
    color:#0f172a;
    font-size:30px;
    font-weight:850;
    letter-spacing:-.5px;
}

.login-subtitle{
    margin:9px 0 0;
    color:#64748b;
    font-size:13px;
    line-height:1.6;
}

.role-badge{
    display:inline-flex;
    align-items:center;
    gap:6px;
    margin-top:16px;
    padding:7px 11px;
    border-radius:999px;
    background:#f1f5f9;
    color:#475569;
    font-size:10px;
    font-weight:750;
    text-transform:uppercase;
    letter-spacing:.4px;
}


/* =========================================================
   FORM
========================================================= */

.login-form form{
    margin-top:32px;
}

.form-label{
    margin-bottom:7px;
    color:#334155;
    font-size:12px;
    font-weight:700;
}

.input-group-custom{
    position:relative;
}

.input-icon{
    position:absolute;
    left:15px;
    top:50%;
    transform:translateY(-50%);
    color:#94a3b8;
    font-size:15px;
    z-index:3;
}

.form-control{
    height:50px;
    padding:12px 45px;
    border:1px solid #dbe3ec;
    border-radius:11px;
    background:#ffffff;
    color:#0f172a;
    font-size:13px;
    box-shadow:none !important;
    transition:.2s ease;
}

.form-control:focus{
    border-color:#60a5fa;
    box-shadow:0 0 0 4px rgba(37,99,235,.08) !important;
}

.form-control::placeholder{
    color:#b3bdca;
}

.password-toggle{
    position:absolute;
    right:14px;
    top:50%;
    transform:translateY(-50%);
    padding:3px;
    border:none;
    background:transparent;
    color:#94a3b8;
    font-size:16px;
    z-index:4;
}

.password-toggle:hover{
    color:#2563eb;
}


/* =========================================================
   LOGIN BUTTON
========================================================= */

.btn-login{
    width:100%;
    min-height:50px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    margin-top:26px;
    border:none;
    border-radius:11px;
    background:#2563eb;
    color:white;
    font-size:13px;
    font-weight:750;
    box-shadow:0 8px 20px rgba(37,99,235,.22);
    transition:.2s ease;
}

.btn-login:hover{
    background:#1d4ed8;
    color:white;
    transform:translateY(-1px);
    box-shadow:0 10px 24px rgba(37,99,235,.28);
}


/* =========================================================
   ERROR
========================================================= */

.login-error{
    display:flex;
    align-items:flex-start;
    gap:9px;
    margin-top:18px;
    padding:12px 14px;
    border:1px solid #fecaca;
    border-radius:10px;
    background:#fef2f2;
    color:#b91c1c;
    font-size:11px;
    line-height:1.5;
}

.login-error i{
    margin-top:1px;
    font-size:14px;
}


/* =========================================================
   FOOTER TEXT
========================================================= */

.login-note{
    margin-top:25px;
    text-align:center;
    color:#94a3b8;
    font-size:10px;
    line-height:1.6;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:991px){

    .login-wrapper{
        max-width:600px;
    }

    .login-left{
        padding:45px;
    }

    .login-left h1{
        font-size:36px;
    }

    .brand{
        margin-bottom:40px;
    }

    .login-right{
        padding:45px;
    }
}

@media(max-width:767px){

    .login-page{
        padding:18px;
        align-items:flex-start;
    }

    .login-wrapper{
        margin-top:15px;
        border-radius:20px;
    }

    .login-left{
        padding:35px 28px;
    }

    .brand{
        margin-bottom:30px;
        font-size:21px;
    }

    .brand-icon{
        width:39px;
        height:39px;
    }

    .login-left h1{
        font-size:30px;
    }

    .login-left p{
        font-size:13px;
    }

    .security-box{
        margin-top:25px;
    }

    .login-right{
        padding:38px 28px;
    }

    .login-title{
        font-size:27px;
    }
}

</style>

</head>

<body>


<div class="login-page">

    <div class="login-wrapper">

        <div class="row g-0 login-row">


            <!-- =================================================
                 LEFT
            ================================================== -->

            <div class="col-lg-6">

                <div class="login-left">

                    <div class="left-content">


                        <div class="brand">

                            <div class="brand-icon">

                                <i class="bi bi-hospital"></i>

                            </div>

                            ZB-CARE

                        </div>


                        <h1>

                            Secure Access for
                            Healthcare Staff

                        </h1>


                        <p>

                            Sign in to access your assigned ZB-CARE
                            hospital workspace and manage healthcare
                            operations securely.

                        </p>


                        <div class="security-box">

                            <div class="security-icon">

                                <i class="bi bi-shield-lock-fill"></i>

                            </div>


                            <div>

                                <strong>
                                    Authorized Personnel Only
                                </strong>

                                <span>
                                    Access is restricted according to staff role.
                                </span>

                            </div>

                        </div>


                    </div>

                </div>

            </div>


            <!-- =================================================
                 RIGHT
            ================================================== -->

            <div class="col-lg-6">

                <div class="login-right">

                    <div class="login-form">


                        <a
                            href="../pages/staff_portal.php"
                            class="back-link"
                        >

                            <i class="bi bi-arrow-left"></i>

                            Back to Staff Portal

                        </a>


                        <div class="role-icon <?= htmlspecialchars($roleClass) ?>">

                            <i class="bi <?= htmlspecialchars($roleIcon) ?>"></i>

                        </div>


                        <h2 class="login-title">

                            Welcome Back

                        </h2>


                        <p class="login-subtitle">

                            Enter your staff credentials to continue.

                        </p>


                        <div class="role-badge">

                            <i class="bi bi-person-badge"></i>

                            <?= htmlspecialchars($roleName) ?> Login

                        </div>


                        <form
                            action="process_login.php"
                            method="POST"
                        >


                            <!-- PASS ROLE -->

                            <input
                                type="hidden"
                                name="role"
                                value="<?= htmlspecialchars($role) ?>"
                            >


                            <!-- USERNAME -->

                            <div class="mb-3">

                                <label class="form-label">

                                    Username

                                </label>


                                <div class="input-group-custom">

                                    <i class="bi bi-person input-icon"></i>

                                    <input
                                        type="text"
                                        name="username"
                                        class="form-control"
                                        placeholder="Enter your username"
                                        autocomplete="username"
                                        required
                                    >

                                </div>

                            </div>


                            <!-- PASSWORD -->

                            <div class="mb-3">

                                <label class="form-label">

                                    Password

                                </label>


                                <div class="input-group-custom">

                                    <i class="bi bi-lock input-icon"></i>

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="form-control"
                                        placeholder="Enter your password"
                                        autocomplete="current-password"
                                        required
                                    >


                                    <button
                                        type="button"
                                        class="password-toggle"
                                        onclick="togglePassword()"
                                        aria-label="Show or hide password"
                                    >

                                        <i
                                            class="bi bi-eye"
                                            id="passwordIcon"
                                        ></i>

                                    </button>

                                </div>

                            </div>


                            <!-- LOGIN -->

                            <button
                                type="submit"
                                class="btn-login"
                            >

                                <i class="bi bi-box-arrow-in-right"></i>

                                Login to ZB-CARE

                            </button>


                            <!-- ERROR -->

                            <?php if (isset($_SESSION['error'])): ?>

                                <div class="login-error">

                                    <i class="bi bi-exclamation-circle-fill"></i>

                                    <span>

                                        <?= htmlspecialchars($_SESSION['error']) ?>

                                    </span>

                                </div>

                                <?php unset($_SESSION['error']); ?>

                            <?php endif; ?>


                        </form>


                        <div class="login-note">

                            ZB-CARE Specialist Hospital System<br>
                            Secure Staff Authentication Portal

                        </div>


                    </div>

                </div>

            </div>


        </div>

    </div>

</div>


<script>

/* =========================
   SHOW / HIDE PASSWORD
========================= */

function togglePassword()
{
    const password =
        document.getElementById("password");

    const icon =
        document.getElementById("passwordIcon");


    if(password.type === "password")
    {
        password.type = "text";

        icon.classList.remove("bi-eye");
        icon.classList.add("bi-eye-slash");
    }
    else
    {
        password.type = "password";

        icon.classList.remove("bi-eye-slash");
        icon.classList.add("bi-eye");
    }
}

</script>


</body>
</html>