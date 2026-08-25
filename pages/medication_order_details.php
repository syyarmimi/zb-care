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
   GET MEDICATION ORDER ID
   ============================================================ */

$medOrderId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;


if ($medOrderId <= 0) {

    die("Invalid medication order ID.");

}


/* ============================================================
   FETCH MEDICATION ORDER DETAILS
   ============================================================ */

$sql = "

SELECT

    mo.MEDORDER_ID,

    p.NAME AS PATIENT_NAME,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,


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
    ) AS BED_NUMBER,


    /* ========================================================
       PREPARATION
       ======================================================== */

    NVL(
        pp.STATUS,
        'Pending'
    ) AS PREPARATION_STATUS,


    TO_CHAR(
        pp.PREPARED_TIME,
        'DD-MON-YYYY'
    ) AS PREPARED_DATE,


    /* ========================================================
       DELIVERY
       ======================================================== */

    CASE

        WHEN md.MEDORDER_ID IS NOT NULL
            THEN 'Delivered'

        ELSE
            'Pending'

    END AS DELIVERY_STATUS,


    hs.USERNAME AS DELIVERED_BY,


    TO_CHAR(
        md.DELIVERY_TIME,
        'DD-MON-YYYY'
    ) AS DELIVERY_DATE


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
   STAFF
============================================================ */

LEFT JOIN SYARMIMI.HOSPITAL_STAFF hs

    ON md.ACCOUNT_ID = hs.ACCOUNT_ID


/* ============================================================
   MEDICATION ORDER
============================================================ */

WHERE mo.MEDORDER_ID = :medorder_id

";


try {

    $stmt = $conn->prepare($sql);

    $stmt->bindValue(
        ':medorder_id',
        $medOrderId,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $order = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Database Error: "
        . htmlspecialchars($e->getMessage())
    );

}


/* ============================================================
   ORDER NOT FOUND
   ============================================================ */

if (!$order) {

    die("Medication order not found.");

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

<title>Medication Order Details</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<style>

body {

    background: #eef2f7;

}


.details-box {

    background: white;

    padding: 30px;

    border-radius: 15px;

    box-shadow:
        0 5px 15px rgba(0,0,0,0.05);

}


.detail-label {

    font-size: 13px;

    color: #6c757d;

    font-weight: 600;

    margin-bottom: 5px;

}


.detail-value {

    font-size: 16px;

    font-weight: 500;

    margin-bottom: 20px;

}


.section-title {

    font-weight: 700;

    color: #172033;

    border-bottom: 1px solid #dee2e6;

    padding-bottom: 10px;

    margin-bottom: 20px;

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
     MAIN
========================================================= -->

<div class="p-4 w-100">


<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">


<div>

<h4 class="fw-bold mb-1">

💊 Medication Order Details

</h4>

<small class="text-muted">

Medication Order #<?= htmlspecialchars($order['MEDORDER_ID']) ?>

</small>

</div>


<a
    href="med.php"
    class="btn btn-secondary"
>

<i class="bi bi-arrow-left"></i>

Back

</a>


</div>


<!-- =========================================================
     DETAILS
========================================================= -->

<div class="details-box">


<!-- =========================================================
     PATIENT & ORDER
========================================================= -->

<h5 class="section-title">

<i class="bi bi-person"></i>

Patient & Order Information

</h5>


<div class="row">


<div class="col-md-6">

<div class="detail-label">

Medication Order ID

</div>

<div class="detail-value">

<?= htmlspecialchars($order['MEDORDER_ID']) ?>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Patient

</div>

<div class="detail-value">

<?= htmlspecialchars($order['PATIENT_NAME']) ?>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Order Type

</div>

<div class="detail-value">

<span class="badge bg-danger">

<?= htmlspecialchars($order['ORDER_TYPE']) ?>

</span>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Location

</div>

<div class="detail-value">

<?= htmlspecialchars($order['WARD_NAME']) ?>


<?php if ($order['BED_NUMBER'] !== '-'): ?>

<br>

<small class="text-muted">

Bed <?= htmlspecialchars($order['BED_NUMBER']) ?>

</small>

<?php endif; ?>

</div>

</div>

</div>


<!-- =========================================================
     MEDICATION
========================================================= -->

<h5 class="section-title mt-3">

<i class="bi bi-capsule"></i>

Medication Information

</h5>


<div class="row">


<div class="col-md-6">

<div class="detail-label">

Medication

</div>

<div class="detail-value">

<?= htmlspecialchars($order['MEDICATION_NAME']) ?>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Dosage

</div>

<div class="detail-value">

<?= htmlspecialchars($order['DOSAGE']) ?>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Frequency

</div>

<div class="detail-value">

<?= htmlspecialchars($order['FREQUENCY']) ?>

</div>

</div>

</div>


<!-- =========================================================
     PREPARATION
========================================================= -->

<h5 class="section-title mt-3">

<i class="bi bi-prescription2"></i>

Pharmacy Preparation

</h5>


<div class="row">


<div class="col-md-6">

<div class="detail-label">

Preparation Status

</div>

<div class="detail-value">


<?php if (
    $order['PREPARATION_STATUS']
    === 'Pending'
): ?>

<span class="badge bg-warning text-dark">

Pending

</span>


<?php elseif (
    $order['PREPARATION_STATUS']
    === 'Ready For Nurse Pickup'
): ?>

<span class="badge bg-info">

Ready For Nurse Pickup

</span>


<?php elseif (
    $order['PREPARATION_STATUS']
    === 'Collected'
): ?>

<span class="badge bg-success">

Collected

</span>


<?php else: ?>

<span class="badge bg-secondary">

<?= htmlspecialchars(
    $order['PREPARATION_STATUS']
) ?>

</span>

<?php endif; ?>


</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Prepared Date

</div>

<div class="detail-value">

<?= !empty($order['PREPARED_DATE'])
    ? htmlspecialchars($order['PREPARED_DATE'])
    : '-' ?>

</div>

</div>

</div>


<!-- =========================================================
     DELIVERY
========================================================= -->

<h5 class="section-title mt-3">

<i class="bi bi-truck"></i>

Medication Delivery

</h5>


<div class="row">


<div class="col-md-6">

<div class="detail-label">

Delivery Status

</div>

<div class="detail-value">


<?php if (
    $order['DELIVERY_STATUS']
    === 'Delivered'
): ?>

<span class="badge bg-success">

Delivered

</span>


<?php else: ?>

<span class="badge bg-warning text-dark">

Pending

</span>

<?php endif; ?>


</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Delivered By

</div>

<div class="detail-value">

<?= !empty($order['DELIVERED_BY'])
    ? htmlspecialchars($order['DELIVERED_BY'])
    : '-' ?>

</div>

</div>


<div class="col-md-6">

<div class="detail-label">

Delivery Date

</div>

<div class="detail-value">

<?= !empty($order['DELIVERY_DATE'])
    ? htmlspecialchars($order['DELIVERY_DATE'])
    : '-' ?>

</div>

</div>

</div>


</div>


</div>


</div>


</body>

</html>