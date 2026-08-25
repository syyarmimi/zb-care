<?php
session_start();
include("../config/config.php");
/* ============================================================
   ROLE CHECK
   ============================================================ */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {

    die("Access Denied");

}


/* ============================================================
   GET FILTERS
   ============================================================ */

$dateFilter = $_GET['date'] ?? 'all';
$statusFilter = $_GET['status'] ?? '';
$allowedDateFilters = [

    'all',
    'today',
    'yesterday',
    '7days',
    'month',
    'custom'
];

if (
    !in_array(
        $dateFilter,
        $allowedDateFilters,
        true
    )
) 
{

    $dateFilter = 'all';
}


if (
    $statusFilter !== 'Pending'
    && $statusFilter !== 'Delivered'
) {

    $statusFilter = '';

}


$customDate = $_GET['custom_date'] ?? '';
$validCustomDate = '';
if (
    $dateFilter === 'custom'
    && preg_match(
        '/^\d{4}-\d{2}-\d{2}$/',
        $customDate
    )
) {

    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $customDate
        );
    if (
        $dateObject
        && $dateObject->format('Y-m-d')
            === $customDate
    ) {

        $validCustomDate = $customDate;

    }

}


/* ============================================================
   DATE CONDITION
============================================================ */

$dateCondition = '';
$dateParameter = null;
if ($dateFilter === 'today') {
    $dateCondition = "

        AND md.DELIVERY_TIME >= TRUNC(SYSDATE)

        AND md.DELIVERY_TIME < TRUNC(SYSDATE) + 1

    ";

}

elseif ($dateFilter === 'yesterday') {
    $dateCondition = "
        AND md.DELIVERY_TIME >= TRUNC(SYSDATE) - 1
        AND md.DELIVERY_TIME < TRUNC(SYSDATE)

    ";

}

elseif ($dateFilter === '7days') {
    $dateCondition = "
        AND md.DELIVERY_TIME >= TRUNC(SYSDATE) - 6
        AND md.DELIVERY_TIME < TRUNC(SYSDATE) + 1
    ";

}

elseif ($dateFilter === 'month') {
    $dateCondition = "
        AND md.DELIVERY_TIME >= TRUNC(SYSDATE, 'MM')
        AND md.DELIVERY_TIME < TRUNC(SYSDATE) + 1
    ";

}

elseif (
    $dateFilter === 'custom'
    && $validCustomDate !== ''
) {

    $dateCondition = "

        AND md.DELIVERY_TIME >=
            TO_DATE(:custom_date, 'YYYY-MM-DD')

        AND md.DELIVERY_TIME <
            TO_DATE(:custom_date, 'YYYY-MM-DD') + 1

    ";

    /*
        IMPORTANT:

        Do NOT bind the same named parameter twice
        with PDO ODBC.

        Therefore we use two different placeholders below.
    */

    $dateCondition = "

        AND md.DELIVERY_TIME >=
            TO_DATE(:custom_date_start, 'YYYY-MM-DD')

        AND md.DELIVERY_TIME <
            TO_DATE(:custom_date_end, 'YYYY-MM-DD') + 1

    ";

    $dateParameter = $validCustomDate;

}

/* ============================================================
   STATUS CONDITION
============================================================ */

$statusCondition = '';

if ($statusFilter === 'Delivered') {

    $statusCondition = "

        AND md.MEDORDER_ID IS NOT NULL

    ";

}

elseif ($statusFilter === 'Pending') {

    /*
        Pending medication does not have DELIVERY_TIME.

        Therefore when a date is selected, Pending records
        cannot belong to a delivery date.

        If a date filter is used, the report will therefore
        naturally contain delivered records for that date.
    */

    $statusCondition = "

        AND md.MEDORDER_ID IS NULL

    ";

}


/* ============================================================
   FETCH REPORT
============================================================ */

$sql = "

SELECT

    mo.MEDORDER_ID,

    p.NAME AS PATIENT_NAME,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,


    CASE

        WHEN md.MEDORDER_ID IS NOT NULL
            THEN 'Delivered'

        ELSE
            'Pending'

    END AS STATUS,


    hs.USERNAME AS DELIVERED_BY,


    TO_CHAR(
        md.DELIVERY_TIME,
        'DD-MON-YYYY'
    ) AS DELIVERY_DATE,


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
   ADMISSION ONLY
============================================================ */

WHERE mo.ADMISSION_ID IS NOT NULL

$dateCondition

$statusCondition


ORDER BY mo.MEDORDER_ID DESC

";


try {

    $stmt = $conn->prepare($sql);


    if (
        $dateFilter === 'custom'
        && $dateParameter !== null
    ) {

        $stmt->bindValue(
            ':custom_date_start',
            $dateParameter,
            PDO::PARAM_STR
        );

        $stmt->bindValue(
            ':custom_date_end',
            $dateParameter,
            PDO::PARAM_STR
        );

    }


    $stmt->execute();

    $records =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die(
        "Database Error: "
        . htmlspecialchars($e->getMessage())
    );

}


