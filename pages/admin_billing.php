<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   HELPERS
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function money($amount)
{
    return number_format(
        (float)$amount,
        2
    );
}


/* =========================================================
   FILTERS
========================================================= */

$search =
    trim(
        $_GET['search']
        ?? ''
    );

$statusFilter =
    strtoupper(
        trim(
            $_GET['status']
            ?? ''
        )
    );

$typeFilter =
    strtoupper(
        trim(
            $_GET['type']
            ?? ''
        )
    );

$allowedStatuses = [
    '',
    'PAID',
    'UNPAID'
];

$allowedTypes = [
    '',
    'APPOINTMENT',
    'WALKIN',
    'ADMISSION'
];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if (!in_array($typeFilter, $allowedTypes, true)) {
    $typeFilter = '';
}


/* =========================================================
   SUMMARY
========================================================= */

$totalBills = 0;
$paidBills = 0;
$unpaidBills = 0;
$totalRevenue = 0.00;
$todayRevenue = 0.00;

$errorMessage = '';

try {

    $summaryStmt =
        $conn->query("
            SELECT
                COUNT(*) AS TOTAL_BILLS,

                SUM(
                    CASE
                        WHEN UPPER(TRIM(STATUS)) = 'PAID'
                        THEN 1
                        ELSE 0
                    END
                ) AS PAID_BILLS,

                SUM(
                    CASE
                        WHEN UPPER(TRIM(STATUS)) = 'UNPAID'
                        THEN 1
                        ELSE 0
                    END
                ) AS UNPAID_BILLS

            FROM SYARMIMI.BILL
        ");

    $summary =
        $summaryStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($summary) {

        $totalBills =
            (int)($summary['TOTAL_BILLS'] ?? 0);

        $paidBills =
            (int)($summary['PAID_BILLS'] ?? 0);

        $unpaidBills =
            (int)($summary['UNPAID_BILLS'] ?? 0);
    }


    $revenueStmt =
        $conn->query("
            SELECT
                NVL(
                    SUM(
                        CASE
                            WHEN UPPER(TRIM(PAYMENT_STATUS)) = 'PAID'
                            THEN AMOUNT
                            ELSE 0
                        END
                    ),
                    0
                ) AS TOTAL_REVENUE,

                NVL(
                    SUM(
                        CASE
                            WHEN
                                UPPER(TRIM(PAYMENT_STATUS)) = 'PAID'
                                AND TRUNC(PAYMENT_DATE) = TRUNC(SYSDATE)
                            THEN AMOUNT
                            ELSE 0
                        END
                    ),
                    0
                ) AS TODAY_REVENUE

            FROM SYARMIMI.PAYMENT
        ");

    $revenue =
        $revenueStmt->fetch(
            PDO::FETCH_ASSOC
        );

    if ($revenue) {

        $totalRevenue =
            (float)($revenue['TOTAL_REVENUE'] ?? 0);

        $todayRevenue =
            (float)($revenue['TODAY_REVENUE'] ?? 0);
    }

}
catch (Throwable $e) {

    $errorMessage =
        "Unable to load billing summary. " .
        $e->getMessage();
}


/* =========================================================
   BILL LIST
========================================================= */

$bills = [];

try {

    $sql = "
        SELECT
            B.BILL_ID,
            B.PATIENT_ID,
            B.APPOINTMENT_ID,
            B.CONSULTATION_ID,
            B.ADMISSION_ID,
            B.TOTAL_AMOUNT,
            B.STATUS,

            TO_CHAR(
                B.BILL_DATE,
                'DD-MON-YYYY'
            ) AS BILL_DATE_DISPLAY,

            P.NAME AS PATIENT_NAME,
            P.IC_NUMBER,
            P.PHONE,

            CASE
                WHEN B.ADMISSION_ID IS NOT NULL
                    THEN 'ADMISSION'
                WHEN B.CONSULTATION_ID IS NOT NULL
                    THEN 'WALKIN'
                WHEN B.APPOINTMENT_ID IS NOT NULL
                    THEN 'APPOINTMENT'
                ELSE 'GENERAL'
            END AS BILL_TYPE,

            PY.PAYMENT_ID,
            PY.PAYMENT_METHOD,
            PY.PAYMENT_STATUS,
            PY.REFERENCE_NO,

            TO_CHAR(
                PY.PAYMENT_DATE,
                'DD-MON-YYYY HH24:MI'
            ) AS PAYMENT_DATE_DISPLAY

        FROM SYARMIMI.BILL B

        JOIN SYARMIMI.PATIENT P
            ON B.PATIENT_ID =
               P.PATIENT_ID

        LEFT JOIN
        (
            SELECT
                X.PAYMENT_ID,
                X.BILL_ID,
                X.PAYMENT_METHOD,
                X.PAYMENT_STATUS,
                X.REFERENCE_NO,
                X.PAYMENT_DATE

            FROM SYARMIMI.PAYMENT X

            WHERE X.PAYMENT_ID =
            (
                SELECT MAX(Y.PAYMENT_ID)

                FROM SYARMIMI.PAYMENT Y

                WHERE Y.BILL_ID =
                      X.BILL_ID
            )
        ) PY
            ON PY.BILL_ID =
               B.BILL_ID

        WHERE 1 = 1
    ";

    $params = [];


    if ($search !== '') {

        $sql .= "
            AND
            (
                UPPER(P.NAME)
                    LIKE UPPER(?)

                OR

                REGEXP_REPLACE(
                    NVL(P.IC_NUMBER, ''),
                    '[^0-9]',
                    ''
                )
                    LIKE
                REGEXP_REPLACE(
                    NVL(?, ''),
                    '[^0-9]',
                    ''
                )

                OR

                TO_CHAR(B.BILL_ID)
                    LIKE ?
            )
        ";

        $likeSearch =
            '%' . $search . '%';

        $digitsOnly =
            preg_replace(
                '/\D+/',
                '',
                $search
            );

        $params[] = $likeSearch;
        $params[] = '%' . $digitsOnly . '%';
        $params[] = $likeSearch;
    }


    if ($statusFilter !== '') {

        $sql .= "
            AND UPPER(TRIM(B.STATUS)) = ?
        ";

        $params[] = $statusFilter;
    }


    if ($typeFilter === 'APPOINTMENT') {

        $sql .= "
            AND B.APPOINTMENT_ID IS NOT NULL
            AND B.ADMISSION_ID IS NULL
        ";

    }
    elseif ($typeFilter === 'WALKIN') {

        $sql .= "
            AND B.CONSULTATION_ID IS NOT NULL
            AND B.ADMISSION_ID IS NULL
        ";

    }
    elseif ($typeFilter === 'ADMISSION') {

        $sql .= "
            AND B.ADMISSION_ID IS NOT NULL
        ";
    }


    $sql .= "
        ORDER BY
            B.BILL_DATE DESC,
            B.BILL_ID DESC
    ";


    $billStmt =
        $conn->prepare(
            $sql
        );

    $billStmt->execute(
        $params
    );

    $bills =
        $billStmt->fetchAll(
            PDO::FETCH_ASSOC
        );

}
catch (Throwable $e) {

    if ($errorMessage === '') {

        $errorMessage =
            "Unable to load billing records. " .
            $e->getMessage();
    }
}


/* =========================================================
   VIEW BILL DETAILS
========================================================= */

$selectedBill = null;
$billItems = [];

$viewBillId =
    (int)(
        $_GET['view']
        ?? 0
    );

if ($viewBillId > 0) {

    try {

        $detailStmt =
            $conn->prepare("
                SELECT
                    B.BILL_ID,
                    B.PATIENT_ID,
                    B.APPOINTMENT_ID,
                    B.CONSULTATION_ID,
                    B.ADMISSION_ID,
                    B.TOTAL_AMOUNT,
                    B.STATUS,

                    TO_CHAR(
                        B.BILL_DATE,
                        'DD-MON-YYYY'
                    ) AS BILL_DATE_DISPLAY,

                    P.NAME AS PATIENT_NAME,
                    P.IC_NUMBER,
                    P.PHONE,

                    CASE
                        WHEN B.ADMISSION_ID IS NOT NULL
                            THEN 'ADMISSION'
                        WHEN B.CONSULTATION_ID IS NOT NULL
                            THEN 'WALKIN'
                        WHEN B.APPOINTMENT_ID IS NOT NULL
                            THEN 'APPOINTMENT'
                        ELSE 'GENERAL'
                    END AS BILL_TYPE,

                    PY.PAYMENT_ID,
                    PY.PAYMENT_METHOD,
                    PY.PAYMENT_STATUS,
                    PY.REFERENCE_NO,

                    TO_CHAR(
                        PY.PAYMENT_DATE,
                        'DD-MON-YYYY HH24:MI'
                    ) AS PAYMENT_DATE_DISPLAY

                FROM SYARMIMI.BILL B

                JOIN SYARMIMI.PATIENT P
                    ON B.PATIENT_ID =
                       P.PATIENT_ID

                LEFT JOIN
                (
                    SELECT
                        X.PAYMENT_ID,
                        X.BILL_ID,
                        X.PAYMENT_METHOD,
                        X.PAYMENT_STATUS,
                        X.REFERENCE_NO,
                        X.PAYMENT_DATE

                    FROM SYARMIMI.PAYMENT X

                    WHERE X.PAYMENT_ID =
                    (
                        SELECT MAX(Y.PAYMENT_ID)

                        FROM SYARMIMI.PAYMENT Y

                        WHERE Y.BILL_ID =
                              X.BILL_ID
                    )
                ) PY
                    ON PY.BILL_ID =
                       B.BILL_ID

                WHERE
                    B.BILL_ID = ?
            ");

        $detailStmt->execute([
            $viewBillId
        ]);

        $selectedBill =
            $detailStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($selectedBill) {

            $itemStmt =
                $conn->prepare("
                    SELECT
                        BILL_ITEM_ID,
                        ITEM_TYPE,
                        DESCRIPTION,
                        QUANTITY,
                        UNIT_PRICE,
                        SUBTOTAL

                    FROM SYARMIMI.BILL_ITEM

                    WHERE
                        BILL_ID = ?

                    ORDER BY
                        BILL_ITEM_ID
                ");

            $itemStmt->execute([
                $viewBillId
            ]);

            $billItems =
                $itemStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    }
    catch (Throwable $e) {

        $errorMessage =
            "Unable to load bill details. " .
            $e->getMessage();
    }
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
Billing & Payments
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    background:#f5f7fb;
    color:#1f2937;
    font-family:'Segoe UI',Arial,sans-serif;
}

.content{
    flex:1;
    min-width:0;
    min-height:100vh;
    padding:28px 30px 50px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom:22px;
}

.page-title{
    margin:0;
    color:#111827;
    font-size:29px;
    font-weight:800;
}

.page-subtitle{
    margin-top:5px;
    color:#64748b;
    font-size:13px;
}

.header-badge{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:8px 11px;
    border:1px solid #dbeafe;
    border-radius:999px;
    background:#eff6ff;
    color:#2563eb;
    font-size:11px;
    font-weight:700;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:13px;
    margin-bottom:19px;
}

.stat-card{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    min-height:116px;
    padding:17px;
    border:1px solid #e5eaf0;
    border-radius:14px;
    background:#fff;
    box-shadow:0 4px 15px rgba(15,23,42,.035);
}

.stat-label{
    color:#64748b;
    font-size:10px;
    font-weight:750;
    text-transform:uppercase;
    letter-spacing:.04em;
}

.stat-value{
    margin-top:8px;
    color:#0f172a;
    font-size:24px;
    font-weight:850;
    letter-spacing:-.5px;
}

.stat-sub{
    margin-top:3px;
    color:#94a3b8;
    font-size:10px;
}

.stat-icon{
    width:36px;
    height:36px;
    display:grid;
    place-items:center;
    flex:0 0 auto;
    border-radius:10px;
    font-size:16px;
}

.icon-total{
    background:#eff6ff;
    color:#2563eb;
}

.icon-paid{
    background:#ecfdf5;
    color:#059669;
}

.icon-unpaid{
    background:#fff7ed;
    color:#ea580c;
}

.icon-revenue{
    background:#f5f3ff;
    color:#7c3aed;
}

.icon-today{
    background:#fdf2f8;
    color:#db2777;
}

.card-box{
    margin-bottom:18px;
    padding:20px;
    border:1px solid #e5eaf0;
    border-radius:14px;
    background:#fff;
    box-shadow:0 4px 15px rgba(15,23,42,.035);
}

.filter-grid{
    display:grid;
    grid-template-columns:minmax(220px,1fr) 190px 190px auto;
    gap:10px;
    align-items:end;
}

.form-label{
    margin-bottom:5px;
    color:#475569;
    font-size:11px;
    font-weight:700;
}

.form-control,
.form-select{
    min-height:43px;
    border:1px solid #dce3eb;
    border-radius:9px;
    font-size:12px;
}

.btn{
    border-radius:9px;
    font-size:12px;
    font-weight:700;
}

.filter-actions{
    display:flex;
    gap:7px;
}

.table-wrap{
    overflow-x:auto;
    border:1px solid #edf0f3;
    border-radius:11px;
}

.table{
    margin:0;
    vertical-align:middle;
}

.table thead th{
    padding:12px 13px;
    border-bottom:1px solid #e8edf2;
    background:#f8fafc;
    color:#64748b;
    font-size:9px;
    font-weight:800;
    text-transform:uppercase;
    white-space:nowrap;
}

.table tbody td{
    padding:13px;
    border-color:#eef2f6;
    color:#334155;
    font-size:11px;
}

.patient-name{
    color:#0f172a;
    font-size:12px;
    font-weight:750;
}

.patient-ic{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}

.type-badge,
.status-badge{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:999px;
    font-size:9px;
    font-weight:800;
}

.type-appointment{
    background:#eff6ff;
    color:#2563eb;
}

.type-walkin{
    background:#fff7ed;
    color:#c2410c;
}

.type-admission{
    background:#f5f3ff;
    color:#7c3aed;
}

.status-paid{
    background:#ecfdf5;
    color:#15803d;
}

.status-unpaid{
    background:#fef2f2;
    color:#dc2626;
}

.amount{
    color:#0f172a;
    font-weight:800;
    white-space:nowrap;
}

.payment-method{
    color:#475569;
    font-size:10px;
}

.ref-no{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
}

.empty-state{
    padding:35px;
    color:#64748b;
    text-align:center;
    font-size:12px;
}

.detail-header{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:16px;
    margin-bottom:18px;
}

.detail-title{
    color:#0f172a;
    font-size:20px;
    font-weight:800;
}

.detail-meta{
    margin-top:4px;
    color:#64748b;
    font-size:11px;
}

.detail-total{
    color:#0f172a;
    text-align:right;
    font-size:25px;
    font-weight:850;
}

.detail-grid{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:9px;
    margin-bottom:18px;
}

.detail-box{
    padding:12px;
    border:1px solid #edf0f3;
    border-radius:9px;
    background:#f8fafc;
}

.detail-label{
    color:#94a3b8;
    font-size:9px;
    font-weight:750;
    text-transform:uppercase;
}

.detail-value{
    margin-top:4px;
    color:#334155;
    font-size:11px;
    font-weight:650;
}

.bill-items{
    overflow:hidden;
    border:1px solid #e7ecf2;
    border-radius:10px;
}

.bill-item-row{
    display:grid;
    grid-template-columns:minmax(0,1fr) 80px 100px 105px;
    gap:10px;
    align-items:center;
    padding:12px 14px;
    border-bottom:1px solid #edf1f5;
    font-size:11px;
}

.bill-item-row:last-child{
    border-bottom:0;
}

.bill-item-head{
    background:#f8fafc;
    color:#64748b;
    font-size:9px;
    font-weight:800;
    text-transform:uppercase;
}

.item-name{
    color:#334155;
    font-weight:700;
}

.item-type{
    margin-top:2px;
    color:#94a3b8;
    font-size:8px;
}

.total-line{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-top:12px;
    padding:13px 15px;
    border-radius:9px;
    background:#eff6ff;
}

.total-line strong{
    color:#1d4ed8;
    font-size:18px;
}

.alert{
    border-radius:10px;
    font-size:12px;
}

@media(max-width:1200px){

    .stats-grid{
        grid-template-columns:repeat(3,minmax(0,1fr));
    }
}

@media(max-width:900px){

    .content{
        padding:20px 18px 40px;
    }

    .stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }

    .filter-grid{
        grid-template-columns:1fr 1fr;
    }

    .detail-grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:600px){

    .stats-grid,
    .filter-grid,
    .detail-grid{
        grid-template-columns:1fr;
    }

    .page-header,
    .detail-header{
        flex-direction:column;
    }

    .detail-total{
        text-align:left;
    }

    .bill-item-row{
        grid-template-columns:minmax(0,1fr) 65px 90px;
    }

    .bill-item-row > :nth-child(3){
        display:none;
    }
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">

<div>

<h1 class="page-title">
Billing & Payments
</h1>

<div class="page-subtitle">
Monitor patient bills, payment status and transaction history.
</div>

</div>

<div class="header-badge">
<i class="bi bi-shield-check"></i>
Admin Monitoring
</div>

</div>


<?php if ($errorMessage !== ''): ?>

<div class="alert alert-danger">

<i class="bi bi-exclamation-circle me-1"></i>

<?= h($errorMessage) ?>

</div>

<?php endif; ?>


<!-- =====================================================
     SUMMARY
===================================================== -->

<div class="stats-grid">

<div class="stat-card">

<div>

<div class="stat-label">
Total Bills
</div>

<div class="stat-value">
<?= $totalBills ?>
</div>

<div class="stat-sub">
All generated bills
</div>

</div>

<div class="stat-icon icon-total">
<i class="bi bi-receipt"></i>
</div>

</div>


<div class="stat-card">

<div>

<div class="stat-label">
Paid Bills
</div>

<div class="stat-value">
<?= $paidBills ?>
</div>

<div class="stat-sub">
Completed payments
</div>

</div>

<div class="stat-icon icon-paid">
<i class="bi bi-check-circle"></i>
</div>

</div>


<div class="stat-card">

<div>

<div class="stat-label">
Unpaid Bills
</div>

<div class="stat-value">
<?= $unpaidBills ?>
</div>

<div class="stat-sub">
Outstanding bills
</div>

</div>

<div class="stat-icon icon-unpaid">
<i class="bi bi-clock-history"></i>
</div>

</div>


<div class="stat-card">

<div>

<div class="stat-label">
Total Revenue
</div>

<div class="stat-value">
RM <?= money($totalRevenue) ?>
</div>

<div class="stat-sub">
Paid transactions
</div>

</div>

<div class="stat-icon icon-revenue">
<i class="bi bi-cash-stack"></i>
</div>

</div>


<div class="stat-card">

<div>

<div class="stat-label">
Today's Revenue
</div>

<div class="stat-value">
RM <?= money($todayRevenue) ?>
</div>

<div class="stat-sub">
Payments received today
</div>

</div>

<div class="stat-icon icon-today">
<i class="bi bi-calendar-check"></i>
</div>

</div>

</div>


<!-- =====================================================
     FILTER
===================================================== -->

<div class="card-box">

<form
    method="GET"
    class="filter-grid"
>

<div>

<label class="form-label">
Search
</label>

<input
    type="text"
    name="search"
    class="form-control"
    placeholder="Patient name, IC number or bill ID"
    value="<?= h($search) ?>"
>

</div>


<div>

<label class="form-label">
Payment Status
</label>

<select
    name="status"
    class="form-select"
>

<option value="">
All Status
</option>

<option
    value="PAID"
    <?= $statusFilter === 'PAID'
        ? 'selected'
        : ''
    ?>
>
Paid
</option>

<option
    value="UNPAID"
    <?= $statusFilter === 'UNPAID'
        ? 'selected'
        : ''
    ?>
>
Unpaid
</option>

</select>

</div>


<div>

<label class="form-label">
Bill Type
</label>

<select
    name="type"
    class="form-select"
>

<option value="">
All Types
</option>

<option
    value="APPOINTMENT"
    <?= $typeFilter === 'APPOINTMENT'
        ? 'selected'
        : ''
    ?>
>
Appointment
</option>

<option
    value="WALKIN"
    <?= $typeFilter === 'WALKIN'
        ? 'selected'
        : ''
    ?>
>
Walk-In
</option>

<option
    value="ADMISSION"
    <?= $typeFilter === 'ADMISSION'
        ? 'selected'
        : ''
    ?>
>
Admission
</option>

</select>

</div>


<div class="filter-actions">

<button
    type="submit"
    class="btn btn-primary"
>
<i class="bi bi-funnel me-1"></i>
Filter
</button>

<a
    href="admin_billing.php"
    class="btn btn-outline-secondary"
>
Reset
</a>

</div>

</form>

</div>


<!-- =====================================================
     TABLE
===================================================== -->

<div class="card-box">

<div class="d-flex justify-content-between align-items-center mb-3">

<div>

<div class="fw-bold" style="font-size:15px;">
Billing Records
</div>

<div class="text-muted" style="font-size:10px;">
<?= count($bills) ?> record(s) shown
</div>

</div>

</div>


<div class="table-wrap">

<table class="table">

<thead>

<tr>

<th>Bill</th>
<th>Patient</th>
<th>Type</th>
<th>Date</th>
<th>Amount</th>
<th>Status</th>
<th>Payment</th>
<th>Action</th>

</tr>

</thead>

<tbody>

<?php if (!empty($bills)): ?>

<?php foreach ($bills as $bill): ?>

<?php
$billType =
    strtoupper(
        trim(
            (string)$bill['BILL_TYPE']
        )
    );

$status =
    strtoupper(
        trim(
            (string)$bill['STATUS']
        )
    );

$typeClass =
    $billType === 'APPOINTMENT'
        ? 'type-appointment'
        : (
            $billType === 'WALKIN'
            ? 'type-walkin'
            : 'type-admission'
        );
?>

<tr>

<td>
<strong>#<?= (int)$bill['BILL_ID'] ?></strong>
</td>


<td>

<div class="patient-name">
<?= h($bill['PATIENT_NAME']) ?>
</div>

<div class="patient-ic">
<?= h($bill['IC_NUMBER'] ?: '-') ?>
</div>

</td>


<td>

<span class="type-badge <?= $typeClass ?>">

<?= $billType === 'WALKIN'
    ? 'Walk-In'
    : h(ucfirst(strtolower($billType)))
?>

</span>

</td>


<td>
<?= h($bill['BILL_DATE_DISPLAY']) ?>
</td>


<td class="amount">
RM <?= money($bill['TOTAL_AMOUNT']) ?>
</td>


<td>

<span class="status-badge <?= $status === 'PAID'
    ? 'status-paid'
    : 'status-unpaid'
?>">

<?= h(ucfirst(strtolower($status))) ?>

</span>

</td>


<td>

<?php if (!empty($bill['PAYMENT_ID'])): ?>

<div class="payment-method">
<?= h($bill['PAYMENT_METHOD'] ?: '-') ?>
</div>

<div class="ref-no">
<?= h($bill['REFERENCE_NO'] ?: '-') ?>
</div>

<?php else: ?>

<span class="text-muted">
—
</span>

<?php endif; ?>

</td>


<td>

<a
    href="admin_billing.php?view=<?= (int)$bill['BILL_ID'] ?>"
    class="btn btn-sm btn-outline-primary"
>
<i class="bi bi-eye me-1"></i>
View
</a>

</td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

<td
    colspan="8"
    class="empty-state"
>

<i class="bi bi-receipt d-block mb-2 fs-4"></i>

No billing records found.

</td>

</tr>

<?php endif; ?>

</tbody>

</table>

</div>

</div>


<!-- =====================================================
     BILL DETAIL
===================================================== -->

<?php if ($selectedBill): ?>

<div class="card-box">

<div class="detail-header">

<div>

<div class="detail-title">
Bill #<?= (int)$selectedBill['BILL_ID'] ?>
</div>

<div class="detail-meta">

<?= h($selectedBill['PATIENT_NAME']) ?>

&nbsp;•&nbsp;

<?= h(
    $selectedBill['BILL_TYPE'] === 'WALKIN'
        ? 'Walk-In'
        : ucfirst(
            strtolower(
                $selectedBill['BILL_TYPE']
            )
        )
) ?>

&nbsp;•&nbsp;

<?= h($selectedBill['BILL_DATE_DISPLAY']) ?>

</div>

</div>


<div>

<div
    class="text-muted"
    style="font-size:9px;text-transform:uppercase;text-align:right;"
>
Total Amount
</div>

<div class="detail-total">
RM <?= money($selectedBill['TOTAL_AMOUNT']) ?>
</div>

</div>

</div>


<div class="detail-grid">

<div class="detail-box">

<div class="detail-label">
Patient
</div>

<div class="detail-value">
<?= h($selectedBill['PATIENT_NAME']) ?>
</div>

</div>


<div class="detail-box">

<div class="detail-label">
IC Number
</div>

<div class="detail-value">
<?= h($selectedBill['IC_NUMBER'] ?: '-') ?>
</div>

</div>


<div class="detail-box">

<div class="detail-label">
Bill Status
</div>

<div class="detail-value">
<?= h($selectedBill['STATUS']) ?>
</div>

</div>


<div class="detail-box">

<div class="detail-label">
Payment Method
</div>

<div class="detail-value">
<?= h($selectedBill['PAYMENT_METHOD'] ?: 'Not Paid') ?>
</div>

</div>


<?php if (!empty($selectedBill['PAYMENT_ID'])): ?>

<div class="detail-box">

<div class="detail-label">
Payment Date
</div>

<div class="detail-value">
<?= h($selectedBill['PAYMENT_DATE_DISPLAY'] ?: '-') ?>
</div>

</div>


<div class="detail-box">

<div class="detail-label">
Reference No.
</div>

<div class="detail-value">
<?= h($selectedBill['REFERENCE_NO'] ?: '-') ?>
</div>

</div>

<?php endif; ?>

</div>


<div class="bill-items">

<div class="bill-item-row bill-item-head">

<div>Description</div>
<div class="text-end">Qty</div>
<div class="text-end">Unit Price</div>
<div class="text-end">Subtotal</div>

</div>


<?php if (!empty($billItems)): ?>

<?php foreach ($billItems as $item): ?>

<div class="bill-item-row">

<div>

<div class="item-name">
<?= h($item['DESCRIPTION']) ?>
</div>

<div class="item-type">
<?= h($item['ITEM_TYPE']) ?>
</div>

</div>

<div class="text-end">
<?= h($item['QUANTITY']) ?>
</div>

<div class="text-end">
RM <?= money($item['UNIT_PRICE']) ?>
</div>

<div class="text-end">
<strong>
RM <?= money($item['SUBTOTAL']) ?>
</strong>
</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-state">
No bill items found.
</div>

<?php endif; ?>

</div>


<div class="total-line">

<span>
Bill Total
</span>

<strong>
RM <?= money($selectedBill['TOTAL_AMOUNT']) ?>
</strong>

</div>


<div class="mt-3">

<a
    href="admin_billing.php"
    class="btn btn-outline-secondary btn-sm"
>
<i class="bi bi-arrow-left me-1"></i>
Close Details
</a>

</div>

</div>

<?php endif; ?>


</div>

</div>

</body>
</html>
