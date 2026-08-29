<?php

session_start();

include("../config/config.php");


/* ============================================================
   ROLE CHECK
============================================================ */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'nurse'
) {

    header("Location: ../auth/login.php");
    exit();

}


/* ============================================================
   SAFE HTML
============================================================ */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* ============================================================
   SELECTED WARD
============================================================ */

$ward_id =
    $_GET['ward']
    ?? 'All';



/* ============================================================
   WARD LIST
============================================================ */

$wardsStmt =
    $conn->query("

        SELECT
            WARD_ID,
            WARD_NAME

        FROM
            SYARMIMI.WARD

        ORDER BY
            WARD_NAME

    ");


$wards =
    $wardsStmt->fetchAll(
        PDO::FETCH_ASSOC
    );



/* ============================================================
   BED / PATIENT / MEDICATION
============================================================ */

$sql = "

SELECT

    b.BED_ID,

    b.BED_NUMBER,

    b.STATUS,

    w.WARD_NAME,

    p.NAME,

    p.AGE,

    p.GENDER,

    a.ADMISSION_ID,


    /* ========================================================
       PENDING MEDICATION LIST
    ======================================================== */

    (
        SELECT

            LISTAGG(

                mo.MEDORDER_ID
                || '|' ||
                m.MEDICATION_NAME
                || '|' ||
                mo.DOSAGE
                || '|' ||
                mo.FREQUENCY,

                '~~'

            )

            WITHIN GROUP (
                ORDER BY
                    mo.MEDORDER_ID
            )

        FROM
            SYARMIMI.MEDICATION_ORDER mo

        JOIN
            SYARMIMI.MEDICATION m

            ON
            mo.MEDICATION_ID =
            m.MEDICATION_ID

        JOIN
            SYARMIMI.PHARMACY_PREPARATION pp

            ON
            mo.MEDORDER_ID =
            pp.MEDORDER_ID

        WHERE
            mo.ADMISSION_ID =
            a.ADMISSION_ID

        AND
            pp.STATUS =
            'Ready For Nurse Pickup'

        AND NOT EXISTS
        (
            SELECT 1

            FROM
                SYARMIMI.MEDICATION_ADMIN ma

            WHERE
                ma.MEDORDER_ID =
                mo.MEDORDER_ID
        )

    )
    AS MED_LIST,


    /* ========================================================
       PENDING MEDICATION COUNT
    ======================================================== */

    (
        SELECT
            COUNT(*)

        FROM
            SYARMIMI.MEDICATION_ORDER mo

        JOIN
            SYARMIMI.PHARMACY_PREPARATION pp

            ON
            mo.MEDORDER_ID =
            pp.MEDORDER_ID

        WHERE
            mo.ADMISSION_ID =
            a.ADMISSION_ID

        AND
            pp.STATUS =
            'Ready For Nurse Pickup'

        AND NOT EXISTS
        (
            SELECT 1

            FROM
                SYARMIMI.MEDICATION_ADMIN ma

            WHERE
                ma.MEDORDER_ID =
                mo.MEDORDER_ID
        )

    )
    AS MED_COUNT


FROM
    SYARMIMI.BED b


JOIN
    SYARMIMI.WARD w

    ON
    b.WARD_ID =
    w.WARD_ID


/* ============================================================
   LATEST ACTIVE ADMISSION
============================================================ */

LEFT JOIN
    SYARMIMI.ADMISSION a

    ON
    a.ADMISSION_ID =
    (
        SELECT
            MAX(a2.ADMISSION_ID)

        FROM
            SYARMIMI.ADMISSION a2

        WHERE
            a2.BED_ID =
            b.BED_ID

        AND
            a2.DISCHARGE_DATE
            IS NULL
    )


LEFT JOIN
    SYARMIMI.PATIENT p

    ON
    a.PATIENT_ID =
    p.PATIENT_ID


WHERE
    1 = 1

";


/* ============================================================
   WARD FILTER
============================================================ */

if (
    $ward_id !== 'All'
) {

    $sql .= "

        AND
            b.WARD_ID = :ward_id

    ";

}


$sql .= "

    ORDER BY
        b.BED_ID

";


$stmt =
    $conn->prepare($sql);


if (
    $ward_id !== 'All'
) {

    $stmt->execute([
        ':ward_id' => $ward_id
    ]);

}
else {

    $stmt->execute();

}


$result =
    $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );



/* ============================================================
   WARD SUMMARY
============================================================ */

$wardSummarySql = "

SELECT

    w.WARD_ID,

    w.WARD_NAME,


    COUNT(
        DISTINCT b.BED_ID
    )
    AS TOTAL_BED,


    COUNT(
        DISTINCT
        CASE

            WHEN
                b.STATUS = 'Occupied'

            THEN
                b.BED_ID

        END
    )
    AS OCCUPIED_BED,


    COUNT(
        DISTINCT
        CASE

            WHEN
                pp.STATUS =
                'Ready For Nurse Pickup'

            AND
                ma.MEDORDER_ID
                IS NULL

            THEN
                mo.MEDORDER_ID

        END
    )
    AS PENDING_MED,


    COUNT(
        DISTINCT ma.MEDORDER_ID
    )
    AS DELIVERED_MED


FROM
    SYARMIMI.WARD w


LEFT JOIN
    SYARMIMI.BED b

    ON
    w.WARD_ID =
    b.WARD_ID


LEFT JOIN
    SYARMIMI.ADMISSION a

    ON
    b.BED_ID =
    a.BED_ID

AND
    a.DISCHARGE_DATE
    IS NULL


LEFT JOIN
    SYARMIMI.MEDICATION_ORDER mo

    ON
    a.ADMISSION_ID =
    mo.ADMISSION_ID


LEFT JOIN
    SYARMIMI.PHARMACY_PREPARATION pp

    ON
    mo.MEDORDER_ID =
    pp.MEDORDER_ID


LEFT JOIN
    SYARMIMI.MEDICATION_ADMIN ma

    ON
    mo.MEDORDER_ID =
    ma.MEDORDER_ID


GROUP BY

    w.WARD_ID,

    w.WARD_NAME


ORDER BY

    w.WARD_NAME

";


$wardSummary =
    $conn
        ->query(
            $wardSummarySql
        )
        ->fetchAll(
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
Ward Layout
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

/* ============================================================
   GLOBAL
============================================================ */

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


/* ============================================================
   MAIN CONTENT
============================================================ */

.main-content{

    min-height:100vh;

    margin-left:260px;

    padding:30px;
}


/* ============================================================
   HEADER
============================================================ */

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

    font-weight:700;
}


.page-subtitle{

    margin-top:6px;

    color:#8a94a3;

    font-size:14px;
}


.header-badge{

    display:flex;

    align-items:center;

    gap:8px;

    padding:10px 14px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:9px;

    color:#2563eb;

    font-size:12px;

    font-weight:650;
}


/* ============================================================
   FILTER
============================================================ */

.filter-card{

    margin-bottom:22px;

    padding:16px 18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:11px;
}


.filter-row{

    display:flex;

    align-items:end;

    gap:12px;

    flex-wrap:wrap;
}


.filter-group{

    width:300px;

    max-width:100%;
}


.filter-label{

    margin-bottom:7px;

    color:#64748b;

    font-size:11px;

    font-weight:650;

    letter-spacing:.4px;

    text-transform:uppercase;
}


.form-select{

    min-height:44px;

    border:1px solid #dfe3e8;

    border-radius:8px;

    color:#374151;

    font-size:13px;
}


.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(59,130,246,.07);
}


/* ============================================================
   WARD SUMMARY
============================================================ */

.summary-grid{

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:24px;
}


.ward-summary-card{

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;

    cursor:pointer;

    transition:.18s;
}


.ward-summary-card:hover{

    border-color:#cbd5e1;

    transform:translateY(-2px);
}