/* ============================================================
   REPORT TITLE
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

            $dateLabel =
                date(
                    'd-M-Y',
                    strtotime($validCustomDate)
                );

        }

        break;

}


$statusLabel =
    $statusFilter !== ''
    ? $statusFilter
    : 'All Status';


?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<title>Medication Delivery Report</title>


<style>

/* ============================================================
   PAGE
============================================================ */

body {

    font-family: Arial, Helvetica, sans-serif;

    margin: 30px;

    color: #222;

}


/* ============================================================
   HEADER
============================================================ */

.header {

    text-align: center;

    margin-bottom: 25px;

}


.header h1 {

    margin: 0;

    font-size: 24px;

}


.header h2 {

    margin: 5px 0;

    font-size: 18px;

}


.header p {

    margin: 4px 0;

    font-size: 13px;

    color: #555;

}


/* ============================================================
   REPORT INFORMATION
============================================================ */

.report-info {

    margin-bottom: 20px;

    padding: 12px;

    border: 1px solid #ddd;

    background: #f8f9fa;

}


.report-info strong {

    margin-right: 5px;

}


/* ============================================================
   TABLE
============================================================ */

table {

    width: 100%;

    border-collapse: collapse;

    font-size: 11px;

}


th {

    background: #212529;

    color: white;

    padding: 8px;

    border: 1px solid #111;

    text-align: left;

}


td {

    padding: 7px;

    border: 1px solid #ccc;

    vertical-align: middle;

}


/* ============================================================
   FOOTER
============================================================ */

.footer {

    margin-top: 20px;

    font-size: 11px;

    color: #666;

    text-align: right;

}


/* ============================================================
   PRINT
============================================================ */

@media print {

    body {

        margin: 10mm;

    }


    .no-print {

        display: none !important;

    }


    @page {

        size: A4 landscape;

        margin: 10mm;

    }

}

</style>

</head>


<body>


<!-- =========================================================
     PRINT BUTTON
========================================================= -->

<div class="no-print"
     style="text-align:right; margin-bottom:20px;">

<button
    onclick="window.print()"
    style="
        padding:10px 18px;
        border:0;
        border-radius:6px;
        background:#dc3545;
        color:white;
        cursor:pointer;
        font-size:14px;
    "
>

📄 Save / Print PDF

</button>

</div>


<!-- =========================================================
     REPORT HEADER
========================================================= -->

<div class="header">

<h1>

ZB-CARE SPECIALIST HOSPITAL

</h1>


<h2>

MEDICATION DELIVERY REPORT

</h2>


<p>

Generated:
<?= date('d-M-Y') ?>

</p>

</div>


<!-- =========================================================
     REPORT FILTER
========================================================= -->

<div class="report-info">


<strong>Date:</strong>

<?= htmlspecialchars($dateLabel) ?>


&nbsp;&nbsp;&nbsp;


<strong>Status:</strong>

<?= htmlspecialchars($statusLabel) ?>


&nbsp;&nbsp;&nbsp;


<strong>Total Records:</strong>

<?= count($records) ?>


</div>


<!-- =========================================================
     REPORT TABLE
========================================================= -->

<table>


<thead>

<tr>

<th>ID</th>

<th>Patient</th>

<th>Location</th>

<th>Medication</th>

<th>Dosage</th>

<th>Frequency</th>

<th>Status</th>

<th>Delivered By</th>

<th>Delivery Date</th>

</tr>

</thead>


<tbody>


<?php if (count($records) === 0): ?>


<tr>

<td
    colspan="9"
    style="text-align:center;"
>

No medication delivery records found.

</td>

</tr>


<?php else: ?>


<?php foreach ($records as $row): ?>


<tr>


<td>

<?= htmlspecialchars(
    $row['MEDORDER_ID']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['PATIENT_NAME']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['WARD_NAME']
) ?>


<?php if (
    $row['BED_NUMBER'] !== '-'
): ?>

<br>

Bed
<?= htmlspecialchars(
    $row['BED_NUMBER']
) ?>

<?php endif; ?>

</td>


<td>

<?= htmlspecialchars(
    $row['MEDICATION_NAME']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['DOSAGE']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['FREQUENCY']
) ?>

</td>


<td>

<?= htmlspecialchars(
    $row['STATUS']
) ?>

</td>


<td>

<?= !empty($row['DELIVERED_BY'])
    ? htmlspecialchars($row['DELIVERED_BY'])
    : '-' ?>

</td>


<td>

<?= !empty($row['DELIVERY_DATE'])
    ? htmlspecialchars($row['DELIVERY_DATE'])
    : '-' ?>

</td>


</tr>


<?php endforeach; ?>


<?php endif; ?>


</tbody>


</table>


<!-- =========================================================
     FOOTER
========================================================= -->

<div class="footer">

ZB-CARE Medication Management System

</div>


<script>

/*
    Automatically open the browser print dialog.

    From there choose:

    Destination:
        Save as PDF

    Then click:
        Save
*/

window.addEventListener(
    'load',
    function () {

        setTimeout(
            function () {

                window.print();

            },
            500
        );

    }
);

</script>


</body>

</html>