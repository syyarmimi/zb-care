<?php

session_start();

include("../config/config.php");

/* ==========================================================================
   CHECK LOGIN
========================================================================== */

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}


/* ==========================================================================
   GET FILTER VALUES
========================================================================== */

$currentType   = $_GET['type'] ?? '';
$currentSearch = $_GET['search'] ?? '';
$currentSort   = $_GET['sort'] ?? 'desc';


/* ==========================================================================
   VALIDATE TYPE
========================================================================== */

$allowedTypes = [
    '',
    'Appointment',
    'Walk-In',
    'Admission'
];

if (!in_array($currentType, $allowedTypes, true)) {
    $currentType = '';
}


/* ==========================================================================
   VALIDATE SORT
========================================================================== */

if ($currentSort !== 'asc' && $currentSort !== 'desc') {
    $currentSort = 'desc';
}


/* ==========================================================================
   HELPER - REDIRECT WITH FILTERS
========================================================================== */

function redirectWithFilters($page, $extra = [])
{
    global $currentType, $currentSearch, $currentSort;

    $params = [];

    // Preserve type filter
    if ($currentType !== '') {
        $params['type'] = $currentType;
    }

    // Preserve search filter
    if ($currentSearch !== '') {
        $params['search'] = $currentSearch;
    }

    // Preserve sort
    $params['sort'] = $currentSort;

    // Add extra parameters (like success, collected, etc.)
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }

    header(
        "Location: " .
        $page .
        "?" .
        http_build_query($params)
    );

    exit();
}


/* ==========================================================================
   HANDLE PREPARE ACTION
========================================================================== */

