<?php

session_start();
include("../config/config.php");


/* =========================================================
   SECURITY
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
   ADD MEDICATION
========================================================= */

if (isset($_POST['add'])) {

    $name =
        trim($_POST['name'] ?? '');

    $desc =
        trim($_POST['desc'] ?? '');

    $dosage =
        trim($_POST['dosage'] ?? '');


    if (
        $name !== '' &&
        $desc !== '' &&
        $dosage !== ''
    ) {

        try {

            $stmt = $conn->prepare("

                INSERT INTO SYARMIMI.MEDICATION
                (
                    MEDICATION_ID,
                    MEDICATION_NAME,
                    DESCRIPTION,
                    DOSAGE_FORM,
                    STOCK
                )

                VALUES
                (
                    SYARMIMI.MEDICATION_SEQ.NEXTVAL,
                    :name,
                    :description,
                    :dosage,
                    0
                )

            ");

            $stmt->execute([
                ':name'        => $name,
                ':description' => $desc,
                ':dosage'      => $dosage
            ]);


            header(
                "Location: medication.php?added=1"
            );

            exit();

        }
        catch (PDOException $e) {

            header(
                "Location: medication.php?error=add"
            );

            exit();
        }
    }
}


/* =========================================================
   UPDATE MEDICATION
========================================================= */

if (isset($_POST['update'])) {

    $id =
        (int)($_POST['id'] ?? 0);

    $name =
        trim($_POST['name'] ?? '');

    $desc =
        trim($_POST['desc'] ?? '');

    $dosage =
        trim($_POST['dosage'] ?? '');

    $stock =
        (int)($_POST['stock'] ?? 0);


    if ($stock < 0) {
        $stock = 0;
    }


    if (
        $id > 0 &&
        $name !== '' &&
        $desc !== '' &&
        $dosage !== ''
    ) {

        try {

            $stmt = $conn->prepare("

                UPDATE SYARMIMI.MEDICATION

                SET
                    MEDICATION_NAME = :name,
                    DESCRIPTION     = :description,
                    DOSAGE_FORM     = :dosage,
                    STOCK           = :stock

                WHERE
                    MEDICATION_ID = :id

            ");


            $stmt->execute([
                ':name'        => $name,
                ':description' => $desc,
                ':dosage'      => $dosage,
                ':stock'       => $stock,
                ':id'          => $id
            ]);


            header(
                "Location: medication.php?updated=1"
            );

            exit();

        }
        catch (PDOException $e) {

            header(
                "Location: medication.php?error=update"
            );

            exit();
        }
    }
}


/* =========================================================
   DELETE MEDICATION
========================================================= */

if (isset($_GET['delete'])) {

    $id =
        (int)$_GET['delete'];


    try {

        $stmt = $conn->prepare("

            DELETE FROM
                SYARMIMI.MEDICATION

            WHERE
                MEDICATION_ID = :id

        ");


        $stmt->execute([
            ':id' => $id
        ]);


        header(
            "Location: medication.php?deleted=1"
        );

        exit();

    }
    catch (PDOException $e) {

        header(
            "Location: medication.php?error=delete"
        );

        exit();
    }
}


/* =========================================================
   SUMMARY
========================================================= */

$totalMedication =
    (int)$conn->query("

        SELECT COUNT(*)
        FROM SYARMIMI.MEDICATION

    ")->fetchColumn();


$availableMedication =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION

        WHERE NVL(STOCK,0) > 10

    ")->fetchColumn();


$lowStock =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION

        WHERE NVL(STOCK,0) BETWEEN 1 AND 10

    ")->fetchColumn();


$outOfStock =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION

        WHERE NVL(STOCK,0) <= 0

    ")->fetchColumn();


/* =========================================================
   FETCH MEDICATION
========================================================= */

$stmt = $conn->query("

    SELECT
        MEDICATION_ID,
        MEDICATION_NAME,
        DESCRIPTION,
        DOSAGE_FORM,
        NVL(STOCK,0) AS STOCK

    FROM
        SYARMIMI.MEDICATION

    ORDER BY
        MEDICATION_NAME ASC

");


$medications =
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
Medication Management
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
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
    rel="stylesheet"
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
   PAGE HEADER
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

    display:flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:8px;

    color:#2563eb;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   SUMMARY CARDS
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

    gap:15px;

    padding:20px;

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

    font-size:30px;

    font-weight:750;

    line-height:1;
}


.summary-icon{

    width:46px;

    height:46px;

    min-width:46px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    font-size:19px;
}


.icon-total{

    background:#eff6ff;

    color:#2563eb;
}


.icon-available{

    background:#ecfdf5;

    color:#15803d;
}


.icon-low{

    background:#fffbeb;

    color:#d97706;
}


.icon-out{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{

    margin-bottom:20px;

    padding:23px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.card-heading{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:20px;
}


.card-heading-left{

    display:flex;

    align-items:center;

    gap:11px;
}


.card-icon{

    width:40px;

    height:40px;

    min-width:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:17px;
}


.add-icon{

    background:#eff6ff;

    color:#2563eb;
}


.inventory-icon{

    background:#ecfdf5;

    color:#15803d;
}


.card-title{

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
   DOWNLOAD BUTTON
========================================================= */

.btn-download{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    min-height:40px;

    padding:0 15px;

    background:#16a34a;

    border:1px solid #16a34a;

    border-radius:8px;

    color:#fff;

    font-size:11px;

    font-weight:650;

    white-space:nowrap;
}


.btn-download:hover{

    background:#15803d;

    border-color:#15803d;

    color:#fff;
}


/* =========================================================
   FORM
========================================================= */

.form-label{

    margin-bottom:7px;

    color:#475569;

    font-size:11px;

    font-weight:700;
}


.form-control,
.form-select{

    min-height:44px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:12px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


.btn-add{

    min-height:44px;

    padding:0 22px;

    background:#2563eb;

    border:none;

    border-radius:8px;

    font-size:11px;

    font-weight:650;
}


.btn-add:hover{

    background:#1d4ed8;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box{

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

    color:#94a3b8;

    transform:translateY(-50%);

    pointer-events:none;
}


.search-wrapper input{

    padding-left:39px;
}


/* =========================================================
   FILTER INFO
========================================================= */

.filter-info{

    display:flex;

    align-items:center;

    gap:7px;

    margin-top:12px;

    color:#94a3b8;

    font-size:10px;
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

    padding:13px 11px !important;

    background:#f8fafc !important;

    border-bottom:
        1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:700;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:13px 11px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.row-number{

    width:30px;

    height:30px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#f1f5f9;

    border-radius:7px;

    color:#64748b;

    font-size:11px;

    font-weight:700;
}


/* =========================================================
   MEDICATION
========================================================= */

.medication-name{

    display:flex;

    align-items:center;

    gap:9px;
}


.medication-symbol{

    width:35px;

    height:35px;

    min-width:35px;

    display:flex;

    align-items:center;

    justify-content:center;

    background:#eff6ff;

    border-radius:8px;

    color:#2563eb;

    font-size:15px;
}


/* =========================================================
   TABLE INPUT
========================================================= */

.table-input{

    min-height:37px;

    padding:7px 9px;

    background:#fff;

    border:1px solid #e2e8f0;

    border-radius:7px;

    font-size:11px;
}


.table-input:focus{

    border-color:#93c5fd;

    outline:none;

    box-shadow:
        0 0 0 2px
        rgba(37,99,235,.06);
}


.stock-input{

    width:80px;
}


/* =========================================================
   STATUS
========================================================= */

.status-pill{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:6px 9px;

    border-radius:7px;

    font-size:9px;

    font-weight:700;

    white-space:nowrap;
}


.status-available{

    background:#ecfdf5;

    color:#15803d;
}


.status-low{

    background:#fffbeb;

    color:#b45309;
}


.status-out{

    background:#fff1f2;

    color:#dc2626;
}


.status-dot{

    width:6px;

    height:6px;

    background:currentColor;

    border-radius:50%;
}


/* =========================================================
   ACTION
========================================================= */

.action-group{

    display:flex;

    align-items:center;

    gap:6px;
}


.btn-update{

    padding:7px 10px;

    background:#fffbeb;

    border:1px solid #fde68a;

    border-radius:7px;

    color:#b45309;

    font-size:9px;

    font-weight:700;
}


.btn-update:hover{

    background:#f59e0b;

    border-color:#f59e0b;

    color:#fff;
}


.btn-delete{

    padding:7px 10px;

    background:#fff1f2;

    border:1px solid #fecdd3;

    border-radius:7px;

    color:#dc2626;

    font-size:9px;

    font-weight:700;
}


.btn-delete:hover{

    background:#dc2626;

    border-color:#dc2626;

    color:#fff;
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

    padding:4px 24px 4px 8px;
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


@media(max-width:700px){

    .main-content{

        padding:18px;
    }


    .summary-grid{

        grid-template-columns:1fr;
    }


    .page-header,
    .card-heading{

        flex-direction:column;

        align-items:flex-start;
    }


    .page-title{

        font-size:24px;
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

Medication Management

</h1>


<div class="page-subtitle">

Manage the hospital medication catalogue and monitor current inventory levels.

</div>


</div>


<div class="header-badge">

<i class="bi bi-capsule-pill"></i>

Medication Catalogue

</div>


</div>



<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="summary-grid">


<div class="summary-card">

<div>

<div class="summary-label">
Total Medication
</div>

<div class="summary-number">
<?= $totalMedication ?>
</div>

</div>

<div class="summary-icon icon-total">

<i class="bi bi-capsule"></i>

</div>

</div>


<div class="summary-card">

<div>

<div class="summary-label">
Available
</div>

<div class="summary-number">
<?= $availableMedication ?>
</div>

</div>

<div class="summary-icon icon-available">

<i class="bi bi-check-circle"></i>

</div>

</div>


<div class="summary-card">

<div>

<div class="summary-label">
Low Stock
</div>

<div class="summary-number">
<?= $lowStock ?>
</div>

</div>

<div class="summary-icon icon-low">

<i class="bi bi-exclamation-triangle"></i>

</div>

</div>


<div class="summary-card">

<div>

<div class="summary-label">
Out of Stock
</div>

<div class="summary-number">
<?= $outOfStock ?>
</div>

</div>

<div class="summary-icon icon-out">

<i class="bi bi-x-circle"></i>

</div>

</div>


</div>



<!-- =====================================================
     ADD MEDICATION
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">

<div class="card-icon add-icon">

<i class="bi bi-plus-lg"></i>

</div>


<div>

<h5 class="card-title">
Add New Medication
</h5>

<div class="card-subtitle">
Register a new medication in the hospital medication catalogue.
</div>

</div>

</div>


</div>


<form method="POST">


<div class="row g-3 align-items-end">


<div class="col-lg-3">

<label class="form-label">
Medication Name
</label>

<input
    type="text"
    name="name"
    class="form-control"
    placeholder="e.g. Paracetamol"
    required
>

</div>


<div class="col-lg-4">

<label class="form-label">
Description
</label>

<input
    type="text"
    name="desc"
    class="form-control"
    placeholder="Medication description"
    required
>

</div>


<div class="col-lg-3">

<label class="form-label">
Dosage Form
</label>

<input
    type="text"
    name="dosage"
    class="form-control"
    placeholder="e.g. Tablet"
    required
>

</div>


<div class="col-lg-2">

<button
    type="submit"
    name="add"
    class="btn btn-primary btn-add w-100"
>

<i class="bi bi-plus-circle me-1"></i>

Add Medication

</button>

</div>


</div>


</form>


</div>



<!-- =====================================================
     MEDICATION INVENTORY
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon inventory-icon">

<i class="bi bi-list-check"></i>

</div>


<div>

<h5 class="card-title">
Medication Inventory
</h5>

<div class="card-subtitle">
Search, filter, update and download medication information.
</div>

</div>


</div>


<button
    type="button"
    id="downloadMedicationBtn"
    class="btn btn-download"
>

<i class="bi bi-download"></i>

Download Medication List

</button>


</div>



<!-- =================================================
     FILTER
================================================= -->

<div class="filter-box">


<div class="row g-2">


<div class="col-lg-5">

<div class="filter-label">
Search Medication
</div>

<div class="search-wrapper">

<i class="bi bi-search"></i>

<input
    type="text"
    id="medicationSearch"
    class="form-control"
    placeholder="Search medication name, description or dosage..."
>

</div>

</div>


<div class="col-lg-3">

<div class="filter-label">
Stock Status
</div>

<select
    id="stockFilter"
    class="form-select"
>

<option value="">
All Stock Status
</option>

<option value="Available">
Available
</option>

<option value="Low Stock">
Low Stock
</option>

<option value="Out of Stock">
Out of Stock
</option>

</select>

</div>


<div class="col-lg-2">

<div class="filter-label">
Medication Name
</div>

<select
    id="sortFilter"
    class="form-select"
>

<option value="asc">
A-Z
</option>

<option value="desc">
Z-A
</option>

</select>

</div>


<div class="col-lg-2">

<div class="filter-label">
Stock Quantity
</div>

<select
    id="stockSort"
    class="form-select"
>

<option value="">
Default
</option>

<option value="high">
Highest Stock
</option>

<option value="low">
Lowest Stock
</option>

</select>

</div>


</div>


<div class="filter-info">

<i class="bi bi-info-circle"></i>

<span id="filterInfoText">
Showing all medication
</span>

</div>


</div>



<!-- =================================================
     TABLE
================================================= -->

<div class="table-responsive">


<table
    id="medicationTable"
    class="table"
>


<thead>

<tr>

<th>
No.
</th>

<th>
Medication
</th>

<th>
Description
</th>

<th>
Dosage Form
</th>

<th>
Stock
</th>

<th>
Status
</th>

<th>
Action
</th>

</tr>

</thead>


<tbody>


<?php foreach (
    $medications
    as
    $m
): ?>


<?php

$stock =
    (int)$m['STOCK'];


if ($stock <= 0) {

    $stockStatus =
        'Out of Stock';

    $statusClass =
        'status-out';

}
elseif ($stock <= 10) {

    $stockStatus =
        'Low Stock';

    $statusClass =
        'status-low';

}
else {

    $stockStatus =
        'Available';

    $statusClass =
        'status-available';
}


$formId =
    'updateMedicationForm_' .
    (int)$m['MEDICATION_ID'];

?>


<tr
    data-medication-id="<?= h(
        $m['MEDICATION_ID']
    ) ?>"
>


<!-- NUMBER -->

<td class="number-cell"></td>


<!-- MEDICATION -->

<td>


<div class="medication-name">


<div class="medication-symbol">

<i class="bi bi-capsule"></i>

</div>


<div class="flex-grow-1">


<span class="d-none search-med-name">

<?= h(
    $m[
        'MEDICATION_NAME'
    ]
) ?>

</span>


<input
    type="text"
    name="name"
    class="form-control table-input medication-name-input"
    value="<?= h(
        $m[
            'MEDICATION_NAME'
        ]
    ) ?>"
    form="<?= h(
        $formId
    ) ?>"
    required
>


</div>


</div>


</td>


<!-- DESCRIPTION -->

<td>


<span class="d-none search-description">

<?= h(
    $m[
        'DESCRIPTION'
    ]
) ?>

</span>


<input
    type="text"
    name="desc"
    class="form-control table-input medication-description-input"
    value="<?= h(
        $m[
            'DESCRIPTION'
        ]
    ) ?>"
    form="<?= h(
        $formId
    ) ?>"
    required
>


</td>


<!-- DOSAGE -->

<td>


<span class="d-none search-dosage">

<?= h(
    $m[
        'DOSAGE_FORM'
    ]
) ?>

</span>


<input
    type="text"
    name="dosage"
    class="form-control table-input medication-dosage-input"
    value="<?= h(
        $m[
            'DOSAGE_FORM'
        ]
    ) ?>"
    form="<?= h(
        $formId
    ) ?>"
    required
>


</td>


<!-- STOCK -->

<td
    data-order="<?= $stock ?>"
>


<input
    type="number"
    name="stock"
    class="form-control table-input stock-input medication-stock-input"
    value="<?= $stock ?>"
    min="0"
    form="<?= h(
        $formId
    ) ?>"
    required
>


</td>


<!-- STATUS -->

<td>


<span
    class="
        status-pill
        <?= $statusClass ?>
    "
>


<span class="status-dot"></span>


<span class="status-text">

<?= h(
    $stockStatus
) ?>

</span>


</span>


</td>


<!-- ACTION -->

<td>


<form
    method="POST"
    id="<?= h(
        $formId
    ) ?>"
>


<input
    type="hidden"
    name="id"
    value="<?= h(
        $m[
            'MEDICATION_ID'
        ]
    ) ?>"
>


</form>


<div class="action-group">


<button
    type="submit"
    name="update"
    value="1"
    form="<?= h(
        $formId
    ) ?>"
    class="btn btn-update"
>

<i class="bi bi-pencil-square me-1"></i>

Update

</button>


<button
    type="button"
    class="btn btn-delete deleteMedication"

    data-id="<?= h(
        $m[
            'MEDICATION_ID'
        ]
    ) ?>"

    data-name="<?= h(
        $m[
            'MEDICATION_NAME'
        ]
    ) ?>"
