<?php

session_start();

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'pharmacist'
) {

    header("Location: ../auth/login.php");
    exit();

}


/* =========================================================
   RESTOCK
========================================================= */

if (
    isset($_POST['restock'])
) {

    $med_id =
        $_POST['med_id']
        ?? '';

    $qty =
        (int)(
            $_POST['qty']
            ?? 0
        );


    if (
        $qty > 0
    ) {

        $stmt =
            $conn->prepare("

                UPDATE
                    SYARMIMI.MEDICATION

                SET
                    STOCK =
                    NVL(STOCK,0)
                    +
                    :qty

                WHERE
                    MEDICATION_ID = :id

            ");


        $stmt->execute([

            ':qty' =>
                $qty,

            ':id' =>
                $med_id

        ]);


        header(
            "Location: pharmacy_inventory.php?restock=1"
        );

        exit();

    }

}



/* =========================================================
   TOGGLE STATUS
========================================================= */

if (
    isset($_GET['toggle_status'])
) {

    $med_id =
        $_GET['toggle_status'];


    $statusStmt =
        $conn->prepare("

            SELECT
                NVL(
                    IS_AVAILABLE,
                    1
                )
                AS IS_AVAILABLE

            FROM
                SYARMIMI.MEDICATION

            WHERE
                MEDICATION_ID = :id

        ");


    $statusStmt->execute([
        ':id' => $med_id
    ]);


    $currentStatus =
        $statusStmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (
        $currentStatus
    ) {

        $newStatus =
            (
                (int)$currentStatus['IS_AVAILABLE']
                ===
                1
            )
            ?
            0
            :
            1;


        $updateStmt =
            $conn->prepare("

                UPDATE
                    SYARMIMI.MEDICATION

                SET
                    IS_AVAILABLE = :status

                WHERE
                    MEDICATION_ID = :id

            ");


        $updateStmt->execute([

            ':status' =>
                $newStatus,

            ':id' =>
                $med_id

        ]);

    }


    header(
        "Location: pharmacy_inventory.php?status_toggled=1"
    );

    exit();

}



/* =========================================================
   LOW STOCK COUNT
========================================================= */

$lowStock =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION

            WHERE
                NVL(STOCK,0) < 10

            AND
                NVL(IS_AVAILABLE,1) = 1

        ")
        ->fetchColumn();



/* =========================================================
   FETCH MEDICATION
========================================================= */

$stmt =
    $conn->query("

        SELECT

            MEDICATION_ID,

            MEDICATION_NAME,

            DESCRIPTION,

            DOSAGE_FORM,

            NVL(
                STOCK,
                0
            )
            AS STOCK,

            NVL(
                IS_AVAILABLE,
                1
            )
            AS IS_AVAILABLE

        FROM
            SYARMIMI.MEDICATION

        ORDER BY
            MEDICATION_NAME

    ");



/* =========================================================
   TOTAL MEDICATION
========================================================= */

$totalMedication =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION

        ")
        ->fetchColumn();



/* =========================================================
   AVAILABLE STOCK
========================================================= */

$availableStock =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION

            WHERE
                STOCK >= 10

            AND
                NVL(IS_AVAILABLE,1) = 1

        ")
        ->fetchColumn();



/* =========================================================
   OUT OF STOCK / UNAVAILABLE
========================================================= */

$outOfStock =
    (int)$conn
        ->query("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION

            WHERE
                STOCK <= 0

            OR
                NVL(IS_AVAILABLE,0) = 0

        ")
        ->fetchColumn();

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
Pharmacy Inventory
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


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

    font-size:26px;

    font-weight:700;
}


.page-subtitle{

    margin-top:5px;

    color:#8a94a3;

    font-size:13px;
}


/* =========================================================
   HEADER LOW STOCK BADGE
========================================================= */

.low-stock-indicator{

    display:flex;

    align-items:center;

    gap:8px;

    padding:9px 12px;

    background:#fff1f2;

    border:1px solid #fecdd3;

    border-radius:9px;

    color:#be123c;

    font-size:11px;

    font-weight:650;
}


/* =========================================================
   KPI
========================================================= */

.stat-card{

    height:100%;

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;

    transition:.2s;
}


.stat-card:hover{

    border-color:#d7dce3;

    transform:translateY(-2px);
}


.stat-content{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:15px;
}


.stat-label{

    color:#8a94a3;

    font-size:12px;

    font-weight:600;
}


.stat-number{

    margin-top:5px;

    color:#111827;

    font-size:28px;

    line-height:1;

    font-weight:700;
}


.stat-description{

    margin-top:7px;

    color:#94a3b8;

    font-size:10px;
}


.stat-icon{

    width:40px;

    height:40px;

    min-width:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:17px;
}


.icon-total{

    background:#eff6ff;

    color:#2563eb;
}


.icon-available{

    background:#ecfdf5;

    color:#15803d;
}


.icon-unavailable{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   ALERT
========================================================= */

.inventory-alert{

    display:flex;

    align-items:flex-start;

    gap:9px;

    margin-top:18px;

    margin-bottom:0;

    padding:12px 14px;

    border-radius:9px;

    font-size:12px;
}


/* =========================================================
   TABLE CARD
========================================================= */

.inventory-card{

    margin-top:22px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;
}


.inventory-card-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:16px;
}


.inventory-title{

    margin:0;

    color:#1f2937;

    font-size:16px;

    font-weight:650;
}


.inventory-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box{

    margin-bottom:18px;

    padding:14px;

    background:#f8fafc;

    border:1px solid #e8ebef;

    border-radius:10px;
}


.filter-label{

    margin-bottom:6px;

    color:#64748b;

    font-size:10px;

    font-weight:650;

    text-transform:uppercase;

    letter-spacing:.3px;
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

    font-size:13px;

    transform:translateY(-50%);
}


.search-wrapper input{

    padding-left:37px;
}


.form-control,
.form-select{

    min-height:42px;

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
        rgba(59,130,246,.07);
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{

    overflow-x:auto;

    border:1px solid #edf0f3;

    border-radius:9px;
}


.table{

    width:100% !important;

    margin-bottom:0 !important;

    vertical-align:middle;
}


.table thead th{

    padding:11px 12px !important;

    background:#f8fafc !important;

    border-bottom:1px solid #e5e7eb !important;

    color:#64748b !important;

    font-size:10px;

    font-weight:650;

    letter-spacing:.3px;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:12px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:12px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.medication-name{

    color:#1f2937;

    font-weight:650;
}


.description-text{

    max-width:300px;

    color:#64748b;

    line-height:1.45;
}


/* =========================================================
   STATUS
========================================================= */

.status-badge{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:5px 8px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;

    white-space:nowrap;
}


.status-available{

    background:#ecfdf5;

    color:#15803d;
}


.status-low{

    background:#fff7ed;

    color:#c2410c;
}


.status-out{

    background:#fff1f2;

    color:#dc2626;
}


.status-unavailable{

    background:#f1f5f9;

    color:#64748b;
}


/* =========================================================
   STOCK
========================================================= */

.stock-value{

    display:inline-flex;

    align-items:center;

    justify-content:center;

    min-width:40px;

    padding:5px 8px;

    border-radius:6px;

    background:#f8fafc;

    color:#334155;

    font-size:11px;

    font-weight:700;
}


.stock-critical{

    background:#fff1f2;

    color:#dc2626;
}


.stock-low{

    background:#fff7ed;

    color:#c2410c;
}


/* =========================================================
   ACTION AREA
========================================================= */

.action-area{

    display:flex;

    align-items:center;

    gap:7px;

    flex-wrap:wrap;
}


.restock-form{

    display:flex;

    align-items:center;

    gap:5px;
}


.qty-input{

    width:68px;

    min-height:34px !important;

    padding:5px 7px;

    font-size:11px;
}


.btn-restock{

    min-height:34px;

    padding:0 10px;

    border:0;

    border-radius:7px;

    background:#16a34a;

    color:#fff;

    font-size:10px;

    font-weight:600;
}


.btn-restock:hover{

    background:#15803d;

    color:#fff;
}


.btn-toggle{

    min-height:34px;

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:0 10px;

    border-radius:7px;

    font-size:10px;

    font-weight:600;
}


.btn-make-unavailable{

    border:1px solid #f59e0b;

    background:#fff;

    color:#b45309;
}


.btn-make-unavailable:hover{

    background:#fff7ed;

    border-color:#f59e0b;

    color:#92400e;
}


.btn-make-available{

    border:1px solid #60a5fa;

    background:#fff;

    color:#2563eb;
}


.btn-make-available:hover{

    background:#eff6ff;

    border-color:#60a5fa;

    color:#1d4ed8;
}


/* =========================================================
   DATATABLE
========================================================= */

.dataTables_wrapper
.dataTables_info{

    padding-top:16px !important;

    color:#94a3b8 !important;

    font-size:11px;
}


.dataTables_wrapper
.dataTables_paginate{

    padding-top:11px !important;
}


.page-link{

    min-width:33px;

    height:33px;

    display:flex;

    align-items:center;

    justify-content:center;

    border:1px solid #e2e8f0;

    border-radius:6px !important;

    color:#64748b;

    font-size:11px;
}


.page-item.active .page-link{

    background:#2563eb;

    border-color:#2563eb;

    color:#fff;
}


.page-link:focus{

    box-shadow:none;
}


.dataTables_empty{

    padding:35px !important;

    color:#94a3b8 !important;

    text-align:center;
}


/* =========================================================
   SWEETALERT
========================================================= */

.swal2-popup{

    border-radius:14px !important;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px){

    .content{

        padding:18px;
    }


    .page-header{

        flex-direction:column;
    }


    .inventory-card{

        padding:15px;
    }


    .inventory-card-header{

        align-items:flex-start;

        flex-direction:column;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include(
    "../includes/sidebar_pharma.php"
);
?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Pharmacy Inventory

</h1>


<div class="page-subtitle">

Manage medication stock levels and availability status.

</div>


</div>


<?php if (
    $lowStock > 0
): ?>


<div class="low-stock-indicator">

<i class="bi bi-exclamation-triangle"></i>

Low Stock:

<strong>

<?= $lowStock ?>

</strong>

</div>


<?php endif; ?>


</div>



<!-- =====================================================
     KPI
===================================================== -->

<div class="row g-3">


<div class="col-lg-4 col-md-6">


<div class="stat-card">


<div class="stat-content">


<div>


<div class="stat-label">

Total Medication

</div>


<div class="stat-number">

<?= $totalMedication ?>

</div>


<div class="stat-description">

Medication types in inventory

</div>


</div>


<div class="stat-icon icon-total">

<i class="bi bi-capsule"></i>

</div>


</div>


</div>


</div>



<div class="col-lg-4 col-md-6">


<div class="stat-card">


<div class="stat-content">


<div>


<div class="stat-label">

Available

</div>


<div class="stat-number">

<?= $availableStock ?>

</div>


<div class="stat-description">

Stock level 10 and above

</div>


</div>


<div class="stat-icon icon-available">

<i class="bi bi-check-circle"></i>

</div>


</div>


</div>


</div>



<div class="col-lg-4 col-md-6">


<div class="stat-card">


<div class="stat-content">


<div>


<div class="stat-label">

Out of Stock / Unavailable

</div>


<div class="stat-number">

<?= $outOfStock ?>

</div>


<div class="stat-description">

Requires attention

</div>


</div>


<div class="stat-icon icon-unavailable">

<i class="bi bi-exclamation-circle"></i>

</div>


</div>


</div>


</div>


</div>



<!-- =====================================================
     LOW STOCK ALERT
===================================================== -->

<?php if (
    $lowStock > 0
): ?>


<div
    class="
        alert
        alert-danger
        inventory-alert
    "
>

<i class="bi bi-exclamation-triangle"></i>

<div>

<strong>

<?= $lowStock ?>

medication(s)

</strong>

currently have stock below 10 units.

</div>

</div>


<?php endif; ?>



<!-- =====================================================
     INVENTORY CARD
===================================================== -->

<div class="inventory-card">


<div class="inventory-card-header">


<div>


<h5 class="inventory-title">

Medication Inventory

</h5>


<div class="inventory-subtitle">

Search, filter, restock and update medication availability.

</div>


</div>


</div>



<!-- =================================================
     FILTER
================================================= -->

<div class="filter-box">


<div class="row g-2">


<!-- SEARCH -->

<div class="col-lg-5">


<div class="filter-label">

Search

</div>


<div class="search-wrapper">


<i class="bi bi-search"></i>


<input
    type="text"
    id="searchInput"
    class="form-control"
    placeholder="Search medication..."
>


</div>


</div>



<!-- STATUS -->

<div class="col-lg-4">


<div class="filter-label">

Status

</div>


<select
    id="statusFilter"
    class="form-select"
>


<option value="">

All Status

</option>


<option value="Available">

Available

</option>


<option value="Low Stock">

Low Stock

</option>


<option value="Out Of Stock">

Out Of Stock

</option>


<option value="Unavailable">

Unavailable

</option>


</select>


</div>



<!-- SORT -->

<div class="col-lg-3">


<div class="filter-label">

Sort

</div>


<select
    id="sortFilter"
    class="form-select"
>


<option value="asc">

Medication A-Z

</option>


<option value="desc">

Medication Z-A

</option>


</select>


</div>


</div>


</div>



<!-- =================================================
     TABLE
================================================= -->

<div class="table-responsive">


<table
    id="inventoryTable"
    class="table"
>


<thead>


<tr>

<th>ID</th>

<th>Medication</th>

<th>Description</th>

<th>Dosage Form</th>

<th>Stock</th>

<th>Status</th>

<th>Action</th>

</tr>


</thead>


<tbody>


<?php while (
    $row =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        )
): ?>


<?php

$statusText = '';
$statusClass = '';
$statusFilterText = '';


if (
    (int)$row['IS_AVAILABLE']
    ===
    0
) {

    $statusText =
        'Unavailable';

    $statusClass =
        'status-unavailable';

    $statusFilterText =
        'Unavailable';

}

elseif (
    (int)$row['STOCK']
    <=
    0
) {

    $statusText =
        'Out Of Stock';

    $statusClass =
        'status-out';

    $statusFilterText =
        'Out Of Stock';

}

elseif (
    (int)$row['STOCK']
    <
    10
) {

    $statusText =
        'Low Stock';

    $statusClass =
        'status-low';

    $statusFilterText =
        'Low Stock';

}

else {

    $statusText =
        'Available';

    $statusClass =
        'status-available';

    $statusFilterText =
        'Available';

}


$stockClass =
    '';


if (
    (int)$row['STOCK']
    <=
    0
) {

    $stockClass =
        'stock-critical';

}

elseif (
    (int)$row['STOCK']
    <
    10
) {

    $stockClass =
        'stock-low';

}

?>


<tr>


<td>

<strong>

#<?= htmlspecialchars(
    $row['MEDICATION_ID']
) ?>

</strong>

</td>



<td>


<span class="medication-name">

<?= htmlspecialchars(
    $row['MEDICATION_NAME']
) ?>

</span>


</td>



<td>


<div class="description-text">

<?= htmlspecialchars(
    $row['DESCRIPTION']
    ?? '-'
) ?>

</div>


</td>



<td>

<?= htmlspecialchars(
    $row['DOSAGE_FORM']
    ?? '-'
) ?>

</td>



<td>


<span
    class="
        stock-value
        <?= $stockClass ?>
    "
>

<?= (int)$row['STOCK'] ?>

</span>


</td>



<td>


<span
    class="
        status-badge
        <?= $statusClass ?>
    "
>


<?php if (
    $statusFilterText
    ===
    'Available'
): ?>

<i class="bi bi-check-circle"></i>


<?php elseif (
    $statusFilterText
    ===
    'Low Stock'
): ?>

<i class="bi bi-exclamation-triangle"></i>


<?php elseif (
    $statusFilterText
    ===
    'Out Of Stock'
): ?>

<i class="bi bi-exclamation-circle"></i>


<?php else: ?>

<i class="bi bi-dash-circle"></i>


<?php endif; ?>


<?= htmlspecialchars(
    $statusText
) ?>


</span>


<span class="d-none">

<?= htmlspecialchars(
    $statusFilterText
) ?>

</span>


</td>



<td>


<div class="action-area">


<!-- RESTOCK -->

<form
    method="POST"
    class="restock-form"
>


<input
    type="hidden"
    name="med_id"
    value="<?= htmlspecialchars(
        $row['MEDICATION_ID']
    ) ?>"
>


<input
    type="number"
    name="qty"
    class="form-control qty-input"
    placeholder="Qty"
    min="1"
    required
>


<button
    type="submit"
    name="restock"
    class="btn-restock"
>

<i class="bi bi-plus-circle me-1"></i>

Restock

</button>


</form>



<!-- TOGGLE STATUS -->

<?php if (
    (int)$row['IS_AVAILABLE']
    ===
    1
): ?>


<a
    href="?toggle_status=<?= urlencode(
        $row['MEDICATION_ID']
    ) ?>"
    class="
        btn-toggle
        btn-make-unavailable
        text-decoration-none
    "
    onclick="
        return confirmAvailabilityChange(
            this,
            'unavailable'
        );
    "
>

<i class="bi bi-x-circle"></i>

Unavailable

</a>


<?php else: ?>


<a
    href="?toggle_status=<?= urlencode(
        $row['MEDICATION_ID']
    ) ?>"
    class="
        btn-toggle
        btn-make-available
        text-decoration-none
    "
    onclick="
        return confirmAvailabilityChange(
            this,
            'available'
        );
    "
>

<i class="bi bi-check-circle"></i>

Available

</a>


<?php endif; ?>


</div>


</td>


</tr>


<?php endwhile; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>



<script
    src="https://code.jquery.com/jquery-3.7.1.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>


<script
    src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js">
</script>



<script>

/* =========================================================
   SUCCESS MESSAGE
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        const params =
            new URLSearchParams(
                window.location.search
            );


        if (
            params.has(
                'restock'
            )
        ) {

            Swal.fire({

                icon:
                    'success',

                title:
                    'Stock Updated',

                text:
                    'Medication inventory has been updated successfully.',

                confirmButtonColor:
                    '#2563eb'

            });

        }


        if (
            params.has(
                'status_toggled'
            )
        ) {

            Swal.fire({

                icon:
                    'success',

                title:
                    'Status Updated',

                text:
                    'Medication availability status has been changed.',

                confirmButtonColor:
                    '#2563eb'

            });

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
        $('#inventoryTable')
        .DataTable({

            pageLength:
                10,

            lengthMenu:
                [
                    [10,25,50,100],
                    [10,25,50,100]
                ],

            order:
                [
                    [1,'asc']
                ],

            dom:
                'tip'

        });


    /* =====================================================
       SEARCH
    ===================================================== */

    $('#searchInput').on(
        'keyup',
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

    $('#statusFilter').on(
        'change',
        function()
        {

            const value =
                this.value;


            if (
                value
                ===
                ''
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
                        value
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
                .order(
                    [
                        1,
                        this.value
                    ]
                )
                .draw();

        }
    );

}
);


/* =========================================================
   STATUS CONFIRMATION
========================================================= */

function confirmAvailabilityChange(
    link,
    newStatus
)
{

    const message =
        newStatus === 'available'
        ?
        'Mark this medication as available?'
        :
        'Mark this medication as unavailable?';


    Swal.fire({

        icon:
            'question',

        title:
            'Update Availability',

        text:
            message,

        showCancelButton:
            true,

        confirmButtonText:
            'Yes, update',

        cancelButtonText:
            'Cancel',

        confirmButtonColor:
            '#2563eb',

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
                    link.href;

            }

        }
    );


    return false;

}

</script>


</body>

</html>