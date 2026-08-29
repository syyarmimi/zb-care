<?php

session_start();
include("../config/config.php");

date_default_timezone_set('Asia/Kuala_Lumpur');

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

$staffId = (int)($_SESSION['user_id'] ?? 0);

if ($staffId <= 0) {
    die("Invalid pharmacist account.");
}

/* =========================================================
   SAFE HTML
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
   HANDLE NURSE COLLECTED
========================================================= */

if (isset($_GET['nurse_collected'])) {

    $prepId = (int)$_GET['nurse_collected'];

    try {

        $conn->beginTransaction();

        /* =================================================
           GET PREPARATION + SCHEDULE + ADMISSION
        ================================================= */

        $checkStmt = $conn->prepare("
            SELECT
                PP.PREP_ID,
                PP.MEDORDER_ID,
                PP.SCHEDULE_ID,
                PP.STATUS AS PREPARATION_STATUS,

                MO.ADMISSION_ID,

                MS.STATUS AS SCHEDULE_STATUS,
                MS.SCHEDULE_DATE,
                MS.SCHEDULE_TIME,

                A.DISCHARGE_DATE

            FROM
                SYARMIMI.PHARMACY_PREPARATION PP

            JOIN
                SYARMIMI.MEDICATION_ORDER MO
                ON PP.MEDORDER_ID = MO.MEDORDER_ID

            JOIN
                SYARMIMI.MEDICATION_SCHEDULE MS
                ON PP.SCHEDULE_ID = MS.SCHEDULE_ID

            JOIN
                SYARMIMI.ADMISSION A
                ON MO.ADMISSION_ID = A.ADMISSION_ID

            WHERE
                PP.PREP_ID = ?

            AND
                MO.ADMISSION_ID IS NOT NULL

            AND
                PP.SCHEDULE_ID IS NOT NULL

            AND
                PP.STATUS = 'Ready For Nurse Pickup'

            FOR UPDATE
        ");

        $checkStmt->execute([
            $prepId
        ]);

        $preparation = $checkStmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$preparation) {

            $conn->rollBack();

            header(
                "Location: med_delivery.php?error=not_available"
            );

            exit();
        }

        /* =================================================
           DISCHARGED CHECK
        ================================================= */

        if (!empty($preparation['DISCHARGE_DATE'])) {

            $conn->rollBack();

            header(
                "Location: med_delivery.php?error=discharged"
            );

            exit();
        }

        $medOrderId = (int)$preparation['MEDORDER_ID'];
        $scheduleId = (int)$preparation['SCHEDULE_ID'];

        /* =================================================
           CHECK DELIVERY BY SCHEDULE
        ================================================= */

        $checkDeliveryStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM SYARMIMI.MEDICATION_DELIVERY
            WHERE SCHEDULE_ID = ?
        ");

        $checkDeliveryStmt->execute([
            $scheduleId
        ]);

        $deliveryExists = (int)$checkDeliveryStmt->fetchColumn();

        if ($deliveryExists > 0) {

            $conn->rollBack();

            header(
                "Location: med_delivery.php?error=already_collected"
            );

            exit();
        }

        /* =================================================
           UPDATE PHARMACY PREPARATION
        ================================================= */

        $updatePrepStmt = $conn->prepare("
            UPDATE
                SYARMIMI.PHARMACY_PREPARATION

            SET
                STATUS = 'Collected'

            WHERE
                PREP_ID = ?

            AND
                STATUS = 'Ready For Nurse Pickup'
        ");

        $updatePrepStmt->execute([
            $prepId
        ]);

        if ($updatePrepStmt->rowCount() === 0) {
            throw new Exception(
                "Unable to update pharmacy preparation."
            );
        }

        /* =================================================
           UPDATE MEDICATION SCHEDULE
        ================================================= */

        $updateScheduleStmt = $conn->prepare("
            UPDATE
                SYARMIMI.MEDICATION_SCHEDULE

            SET
                STATUS = 'Collected By Nurse'

            WHERE
                SCHEDULE_ID = ?

            AND
                UPPER(
                    TRIM(
                        NVL(
                            STATUS,
                            'Pending Preparation'
                        )
                    )
                )
                <> 'ADMINISTERED'
        ");

        $updateScheduleStmt->execute([
            $scheduleId
        ]);

        /* =================================================
           INSERT DELIVERY
        ================================================= */

        $insertDeliveryStmt = $conn->prepare("
            INSERT INTO
                SYARMIMI.MEDICATION_DELIVERY
            (
                MEDDELIVERY_ID,
                DELIVERY_TIME,
                STATUS,
                ACCOUNT_ID,
                MEDORDER_ID,
                SCHEDULE_ID
            )

            VALUES
            (
                (
                    SELECT
                        NVL(
                            MAX(MEDDELIVERY_ID),
                            0
                        ) + 1

                    FROM
                        SYARMIMI.MEDICATION_DELIVERY
                ),

                SYSDATE,
                'Delivered',
                ?,
                ?,
                ?
            )
        ");

        $insertDeliveryStmt->execute([
            $staffId,
            $medOrderId,
            $scheduleId
        ]);

        $conn->commit();

        header(
            "Location: med_delivery.php?success=1"
        );

        exit();

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        die(
            "Error updating medication delivery: " .
            h($e->getMessage())
        );
    }
}

/* =========================================================
   COUNT READY MEDICATION
========================================================= */

$deliveryCountStmt = $conn->query("
    SELECT
        COUNT(*)

    FROM
        SYARMIMI.PHARMACY_PREPARATION PP

    JOIN
        SYARMIMI.MEDICATION_ORDER MO
        ON PP.MEDORDER_ID = MO.MEDORDER_ID

    JOIN
        SYARMIMI.MEDICATION_SCHEDULE MS
        ON PP.SCHEDULE_ID = MS.SCHEDULE_ID

    JOIN
        SYARMIMI.ADMISSION A
        ON MO.ADMISSION_ID = A.ADMISSION_ID

    WHERE
        MO.ADMISSION_ID IS NOT NULL

    AND
        PP.SCHEDULE_ID IS NOT NULL

    AND
        PP.STATUS = 'Ready For Nurse Pickup'

    AND
        A.DISCHARGE_DATE IS NULL
");

$deliveryCount =
    (int)$deliveryCountStmt->fetchColumn();

/* =========================================================
   FETCH READY MEDICATION
========================================================= */

$sql = "
    SELECT

        PP.PREP_ID,
        PP.MEDORDER_ID,
        PP.SCHEDULE_ID,

        P.NAME
        AS PATIENT_NAME,

        M.MEDICATION_NAME,

        MO.DOSAGE,
        MO.FREQUENCY,

        PP.STATUS,

        TO_CHAR(
            PP.PREPARED_TIME,
            'DD-MON-YYYY'
        )
        AS PREPARED_DATE,

        TO_CHAR(
            PP.PREPARED_TIME,
            'HH:MI AM'
        )
        AS PREPARED_TIME_DISPLAY,

        TO_CHAR(
            MS.SCHEDULE_DATE,
            'DD-MON-YYYY'
        )
        AS SCHEDULE_DATE_DISPLAY,

        TO_CHAR(
            MS.SCHEDULE_DATE,
            'YYYY-MM-DD'
        )
        AS SCHEDULE_DATE_VALUE,

        MS.SCHEDULE_TIME,

        MS.STATUS
        AS SCHEDULE_STATUS,

        NVL(
            W.WARD_NAME,
            '-'
        )
        AS WARD_NAME,

        NVL(
            B.BED_NUMBER,
            '-'
        )
        AS BED_NUMBER

    FROM
        SYARMIMI.PHARMACY_PREPARATION PP

    JOIN
        SYARMIMI.MEDICATION_ORDER MO
        ON PP.MEDORDER_ID = MO.MEDORDER_ID

    JOIN
        SYARMIMI.MEDICATION_SCHEDULE MS
        ON PP.SCHEDULE_ID = MS.SCHEDULE_ID

    JOIN
        SYARMIMI.ADMISSION A
        ON MO.ADMISSION_ID = A.ADMISSION_ID

    JOIN
        SYARMIMI.PATIENT P
        ON A.PATIENT_ID = P.PATIENT_ID

    JOIN
        SYARMIMI.MEDICATION M
        ON MO.MEDICATION_ID = M.MEDICATION_ID

    LEFT JOIN
        SYARMIMI.BED B
        ON A.BED_ID = B.BED_ID

    LEFT JOIN
        SYARMIMI.WARD W
        ON B.WARD_ID = W.WARD_ID

    WHERE
        MO.ADMISSION_ID IS NOT NULL

    AND
        PP.SCHEDULE_ID IS NOT NULL

    AND
        PP.STATUS = 'Ready For Nurse Pickup'

    AND
        A.DISCHARGE_DATE IS NULL

    ORDER BY
        PP.PREP_ID DESC
";

$stmt = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Medication Delivery</title>

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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    background:#f5f7fa;
    color:#1f2937;
    font-family:'Segoe UI', Arial, sans-serif;
}

.main-content{
    flex:1;
    min-width:0;
    min-height:100vh;
    padding:28px;
}

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

.ready-indicator{
    display:flex;
    align-items:center;
    gap:7px;
    padding:9px 12px;
    background:#ecfeff;
    border:1px solid #a5f3fc;
    border-radius:9px;
    color:#0e7490;
    font-size:11px;
    font-weight:650;
}

.delivery-alert{
    display:flex;
    align-items:flex-start;
    gap:9px;
    margin-bottom:18px;
    padding:12px 14px;
    border-radius:9px;
    font-size:12px;
}

.delivery-card{
    padding:20px;
    background:#fff;
    border:1px solid #e7eaee;
    border-radius:12px;
}

.card-header-clean{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:15px;
    margin-bottom:16px;
}

.card-title-clean{
    margin:0;
    color:#1f2937;
    font-size:16px;
    font-weight:650;
}

.card-subtitle-clean{
    margin-top:3px;
    color:#94a3b8;
    font-size:11px;
}

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
    box-shadow:0 0 0 3px rgba(59,130,246,.07);
}

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
    padding:11px 10px !important;
    background:#f8fafc !important;
    border-bottom:1px solid #e5e7eb !important;
    color:#64748b !important;
    font-size:9px;
    font-weight:650;
    letter-spacing:.25px;
    text-transform:uppercase;
    white-space:nowrap;
}

.table tbody td{
    padding:12px 10px !important;
    border-color:#eef1f4 !important;
    color:#374151;
    font-size:11px;
}

.table tbody tr:hover td{
    background:#fafbfc;
}

.number-cell{
    width:45px;
    color:#64748b;
    font-weight:650;
    text-align:center;
}

.patient-name,
.medication-name{
    color:#1f2937;
    font-weight:650;
}

.location-main{
    color:#374151;
    font-weight:500;
}

.location-bed{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}

.prepared-date,
.schedule-date{
    color:#475569;
    line-height:1.4;
}

.prepared-time,
.schedule-time{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}

.status-badge{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:5px 7px;
    border-radius:6px;
    font-size:9px;
    font-weight:650;
    white-space:nowrap;
}

.status-ready{
    background:#ecfeff;
    color:#0e7490;
}

.action-btn{
    min-height:32px;
    display:inline-flex;
    align-items:center;
    gap:5px;
    padding:0 10px;
    border:0;
    border-radius:7px;
    background:#16a34a;
    color:#fff;
    font-size:9px;
    font-weight:600;
    text-decoration:none;
    white-space:nowrap;
}

.action-btn:hover{
    background:#15803d;
    color:#fff;
}

.dataTables_wrapper .dataTables_filter{
    display:none;
}

.dataTables_wrapper .dataTables_length{
    margin-bottom:12px;
    color:#64748b;
    font-size:10px;
}

.dataTables_wrapper .dataTables_length select{
    min-height:31px;
    padding:4px 24px 4px 7px;
    border:1px solid #dfe3e8;
    border-radius:6px;
    font-size:10px;
}

.dataTables_wrapper .dataTables_info{
    padding-top:16px !important;
    color:#94a3b8 !important;
    font-size:10px;
}

.dataTables_wrapper .dataTables_paginate{
    padding-top:11px !important;
}

.page-link{
    min-width:32px;
    height:32px;
    display:flex;
    align-items:center;
    justify-content:center;
    border:1px solid #e2e8f0;
    border-radius:6px !important;
    color:#64748b;
    font-size:10px;
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

@media(max-width:900px){

    .main-content{
        padding:18px;
    }

    .page-header{
        flex-direction:column;
    }

    .delivery-card{
        padding:15px;
    }

    .card-header-clean{
        flex-direction:column;
        align-items:flex-start;
    }
}

</style>

</head>

<body>

<div class="d-flex">

<?php
include("../includes/sidebar_pharma.php");
?>

<div class="main-content">

<div class="page-header">

<div>

<h1 class="page-title">
Medication Delivery
</h1>

<div class="page-subtitle">
Confirm prepared admission medication collected by nurses.
</div>

</div>

<?php if ($deliveryCount > 0): ?>

<div class="ready-indicator">

<i class="bi bi-box-seam"></i>

Ready for Nurse:

<strong>
<?= $deliveryCount ?>
</strong>

</div>

<?php endif; ?>

</div>

<?php if ($deliveryCount > 0): ?>

<div class="alert alert-info delivery-alert">

<i class="bi bi-info-circle"></i>

<div>

<strong>
<?= $deliveryCount ?>
medication dose(s)
</strong>

are ready for nurse pickup.

</div>

</div>

<?php else: ?>

<div class="alert alert-secondary delivery-alert">

<i class="bi bi-check-circle"></i>

<div>
No medication is currently waiting for nurse pickup.
</div>

</div>

<?php endif; ?>

<div class="delivery-card">

<div class="card-header-clean">

<div>

<h5 class="card-title-clean">
Medication Waiting for Nurse Pickup
</h5>

<div class="card-subtitle-clean">
Each row represents one prepared scheduled dose for an admitted patient.
</div>

</div>

</div>

<div class="filter-box">

<div class="row g-2">

<div class="col-lg-6">

<div class="filter-label">
Search
</div>

<div class="search-wrapper">

<i class="bi bi-search"></i>

<input
    type="text"
    id="searchInput"
    class="form-control"
    placeholder="Search patient, medication, ward or schedule..."
>

</div>

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

</div>

<div class="table-responsive">

<table
    id="deliveryTable"
    class="table"
>

<thead>

<tr>

<th>No.</th>
<th>Patient</th>
<th>Location</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Scheduled Dose</th>
<th>Prepared</th>
<th>Status</th>
<th>Action</th>
<th>Prep ID</th>

</tr>

</thead>

<tbody>

<?php while (
    $row = $stmt->fetch(PDO::FETCH_ASSOC)
): ?>

<tr>

<td class="number-cell"></td>

<td>

<span class="patient-name">

<?= h(
    $row['PATIENT_NAME']
) ?>

</span>

</td>

<td>

<div class="location-main">

<?= h(
    $row['WARD_NAME']
) ?>

</div>

<?php if (
    $row['BED_NUMBER'] !== '-'
): ?>

<div class="location-bed">

Bed
<?= h(
    $row['BED_NUMBER']
) ?>

</div>

<?php endif; ?>

</td>

<td>

<span class="medication-name">

<?= h(
    $row['MEDICATION_NAME']
) ?>

</span>

</td>

<td>

<?= h(
    $row['DOSAGE'] ?? '-'
) ?>

</td>

<td>

<?= h(
    $row['FREQUENCY'] ?? '-'
) ?>

</td>

<td>

<div class="schedule-date">

<?= h(
    $row['SCHEDULE_DATE_DISPLAY']
) ?>

</div>

<div class="schedule-time">

<?= h(
    $row['SCHEDULE_TIME']
) ?>

</div>

</td>

<td>

<div class="prepared-date">

<?= h(
    $row['PREPARED_DATE']
) ?>

</div>

<div class="prepared-time">

<?= h(
    $row['PREPARED_TIME_DISPLAY']
) ?>

</div>

</td>

<td>

<span class="status-badge status-ready">

<i class="bi bi-box-seam"></i>

Ready For Nurse

</span>

</td>

<td>

<a
    href="med_delivery.php?nurse_collected=<?= urlencode(
        $row['PREP_ID']
    ) ?>"
    class="action-btn nurseCollectedBtn"
>

<i class="bi bi-check-circle"></i>

Nurse Collected

</a>

</td>

<td>

<?= (int)$row['PREP_ID'] ?>

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

$(document).ready(function()
{

    const table =
        $('#deliveryTable')
        .DataTable({

            pageLength:10,

            lengthMenu:[
                [10,25,50,100],
                [10,25,50,100]
            ],

            /*
             Column 10 = hidden PREP_ID.
            */

            order:[
                [10,'desc']
            ],

            columnDefs:[
                {
                    targets:0,
                    orderable:false,
                    searchable:false
                },
                {
                    targets:9,
                    orderable:false,
                    searchable:false
                },
                {
                    targets:10,
                    visible:false,
                    searchable:false
                }
            ],

            searching:true,
            paging:true,
            info:true,

            drawCallback:function()
            {

                const api =
                    this.api();

                const pageInfo =
                    api.page.info();

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
                                pageInfo.start +
                                index +
                                1;
                        }
                    );
            }
        });

    $('#searchInput').on(
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

    $('#sortFilter').on(
        'change',
        function()
        {
            table
                .order([
                    [
                        10,
                        this.value
                    ]
                ])
                .draw();
        }
    );

    $(document).on(
        'click',
        '.nurseCollectedBtn',
        function(event)
        {

            event.preventDefault();

            const url =
                this.href;

            Swal.fire({

                icon:'question',

                title:
                    'Confirm Nurse Collection',

                html:
                    `
                    <div style="
                        text-align:left;
                        font-size:13px;
                        line-height:1.6;
                        color:#475569;
                    ">

                        <p>
                            Has the nurse collected this scheduled medication dose from the pharmacy?
                        </p>

                        <p style="
                            margin-bottom:0;
                            font-weight:600;
                            color:#1f2937;
                        ">
                            Only this scheduled dose will be marked as collected.
                        </p>

                    </div>
                    `,

                showCancelButton:true,

                confirmButtonText:
                    'Yes, Nurse Collected',

                cancelButtonText:
                    'Cancel',

                confirmButtonColor:
                    '#16a34a',

                cancelButtonColor:
                    '#64748b'

            })
            .then(
                function(result)
                {
                    if (result.isConfirmed) {

                        window.location.href =
                            url;
                    }
                }
            );
        }
    );

});