>

<i class="bi bi-trash3 me-1"></i>

Delete

</button>


</div>


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

$(document).ready(function(){


    /* =====================================================
       DATATABLE
    ===================================================== */

    const table =
        $('#medicationTable')
        .DataTable({

            pageLength:10,

            lengthMenu:
            [
                [10,25,50,100],
                [10,25,50,100]
            ],

            dom:'lrtip',

            order:
            [
                [1,'asc']
            ],

            columnDefs:
            [

                {
                    targets:0,
                    orderable:false,
                    searchable:false
                },

                {
                    targets:6,
                    orderable:false,
                    searchable:false
                }

            ],


            drawCallback:function()
            {

                const api =
                    this.api();


                const info =
                    api.page.info();


                /*
                 AUTO NUMBER 1,2,3...
                */

                api
                    .column(
                        0,
                        {
                            page:'current',
                            search:'applied',
                            order:'applied'
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
                                '<div class="row-number">'
                                +
                                (
                                    info.start
                                    +
                                    index
                                    +
                                    1
                                )
                                +
                                '</div>';

                        }
                    );


                updateFilterInfo(
                    api
                );

            }

        });



    /* =====================================================
       SEARCH
    ===================================================== */

    $('#medicationSearch').on(
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
       STATUS FILTER
    ===================================================== */

    $('#stockFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value === ''
            ) {

                table
                    .column(5)
                    .search('')
                    .draw();

            }
            else {

                table
                    .column(5)
                    .search(
                        '^'
                        +
                        $.fn.dataTable.util.escapeRegex(
                            value
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
       NAME SORT
    ===================================================== */

    $('#sortFilter').on(
        'change',
        function()
        {

            table
                .order([
                    [
                        1,
                        this.value
                    ]
                ])
                .draw();

        }
    );



    /* =====================================================
       STOCK SORT
    ===================================================== */

    $('#stockSort').on(
        'change',
        function()
        {

            if (
                this.value ===
                'high'
            ) {

                table
                    .order([
                        [4,'desc']
                    ])
                    .draw();

            }
            else if (
                this.value ===
                'low'
            ) {

                table
                    .order([
                        [4,'asc']
                    ])
                    .draw();

            }
            else {

                table
                    .order([
                        [
                            1,
                            $('#sortFilter').val()
                        ]
                    ])
                    .draw();

            }

        }
    );



    /* =====================================================
       LIVE STOCK STATUS

       If Admin changes stock input before pressing Update,
       status badge changes visually too.
    ===================================================== */

    $(document).on(
        'input',
        '.medication-stock-input',
        function()
        {

            let stock =
                parseInt(
                    this.value
                    ||
                    0
                );


            if (
                isNaN(stock)
                ||
                stock < 0
            ) {

                stock = 0;
            }


            const row =
                $(this)
                .closest('tr');


            const badge =
                row.find(
                    '.status-pill'
                );


            const text =
                row.find(
                    '.status-text'
                );


            badge.removeClass(
                'status-available status-low status-out'
            );


            if (
                stock <= 0
            ) {

                badge.addClass(
                    'status-out'
                );


                text.text(
                    'Out of Stock'
                );

            }
            else if (
                stock <= 10
            ) {

                badge.addClass(
                    'status-low'
                );


                text.text(
                    'Low Stock'
                );

            }
            else {

                badge.addClass(
                    'status-available'
                );


                text.text(
                    'Available'
                );

            }

        }
    );



    /* =====================================================
       DELETE
    ===================================================== */

    $(document).on(
        'click',
        '.deleteMedication',
        function()
        {

            const id =
                $(this)
                .data(
                    'id'
                );


            const name =
                $(this)
                .data(
                    'name'
                );


            Swal.fire({

                icon:
                    'warning',

                title:
                    'Delete Medication?',

                html:
                    'Are you sure you want to delete <strong>'
                    +
                    escapeHtml(
                        name
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

                        window.location.href =
                            'medication.php?delete='
                            +
                            encodeURIComponent(
                                id
                            );

                    }

                }
            );

        }
    );



    /* =====================================================
       DOWNLOAD MEDICATION LIST
    ===================================================== */

    $('#downloadMedicationBtn').on(
        'click',
        function()
        {

            downloadMedicationCSV(
                table
            );

        }
    );


});


/* =========================================================
   FILTER INFORMATION
========================================================= */

function updateFilterInfo(table)
{

    const total =
        table.rows().count();


    const filtered =
        table
        .rows({
            search:'applied'
        })
        .count();


    const search =
        $('#medicationSearch')
        .val()
        .trim();


    const status =
        $('#stockFilter')
        .val();


    let text = '';


    if (
        search === ''
        &&
        status === ''
    ) {

        text =
            'Showing all '
            +
            total
            +
            ' medication record(s)';

    }
    else {

        text =
            'Showing '
            +
            filtered
            +
            ' matching medication record(s)';


        if (
            status !== ''
        ) {

            text +=
                ' • Status: '
                +
                status;

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

    }


    $('#filterInfoText')
    .text(
        text
    );

}


/* =========================================================
   DOWNLOAD CSV
========================================================= */

function downloadMedicationCSV(table)
{

    const rows = [];


    /*
     Only rows currently matching Search / Filter.
     Pagination does NOT matter.
     All matching pages are downloaded.
    */

    table
    .rows({
        search:'applied',
        order:'applied'
    })
    .every(
        function(
            rowIndex,
            tableLoop,
            rowLoop
        )
        {

            const row =
                this.node();


            if (!row) {
                return;
            }


            const medicationName =
                $(row)
                .find(
                    '.medication-name-input'
                )
                .val()
                ||
                '';


            const description =
                $(row)
                .find(
                    '.medication-description-input'
                )
                .val()
                ||
                '';


            const dosage =
                $(row)
                .find(
                    '.medication-dosage-input'
                )
                .val()
                ||
                '';


            const stock =
                $(row)
                .find(
                    '.medication-stock-input'
                )
                .val()
                ||
                '0';


            const status =
                $(row)
                .find(
                    '.status-text'
                )
                .text()
                .trim();


            rows.push([
                rows.length + 1,
                medicationName,
                description,
                dosage,
                stock,
                status
            ]);

        }
    );


    if (
        rows.length === 0
    ) {

        Swal.fire({

            icon:
                'info',

            title:
                'No Medication Found',

            text:
                'There are no medication records to download for the current filter.',

            confirmButtonColor:
                '#2563eb'

        });


        return;
    }


    /* =====================================================
       CSV CONTENT
    ===================================================== */

    const csvRows = [];


    /*
     Report Header
    */

    csvRows.push([
        'ZB-CARE Specialist Hospital System'
    ]);


    csvRows.push([
        'Medication Inventory List'
    ]);


    csvRows.push([
        'Generated Date',
        formatCurrentDate()
    ]);


    const stockFilter =
        $('#stockFilter')
        .val();


    const search =
        $('#medicationSearch')
        .val()
        .trim();


    if (
        stockFilter !== ''
    ) {

        csvRows.push([
            'Stock Filter',
            stockFilter
        ]);
    }


    if (
        search !== ''
    ) {

        csvRows.push([
            'Search',
            search
        ]);
    }


    csvRows.push([]);


    /*
     Column Header
    */

    csvRows.push([
        'No.',
        'Medication Name',
        'Description',
        'Dosage Form',
        'Stock',
        'Stock Status'
    ]);


    /*
     Data
    */

    rows.forEach(
        function(row)
        {

            csvRows.push(
                row
            );

        }
    );


    /*
     Convert to CSV
    */

    const csvContent =
        '\uFEFF'
        +
        csvRows
        .map(
            function(row)
            {

                return row
                    .map(
                        escapeCSV
                    )
                    .join(',');

            }
        )
        .join('\r\n');


    /*
     Download
    */

    const blob =
        new Blob(
            [csvContent],
            {
                type:
                    'text/csv;charset=utf-8;'
            }
        );


    const url =
        URL.createObjectURL(
            blob
        );


    const link =
        document.createElement(
            'a'
        );


    const today =
        new Date()
        .toISOString()
        .slice(
            0,
            10
        );


    link.href =
        url;


    link.download =
        'ZB-CARE-Medication-List-'
        +
        today
        +
        '.csv';


    document
    .body
    .appendChild(
        link
    );


    link.click();


    document
    .body
    .removeChild(
        link
    );


    URL.revokeObjectURL(
        url
    );

}


/* =========================================================
   ESCAPE CSV
========================================================= */

function escapeCSV(value)
{

    value =
        String(
            value
            ??
            ''
        );


    value =
        value.replace(
            /"/g,
            '""'
        );


    return (
        '"'
        +
        value
        +
        '"'
    );

}


/* =========================================================
   CURRENT DATE
========================================================= */

function formatCurrentDate()
{

    const date =
        new Date();


    const day =
        String(
            date.getDate()
        )
        .padStart(
            2,
            '0'
        );


    const months =
    [
        'JAN',
        'FEB',
        'MAR',
        'APR',
        'MAY',
        'JUN',
        'JUL',
        'AUG',
        'SEP',
        'OCT',
        'NOV',
        'DEC'
    ];


    const month =
        months[
            date.getMonth()
        ];


    const year =
        date
        .getFullYear();


    return (
        day
        +
        '-'
        +
        month
        +
        '-'
        +
        year
    );

}


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
     SWEET ALERT - ADDED
===================================================== -->

<?php if (
    isset(
        $_GET[
            'added'
        ]
    )
): ?>


<script>

Swal.fire({

    icon:
        'success',

    title:
        'Medication Added',

    text:
        'The medication has been added successfully.',

    confirmButtonColor:
        '#2563eb'

});

</script>


<?php endif; ?>



<!-- =====================================================
     SWEET ALERT - UPDATED
===================================================== -->

<?php if (
    isset(
        $_GET[
            'updated'
        ]
    )
): ?>


<script>

Swal.fire({

    icon:
        'success',

    title:
        'Medication Updated',

    text:
        'Medication information has been updated successfully.',

    confirmButtonColor:
        '#2563eb'

});

</script>


<?php endif; ?>



<!-- =====================================================
     SWEET ALERT - DELETED
===================================================== -->

<?php if (
    isset(
        $_GET[
            'deleted'
        ]
    )
): ?>


<script>

Swal.fire({

    icon:
        'success',

    title:
        'Medication Deleted',

    text:
        'The medication has been removed successfully.',

    confirmButtonColor:
        '#2563eb'

});

</script>


<?php endif; ?>



<!-- =====================================================
     SWEET ALERT - ERROR
===================================================== -->

<?php if (
    isset(
        $_GET[
            'error'
        ]
    )
): ?>


<script>

<?php if (
    $_GET[
        'error'
    ]
    ===
    'delete'
): ?>


Swal.fire({

    icon:
        'error',

    title:
        'Cannot Delete Medication',

    text:
        'This medication may already be linked to a patient medication order and cannot be deleted.',

    confirmButtonColor:
        '#2563eb'

});


<?php else: ?>


Swal.fire({

    icon:
        'error',

    title:
        'Something Went Wrong',

    text:
        'The medication information could not be saved. Please try again.',

    confirmButtonColor:
        '#2563eb'

});


<?php endif; ?>


</script>


<?php endif; ?>


</body>

</html>