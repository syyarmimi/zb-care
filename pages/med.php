<?php

session_start();

include("../config/config.php");


/* ============================================================
   ROLE CHECK
   ============================================================ */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {

    header("Location: ../auth/login.php");
    exit();

}


/* ============================================================
   DATE FILTER
   ============================================================ */

$dateFilter = $_GET['date'] ?? 'all';

$allowedDateFilters = [
    'all',
    'today',
    'yesterday',
    '7days',
    'month',
    'custom'
];

if (!in_array($dateFilter, $allowedDateFilters, true)) {

    $dateFilter = 'all';

}


/* ============================================================
   CUSTOM DATE
   ============================================================ */

$customDate = $_GET['custom_date'] ?? '';

$validCustomDate = '';

if (
    $dateFilter === 'custom'
    && preg_match('/^\d{4}-\d{2}-\d{2}$/', $customDate)
) {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $customDate
    );

    if (
        $dateObject
        && $dateObject->format('Y-m-d') === $customDate
    ) {

        $validCustomDate = $customDate;

    }

}


/* ============================================================
   BUILD DATE CONDITION
   ============================================================ */

$dateCondition = '';

$dateParameter = null;

if ($dateFilter === 'today') {

    $dateCondition = "
        AND TRUNC(md.DELIVERY_TIME) = TRUNC(SYSDATE)
    ";

}

elseif ($dateFilter === 'yesterday') {

    $dateCondition = "
        AND TRUNC(md.DELIVERY_TIME) = TRUNC(SYSDATE) - 1
    ";

}

elseif ($dateFilter === '7days') {

    $dateCondition = "
        AND TRUNC(md.DELIVERY_TIME)
            BETWEEN TRUNC(SYSDATE) - 6
            AND TRUNC(SYSDATE)
    ";

}

elseif ($dateFilter === 'month') {

    $dateCondition = "
        AND TRUNC(md.DELIVERY_TIME)
            BETWEEN TRUNC(SYSDATE, 'MM')
            AND TRUNC(SYSDATE)
    ";

}

elseif (
    $dateFilter === 'custom'
    && $validCustomDate !== ''
) {

    $dateCondition = "
        AND TRUNC(md.DELIVERY_TIME)
            = TO_DATE(:custom_date, 'YYYY-MM-DD')
    ";

    $dateParameter = $validCustomDate;

}


/* ============================================================
   FETCH MEDICATION DELIVERY
   ============================================================ */

/*
    Important:

    This page is for medication related to ADMISSION.

    Workflow:

    Medication Order
          ↓
    Pharmacist prepares
          ↓
    Ready For Nurse Pickup
          ↓
    Nurse collects
          ↓
    MEDICATION_DELIVERY
          ↓
    Delivered
*/


$sql = "

SELECT

    mo.MEDORDER_ID,

    p.NAME AS PATIENT_NAME,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,


    /* ========================================================
       DELIVERY STAFF
       ======================================================== */

    hs.USERNAME AS DELIVERED_BY,


    /* ========================================================
       DELIVERY STATUS
       ======================================================== */

    CASE

        WHEN md.MEDORDER_ID IS NOT NULL
            THEN 'Delivered'

        ELSE
            'Pending'

    END AS STATUS,


    /* ========================================================
       DELIVERY DATE
       DATE ONLY
       ======================================================== */

    TO_CHAR(
        md.DELIVERY_TIME,
        'DD-MON-YYYY'
    ) AS DELIVERY_DATE,


    /* ========================================================
       PREPARATION STATUS
       ======================================================== */

    pp.STATUS AS PREPARATION_STATUS,


    /* ========================================================
       PREPARED DATE
       DATE ONLY
       ======================================================== */

    TO_CHAR(
        pp.PREPARED_TIME,
        'DD-MON-YYYY'
    ) AS PREPARED_DATE,


    /* ========================================================
       ORDER TYPE
       ======================================================== */

    CASE

        WHEN mo.ADMISSION_ID IS NOT NULL
            THEN 'Admission'

        WHEN mo.APPOINTMENT_ID IS NOT NULL
            THEN 'Appointment'

        WHEN mo.CONSULTATION_ID IS NOT NULL
            THEN 'Walk-In'

        ELSE
            'Unknown'

    END AS ORDER_TYPE,


    /* ========================================================
       LOCATION
       ======================================================== */

    NVL(
        w.WARD_NAME,
        'Pharmacy Counter'
    ) AS WARD_NAME,


    NVL(
        b.BED_NUMBER,
        '-'
    ) AS BED_NUMBER


FROM SYARMIMI.MEDICATION_ORDER mo


/* ============================================================
   PATIENT
   ============================================================ */