.ward-summary-header{

    display:flex;

    align-items:center;

    gap:12px;

    margin-bottom:17px;
}


.ward-summary-icon{

    width:44px;
    height:44px;

    min-width:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    background:#eff6ff;

    color:#2563eb;

    font-size:19px;
}


.ward-summary-name{

    color:#111827;

    font-size:15px;

    font-weight:650;
}


.ward-summary-sub{

    margin-top:3px;

    color:#94a3b8;

    font-size:11px;
}


.summary-stats{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:9px;
}


.summary-stat{

    padding:11px;

    background:#f8fafc;

    border-radius:8px;
}


.summary-stat-label{

    color:#94a3b8;

    font-size:10px;

    font-weight:500;
}


.summary-stat-value{

    margin-top:3px;

    color:#374151;

    font-size:18px;

    font-weight:700;
}


.value-danger{

    color:#dc2626;
}


.value-success{

    color:#15803d;
}


/* ============================================================
   BED SECTION
============================================================ */

.bed-section{

    padding:22px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:13px;
}


.bed-section-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:20px;
}


.section-title{

    margin:0;

    color:#1f2937;

    font-size:19px;

    font-weight:650;
}


.section-subtitle{

    margin-top:4px;

    color:#94a3b8;

    font-size:12px;
}


/* ============================================================
   LEGEND
============================================================ */

.legend{

    display:flex;

    align-items:center;

    gap:16px;

    flex-wrap:wrap;
}


.legend-item{

    display:flex;

    align-items:center;

    gap:7px;

    color:#64748b;

    font-size:11px;

    font-weight:600;
}


.legend-dot{

    width:9px;
    height:9px;

    border-radius:50%;
}


.dot-available{

    background:#22c55e;
}


.dot-occupied{

    background:#ef4444;
}


/* ============================================================
   BED GRID
============================================================ */

.bed-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;
}


/* ============================================================
   BED CARD
============================================================ */

.bed-card{

    position:relative;

    min-height:170px;

    padding:18px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    border-radius:12px;

    cursor:pointer;

    transition:.18s;
}


.bed-card:hover{

    transform:translateY(-2px);
}


/* ============================================================
   AVAILABLE
============================================================ */

.bed-card.available{

    background:#f0fdf4;

    border:1px solid #bbf7d0;
}


.bed-card.available:hover{

    border-color:#86efac;
}


/* ============================================================
   OCCUPIED
============================================================ */

.bed-card.occupied{

    background:#fff7f7;

    border:1px solid #fecaca;
}


.bed-card.occupied:hover{

    border-color:#fca5a5;
}


/* ============================================================
   BED ICON
============================================================ */

.bed-icon{

    width:50px;
    height:50px;

    margin-bottom:11px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    font-size:22px;
}


.available
.bed-icon{

    background:#dcfce7;

    color:#15803d;
}


.occupied
.bed-icon{

    background:#fee2e2;

    color:#dc2626;
}


/* ============================================================
   BED NUMBER
============================================================ */

.bed-number{

    margin:0;

    color:#1f2937;

    font-size:16px;

    font-weight:700;
}


/* ============================================================
   BED STATUS
============================================================ */

.bed-status{

    margin-top:8px;

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 9px;

    border-radius:6px;

    font-size:10px;

    font-weight:650;
}


.status-available{

    background:#dcfce7;

    color:#15803d;
}


.status-occupied{

    background:#fee2e2;

    color:#dc2626;
}


/* ============================================================
   MEDICATION COUNT
============================================================ */

.med-indicator{

    position:absolute;

    top:10px;
    right:10px;

    min-width:27px;
    height:27px;

    padding:0 8px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:20px;

    background:#ea580c;

    color:#fff;

    font-size:10px;

    font-weight:700;
}


/* ============================================================
   TOOLTIP
============================================================ */

