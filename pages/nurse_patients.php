<?php

session_start();

include("../config/config.php");


/* ============================================================
   ROLE CHECK
============================================================ */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'nurse') {

    header("Location: ../auth/login.php");
    exit();

}


/* ============================================================
   SAFE HTML ESCAPE
   Prevents htmlspecialchars(NULL) deprecated warning
============================================================ */

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}


/* ============================================================
   GET SELECTED WARD
============================================================ */

$ward_id = $_GET['ward'] ?? 'All';


/* ============================================================
   GET WARD LIST
============================================================ */

$wardsStmt = $conn->query("
    SELECT
        WARD_ID,
        WARD_NAME
    FROM SYARMIMI.WARD
    ORDER BY WARD_NAME
");

$wards = $wardsStmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   FETCH BED / PATIENT / MEDICATION DATA
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
       Only medication that:
       1. Belongs to this admission
       2. Has been prepared
       3. Is ready for nurse pickup
       4. Has NOT been administered
    ======================================================== */

    (
        SELECT LISTAGG(
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
            ORDER BY mo.MEDORDER_ID
        )

        FROM SYARMIMI.MEDICATION_ORDER mo

        JOIN SYARMIMI.MEDICATION m
            ON mo.MEDICATION_ID = m.MEDICATION_ID

        JOIN SYARMIMI.PHARMACY_PREPARATION pp
            ON mo.MEDORDER_ID = pp.MEDORDER_ID

        WHERE mo.ADMISSION_ID = a.ADMISSION_ID

        AND pp.STATUS = 'Ready For Nurse Pickup'

        AND NOT EXISTS (
            SELECT 1
            FROM SYARMIMI.MEDICATION_ADMIN ma
            WHERE ma.MEDORDER_ID = mo.MEDORDER_ID
        )
    ) AS MED_LIST,


    /* ========================================================
       COUNT ONLY MEDICATION WAITING FOR NURSE PICKUP
    ======================================================== */

    (
        SELECT COUNT(*)

        FROM SYARMIMI.MEDICATION_ORDER mo

        JOIN SYARMIMI.PHARMACY_PREPARATION pp
            ON mo.MEDORDER_ID = pp.MEDORDER_ID

        WHERE mo.ADMISSION_ID = a.ADMISSION_ID

        AND pp.STATUS = 'Ready For Nurse Pickup'

        AND NOT EXISTS (
            SELECT 1
            FROM SYARMIMI.MEDICATION_ADMIN ma
            WHERE ma.MEDORDER_ID = mo.MEDORDER_ID
        )
    ) AS MED_COUNT


FROM SYARMIMI.BED b


JOIN SYARMIMI.WARD w
    ON b.WARD_ID = w.WARD_ID


/* ============================================================
   GET LATEST ADMISSION FOR EACH BED
============================================================ */

LEFT JOIN SYARMIMI.ADMISSION a

    ON a.ADMISSION_ID = (

        SELECT MAX(a2.ADMISSION_ID)

        FROM SYARMIMI.ADMISSION a2

        WHERE a2.BED_ID = b.BED_ID

    )


/* ============================================================
   PATIENT
============================================================ */

LEFT JOIN SYARMIMI.PATIENT p

    ON a.PATIENT_ID = p.PATIENT_ID


WHERE 1 = 1
";


/* ============================================================
   WARD FILTER
============================================================ */

if ($ward_id !== 'All') {

    $sql .= "
        AND b.WARD_ID = :ward_id
    ";

}


$sql .= "
    ORDER BY b.BED_ID
";


/* ============================================================
   EXECUTE BED QUERY
============================================================ */

$stmt = $conn->prepare($sql);


if ($ward_id !== 'All') {

    $stmt->execute([
        ':ward_id' => $ward_id
    ]);

} else {

    $stmt->execute();

}


$result = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* ============================================================
   WARD SUMMARY
============================================================ */

$wardSummarySql = "

SELECT

    w.WARD_ID,

    w.WARD_NAME,


    /* TOTAL BEDS */

    COUNT(DISTINCT b.BED_ID) AS TOTAL_BED,


    /* OCCUPIED BEDS */

    COUNT(
        DISTINCT
        CASE
            WHEN b.STATUS = 'Occupied'
            THEN b.BED_ID
        END
    ) AS OCCUPIED_BED,


    /* ========================================================
       PENDING MEDICATION
       Only medication ready for nurse pickup
    ======================================================== */

    COUNT(
        DISTINCT
        CASE

            WHEN pp.STATUS = 'Ready For Nurse Pickup'

            AND ma.MEDORDER_ID IS NULL

            THEN mo.MEDORDER_ID

        END
    ) AS PENDING_MED,


    /* ========================================================
       DELIVERED / ADMINISTERED MEDICATION
    ======================================================== */

    COUNT(
        DISTINCT ma.MEDORDER_ID
    ) AS DELIVERED_MED


FROM SYARMIMI.WARD w


LEFT JOIN SYARMIMI.BED b

    ON w.WARD_ID = b.WARD_ID


LEFT JOIN SYARMIMI.ADMISSION a

    ON b.BED_ID = a.BED_ID


LEFT JOIN SYARMIMI.MEDICATION_ORDER mo

    ON a.ADMISSION_ID = mo.ADMISSION_ID


LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp

    ON mo.MEDORDER_ID = pp.MEDORDER_ID


LEFT JOIN SYARMIMI.MEDICATION_ADMIN ma

    ON mo.MEDORDER_ID = ma.MEDORDER_ID


GROUP BY

    w.WARD_ID,

    w.WARD_NAME


ORDER BY

    w.WARD_NAME

";


$wardSummary = $conn
    ->query($wardSummarySql)
    ->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Ward Layout</title>


<!-- ============================================================
     BOOTSTRAP
============================================================ -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<!-- ============================================================
     BOOTSTRAP ICONS
============================================================ -->

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<style>

body {

    background: #eef2f7;

}


.main-content {

    margin-left: 270px;

}


.page-content {

    margin-top: 20px;

}


.ward-box {

    background: white;

    padding: 30px;

    border-radius: 20px;

    box-shadow: 0 8px 20px rgba(0,0,0,0.08);

}


.bed-grid {

    display: grid;

    grid-template-columns: repeat(4, 1fr);

    gap: 25px;

}


.bed-card {

    height: 150px;

    border-radius: 18px;

    padding: 18px;

    text-align: center;

    position: relative;

    cursor: pointer;

    transition: 0.25s ease;

}


.bed-card:hover {

    transform: translateY(-5px) scale(1.03);

}


.available {

    background: #d1fae5;

}


.occupied {

    background: #fee2e2;

}


.tooltip-box {

    display: none;

    position: absolute;

    bottom: 170px;

    left: 0;

    background: white;

    padding: 12px;

    border-radius: 12px;

    width: 220px;

    box-shadow: 0 8px 20px rgba(0,0,0,0.2);

    z-index: 100;

    font-size: 14px;

    text-align: left;

}


.bed-card:hover .tooltip-box {

    display: block;

}


.modal-content {

    border: none;

    border-radius: 20px;

    overflow: hidden;

}


.modal-header {

    background: #0f172a;

    color: white;

}


.modal-body {

    padding: 25px;

}


.alert-primary {

    border: none;

    background: #eff6ff;

    border-radius: 15px;

}


.ward-summary-card {

    cursor: pointer;

    border-radius: 20px;

    transition: .3s;

}


.ward-summary-card:hover {

    transform: translateY(-5px);

    box-shadow: 0 12px 30px rgba(0,0,0,.15);

}


.btn-success {

    border-radius: 12px;

    font-weight: 600;

    padding: 12px;

}


/* ============================================================
   RESPONSIVE
============================================================ */

@media (max-width: 1200px) {

    .bed-grid {

        grid-template-columns: repeat(3, 1fr);

    }

}


@media (max-width: 900px) {

    .main-content {

        margin-left: 0;

    }

    .bed-grid {

        grid-template-columns: repeat(2, 1fr);

    }

}


@media (max-width: 600px) {

    .bed-grid {

        grid-template-columns: 1fr;

    }

}

</style>

</head>


<body>


<!-- ============================================================
     SIDEBAR
============================================================ -->

<?php include("../includes/sidebar_nurse.php"); ?>


<!-- ============================================================
     MAIN CONTENT
============================================================ -->

<div class="main-content p-4">


    <!-- ========================================================
         PAGE TITLE
    ======================================================== -->

    <h3 class="mb-4">

        🏥 Ward Layout

    </h3>


    <!-- ========================================================
         WARD FILTER
    ======================================================== -->

    <form method="GET" class="mb-4">

        <label class="me-2 fw-semibold">

            Select Ward:

        </label>


        <select
            name="ward"
            onchange="this.form.submit()"
            class="form-control w-25 d-inline-block"
        >

            <option value="All">

                All

            </option>


            <?php foreach ($wards as $w): ?>

                <option
                    value="<?= h($w['WARD_ID']) ?>"
                    <?= ($ward_id == $w['WARD_ID']) ? 'selected' : '' ?>
                >

                    <?= h($w['WARD_NAME']) ?>

                </option>

            <?php endforeach; ?>

        </select>

    </form>


    <!-- ========================================================
         WARD SUMMARY
    ======================================================== -->

    <div class="row mb-4">


        <?php foreach ($wardSummary as $w): ?>


            <div class="col-md-4 mb-3">


                <div
                    class="card shadow border-0 ward-summary-card"
                    onclick='showWardDetails(
                        <?= json_encode($w["WARD_NAME"] ?? "") ?>,
                        <?= json_encode((int)$w["TOTAL_BED"]) ?>,
                        <?= json_encode((int)$w["OCCUPIED_BED"]) ?>,
                        <?= json_encode((int)$w["PENDING_MED"]) ?>,
                        <?= json_encode((int)$w["DELIVERED_MED"]) ?>
                    )'
                >


                    <div class="card-body text-center">


                        <h5>

                            🏥
                            <?= h($w['WARD_NAME']) ?>

                        </h5>


                        <p class="mb-1">

                            Beds:
                            <?= (int)$w['TOTAL_BED'] ?>

                        </p>


                        <p class="mb-1">

                            Occupied:
                            <?= (int)$w['OCCUPIED_BED'] ?>

                        </p>


                        <p class="text-danger fw-bold">

                            💊 Pending:
                            <?= (int)$w['PENDING_MED'] ?>

                        </p>


                        <p class="text-success fw-bold">

                            ✅ Delivered:
                            <?= (int)$w['DELIVERED_MED'] ?>

                        </p>


                    </div>

                </div>

            </div>


        <?php endforeach; ?>


    </div>


    <!-- ========================================================
         BED LEGEND
    ======================================================== -->

    <div class="d-flex gap-3 mb-3">


        <div class="d-flex align-items-center">

            <div
                style="
                    width:18px;
                    height:18px;
                    background:#d1fae5;
                    border-radius:4px;
                    margin-right:8px;
                    border:1px solid #10b981;
                "
            ></div>


            <span class="fw-semibold">

                Available Bed

            </span>

        </div>


        <div class="d-flex align-items-center">

            <div
                style="
                    width:18px;
                    height:18px;
                    background:#fee2e2;
                    border-radius:4px;
                    margin-right:8px;
                    border:1px solid #ef4444;
                "
            ></div>


            <span class="fw-semibold">

                Occupied Bed

            </span>

        </div>


    </div>


    <!-- ========================================================
         CURRENT WARD
    ======================================================== -->

    <h5 class="mb-3">

        Ward:

        <?php if ($ward_id === 'All'): ?>

            All

        <?php else: ?>

            <?= h($result[0]['WARD_NAME'] ?? '') ?>

        <?php endif; ?>

    </h5>


    <!-- ========================================================
         BED LAYOUT
    ======================================================== -->

    <div class="ward-box mt-3">


        <div class="bed-grid">


            <?php foreach ($result as $row): ?>


                <?php

                $status = $row['STATUS'] ?? 'Available';

                $statusClass =
                    ($status === 'Available')
                    ? 'available'
                    : 'occupied';

                ?>


                <div
                    class="bed-card <?= $statusClass ?>"
                    onclick='openModal(
                        <?= json_encode($row["NAME"] ?? null) ?>,
                        <?= json_encode($row["AGE"] ?? null) ?>,
                        <?= json_encode($row["GENDER"] ?? null) ?>,
                        <?= json_encode($status) ?>,
                        <?= json_encode($row["ADMISSION_ID"] ?? null) ?>,
                        <?= json_encode($row["MED_LIST"] ?? null) ?>,
                        <?= json_encode((int)($row["MED_COUNT"] ?? 0)) ?>
                    )'
                >


                    <!-- =================================================
                         BED ICON
                    ================================================= -->

                    <div
                        style="
                            font-size:60px;
                            margin-bottom:10px;
                        "
                    >

                        🛏️

                    </div>


                    <!-- =================================================
                         BED NUMBER
                    ================================================= -->

                    <h5 class="fw-bold">

                        <?= h($row['BED_NUMBER']) ?>

                    </h5>


                    <!-- =================================================
                         STATUS
                    ================================================= -->

                    <span
                        class="badge
                        <?= ($status === 'Occupied')
                            ? 'bg-danger'
                            : 'bg-success' ?>"
                    >

                        <?= h($status) ?>

                    </span>


                    <!-- =================================================
                         HOVER INFORMATION
                    ================================================= -->

                    <div class="tooltip-box">


                        <?php if ($status === 'Available'): ?>


                            <span class="text-muted">

                                No patient

                            </span>


                        <?php else: ?>


                            <strong>

                                <?= h($row['NAME']) ?>

                            </strong>

                            <br>


                            Age:

                            <?= h($row['AGE'] ?? 'Not available') ?>

                            <br>


                            Sex:

                            <?= h($row['GENDER'] ?? 'Not available') ?>


                        <?php endif; ?>


                    </div>


                </div>


            <?php endforeach; ?>


        </div>


    </div>