JOIN SYARMIMI.PATIENT p

    ON mo.PATIENT_ID = p.PATIENT_ID


/* ============================================================
   MEDICATION
   ============================================================ */

JOIN SYARMIMI.MEDICATION m

    ON mo.MEDICATION_ID = m.MEDICATION_ID


/* ============================================================
   ADMISSION
   ============================================================ */

LEFT JOIN SYARMIMI.ADMISSION a

    ON mo.ADMISSION_ID = a.ADMISSION_ID


/* ============================================================
   BED
   ============================================================ */

LEFT JOIN SYARMIMI.BED b

    ON a.BED_ID = b.BED_ID


/* ============================================================
   WARD
   ============================================================ */

LEFT JOIN SYARMIMI.WARD w

    ON b.WARD_ID = w.WARD_ID


/* ============================================================
   LATEST PREPARATION
   ============================================================ */

LEFT JOIN (

    SELECT

        MEDORDER_ID,

        MAX(PREP_ID) AS PREP_ID

    FROM SYARMIMI.PHARMACY_PREPARATION

    GROUP BY MEDORDER_ID

) latest_pp

    ON mo.MEDORDER_ID = latest_pp.MEDORDER_ID


LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp

    ON latest_pp.PREP_ID = pp.PREP_ID


/* ============================================================
   LATEST DELIVERY
   ============================================================ */

LEFT JOIN (

    SELECT

        MEDORDER_ID,

        MAX(MEDDELIVERY_ID) AS MEDDELIVERY_ID

    FROM SYARMIMI.MEDICATION_DELIVERY

    GROUP BY MEDORDER_ID

) latest_md

    ON mo.MEDORDER_ID = latest_md.MEDORDER_ID


LEFT JOIN SYARMIMI.MEDICATION_DELIVERY md

    ON latest_md.MEDDELIVERY_ID = md.MEDDELIVERY_ID


/* ============================================================
   DELIVERY STAFF
   ============================================================ */

LEFT JOIN SYARMIMI.HOSPITAL_STAFF hs

    ON md.ACCOUNT_ID = hs.ACCOUNT_ID


/* ============================================================
   ONLY ADMISSION MEDICATION
   ============================================================ */

WHERE mo.ADMISSION_ID IS NOT NULL

$dateCondition


ORDER BY mo.MEDORDER_ID DESC

";


try {

    $stmt = $conn->prepare($sql);

    if (
        $dateFilter === 'custom'
        && $dateParameter !== null
    ) {

        $stmt->bindValue(
            ':custom_date',
            $dateParameter,
            PDO::PARAM_STR
        );

    }

    $stmt->execute();

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Database Error: "
        . htmlspecialchars($e->getMessage())
    );

}


/* ============================================================
   DATE FILTER LABEL
   ============================================================ */

$dateLabel = 'All Dates';

switch ($dateFilter) {

    case 'today':

        $dateLabel = 'Today';

        break;


    case 'yesterday':

        $dateLabel = 'Yesterday';

        break;


    case '7days':

        $dateLabel = 'Last 7 Days';

        break;


    case 'month':

        $dateLabel = 'This Month';

        break;


    case 'custom':

        if ($validCustomDate !== '') {

            $dateLabel = date(
                'd-M-Y',
                strtotime($validCustomDate)
            );

        } else {

            $dateLabel = 'Custom Date';

        }

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

<title>Medication Delivery</title>


<!-- =========================================================
     BOOTSTRAP
========================================================= -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- =========================================================
     BOOTSTRAP ICONS
========================================================= -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<!-- =========================================================
     DATATABLES
========================================================= -->

<link
    rel="stylesheet"
    href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css"
>


<style>

body {

    background: #eef2f7;

}


.box {

    background: white;

    padding: 25px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.05);

}


.table td {

    vertical-align: middle;

}


.table th {

    white-space: nowrap;

}


.badge {

    padding: 7px 11px;

    border-radius: 20px;

}


/* ============================================================
   DATATABLES
============================================================ */

.dataTables_wrapper .row:first-child {

    display: flex !important;

    align-items: center !important;

    justify-content: space-between !important;

    margin-bottom: 15px;

    width: 100%;

}


.dataTables_length select {

    width: auto !important;

    display: inline-block !important;

    margin: 0 5px !important;

    padding-right: 30px !important;

}


.dataTables_filter {

    text-align: right !important;

}


.dataTables_filter input {

    width: 200px !important;

    display: inline-block !important;

    margin-left: 10px !important;

}


.dataTables_info {

    text-align: right !important;

    margin-top: 15px !important;

    padding-top: 0 !important;

}


.dataTables_paginate {

    display: flex !important;

    justify-content: flex-end !important;

    margin-top: 10px !important;

}