if (isset($_GET['prepare'])) {

    $medOrderId = (int) $_GET['prepare'];
    $staffId    = $_SESSION['user_id'] ?? null;

    try {

        /* ==============================================================
           GET MEDICATION ORDER
        ============================================================== */

        $orderStmt = $conn->prepare("
            SELECT
                mo.MEDORDER_ID,
                mo.ADMISSION_ID,
                mo.APPOINTMENT_ID,
                mo.CONSULTATION_ID,
                mo.MED_START_DATE,
                mo.MED_END_DATE,
                mo.PATIENT_ID,
                a.ADMISSION_DATE,
                a.EXPECTED_DISCHARGE_DATE,
                a.DISCHARGE_DATE

            FROM SYARMIMI.MEDICATION_ORDER mo

            LEFT JOIN SYARMIMI.ADMISSION a
                ON mo.ADMISSION_ID = a.ADMISSION_ID

            WHERE mo.MEDORDER_ID = ?
        ");

        $orderStmt->execute([
            $medOrderId
        ]);

        $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);


        if (!$orderInfo) {
            die("Medication order not found.");
        }


        /* ==============================================================
           CHECK IF THIS IS ADMISSION MEDICATION
        ============================================================== */

        $isAdmission = !empty($orderInfo['ADMISSION_ID']);


        /* ==============================================================
           ADMISSION MEDICATION
           
           IMPORTANT:
           A patient can have ONE preparation PER DAY.
           
           Previous day's preparation must NOT block today's preparation.
        ============================================================== */

        if ($isAdmission) {

            /* ----------------------------------------------------------
               Check admission is still active
            ---------------------------------------------------------- */

            if (!empty($orderInfo['DISCHARGE_DATE'])) {

                die("This patient has already been discharged.");

            }


            /* ----------------------------------------------------------
               Check medication start/end date
            ---------------------------------------------------------- */

            $dateCheck = $conn->prepare("
                SELECT COUNT(*)
                FROM SYARMIMI.MEDICATION_ORDER mo
                LEFT JOIN SYARMIMI.ADMISSION a
                    ON mo.ADMISSION_ID = a.ADMISSION_ID

                WHERE mo.MEDORDER_ID = ?

                  AND TRUNC(SYSDATE) >=
                      TRUNC(
                          NVL(
                              mo.MED_START_DATE,
                              a.ADMISSION_DATE
                          )
                      )

                  AND
                  (
                      mo.MED_END_DATE IS NULL
                      OR
                      TRUNC(SYSDATE) <= TRUNC(mo.MED_END_DATE)
                  )

                  AND
                  (
                      a.EXPECTED_DISCHARGE_DATE IS NULL
                      OR
                      TRUNC(SYSDATE) <=
                      TRUNC(a.EXPECTED_DISCHARGE_DATE)
                  )
            ");

            $dateCheck->execute([
                $medOrderId
            ]);

            $activeToday = (int) $dateCheck->fetchColumn();


            if ($activeToday === 0) {

                die(
                    "This medication is not active for today."
                );

            }


            /* ----------------------------------------------------------
               CHECK TODAY'S PREPARATION ONLY
            ---------------------------------------------------------- */

            $checkStmt = $conn->prepare("
                SELECT COUNT(*)
                FROM SYARMIMI.PHARMACY_PREPARATION
                WHERE MEDORDER_ID = ?
                  AND TRUNC(PREPARED_TIME) = TRUNC(SYSDATE)
            ");

            $checkStmt->execute([
                $medOrderId
            ]);

            $alreadyPreparedToday =
                (int) $checkStmt->fetchColumn();


            if ($alreadyPreparedToday > 0) {

                redirectWithFilters(
                    "pharmacy_preparation.php",
                    [
                        'success' => 'already'
                    ]
                );

            }


            /* ----------------------------------------------------------
               ADMISSION STATUS
            ---------------------------------------------------------- */

            $status = 'Ready For Nurse Pickup';


        } else {

            /* ==========================================================
               APPOINTMENT / WALK-IN
               
               These are prepared only once.
            ========================================================== */

            $checkStmt = $conn->prepare("
                SELECT COUNT(*)
                FROM SYARMIMI.PHARMACY_PREPARATION
                WHERE MEDORDER_ID = ?
            ");

            $checkStmt->execute([
                $medOrderId
            ]);

            $alreadyPrepared =
                (int) $checkStmt->fetchColumn();


            if ($alreadyPrepared > 0) {

                redirectWithFilters(
                    "pharmacy_preparation.php",
                    [
                        'success' => 'already'
                    ]
                );

            }


            $status = 'Ready For Pickup';

        }


        /* ==============================================================
           INSERT PREPARATION
        ============================================================== */

        $insertStmt = $conn->prepare("
            INSERT INTO SYARMIMI.PHARMACY_PREPARATION
            (
                PREP_ID,
                STATUS,
                PREPARED_TIME,
                MEDORDER_ID,
                ACCOUNT_ID
            )
            VALUES
            (
                SYARMIMI.PREPARATION_SEQ.NEXTVAL,
                ?,
                SYSDATE,
                ?,
                ?
            )
        ");

        $insertStmt->execute([
            $status,
            $medOrderId,
            $staffId
        ]);


        /* ==============================================================
           SUCCESS REDIRECT
        ============================================================== */

        redirectWithFilters(
            "pharmacy_preparation.php",
            [
                'success' => '1'
            ]
        );


    } catch (PDOException $e) {

        die(
            "Error preparing medication: " .
            htmlspecialchars($e->getMessage())
        );

    }
}


/* ==========================================================================
   HANDLE PATIENT COLLECTION
========================================================================== */

if (isset($_GET['collect'])) {

    $medOrderId = (int) $_GET['collect'];

    try {

        /* --------------------------------------------------------------
           Appointment / Walk-In ONLY
        -------------------------------------------------------------- */

        $updateStmt = $conn->prepare("
            UPDATE SYARMIMI.PHARMACY_PREPARATION pp

            SET pp.STATUS = 'Collected'

            WHERE pp.MEDORDER_ID = ?

              AND pp.STATUS = 'Ready For Pickup'

              AND EXISTS
              (
                  SELECT 1

                  FROM SYARMIMI.MEDICATION_ORDER mo

                  WHERE mo.MEDORDER_ID = pp.MEDORDER_ID

                    AND mo.ADMISSION_ID IS NULL
              )
        ");

        $updateStmt->execute([
            $medOrderId
        ]);


        redirectWithFilters(
            "pharmacy_preparation.php",
            [
                'collected' => '1'
            ]
        );


    } catch (PDOException $e) {

        die(
            "Error updating collection status: " .
            htmlspecialchars($e->getMessage())
        );

    }
}


/* ==========================================================================
   PENDING COUNT
========================================================================== */

/*
   Pending means:

   APPOINTMENT / WALK-IN:
       No preparation exists.

   ADMISSION:
       Medication is active TODAY
       AND
       there is NO preparation for TODAY.
*/

$pendingStmt = $conn->query("

    SELECT COUNT(*)

    FROM SYARMIMI.MEDICATION_ORDER mo

    LEFT JOIN SYARMIMI.ADMISSION a
        ON mo.ADMISSION_ID = a.ADMISSION_ID

    LEFT JOIN
    (
        SELECT
            MEDORDER_ID,
            COUNT(*) AS TODAY_PREPARED

        FROM SYARMIMI.PHARMACY_PREPARATION

        WHERE TRUNC(PREPARED_TIME) = TRUNC(SYSDATE)

        GROUP BY MEDORDER_ID

    ) today_pp

        ON mo.MEDORDER_ID = today_pp.MEDORDER_ID

    WHERE

    (

        /* ----------------------------------------------------------
           ADMISSION MEDICATION
        ---------------------------------------------------------- */

        mo.ADMISSION_ID IS NOT NULL

        AND a.DISCHARGE_DATE IS NULL

        AND TRUNC(SYSDATE) >=
            TRUNC(
                NVL(
                    mo.MED_START_DATE,
                    a.ADMISSION_DATE
                )
            )

        AND
        (
            mo.MED_END_DATE IS NULL
            OR
            TRUNC(SYSDATE) <= TRUNC(mo.MED_END_DATE)
        )

        AND
        (
            a.EXPECTED_DISCHARGE_DATE IS NULL
            OR
            TRUNC(SYSDATE) <=
            TRUNC(a.EXPECTED_DISCHARGE_DATE)
        )

        AND NVL(today_pp.TODAY_PREPARED, 0) = 0

    )

    OR

    (

        /* ----------------------------------------------------------
           APPOINTMENT / WALK-IN
        ---------------------------------------------------------- */

        mo.ADMISSION_ID IS NULL

        AND today_pp.MEDORDER_ID IS NULL

        AND NOT EXISTS
        (
            SELECT 1

            FROM SYARMIMI.PHARMACY_PREPARATION pp2

            WHERE pp2.MEDORDER_ID = mo.MEDORDER_ID
        )

    )

");

$pendingCount = (int) $pendingStmt->fetchColumn();


/* ==========================================================================
   FETCH MEDICATION ORDERS
========================================================================== */

/*
   IMPORTANT:

   Admission medication may have PATIENT_ID = NULL in
   MEDICATION_ORDER.

   Therefore:

       Admission
           MEDICATION_ORDER
               ↓
           ADMISSION
               ↓
            PATIENT

   is used.

   Appointment / Walk-In still uses MEDICATION_ORDER.PATIENT_ID.
*/

$sql = "

SELECT

    mo.MEDORDER_ID,

    /* ==============================================================
       PATIENT NAME
    ============================================================== */

    CASE

        WHEN mo.ADMISSION_ID IS NOT NULL
            THEN pa.NAME

        ELSE
            p.NAME

    END AS PATIENT_NAME,


    /* ==============================================================
       MEDICATION
    ============================================================== */

    m.MEDICATION_NAME,


    mo.DOSAGE,

    mo.FREQUENCY,


    /* ==============================================================
       LOCATION
    ============================================================== */

    CASE

        WHEN mo.ADMISSION_ID IS NOT NULL
            THEN NVL(w.WARD_NAME, 'Ward')

        ELSE
            'Pharmacy Counter'

    END AS WARD_NAME,


    CASE

        WHEN mo.ADMISSION_ID IS NOT NULL
            THEN NVL(b.BED_NUMBER, '-')

        ELSE
            '-'

    END AS BED_NUMBER,


    /* ==============================================================
       ORDER TYPE
    ============================================================== */

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


    /* ==============================================================
       COLLECTION METHOD
    ============================================================== */

    CASE

        WHEN mo.ADMISSION_ID IS NOT NULL
            THEN 'Nurse Pickup'

        ELSE
            'Patient Pickup'

    END AS COLLECTION_METHOD,


    /* ==============================================================
       TODAY'S PREPARATION
    ============================================================== */

    today_pp.STATUS AS TODAY_STATUS,


    /* ==============================================================
       LATEST PREPARATION FOR NON-ADMISSION
    ============================================================== */

    latest_pp.STATUS AS LATEST_STATUS,


    /* ==============================================================
       MEDICATION DATES
    ============================================================== */

    mo.MED_START_DATE,

    mo.MED_END_DATE,

    a.EXPECTED_DISCHARGE_DATE,

    a.DISCHARGE_DATE


FROM SYARMIMI.MEDICATION_ORDER mo


/* ==============================================================
   NORMAL PATIENT
================================================================ */

LEFT JOIN SYARMIMI.PATIENT p
    ON mo.PATIENT_ID = p.PATIENT_ID


/* ==============================================================
   ADMISSION
================================================================ */

LEFT JOIN SYARMIMI.ADMISSION a
    ON mo.ADMISSION_ID = a.ADMISSION_ID


/* ==============================================================
   ADMISSION PATIENT
================================================================ */

LEFT JOIN SYARMIMI.PATIENT pa
    ON a.PATIENT_ID = pa.PATIENT_ID


/* ==============================================================
   MEDICATION
================================================================ */

INNER JOIN SYARMIMI.MEDICATION m
    ON mo.MEDICATION_ID = m.MEDICATION_ID


/* ==============================================================
   BED
================================================================ */

LEFT JOIN SYARMIMI.BED b
    ON a.BED_ID = b.BED_ID


/* ==============================================================
   WARD
================================================================ */

LEFT JOIN SYARMIMI.WARD w
    ON b.WARD_ID = w.WARD_ID


/* ==============================================================
   TODAY'S PREPARATION

   One preparation per medication order per day.
================================================================ */

LEFT JOIN
(
    SELECT
        MEDORDER_ID,
        MAX(PREP_ID) AS PREP_ID
    FROM SYARMIMI.PHARMACY_PREPARATION
    WHERE TRUNC(PREPARED_TIME) = TRUNC(SYSDATE)
    GROUP BY MEDORDER_ID
) today_ids

    ON mo.MEDORDER_ID = today_ids.MEDORDER_ID


LEFT JOIN SYARMIMI.PHARMACY_PREPARATION today_pp

    ON today_ids.PREP_ID = today_pp.PREP_ID


/* ==============================================================
   LATEST PREPARATION

   Used mainly for Appointment / Walk-In.
================================================================ */

LEFT JOIN
(
    SELECT
        MEDORDER_ID,
        MAX(PREP_ID) AS PREP_ID
    FROM SYARMIMI.PHARMACY_PREPARATION
    GROUP BY MEDORDER_ID
) latest_ids

    ON mo.MEDORDER_ID = latest_ids.MEDORDER_ID


LEFT JOIN SYARMIMI.PHARMACY_PREPARATION latest_pp

    ON latest_ids.PREP_ID = latest_pp.PREP_ID


ORDER BY mo.MEDORDER_ID DESC

";

$stmt = $conn->query($sql);

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Prepare Medication</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
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

body {
    background: #f4f6f9;
}

.main-content {
    min-height: 100vh;
}

.box {
    background: white;
    padding: 24px;
    border-radius: 18px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.07);
}

.badge {
    padding: 7px 11px;
    border-radius: 20px;
    font-size: 12px;
    white-space: nowrap;
}

.table td {
    vertical-align: middle;
}

.table th {
    white-space: nowrap;
}

.table thead th {
    background: #212529;
    color: white;
    border: none;
}

.page-title {
    color: #172033;
}

</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="main-content p-4 w-100">

<!-- =========================================================
     HEADER
========================================================= -->

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3 class="fw-bold mb-0 page-title">
💊 Prepare Medication
</h3>

<small class="text-muted">
Manage medication preparation and pickup workflow
</small>

</div>

<div>

<span class="badge bg-warning text-dark fs-6 p-2">
Pending: <?= $pendingCount ?>
</span>

</div>

</div>


<!-- =========================================================
     ALERT
========================================================= -->

<?php if ($pendingCount > 0): ?>

<div class="alert alert-warning">

🔔

<strong><?= $pendingCount ?></strong>

medication(s) require preparation today.

</div>

<?php else: ?>

<div class="alert alert-success">

✅

No medication currently requires preparation today.

</div>

<?php endif; ?>


<!-- =========================================================
     TABLE
========================================================= -->

<div class="box">

<div class="row mb-3">

<div class="col-md-4">

<input
    type="text"
    id="searchInput"
    class="form-control"
    placeholder="🔍 Search patient or medication"
    value="<?= htmlspecialchars($currentSearch) ?>"
>

</div>


<div class="col-md-3">

<select
    id="typeFilter"
    class="form-select"
>

<option value="">
All Types
</option>

<option
    value="Walk-In"
    <?= $currentType === 'Walk-In' ? 'selected' : '' ?>
>
Walk-In
</option>

<option
    value="Appointment"
    <?= $currentType === 'Appointment' ? 'selected' : '' ?>
>
Appointment
</option>

<option
    value="Admission"
    <?= $currentType === 'Admission' ? 'selected' : '' ?>
>
Admission
</option>

</select>

</div>


<div class="col-md-3">

<select
    id="sortFilter"
    class="form-select"
>

<option
    value="desc"
    <?= $currentSort === 'desc' ? 'selected' : '' ?>
>
Newest First
</option>

<option
    value="asc"
    <?= $currentSort === 'asc' ? 'selected' : '' ?>
>
Oldest First
</option>

</select>

</div>

</div>


<div class="table-responsive">

<table
    id="medicationTable"
    class="table table-hover align-middle"
>

<thead>

<tr>

<th>ID</th>

<th>Patient</th>

<th>Type</th>

<th>Collection</th>

<th>Location</th>

<th>Medication</th>

<th>Dosage</th>

<th>Frequency</th>

<th>Medication Period</th>

<th>Status</th>

<th>Action</th>

</tr>

</thead>

<tbody>

<?php foreach ($orders as $row): ?>

<?php

/* ============================================================
   DETERMINE STATUS
============================================================ */

if ($row['ORDER_TYPE'] === 'Admission') {

    if (
        empty($row['DISCHARGE_DATE']) &&
        (
            empty($row['TODAY_STATUS']) ||
            $row['TODAY_STATUS'] === null
        )
    ) {

        /*
           No preparation today.

           Therefore:
           PENDING
        */

        $displayStatus = 'Pending';

    } else {

        /*
           Today's preparation exists.
        */

        $displayStatus =
            $row['TODAY_STATUS'] ?? 'Pending';
    }

} else {

    /*
       Appointment / Walk-In
    */

    $displayStatus =
        $row['LATEST_STATUS'] ?? 'Pending';
}


/* ============================================================
   CHECK WHETHER ADMISSION IS ACTIVE TODAY
============================================================ */

$admissionActiveToday = true;

if ($row['ORDER_TYPE'] === 'Admission') {

    if (!empty($row['DISCHARGE_DATE'])) {

        $admissionActiveToday = false;

    }

}

?>

<tr>

<!-- ID -->

<td>
<?= htmlspecialchars($row['MEDORDER_ID']) ?>
</td>


<!-- PATIENT -->

<td>

<strong>
<?= htmlspecialchars(
    $row['PATIENT_NAME'] ?? 'Unknown Patient'
) ?>
</strong>

</td>


<!-- TYPE -->

<td>

<?php if ($row['ORDER_TYPE'] === 'Appointment'): ?>

<span class="badge bg-primary">
Appointment
</span>

<?php elseif ($row['ORDER_TYPE'] === 'Admission'): ?>

<span class="badge bg-danger">
Admission
</span>

<?php elseif ($row['ORDER_TYPE'] === 'Walk-In'): ?>

<span class="badge bg-warning text-dark">
Walk-In
</span>

<?php else: ?>

<span class="badge bg-secondary">
Unknown
</span>

<?php endif; ?>

</td>


<!-- COLLECTION -->

<td>

<?php if (
    $row['COLLECTION_METHOD'] === 'Nurse Pickup'
): ?>

<span class="badge bg-info">
Nurse Pickup
</span>

<?php else: ?>

<span class="badge bg-success">
Patient Pickup
</span>

<?php endif; ?>

</td>


<!-- LOCATION -->

<td>

<?= htmlspecialchars(
    $row['WARD_NAME'] ?? '-'
) ?>

<?php if (
    $row['BED_NUMBER'] !== '-'
): ?>

<br>

<small class="text-muted">

Bed
<?= htmlspecialchars(
    $row['BED_NUMBER']
) ?>

</small>

<?php endif; ?>

</td>


<!-- MEDICATION -->

<td>

<?= htmlspecialchars(
    $row['MEDICATION_NAME']
) ?>

</td>


<!-- DOSAGE -->

<td>

<?= htmlspecialchars(
    $row['DOSAGE'] ?? '-'
) ?>

</td>


<!-- FREQUENCY -->

<td>

<?= htmlspecialchars(
    $row['FREQUENCY'] ?? '-'
) ?>

</td>


<!-- MEDICATION PERIOD -->

<td>

<?php if (
    $row['ORDER_TYPE'] === 'Admission'
): ?>

<?php

$startDate =
    !empty($row['MED_START_DATE'])
        ? date(
            'd-M-Y',
            strtotime($row['MED_START_DATE'])
        )
        : '-';

$endDate =
    !empty($row['MED_END_DATE'])
        ? date(
            'd-M-Y',
            strtotime($row['MED_END_DATE'])
        )
        : (
            !empty($row['EXPECTED_DISCHARGE_DATE'])
                ? date(
                    'd-M-Y',
                    strtotime($row['EXPECTED_DISCHARGE_DATE'])
                )
                : '-'
        );

?>

<small>

<?= htmlspecialchars($startDate) ?>

<br>

<span class="text-muted">
to
</span>

<br>

<?= htmlspecialchars($endDate) ?>

</small>

<?php else: ?>

<span class="text-muted">
One-time
</span>

<?php endif; ?>

</td>


<!-- STATUS -->

<td>

<?php if ($displayStatus === 'Pending'): ?>

<span class="badge bg-warning text-dark">
Pending
</span>

<?php elseif (
    $displayStatus === 'Ready For Pickup'
): ?>

<span class="badge bg-success">
Ready For Pickup
</span>

<?php elseif (
    $displayStatus === 'Ready For Nurse Pickup'
): ?>

<span class="badge bg-info">
Ready For Nurse Pickup
</span>

<?php elseif (
    $displayStatus === 'Collected'
): ?>

<span class="badge bg-primary">
Collected
</span>

<?php else: ?>

<span class="badge bg-secondary">
<?= htmlspecialchars($displayStatus) ?>
</span>

<?php endif; ?>

</td>


<!-- ACTION -->

<td>

<?php if (
    $displayStatus === 'Pending' &&
    $admissionActiveToday
): ?>

<a
    href="?prepare=<?= urlencode(
        $row['MEDORDER_ID']
    ) ?>&type=<?= urlencode($currentType) ?>&search=<?= urlencode($currentSearch) ?>&sort=<?= urlencode($currentSort) ?>"
    class="btn btn-success btn-sm prepareBtn"
>

<i class="bi bi-box-seam"></i>

Prepare

</a>

<?php elseif (
    $displayStatus === 'Ready For Pickup'
): ?>

<a
    href="?collect=<?= urlencode(
        $row['MEDORDER_ID']
    ) ?>&type=<?= urlencode($currentType) ?>&search=<?= urlencode($currentSearch) ?>&sort=<?= urlencode($currentSort) ?>"
    class="btn btn-primary btn-sm collectBtn"
>

<i class="bi bi-check-circle"></i>

Collected

</a>

<?php elseif (
    $displayStatus === 'Ready For Nurse Pickup'
): ?>

<button
    type="button"
    class="btn btn-info btn-sm"
    disabled
>

<i class="bi bi-person-walking"></i>

Waiting Nurse

</button>

<?php elseif (
    $displayStatus === 'Collected'
): ?>

<button
    type="button"
    class="btn btn-secondary btn-sm"
    disabled
>

<i class="bi bi-check2-all"></i>

Completed

</button>

<?php else: ?>

<button
    type="button"
    class="btn btn-secondary btn-sm"
    disabled
>

No Action

</button>

<?php endif; ?>

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
src="https://code.jquery.com/jquery-3.7.1.min.js">
</script>

<script
src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
</script>

<script
src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js">
</script>


<script>

$(document).ready(function () {

    const table = $('#medicationTable').DataTable({

        pageLength: 10,

        lengthMenu: [
            [10, 25, 50, 100],
            [10, 25, 50, 100]
        ],

        order: [
            [0, '<?= $currentSort ?>']
        ],

        searching: true,

        info: true,

        paging: true

    });


    /* ==========================================================
       RESTORE SEARCH
    ========================================================== */

    const currentSearch =
        <?= json_encode($currentSearch) ?>;

    if (currentSearch !== '') {

        table
            .search(currentSearch)
            .draw();

    }


    /* ==========================================================
       RESTORE TYPE
    ========================================================== */

    const currentType =
        <?= json_encode($currentType) ?>;

    if (currentType !== '') {

        table
            .column(2)
            .search(currentType)
            .draw();

    }


    /* ==========================================================
       SEARCH
    ========================================================== */

    $('#searchInput').on('keyup', function () {

        table
            .search(this.value)
            .draw();

    });


    /* ==========================================================
       TYPE
    ========================================================== */

    $('#typeFilter').on('change', function () {

        table
            .column(2)
            .search(this.value)
            .draw();

    });


    /* ==========================================================
       SORT
    ========================================================== */

    $('#sortFilter').on('change', function () {

        table
            .order([
                [0, this.value]
            ])
            .draw();

    });


    /* ==========================================================
       PREPARE CONFIRMATION
    ========================================================== */

    $(document).on(
        'click',
        '.prepareBtn',
        function (e) {

            e.preventDefault();

            const url = this.href;

            Swal.fire({

                title: 'Prepare Medication?',

                text:
                    'Confirm that this medication is ready for the patient.',

                icon: 'question',

                showCancelButton: true,

                confirmButtonColor: '#198754',

                cancelButtonColor: '#6c757d',

                confirmButtonText: 'Yes, Prepare',

                cancelButtonText: 'Cancel'

            }).then(function (result) {

                if (result.isConfirmed) {

                    window.location.href = url;

                }

            });

        }
    );


    /* ==========================================================
       PATIENT COLLECTION
    ========================================================== */

    $(document).on(
        'click',
        '.collectBtn',
        function (e) {

            e.preventDefault();

            const url = this.href;

            Swal.fire({

                title: 'Confirm Collection',

                text:
                    'Confirm that the medication has been handed to the patient.',

                icon: 'question',

                showCancelButton: true,

                confirmButtonColor: '#0d6efd',

                cancelButtonColor: '#6c757d',

                confirmButtonText: 'Yes, Collected',

                cancelButtonText: 'Cancel'

            }).then(function (result) {

                if (result.isConfirmed) {

                    window.location.href = url;

                }

            });

        }
    );

});


/* ==============================================================
   SUCCESS - PREPARED
============================================================== */

<?php if (isset($_GET['success'])): ?>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        <?php if (
            $_GET['success'] === 'already'
        ): ?>

        Swal.fire({

            icon: 'info',

            title: 'Already Prepared',

            text:
                'This medication has already been prepared for today.',

            confirmButtonColor: '#0d6efd'

        });

        <?php else: ?>

        Swal.fire({

            icon: 'success',

            title: 'Medication Ready',

            text:
                'Medication has been prepared successfully.',

            confirmButtonColor: '#198754'

        });

        <?php endif; ?>

    }
);

<?php endif; ?>


/* ==============================================================
   SUCCESS - COLLECTED
============================================================== */

<?php if (isset($_GET['collected'])): ?>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        Swal.fire({

            icon: 'success',

            title: 'Medication Collected',

            text:
                'Medication has been marked as collected successfully.',

            confirmButtonColor: '#0d6efd'

        });

    }
);

<?php endif; ?>

</script>

</body>

</html>