</div>


<!-- ============================================================
     PATIENT BED MODAL
============================================================ -->

<div
    class="modal fade"
    id="bedModal"
    tabindex="-1"
>

    <div class="modal-dialog">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    Patient Details

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
            ></div>


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

    <div class="modal-dialog">

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">

                    🏥 Ward Summary

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
            ></div>


        </div>

    </div>

</div>


<!-- ============================================================
     BOOTSTRAP JS
============================================================ -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<!-- ============================================================
     SWEETALERT
============================================================ -->

<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


<script>

/* ============================================================
   SHOW WARD DETAILS
============================================================ */

function showWardDetails(
    ward,
    beds,
    occupied,
    pending,
    delivered
) {

    document.getElementById(
        'wardModalContent'
    ).innerHTML = `

        <div class="text-center">

            <h3>
                🏥 ${escapeHtml(ward)}
            </h3>

            <hr>

            <p>
                🛏️ Total Beds:
                <b>${beds}</b>
            </p>

            <p>
                🏥 Occupied Beds:
                <b>${occupied}</b>
            </p>

            <p class="text-danger">
                💊 Pending Medication:
                <b>${pending}</b>
            </p>

            <p class="text-success">
                ✅ Delivered Medication:
                <b>${delivered}</b>
            </p>

        </div>

    `;


    new bootstrap.Modal(
        document.getElementById('wardModal')
    ).show();

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
) {


    let medicationHTML = "";


    /* ========================================================
       BUILD MEDICATION LIST
    ======================================================== */

    if (medList) {


        let meds =
            medList.split("~~");


        meds.forEach(function(item) {


            let data =
                item.split("|");


            if (data.length >= 4) {


                medicationHTML += `

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
                                class="btn btn-success btn-sm"
                            >

                                💊 Give

                            </a>

                        </td>

                    </tr>

                `;

            }

        });

    }


    /* ========================================================
       AVAILABLE BED
    ======================================================== */

    let content = "";


    if (status === "Available") {


        content = `

            <div class="text-center py-4">

                <div
                    style="font-size:60px;"
                >
                    🛏️
                </div>

                <p class="text-muted mb-0">

                    No patient in this bed.

                </p>

            </div>

        `;


    }


    /* ========================================================
       OCCUPIED BED
    ======================================================== */

    else {


        const safeName =
            name ?? "Patient information unavailable";


        const safeAge =
            age ?? "Not available";


        const safeGender =
            gender ?? "Not available";


        content = `

            <div class="text-center mb-3">

                <div
                    style="font-size:60px"
                >
                    🛏️
                </div>

                <h4 class="fw-bold">

                    ${escapeHtml(safeName)}

                </h4>

                <span class="badge bg-danger">

                    Occupied Bed

                </span>

            </div>


            <hr>


            <div class="row text-center">

                <div class="col-6">

                    <strong>
                        Age
                    </strong>

                    <br>

                    ${escapeHtml(safeAge)}

                </div>


                <div class="col-6">

                    <strong>
                        Gender
                    </strong>

                    <br>

                    ${escapeHtml(safeGender)}

                </div>

            </div>


            <hr>


            <div class="alert alert-primary">

                <h6 class="fw-bold mb-3">

                    💊 Medication List

                </h6>


                <table
                    class="table table-sm table-bordered bg-white align-middle"
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

                                        No pending medication pickup.

                                    </td>

                                </tr>
                            `
                        }

                    </tbody>

                </table>


                ${
                    Number(medCount) === 0
                    ?
                    `
                        <div
                            class="alert alert-success mt-3 mb-0 py-2 text-center"
                        >

                            ✅ All medications have been delivered.

                        </div>
                    `
                    :
                    `
                        <div
                            class="alert alert-warning mt-3 mb-0 py-2 text-center"
                        >

                            💊
                            ${medCount}
                            medication(s) waiting for pickup.

                        </div>
                    `
                }


            </div>

        `;

    }


    document.getElementById(
        "modalContent"
    ).innerHTML = content;


    new bootstrap.Modal(
        document.getElementById('bedModal')
    ).show();

}


/* ============================================================
   HTML ESCAPE FOR JAVASCRIPT
============================================================ */

function escapeHtml(value) {

    if (value === null || value === undefined) {

        return "";

    }


    return String(value)

        .replace(/&/g, "&amp;")

        .replace(/</g, "&lt;")

        .replace(/>/g, "&gt;")

        .replace(/"/g, "&quot;")

        .replace(/'/g, "&#039;");

}

</script>


<!-- ============================================================
     ALREADY DELIVERED MESSAGE
============================================================ -->

<?php if (isset($_GET['already_delivered'])): ?>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        Swal.fire({

            icon: 'info',

            title: 'Medication Already Delivered',

            text:
                'All medications for this patient have already been delivered.'

        });

    }
);

</script>

<?php endif; ?>


</body>

</html>