/* ============================================================
   DATE FILTER
============================================================ */

.custom-date-box {

    display: none;

}


.custom-date-box.show {

    display: block;

}

</style>

</head>


<body>


<div class="d-flex">


<!-- =========================================================
     SIDEBAR
========================================================= -->

<?php include("../includes/sidebar_admin.php"); ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="p-4 w-100">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h4 class="mb-1">

💊 Medication Delivery

</h4>

<small class="text-muted">

Monitor medication delivery for admitted patients

</small>

</div>


<div>

<span class="badge bg-primary fs-6">

<?= htmlspecialchars($dateLabel) ?>

</span>

</div>

</div>


<!-- =========================================================
     FILTER BOX
========================================================= -->

<div class="box mb-4">


<div class="row g-3 align-items-end">


<!-- =====================================================
     SEARCH
===================================================== -->

<div class="col-md-3">

<label class="form-label fw-semibold">

Search

</label>

<input
    type="text"
    id="deliverySearch"
    class="form-control"
    placeholder="🔍 Patient / Medication"
>

</div>


<!-- =====================================================
     STATUS
===================================================== -->

<div class="col-md-2">

<label class="form-label fw-semibold">

Status

</label>

<select
    id="statusFilter"
    class="form-select"
>

<option value="">

All Status

</option>

<option value="Pending">

Pending

</option>

<option value="Delivered">

Delivered

</option>

</select>

</div>


<!-- =====================================================
     DATE
===================================================== -->

<div class="col-md-2">

<label class="form-label fw-semibold">

Date

</label>

<select
    id="dateFilter"
    class="form-select"
>

<option
    value="all"
    <?= $dateFilter === 'all' ? 'selected' : '' ?>
>

All Dates

</option>


<option
    value="today"
    <?= $dateFilter === 'today' ? 'selected' : '' ?>
>

Today

</option>


<option
    value="yesterday"
    <?= $dateFilter === 'yesterday' ? 'selected' : '' ?>
>

Yesterday

</option>


<option
    value="7days"
    <?= $dateFilter === '7days' ? 'selected' : '' ?>
>

Last 7 Days

</option>


<option
    value="month"
    <?= $dateFilter === 'month' ? 'selected' : '' ?>
>

This Month

</option>


<option
    value="custom"
    <?= $dateFilter === 'custom' ? 'selected' : '' ?>
>

Custom Date

</option>

</select>

</div>


<!-- =====================================================
     CUSTOM DATE
===================================================== -->

<div
    class="col-md-2 custom-date-box
    <?= $dateFilter === 'custom' ? 'show' : '' ?>"
    id="customDateBox"
>

<label class="form-label fw-semibold">

Choose Date

</label>

<input
    type="date"
    id="customDate"
    class="form-control"
    value="<?= htmlspecialchars($validCustomDate) ?>"
>

</div>


<!-- =====================================================
     SORT
===================================================== -->

<div class="col-md-2">

<label class="form-label fw-semibold">

Sort

</label>

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


<!-- =====================================================
     PDF BUTTON
===================================================== -->

<div class="col-md-1">

<a
    href="#"
    id="pdfButton"
    class="btn btn-danger w-100"
    title="Generate PDF"
>

<i class="bi bi-file-earmark-pdf"></i>

</a>

</div>


</div>


<div class="mt-3 text-muted small">

<i class="bi bi-info-circle"></i>

For date reports, the selected date refers to the
<strong>delivery date</strong>.

</div>


</div>


<!-- =========================================================
     TABLE
========================================================= -->

<div class="box">


<div class="table-responsive">


<table
    id="deliveryTable"
    class="table table-bordered table-hover"
>


<thead class="table-dark">


<tr>

<th>ID</th>

<th>Patient</th>

<th>Medication</th>

<th>Dosage</th>

<th>Frequency</th>

<th>Status</th>

<th>Delivered By</th>

<th>Delivery Date</th>

<th>Action</th>

</tr>


</thead>


<tbody>


<?php foreach ($orders as $row): ?>


<tr>


<!-- =====================================================
     ID
===================================================== -->

<td>

<?= htmlspecialchars($row['MEDORDER_ID']) ?>

</td>


<!-- =====================================================
     PATIENT
===================================================== -->

<td>

<?= htmlspecialchars($row['PATIENT_NAME']) ?>

</td>


<!-- =====================================================
     MEDICATION
===================================================== -->

<td>

<?= htmlspecialchars($row['MEDICATION_NAME']) ?>

</td>


<!-- =====================================================
     DOSAGE
===================================================== -->

<td>

<?= htmlspecialchars($row['DOSAGE']) ?>

</td>


<!-- =====================================================
     FREQUENCY
