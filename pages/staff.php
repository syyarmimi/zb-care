<?php

session_start();

include("../config/config.php");


/* =========================================================
   SESSION CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   SAFE OUTPUT
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   ADD STAFF
========================================================= */

if (isset($_POST['add'])) {

    $username =
        trim(
            $_POST['username']
            ?? ''
        );

    $plainPassword =
        trim(
            $_POST['password']
            ?? ''
        );

    $role =
        trim(
            $_POST['role']
            ?? ''
        );

    $department =
        trim(
            $_POST['department']
            ?? ''
        );


    if ($role !== 'doctor') {

        $department = null;
    }


    try {

        /* =================================================
           DUPLICATE USERNAME CHECK
        ================================================= */

        $checkStmt =
            $conn->prepare("

                SELECT COUNT(*)

                FROM
                    SYARMIMI.HOSPITAL_STAFF

                WHERE
                    LOWER(
                        TRIM(
                            USERNAME
                        )
                    )
                    =
                    LOWER(
                        TRIM(
                            ?
                        )
                    )

            ");


        $checkStmt->execute([
            $username
        ]);


        if (
            (int)$checkStmt
                ->fetchColumn()
            > 0
        ) {

            $_SESSION['staff_swal'] = [

                'icon' =>
                    'warning',

                'title' =>
                    'Username Already Exists',

                'text' =>
                    'Please use a different username.'

            ];


            header(
                "Location: staff.php"
            );

            exit();
        }


        /* =================================================
           PASSWORD
        ================================================= */

        $password =
            password_hash(
                $plainPassword,
                PASSWORD_DEFAULT
            );


        /* =================================================
           INSERT
        ================================================= */

        $sql = "

            INSERT INTO
                SYARMIMI.HOSPITAL_STAFF
            (
                USERNAME,
                PASSWORD,
                ROLE,
                DEPARTMENT
            )

            VALUES
            (
                ?,
                ?,
                ?,
                ?
            )

        ";


        $stmt =
            $conn->prepare(
                $sql
            );


        $stmt->execute([
            $username,
            $password,
            $role,
            $department
        ]);


        $_SESSION['staff_swal'] = [

            'icon' =>
                'success',

            'title' =>
                'Staff Added',

            'text' =>
                'New staff account has been created successfully.'

        ];


        header(
            "Location: staff.php"
        );

        exit();


    }
    catch (PDOException $e) {

        $_SESSION['staff_swal'] = [

            'icon' =>
                'error',

            'title' =>
                'Unable to Add Staff',

            'text' =>
                'The staff account could not be created.'

        ];


        header(
            "Location: staff.php"
        );

        exit();
    }
}


/* =========================================================
   DELETE STAFF
========================================================= */

if (
    isset(
        $_POST[
            'delete_staff'
        ]
    )
) {

    $id =
        (int)(
            $_POST[
                'delete_staff'
            ]
            ?? 0
        );


    try {

        /* =================================================
           CANNOT DELETE CURRENT ADMIN ACCOUNT
        ================================================= */

        if (
            isset(
                $_SESSION['user_id']
            )
            &&
            (int)$_SESSION['user_id']
            ===
            $id
        ) {

            $_SESSION['staff_swal'] = [

                'icon' =>
                    'warning',

                'title' =>
                    'Action Not Allowed',

                'text' =>
                    'You cannot delete your own active account.'

            ];


            header(
                "Location: staff.php"
            );

            exit();
        }


        $sql = "

            DELETE FROM
                SYARMIMI.HOSPITAL_STAFF

            WHERE
                ACCOUNT_ID = ?

        ";


        $stmt =
            $conn->prepare(
                $sql
            );


        $stmt->execute([
            $id
        ]);


        $_SESSION['staff_swal'] = [

            'icon' =>
                'success',

            'title' =>
                'Staff Deleted',

            'text' =>
                'The staff account has been removed successfully.'

        ];


        header(
            "Location: staff.php"
        );

        exit();


    }
    catch (PDOException $e) {

        $_SESSION['staff_swal'] = [

            'icon' =>
                'error',

            'title' =>
                'Unable to Delete Staff',

            'text' =>
                'This staff account may still be linked to hospital records.'

        ];


        header(
            "Location: staff.php"
        );

        exit();
    }
}


/* =========================================================
   SUMMARY COUNTS
========================================================= */

$totalStaff =
    (int)$conn->query("

        SELECT COUNT(*)
        FROM SYARMIMI.HOSPITAL_STAFF

    ")->fetchColumn();


$totalDoctors =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            LOWER(
                TRIM(
                    ROLE
                )
            )
            =
            'doctor'

    ")->fetchColumn();


$totalNurses =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            LOWER(
                TRIM(
                    ROLE
                )
            )
            =
            'nurse'

    ")->fetchColumn();


$totalPharmacists =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            LOWER(
                TRIM(
                    ROLE
                )
            )
            =
            'pharmacist'

    ")->fetchColumn();


/* =========================================================
   FETCH STAFF
========================================================= */

$sql = "

    SELECT
        ACCOUNT_ID,
        USERNAME,
        ROLE,
        DEPARTMENT,
        GENDER

    FROM
        SYARMIMI.HOSPITAL_STAFF

    ORDER BY
        ACCOUNT_ID DESC

";


$stmt =
    $conn->query(
        $sql
    );


$staffList =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

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
Manage Staff
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


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

    color:#1f2937;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;
}


.main-content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:30px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

    margin-bottom:24px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:30px;

    font-weight:750;
}