.tooltip-box{

    display:none;

    position:absolute;

    bottom:calc(100% + 9px);

    left:50%;

    z-index:50;

    width:235px;

    padding:13px 14px;

    background:#0f172a;

    border-radius:9px;

    color:#e2e8f0;

    font-size:11px;

    line-height:1.7;

    text-align:left;

    box-shadow:
        0 12px 30px
        rgba(15,23,42,.20);

    transform:translateX(-50%);
}


.tooltip-box::after{

    content:"";

    position:absolute;

    top:100%;
    left:50%;

    border:6px solid transparent;

    border-top-color:#0f172a;

    transform:translateX(-50%);
}


.bed-card:hover
.tooltip-box{

    display:block;
}


.tooltip-name{

    color:#fff;

    font-size:12px;

    font-weight:650;
}


/* ============================================================
   EMPTY
============================================================ */

.empty-bed-state{

    grid-column:1/-1;

    padding:50px 20px;

    text-align:center;

    color:#94a3b8;

    font-size:13px;
}


/* ============================================================
   MODAL
============================================================ */

.modal-content{

    border:0;

    border-radius:14px;

    overflow:hidden;

    box-shadow:
        0 24px 60px
        rgba(15,23,42,.18);
}


.modal-header{

    padding:20px 22px;

    background:#fff;

    border-bottom:1px solid #eef1f4;
}


.modal-title{

    color:#111827;

    font-size:18px;

    font-weight:650;
}


.modal-body{

    padding:22px;
}


/* ============================================================
   PATIENT PROFILE
============================================================ */

.patient-modal-header{

    display:flex;

    align-items:center;

    gap:14px;

    margin-bottom:20px;
}


.patient-modal-avatar{

    width:55px;
    height:55px;

    min-width:55px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:13px;

    background:#eff6ff;

    color:#2563eb;

    font-size:23px;
}


.patient-modal-name{

    color:#111827;

    font-size:18px;

    font-weight:700;
}


.patient-modal-status{

    margin-top:5px;

    display:inline-flex;

    padding:5px 8px;

    background:#fee2e2;

    border-radius:5px;

    color:#dc2626;

    font-size:10px;

    font-weight:650;
}


/* ============================================================
   PATIENT INFO
============================================================ */

.patient-info-grid{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:12px;

    margin-bottom:20px;
}


.patient-info-item{

    padding:12px;

    background:#f8fafc;

    border-radius:8px;
}


.patient-info-label{

    color:#94a3b8;

    font-size:10px;

    text-transform:uppercase;

    letter-spacing:.3px;
}


.patient-info-value{

    margin-top:4px;

    color:#374151;

    font-size:13px;

    font-weight:600;
}


/* ============================================================
   MEDICATION PANEL
============================================================ */

.medication-panel{

    padding:16px;

    background:#f8fafc;

    border:1px solid #e7eaee;

    border-radius:9px;
}


.medication-panel-title{

    display:flex;

    align-items:center;

    gap:7px;

    margin-bottom:13px;

    color:#374151;

    font-size:13px;

    font-weight:650;
}


.medication-table{

    margin:0;

    background:#fff;

    border-radius:7px;

    overflow:hidden;
}


.medication-table th{

    padding:10px !important;

    background:#f1f5f9 !important;

    color:#64748b !important;

    border-color:#e5e7eb !important;

    font-size:10px;

    font-weight:650;

    text-transform:uppercase;
}


.medication-table td{

    padding:10px !important;

    border-color:#eef1f4 !important;

    color:#374151;

    font-size:11px;
}


.give-btn{

    min-height:32px;

    padding:0 10px;

    display:inline-flex;

    align-items:center;

    gap:5px;

    border:0;

    border-radius:6px;

    background:#16a34a;

    color:#fff;

    text-decoration:none;

    font-size:10px;

    font-weight:600;
}


.give-btn:hover{

    background:#15803d;

    color:#fff;
}


/* ============================================================
   MEDICATION MESSAGE
============================================================ */

.med-message{

    margin-top:11px;

    padding:10px 12px;

    border-radius:7px;

    font-size:11px;

    text-align:center;
}