/* =========================================================
   SUCCESS
========================================================= */

<?php if (
    isset($_GET['success'])
): ?>

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        Swal.fire({

            icon:'success',

            title:
                'Nurse Collection Confirmed',

            text:
                'This scheduled medication dose has been marked as collected.',

            confirmButtonColor:
                '#16a34a'
        });
    }
);

<?php endif; ?>

/* =========================================================
   ERROR
========================================================= */

<?php if (
    isset($_GET['error'])
): ?>

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        <?php if (
            $_GET['error'] === 'discharged'
        ): ?>

        Swal.fire({

            icon:'warning',

            title:
                'Patient Discharged',

            text:
                'This patient has already been discharged.',

            confirmButtonColor:
                '#2563eb'
        });

        <?php elseif (
            $_GET['error'] === 'already_collected'
        ): ?>

        Swal.fire({

            icon:'info',

            title:
                'Already Collected',

            text:
                'This scheduled medication dose has already been collected.',

            confirmButtonColor:
                '#2563eb'
        });

        <?php else: ?>

        Swal.fire({

            icon:'warning',

            title:
                'Medication Not Available',

            text:
                'This medication is no longer available for nurse pickup.',

            confirmButtonColor:
                '#2563eb'
        });

        <?php endif; ?>

    }
);

<?php endif; ?>

</script>

</body>
</html>