.page-subtitle{

    margin-top:6px;

    color:#64748b;

    font-size:14px;
}


.header-badge{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:8px;

    color:#2563eb;

    font-size:12px;

    font-weight:650;
}


/* =========================================================
   SUMMARY
========================================================= */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:22px;
}


.summary-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:14px;

    padding:18px 19px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:13px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.035);
}


.summary-label{

    color:#64748b;

    font-size:12px;

    font-weight:600;
}


.summary-number{

    margin-top:5px;

    color:#111827;

    font-size:28px;

    font-weight:750;

    line-height:1;
}


.summary-icon{

    width:44px;

    height:44px;

    min-width:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    font-size:18px;
}


.icon-total{

    background:#eff6ff;

    color:#2563eb;
}


.icon-doctor{

    background:#f5f3ff;

    color:#7c3aed;
}


.icon-nurse{

    background:#ecfdf5;

    color:#15803d;
}


.icon-pharmacy{

    background:#fff7ed;

    color:#d97706;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{

    margin-bottom:20px;

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.card-heading{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:19px;
}


.card-heading-left{

    display:flex;

    align-items:center;

    gap:11px;
}


.card-icon{

    width:39px;

    height:39px;

    min-width:39px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:16px;
}


.icon-add{

    background:#ecfdf5;

    color:#15803d;
}


.icon-list{

    background:#eff6ff;

    color:#2563eb;
}


.card-title-clean{

    margin:0;

    color:#1f2937;

    font-size:18px;

    font-weight:700;
}


.card-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:12px;
}


/* =========================================================
   FORM
========================================================= */

.form-label{

    margin-bottom:7px;

    color:#475569;

    font-size:12px;

    font-weight:650;
}