.med-complete{

    background:#ecfdf5;

    color:#15803d;
}


.med-pending{

    background:#fff7ed;

    color:#c2410c;
}


/* ============================================================
   WARD MODAL
============================================================ */

.ward-modal-title{

    text-align:center;

    color:#111827;

    font-size:21px;

    font-weight:700;
}


.ward-modal-grid{

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:11px;

    margin-top:20px;
}


.ward-modal-stat{

    padding:14px;

    background:#f8fafc;

    border-radius:8px;

    text-align:center;
}


.ward-modal-label{

    color:#94a3b8;

    font-size:10px;
}


.ward-modal-value{

    margin-top:5px;

    color:#111827;

    font-size:21px;

    font-weight:700;
}


/* ============================================================
   RESPONSIVE
============================================================ */

@media(max-width:1200px){

    .bed-grid{

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );
    }


    .summary-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

}


@media(max-width:850px){

    .main-content{

        margin-left:260px;

        padding:20px;
    }


    .page-header,
    .bed-section-header{

        flex-direction:column;

        align-items:flex-start;
    }


    .bed-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

}


@media(max-width:600px){

    .summary-grid,
    .bed-grid{

        grid-template-columns:
            1fr;
    }


    .patient-info-grid{

        grid-template-columns:
            1fr;
    }

}

</style>

</head>


<body>


<?php
include(
    "../includes/sidebar_nurse.php"
);
?>


<div class="main-content">


<!-- ============================================================
     PAGE HEADER
============================================================ -->

<div class="page-header">


<div>


<h1 class="page-title">

Ward & Bed Layout

</h1>


<div class="page-subtitle">

Monitor ward occupancy, patient beds and pending medication tasks.

</div>


</div>


<div class="header-badge">

<i class="bi bi-hospital"></i>

Ward Monitoring

</div>


</div>



<!-- ============================================================
     FILTER
============================================================ -->

<div class="filter-card">


<form
    method="GET"
    class="filter-row"
>


<div class="filter-group">


<div class="filter-label">

Select Ward

</div>


<select
    name="ward"
    class="form-select"
    onchange="this.form.submit()"
>


<option value="All">

All Wards

</option>


<?php foreach (
    $wards
    as
    $w
): ?>


<option
    value="<?= h(
        $w['WARD_ID']
    ) ?>"
    <?= (
        $ward_id
        ==
        $w['WARD_ID']
    )
    ?
    'selected'
    :
    ''
    ?>
>

<?= h(
    $w['WARD_NAME']
) ?>

</option>


<?php endforeach; ?>


</select>


</div>


</form>


</div>



<!-- ============================================================
     WARD SUMMARY
============================================================ -->

<div class="summary-grid">


<?php foreach (
    $wardSummary
    as
    $w
): ?>


<div
    class="ward-summary-card"

    onclick='showWardDetails(
        <?= json_encode(
            $w["WARD_NAME"] ?? ""
        ) ?>,
        <?= json_encode(
            (int)$w["TOTAL_BED"]
        ) ?>,
        <?= json_encode(
            (int)$w["OCCUPIED_BED"]
        ) ?>,
        <?= json_encode(
            (int)$w["PENDING_MED"]
        ) ?>,
        <?= json_encode(
            (int)$w["DELIVERED_MED"]
        ) ?>
    )'
>


<div class="ward-summary-header">


<div class="ward-summary-icon">

<i class="bi bi-building"></i>

</div>


<div>


<div class="ward-summary-name">

<?= h(
    $w['WARD_NAME']
) ?>

</div>


<div class="ward-summary-sub">

Click to view ward summary

</div>


</div>


</div>



<div class="summary-stats">


<div class="summary-stat">


<div class="summary-stat-label">

Total Beds

</div>


<div class="summary-stat-value">

<?= (int)$w['TOTAL_BED'] ?>

</div>


</div>



<div class="summary-stat">


<div class="summary-stat-label">

Occupied

</div>


<div class="summary-stat-value">

<?= (int)$w['OCCUPIED_BED'] ?>