===================================================== -->

<td>

<?= htmlspecialchars($row['FREQUENCY']) ?>

</td>


<!-- =====================================================
     STATUS
===================================================== -->

<td>

<?php if ($row['STATUS'] === 'Delivered'): ?>

<span class="badge bg-success">

Delivered

</span>

<?php else: ?>

<span class="badge bg-warning text-dark">

Pending

</span>

<?php endif; ?>

</td>


<!-- =====================================================
     DELIVERED BY
===================================================== -->

<td>

<?= !empty($row['DELIVERED_BY'])
    ? htmlspecialchars($row['DELIVERED_BY'])
    : '-' ?>

</td>


<!-- =====================================================
     DELIVERY DATE
===================================================== -->

<td>

<?= !empty($row['DELIVERY_DATE'])
    ? htmlspecialchars($row['DELIVERY_DATE'])
    : '-' ?>

</td>


<!-- =====================================================
     ACTION
===================================================== -->

<td>

<a
    href="medication_order_details.php?id=<?= urlencode($row['MEDORDER_ID']) ?>"
    class="btn btn-primary btn-sm"
>

<i class="bi bi-eye"></i>

View

</a>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>


<!-- =========================================================
     JQUERY
========================================================= -->

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
></script>


<!-- =========================================================
     DATATABLES
========================================================= -->

<script
    src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"
></script>

<script
    src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"
></script>


<script>


$(document).ready(function () {


/* ============================================================
   DATATABLE
============================================================ */

const table = $('#deliveryTable').DataTable({

    dom:
        '<"row"<"col-sm-6"l><"col-sm-6 text-end"f>>' +
        't' +
        '<"row"<"col-sm-12 text-end"i><"col-sm-12 text-end"p>>',

    pageLength: 10,

    order: [
        [0, 'desc']
    ],

    lengthMenu: [

        [10, 25, 50, 100],

        [10, 25, 50, 100]

    ],

    language: {

        lengthMenu: "Show _MENU_ entries",

        search: "Search:"

    }

});


/* ============================================================
   SEARCH
============================================================ */

$('#deliverySearch').on(
    'keyup',
    function () {

        table

            .search(this.value)

            .draw();

    }
);


/* ============================================================
   STATUS FILTER
============================================================ */

$('#statusFilter').on(
    'change',
    function () {

        table

            .column(5)

            .search(this.value)

            .draw();

    }
);


/* ============================================================
   SORT
============================================================ */

$('#sortFilter').on(
    'change',
    function () {

        table

            .order([
                [0, this.value]
            ])

            .draw();

    }
);


/* ============================================================
   DATE FILTER
============================================================ */

$('#dateFilter').on(
    'change',
    function () {

        const value = this.value;


        if (value === 'custom') {

            $('#customDateBox')
                .addClass('show');

            return;

        }


        $('#customDateBox')
            .removeClass('show');


        const url =
            new URL(
                window.location.href
            );


        url.searchParams.set(
            'date',
            value
        );


        url.searchParams.delete(
            'custom_date'
        );


        window.location.href =
            url.toString();

    }
);


/* ============================================================
   CUSTOM DATE
============================================================ */

$('#customDate').on(
    'change',
    function () {

        if (!this.value) {

            return;

        }


        const url =
            new URL(
                window.location.href
            );


        url.searchParams.set(
            'date',
            'custom'
        );


        url.searchParams.set(
            'custom_date',
            this.value
        );


        window.location.href =
            url.toString();

    }
);


/* ============================================================
   PDF BUTTON
============================================================ */

$('#pdfButton').on(
    'click',
    function (e) {

        e.preventDefault();


        const url =
            new URL(
                'medication_delivery_pdf.php',
                window.location.href
            );


        /* ----------------------------------------------------
           DATE
        ---------------------------------------------------- */

        const date =
            $('#dateFilter').val();


        url.searchParams.set(
            'date',
            date
        );


        /* ----------------------------------------------------
           CUSTOM DATE
        ---------------------------------------------------- */

        if (date === 'custom') {

            const customDate =
                $('#customDate').val();


            if (!customDate) {

                alert(
                    'Please select a custom date first.'
                );

                return;

            }


            url.searchParams.set(
                'custom_date',
                customDate
            );

        }


        /* ----------------------------------------------------
           STATUS
        ---------------------------------------------------- */

        const status =
            $('#statusFilter').val();


        if (status !== '') {

            url.searchParams.set(
                'status',
                status
            );

        }


        /* ----------------------------------------------------
           OPEN PDF REPORT
        ---------------------------------------------------- */

        window.open(
            url.toString(),
            '_blank'
        );

    }
);


});

</script>


</body>

</html>