.form-control,
.form-select{

    min-height:45px;

    border:1px solid #dfe3e8;

    border-radius:9px;

    color:#374151;

    font-size:13px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


.password-wrapper{

    position:relative;
}


.password-wrapper input{

    padding-right:43px;
}


.password-toggle{

    position:absolute;

    top:50%;

    right:13px;

    transform:translateY(-50%);

    border:0;

    background:transparent;

    color:#94a3b8;

    padding:0;
}


.password-toggle:hover{

    color:#2563eb;
}


/* =========================================================
   ADD BUTTON
========================================================= */

.btn-add-staff{

    min-height:45px;

    padding:0 22px;

    background:#2563eb;

    border:1px solid #2563eb;

    border-radius:9px;

    color:#fff;

    font-size:12px;

    font-weight:650;
}


.btn-add-staff:hover{

    background:#1d4ed8;

    border-color:#1d4ed8;

    color:#fff;
}


/* =========================================================
   DEPARTMENT DISABLED
========================================================= */

.department-disabled{

    background:#f8fafc !important;

    color:#94a3b8 !important;

    cursor:not-allowed;
}


/* =========================================================
   FILTER
========================================================= */

.filter-area{

    margin-bottom:18px;

    padding:15px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:10px;
}


.filter-label{

    margin-bottom:7px;

    color:#64748b;

    font-size:10px;

    font-weight:700;

    letter-spacing:.3px;

    text-transform:uppercase;
}


.search-wrapper{

    position:relative;
}


.search-wrapper i{

    position:absolute;

    top:50%;

    left:13px;

    z-index:2;

    color:#94a3b8;

    transform:translateY(-50%);
}


.search-wrapper input{

    padding-left:39px;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{

    overflow-x:auto;

    border:1px solid #edf0f3;

    border-radius:10px;
}


.table{

    width:100% !important;

    margin-bottom:0 !important;

    vertical-align:middle;
}


.table thead th{

    padding:12px 10px !important;

    background:#f8fafc !important;

    border-bottom:
        1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:700;

    letter-spacing:.3px;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:13px 10px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.number-cell{

    width:50px;

    text-align:center;

    color:#64748b;

    font-weight:650;
}


.staff-user{

    display:flex;

    align-items:center;

    gap:10px;
}


.staff-avatar{

    width:35px;

    height:35px;

    min-width:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f1f5f9;

    border-radius:9px;

    color:#64748b;

    font-size:14px;
}


.staff-name{

    color:#111827;

    font-size:13px;

    font-weight:650;
}


.staff-subtext{

    margin-top:2px;

    color:#94a3b8;

    font-size:9px;
}


/* =========================================================
   ROLE BADGES
========================================================= */

.role-badge{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:6px 8px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;

    white-space:nowrap;
}


.role-admin{

    background:#fff1f2;

    color:#be123c;
}


.role-doctor{

    background:#eff6ff;

    color:#2563eb;
}


.role-nurse{

    background:#ecfdf5;

    color:#15803d;
}


.role-pharmacist{

    background:#fff7ed;

    color:#c2410c;
}


.role-kitchen{

    background:#f5f3ff;

    color:#7c3aed;
}


/* =========================================================
   DEPARTMENT
========================================================= */

.department-badge{

    display:inline-flex;

    padding:6px 8px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:6px;

    color:#475569;

    font-size:10px;

    font-weight:600;
}


/* =========================================================
   DELETE
========================================================= */

.btn-delete{

    padding:6px 9px;

    background:#fff;

    border:1px solid #fecaca;

    border-radius:7px;

    color:#dc2626;

    font-size:10px;

    font-weight:650;
}


.btn-delete:hover{

    background:#dc2626;

    border-color:#dc2626;

    color:#fff;
}


.btn-delete:disabled{

    background:#f3f4f6;

    border-color:#e5e7eb;

    color:#9ca3af;

    opacity:1;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_filter{

    display:none !important;
}


.dataTables_wrapper
.dataTables_length{

    margin-top:14px;

    color:#64748b;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_length select{

    min-height:32px;

    padding:4px 25px 4px 8px;

    border:1px solid #dfe3e8;

    border-radius:6px;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_info{

    padding-top:17px !important;

    color:#94a3b8 !important;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_paginate{

    padding-top:11px !important;
}


.page-link{

    min-width:32px;

    height:32px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:6px !important;

    font-size:11px;
}


/* =========================================================
   RESULT INFO
========================================================= */

.result-info{

    display:flex;

    align-items:center;

    gap:6px;

    margin-top:12px;

    color:#94a3b8;

    font-size:10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px){

    .summary-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

}


@media(max-width:768px){

    .main-content{

        padding:18px;
    }


    .summary-grid{

        grid-template-columns:1fr;
    }


    .page-title{

        font-size:24px;
    }


    .page-header{

        flex-direction:column;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include("../includes/sidebar_admin.php");
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Staff Management

</h1>


<div class="page-subtitle">

Create staff accounts and manage hospital system access.

</div>


</div>


<div class="header-badge">

<i class="bi bi-people"></i>

<?= $totalStaff ?>

Staff Accounts

</div>


</div>



<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


<div class="summary-card">


<div>

<div class="summary-label">

Total Staff

</div>


<div class="summary-number">

<?= $totalStaff ?>

</div>


</div>


<div class="summary-icon icon-total">

<i class="bi bi-people"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

Doctors

</div>


<div class="summary-number">

<?= $totalDoctors ?>

</div>


</div>


<div class="summary-icon icon-doctor">

<i class="bi bi-person-badge"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

Nurses

</div>


<div class="summary-number">

<?= $totalNurses ?>

</div>


</div>


<div class="summary-icon icon-nurse">

<i class="bi bi-person-heart"></i>

</div>


</div>



<div class="summary-card">


<div>

<div class="summary-label">

Pharmacists

</div>


<div class="summary-number">

<?= $totalPharmacists ?>

</div>


</div>


<div class="summary-icon icon-pharmacy">

<i class="bi bi-capsule"></i>

</div>


</div>


</div>



<!-- =====================================================
     ADD STAFF
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-add">

<i class="bi bi-person-plus"></i>

</div>


<div>


<h5 class="card-title-clean">

Add New Staff

</h5>


<div class="card-subtitle">

Create a new hospital staff login account.

</div>


</div>


</div>


</div>


<form
    method="POST"
    id="staffForm"
>


<div class="row g-3">


<div class="col-lg-3 col-md-6">


<label class="form-label">

Username

</label>


<input
    type="text"
    name="username"
    class="form-control"
    placeholder="Enter username"
    required
>


</div>



<div class="col-lg-3 col-md-6">


<label class="form-label">

Password

</label>


<div class="password-wrapper">


<input
    type="password"
    name="password"
    id="staffPassword"
    class="form-control"
    placeholder="Enter password"
    required
>


<button
    type="button"
    class="password-toggle"
    id="togglePassword"
>

<i
    class="bi bi-eye"
    id="passwordIcon"
></i>

</button>


</div>


</div>



<div class="col-lg-3 col-md-6">


<label class="form-label">

Role

</label>


<select
    name="role"
    id="role"
    class="form-select"
    required
>


<option value="">

Select Role

</option>


<option value="admin">

Admin

</option>


<option value="doctor">

Doctor

</option>


<option value="nurse">

Nurse

</option>


<option value="pharmacist">

Pharmacist

</option>


<option value="kitchen">

Kitchen

</option>


</select>


</div>



<div class="col-lg-3 col-md-6">


<label class="form-label">

Department

</label>


<select
    name="department"
    id="department"
    class="form-select department-disabled"
    disabled
>


<option value="">

Select Department

</option>


<option value="Orthopaedics">

Orthopaedics

</option>


<option value="Paediatrics">

Paediatrics

</option>


<option value="Dietitian & Nutrition">

Dietitian & Nutrition

</option>


</select>


</div>



<div class="col-12">


<button
    type="submit"
    name="add"
    class="btn btn-add-staff"
>

<i class="bi bi-plus-circle me-1"></i>

Add Staff

</button>


</div>


</div>


</form>


</div>



<!-- =====================================================
     STAFF LIST
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-list">

<i class="bi bi-person-lines-fill"></i>

</div>


<div>


<h5 class="card-title-clean">

Staff Accounts

</h5>


<div class="card-subtitle">

Search, filter and manage registered staff accounts.

</div>


</div>


</div>


</div>



<!-- =================================================
     FILTER
================================================= -->

<div class="filter-area">


<div class="row g-2">


<div class="col-lg-5">


<div class="filter-label">

Search

</div>


<div class="search-wrapper">


<i class="bi bi-search"></i>


<input
    type="text"
    id="searchBox"
    class="form-control"
    placeholder="Search username, role or department..."
>


</div>


</div>



<div class="col-lg-4">


<div class="filter-label">

Role

</div>


<select
    id="roleFilter"
    class="form-select"
>


<option value="">

All Roles

</option>


<option value="admin">

Admin

</option>


<option value="doctor">

Doctor

</option>


<option value="nurse">

Nurse

</option>


<option value="pharmacist">

Pharmacist

</option>


<option value="kitchen">

Kitchen

</option>


</select>


</div>



<div class="col-lg-3">


<div class="filter-label">

Sort

</div>


<select
    id="sortFilter"
    class="form-select"
>


<option value="desc">

Newest First

</option>


<option value="asc">

Oldest First

</option>


</select>


</div>


</div>


<div class="result-info">

<i class="bi bi-info-circle"></i>

<span id="resultCount">

Showing all staff accounts

</span>

</div>


</div>



<!-- =================================================
     TABLE
================================================= -->

<div class="table-responsive">


<table
    id="staffTable"
    class="table"
>


<thead>


<tr>

<th>No.</th>

<th>Staff</th>

<th>Role</th>

<th>Department</th>

<th>Action</th>

<th>Account ID</th>

<th>Role Filter</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $staffList
    as
    $row
): ?>


<?php

$staffRole =
    strtolower(
        trim(
            $row[
                'ROLE'
            ]
            ?? ''
        )
    );


$username =
    trim(
        $row[
            'USERNAME'
        ]
        ?? ''
    );


if (
    $staffRole ===
    'doctor'
    &&
    stripos(
        $username,
        'Dr.'
    )
    !==
    0
) {

    $displayName =
        'Dr. ' .
        $username;

} else {

    $displayName =
        $username;
}


$isCurrentUser =
    isset(
        $_SESSION[
            'user_id'
        ]
    )
    &&
    (int)$_SESSION[
        'user_id'
    ]
    ===
    (int)$row[
        'ACCOUNT_ID'
    ];

?>


<tr>


<!-- NUMBER -->

<td class="number-cell"></td>



<!-- STAFF -->

<td>


<div class="staff-user">


<div class="staff-avatar">


<?php if (
    $staffRole ===
    'doctor'
): ?>

<i class="bi bi-person-badge"></i>

<?php elseif (
    $staffRole ===
    'nurse'
): ?>

<i class="bi bi-person-heart"></i>

<?php elseif (
    $staffRole ===
    'pharmacist'
): ?>

<i class="bi bi-capsule"></i>

<?php elseif (
    $staffRole ===
    'admin'
): ?>

<i class="bi bi-shield-check"></i>

<?php else: ?>

<i class="bi bi-person"></i>

<?php endif; ?>


</div>


<div>


<div class="staff-name">

<?= h(
    $displayName
) ?>

</div>


<div class="staff-subtext">

Account #<?= h(
    $row[
        'ACCOUNT_ID'
    ]
) ?>

</div>


</div>


</div>


</td>



<!-- ROLE -->

<td>


<?php if (
    $staffRole ===
    'admin'
): ?>


<span class="role-badge role-admin">

<i class="bi bi-shield-check"></i>

ADMIN

</span>


<?php elseif (
    $staffRole ===
    'doctor'
): ?>


<span class="role-badge role-doctor">

<i class="bi bi-person-badge"></i>

DOCTOR

</span>


<?php elseif (
    $staffRole ===
    'nurse'
): ?>


<span class="role-badge role-nurse">

<i class="bi bi-person-heart"></i>

NURSE

</span>


<?php elseif (
    $staffRole ===
    'pharmacist'
): ?>


<span class="role-badge role-pharmacist">

<i class="bi bi-capsule"></i>

PHARMACIST

</span>


<?php else: ?>


<span class="role-badge role-kitchen">

<i class="bi bi-cup-hot"></i>

KITCHEN

</span>


<?php endif; ?>


</td>



<!-- DEPARTMENT -->

<td>


<?php if (
    !empty(
        $row[
            'DEPARTMENT'
        ]
    )
): ?>


<span class="department-badge">

<?= h(
    $row[
        'DEPARTMENT'
    ]
) ?>

</span>


<?php else: ?>


<span class="text-muted">

—

</span>


<?php endif; ?>


</td>



<!-- ACTION -->

<td>


<form
    method="POST"
    class="deleteStaffForm"
>


<input
    type="hidden"
    name="delete_staff"
    value="<?= h(
        $row[
            'ACCOUNT_ID'
        ]
    ) ?>"
>


<button
    type="submit"
    class="btn-delete"
    data-name="<?= h(
        $displayName
    ) ?>"
    <?= $isCurrentUser
        ?
        'disabled'
        :
        ''
    ?>
>


<i class="bi bi-trash me-1"></i>


<?= $isCurrentUser
    ?
    'Current Account'
    :
    'Delete'
?>


</button>


</form>


</td>



<!-- HIDDEN ACCOUNT ID -->

<td>

<?= h(
    $row[
        'ACCOUNT_ID'
    ]
) ?>

</td>



<!-- HIDDEN ROLE -->

<td>

<?= h(
    $staffRole
) ?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>



<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
></script>


<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
></script>


<script>

/* =========================================================
   ROLE / DEPARTMENT
========================================================= */

const roleSelect =
    document.getElementById(
        'role'
    );


const departmentSelect =
    document.getElementById(
        'department'
    );


function updateDepartmentState()
{

    if (
        roleSelect.value ===
        'doctor'
    ) {

        departmentSelect.disabled =
            false;


        departmentSelect.required =
            true;


        departmentSelect.classList.remove(
            'department-disabled'
        );

    }
    else {

        departmentSelect.value =
            '';


        departmentSelect.disabled =
            true;


        departmentSelect.required =
            false;


        departmentSelect.classList.add(
            'department-disabled'
        );

    }

}


roleSelect.addEventListener(
    'change',
    updateDepartmentState
);


updateDepartmentState();



/* =========================================================
   PASSWORD VISIBILITY
========================================================= */

document
.getElementById(
    'togglePassword'
)
.addEventListener(
    'click',
    function()
    {

        const input =
            document.getElementById(
                'staffPassword'
            );


        const icon =
            document.getElementById(
                'passwordIcon'
            );


        if (
            input.type ===
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



/* =========================================================
   DATATABLE
========================================================= */

$(document).ready(
function()
{

    const table =
        $('#staffTable')
        .DataTable({

            pageLength:
                10,

            lengthMenu:
            [
                [10,25,50,100],
                [10,25,50,100]
            ],

            /*
             Hidden Account ID = 5
            */

            order:
            [
                [5,'desc']
            ],

            dom:
                'lrtip',

            columnDefs:
            [

                {
                    targets:
                        0,

                    orderable:
                        false,

                    searchable:
                        false
                },


                {
                    targets:
                        4,

                    orderable:
                        false,

                    searchable:
                        false
                },


                {
                    targets:
                        5,

                    visible:
                        false,

                    searchable:
                        false
                },


                {
                    targets:
                        6,

                    visible:
                        false,

                    searchable:
                        true
                }

            ],


            drawCallback:
            function()
            {

                const api =
                    this.api();


                const info =
                    api.page.info();


                api
                    .column(
                        0,
                        {
                            page:
                                'current',

                            search:
                                'applied',

                            order:
                                'applied'
                        }
                    )
                    .nodes()
                    .each(
                        function(
                            cell,
                            index
                        )
                        {

                            cell.innerHTML =
                                info.start
                                +
                                index
                                +
                                1;

                        }
                    );


                const total =
                    api.rows().count();


                const filtered =
                    api.rows({
                        search:
                            'applied'
                    }).count();


                const search =
                    $('#searchBox')
                    .val()
                    .trim();


                const role =
                    $('#roleFilter')
                    .val();


                if (
                    search === ''
                    &&
                    role === ''
                ) {

                    $('#resultCount')
                    .text(
                        'Showing all '
                        +
                        total
                        +
                        ' staff account(s)'
                    );

                }
                else {

                    let text =
                        'Showing '
                        +
                        filtered
                        +
                        ' matching staff account(s)';


                    if (
                        role !== ''
                    ) {

                        text +=
                            ' • Role: '
                            +
                            role
                            .charAt(0)
                            .toUpperCase()
                            +
                            role.slice(1);

                    }


                    if (
                        search !== ''
                    ) {

                        text +=
                            ' • Search: "'
                            +
                            search
                            +
                            '"';

                    }


                    $('#resultCount')
                    .text(
                        text
                    );

                }

            }

        });



    /* =====================================================
       SEARCH
    ===================================================== */

    $('#searchBox').on(
        'input',
        function()
        {

            table
                .search(
                    this.value
                )
                .draw();

        }
    );



    /* =====================================================
       ROLE FILTER
    ===================================================== */

    $('#roleFilter').on(
        'change',
        function()
        {

            const selectedRole =
                this.value;


            if (
                selectedRole ===
                ''
            ) {

                table
                    .column(6)
                    .search('')
                    .draw();

            }
            else {

                table
                    .column(6)
                    .search(
                        '^'
                        +
                        $.fn.dataTable.util.escapeRegex(
                            selectedRole
                        )
                        +
                        '$',
                        true,
                        false
                    )
                    .draw();

            }

        }
    );



    /* =====================================================
       SORT
    ===================================================== */

    $('#sortFilter').on(
        'change',
        function()
        {

            table
                .order([
                    [
                        5,
                        this.value
                    ]
                ])
                .draw();

        }
    );



    /* =====================================================
       DELETE CONFIRMATION
    ===================================================== */

    $('.deleteStaffForm').on(
        'submit',
        function(event)
        {

            event.preventDefault();


            const form =
                this;


            const button =
                form.querySelector(
                    '.btn-delete'
                );


            if (
                button.disabled
            ) {

                return;
            }


            const staffName =
                button.dataset.name
                ||
                'this staff member';


            Swal.fire({

                icon:
                    'warning',

                title:
                    'Delete Staff Account?',

                html:
                    'Are you sure you want to delete <strong>'
                    +
                    escapeHtml(
                        staffName
                    )
                    +
                    '</strong>?',

                text:
                    'This action cannot be undone.',

                showCancelButton:
                    true,

                confirmButtonText:
                    'Yes, Delete',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#dc2626',

                cancelButtonColor:
                    '#64748b'

            })
            .then(
                function(result)
                {

                    if (
                        result.isConfirmed
                    ) {

                        form.submit();

                    }

                }
            );

        }
    );

}
);



/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text)
{

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        text
        ??
        '';


    return div.innerHTML;

}

</script>



<!-- =====================================================
     SWEET ALERT
===================================================== -->

<?php if (
    isset(
        $_SESSION[
            'staff_swal'
        ]
    )
): ?>


<script>

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        Swal.fire({

            icon:
                <?= json_encode(
                    $_SESSION[
                        'staff_swal'
                    ][
                        'icon'
                    ]
                ) ?>,

            title:
                <?= json_encode(
                    $_SESSION[
                        'staff_swal'
                    ][
                        'title'
                    ]
                ) ?>,

            text:
                <?= json_encode(
                    $_SESSION[
                        'staff_swal'
                    ][
                        'text'
                    ]
                ) ?>,

            confirmButtonColor:
                '#2563eb'

        });

    }
);

</script>


<?php

unset(
    $_SESSION[
        'staff_swal'
    ]
);

?>


<?php endif; ?>


</body>

</html>