</div>


</div>



<div class="summary-stat">


<div class="summary-stat-label">

Pending Medication

</div>


<div class="summary-stat-value value-danger">

<?= (int)$w['PENDING_MED'] ?>

</div>


</div>



<div class="summary-stat">


<div class="summary-stat-label">

Delivered Medication

</div>


<div class="summary-stat-value value-success">

<?= (int)$w['DELIVERED_MED'] ?>

</div>


</div>


</div>


</div>


<?php endforeach; ?>


</div>



<!-- ============================================================
     BED SECTION
============================================================ -->

<div class="bed-section">


<div class="bed-section-header">


<div>


<h5 class="section-title">

<?php if (
    $ward_id === 'All'
): ?>

All Ward Beds

<?php else: ?>

<?= h(
    $result[0]['WARD_NAME']
    ?? 'Ward'
) ?>

Beds

<?php endif; ?>

</h5>


<div class="section-subtitle">

Click an occupied bed to view patient and medication information.

</div>


</div>



<div class="legend">


<div class="legend-item">

<span class="legend-dot dot-available"></span>

Available

</div>


<div class="legend-item">

<span class="legend-dot dot-occupied"></span>

Occupied

</div>


</div>


</div>



<!-- ============================================================
     BED GRID
============================================================ -->

<div class="bed-grid">


<?php if (
    count($result) > 0
): ?>


<?php foreach (
    $result
    as
    $row
): ?>


<?php

$status =
    $row['STATUS']
    ?? 'Available';


$statusClass =
    (
        $status ===
        'Occupied'
    )
    ?
    'occupied'
    :
    'available';


$medCount =
    (int)(
        $row['MED_COUNT']
        ?? 0
    );

?>


<div
    class="
        bed-card
        <?= $statusClass ?>
    "

    onclick='openModal(
        <?= json_encode(
            $row["NAME"] ?? null
        ) ?>,
        <?= json_encode(
            $row["AGE"] ?? null
        ) ?>,
        <?= json_encode(
            $row["GENDER"] ?? null
        ) ?>,
        <?= json_encode(
            $status
        ) ?>,
        <?= json_encode(
            $row["ADMISSION_ID"] ?? null
        ) ?>,
        <?= json_encode(
            $row["MED_LIST"] ?? null
        ) ?>,
        <?= json_encode(
            $medCount
        ) ?>
    )'
>


<?php if (
    $medCount > 0
): ?>


<div class="med-indicator">

<i class="bi bi-capsule me-1"></i>

<?= $medCount ?>

</div>


<?php endif; ?>



<div class="bed-icon">

<i class="bi bi-hospital"></i>

</div>



<h5 class="bed-number">

Bed
<?= h(
    $row['BED_NUMBER']
) ?>

</h5>



<?php if (
    $status ===
    'Occupied'
): ?>


<span class="bed-status status-occupied">

<i class="bi bi-person-fill"></i>

Occupied

</span>


<?php else: ?>


<span class="bed-status status-available">

<i class="bi bi-check-circle"></i>

Available

</span>


<?php endif; ?>



<div class="tooltip-box">


<?php if (
    $status ===
    'Available'
): ?>


<div class="text-center">

Available for patient admission.

</div>


<?php else: ?>


<div class="tooltip-name">

<?= h(
    $row['NAME']
) ?>

</div>


<div>

Age:
<?= h(
    $row['AGE']
    ?? 'Not available'
) ?>

</div>


<div>

Gender:
<?= h(
    $row['GENDER']
    ?? 'Not available'
) ?>

</div>


<?php if (
    $medCount > 0
): ?>


<div
    style="
        margin-top:6px;
        color:#fdba74;
        font-weight:600;
    "
>

<?= $medCount ?>

medication(s) pending

</div>


<?php endif; ?>


<?php endif; ?>


</div>


</div>


<?php endforeach; ?>


<?php else: ?>


<div class="empty-bed-state">

<i
    class="bi bi-hospital"
    style="
        display:block;
        margin-bottom:8px;
        font-size:30px;
        color:#cbd5e1;
    "
></i>

<div class="fw-semibold">

No beds found

</div>


<div class="mt-1">

There are no bed records for the selected ward.

</div>


</div>


<?php endif; ?>


</div>


</div>


</div>



<!-- ============================================================
     PATIENT MODAL
============================================================ -->

<div
    class="modal fade"
    id="bedModal"
    tabindex="-1"
>


<div
    class="
        modal-dialog
        modal-dialog-centered
        modal-lg
    "
>


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Patient & Medication Details

</h5>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div
    class="modal-body"
    id="modalContent"
>
</div>


</div>


</div>


</div>



<!-- ============================================================
     WARD SUMMARY MODAL
============================================================ -->

<div
    class="modal fade"
    id="wardModal"
    tabindex="-1"
>


<div
    class="
        modal-dialog
        modal-dialog-centered
    "
>


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Ward Summary

</h5>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div
    class="modal-body"
    id="wardModalContent"
>
</div>


</div>


</div>


</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* ============================================================
   WARD DETAILS
============================================================ */

function showWardDetails(
    ward,
    beds,
    occupied,
    pending,
    delivered
)
{

    const available =
        beds - occupied;


    document
        .getElementById(
            'wardModalContent'
        )
        .innerHTML =
        `

        <div class="ward-modal-title">

            ${escapeHtml(ward)}

        </div>


        <div class="ward-modal-grid">


            <div class="ward-modal-stat">

                <div class="ward-modal-label">

                    Total Beds

                </div>

                <div class="ward-modal-value">

                    ${beds}

                </div>

            </div>


            <div class="ward-modal-stat">

                <div class="ward-modal-label">

                    Available Beds

                </div>

                <div
                    class="ward-modal-value"
                    style="color:#15803d"
                >

                    ${available}

                </div>

            </div>


            <div class="ward-modal-stat">

                <div class="ward-modal-label">

                    Occupied Beds

                </div>

                <div
                    class="ward-modal-value"
                    style="color:#dc2626"
                >

                    ${occupied}

                </div>

            </div>


            <div class="ward-modal-stat">

                <div class="ward-modal-label">

                    Pending Medication

                </div>

                <div
                    class="ward-modal-value"
                    style="color:#ea580c"
                >

                    ${pending}

                </div>

            </div>


            <div
                class="ward-modal-stat"
                style="
                    grid-column:1/-1;
                "
            >

                <div class="ward-modal-label">

                    Delivered Medication

                </div>

                <div
                    class="ward-modal-value"
                    style="color:#15803d"
                >

                    ${delivered}

                </div>

            </div>


        </div>

        `;


    new bootstrap.Modal(
        document.getElementById(
            'wardModal'
        )
    )
    .show();

}



/* ============================================================
   OPEN BED MODAL
============================================================ */

function openModal(
    name,
    age,
    gender,
    status,
    admission_id,
    medList,
    medCount
)
{

    let medicationHTML = "";


    /* ========================================================
       MEDICATION LIST
    ======================================================== */

    if (medList)
    {

        const meds =
            medList.split(
                "~~"
            );


        meds.forEach(
            function(item)
            {

                const data =
                    item.split(
                        "|"
                    );


                if (
                    data.length >= 4
                )
                {

                    medicationHTML +=
                    `

                    <tr>

                        <td>

                            ${escapeHtml(data[1])}

                        </td>


                        <td>

                            ${escapeHtml(data[2])}

                        </td>


                        <td>

                            ${escapeHtml(data[3])}

                        </td>


                        <td>

                            <a
                                href="nurse_action.php?give_med=${encodeURIComponent(data[0])}"
                                class="give-btn"
                            >

                                <i class="bi bi-check2-circle"></i>

                                Give

                            </a>

                        </td>

                    </tr>

                    `;

                }

            }
        );

    }


    let content = "";


    /* ========================================================
       AVAILABLE
    ======================================================== */

    if (
        status !==
        "Occupied"
    )
    {

        content =
        `

        <div
            style="
                padding:40px 20px;
                text-align:center;
            "
        >

            <div
                style="
                    width:65px;
                    height:65px;
                    margin:0 auto 14px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#ecfdf5;
                    border-radius:14px;
                    color:#15803d;
                    font-size:27px;
                "
            >

                <i class="bi bi-hospital"></i>

            </div>


            <div
                style="
                    color:#111827;
                    font-size:17px;
                    font-weight:650;
                "
            >

                Available Bed

            </div>


            <div
                style="
                    margin-top:6px;
                    color:#94a3b8;
                    font-size:13px;
                "
            >

                No patient is currently assigned to this bed.

            </div>

        </div>

        `;

    }


    /* ========================================================
       OCCUPIED
    ======================================================== */

    else
    {

        const safeName =
            name ??
            "Patient information unavailable";


        const safeAge =
            age ??
            "Not available";


        const safeGender =
            gender ??
            "Not available";


        content =
        `

        <div class="patient-modal-header">


            <div class="patient-modal-avatar">

                <i class="bi bi-person"></i>

            </div>


            <div>


                <div class="patient-modal-name">

                    ${escapeHtml(safeName)}

                </div>


                <span class="patient-modal-status">

                    Occupied Bed

                </span>


            </div>


        </div>



        <div class="patient-info-grid">


            <div class="patient-info-item">

                <div class="patient-info-label">

                    Age

                </div>

                <div class="patient-info-value">

                    ${escapeHtml(safeAge)}

                </div>

            </div>


            <div class="patient-info-item">

                <div class="patient-info-label">

                    Gender

                </div>

                <div class="patient-info-value">

                    ${escapeHtml(safeGender)}

                </div>

            </div>


        </div>



        <div class="medication-panel">


            <div class="medication-panel-title">

                <i class="bi bi-capsule"></i>

                Medication Waiting for Administration

            </div>


            <div class="table-responsive">


                <table
                    class="
                        table
                        medication-table
                        align-middle
                    "
                >


                    <thead>


                        <tr>

                            <th>
                                Medication
                            </th>

                            <th>
                                Dosage
                            </th>

                            <th>
                                Frequency
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>


                    </thead>


                    <tbody>


                        ${
                            medicationHTML
                            ?
                            medicationHTML
                            :
                            `

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center text-muted"
                                >

                                    No pending medication.

                                </td>

                            </tr>

                            `
                        }


                    </tbody>


                </table>


            </div>


            ${
                Number(medCount) === 0
                ?
                `

                <div class="med-message med-complete">

                    <i class="bi bi-check-circle me-1"></i>

                    All medications have been delivered.

                </div>

                `
                :
                `

                <div class="med-message med-pending">

                    <i class="bi bi-capsule me-1"></i>

                    ${medCount}
                    medication(s) waiting for administration.

                </div>

                `
            }


        </div>

        `;

    }


    document
        .getElementById(
            "modalContent"
        )
        .innerHTML =
        content;


    new bootstrap.Modal(
        document.getElementById(
            'bedModal'
        )
    )
    .show();

}



/* ============================================================
   ESCAPE HTML
============================================================ */

function escapeHtml(value)
{

    if (
        value === null
        ||
        value === undefined
    )
    {

        return "";

    }


    return String(value)

        .replace(
            /&/g,
            "&amp;"
        )

        .replace(
            /</g,
            "&lt;"
        )

        .replace(
            />/g,
            "&gt;"
        )

        .replace(
            /"/g,
            "&quot;"
        )

        .replace(
            /'/g,
            "&#039;"
        );

}

</script>



<?php if (
    isset(
        $_GET[
            'already_delivered'
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
                'info',

            title:
                'Medication Already Delivered',

            text:
                'All medications for this patient have already been delivered.',

            confirmButtonColor:
                '#2563eb'

        });

    }
);

</script>


<?php endif; ?>


</body>

</html>