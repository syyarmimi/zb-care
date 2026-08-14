<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}

/* ==========================================================================
   HANDLE PREPARE ACTION
   ========================================================================== */
if (isset($_GET['prepare'])) {
    $medOrderId = $_GET['prepare'];
    $staffId = $_SESSION['user_id'];

    $typeStmt = $conn->prepare("
        SELECT ADMISSION_ID, APPOINTMENT_ID, CONSULTATION_ID
        FROM SYARMIMI.MEDICATION_ORDER
        WHERE MEDORDER_ID = :id
    ");
    $typeStmt->execute([':id' => $medOrderId]);
    $orderInfo = $typeStmt->fetch(PDO::FETCH_ASSOC);

    // Explicit checking matching SQL CASE criteria
    if (!empty($orderInfo['ADMISSION_ID'])) {
        $status = 'Ready For Nurse Pickup';
    } else {
        $status = 'Ready For Pickup';
    }

    try {
        $check = $conn->prepare("SELECT COUNT(*) FROM SYARMIMI.PHARMACY_PREPARATION WHERE MEDORDER_ID = :id");
        $check->execute([':id' => $medOrderId]);

        if ($check->fetchColumn() > 0) {
            echo "<script>alert('Already Prepared'); window.location='pharmacy_preparation.php';</script>";
            exit();
        }

        $sql = "INSERT INTO SYARMIMI.PHARMACY_PREPARATION
                (PREP_ID, STATUS, PREPARED_TIME, MEDORDER_ID, ACCOUNT_ID)
                VALUES (SYARMIMI.PREPARATION_SEQ.NEXTVAL, :status, SYSDATE, :orderId, :staff)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':status'  => $status,
            ':orderId' => $medOrderId,
            ':staff'   => $staffId
        ]);

        header("Location: pharmacy_preparation.php?success=1");
        exit();
    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}

/* ==========================================================================
   HANDLE COLLECTED ACTION
   ========================================================================== */
if (isset($_GET['collect'])) {
    $medOrderId = $_GET['collect'];

    $update = $conn->prepare("
        UPDATE SYARMIMI.PHARMACY_PREPARATION
        SET STATUS = 'Collected'
        WHERE MEDORDER_ID = :id
    ");
    $update->execute([':id' => $medOrderId]);

    header("Location: pharmacy_preparation.php?collected=1");
    exit();
}

/* ==========================================================================
   COUNT PENDING ORDERS
   ========================================================================== */
$pendingCount = $conn->query("
    SELECT COUNT(*) 
    FROM SYARMIMI.MEDICATION_ORDER mo
    LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp ON mo.MEDORDER_ID = pp.MEDORDER_ID
    WHERE pp.MEDORDER_ID IS NULL
")->fetchColumn();

/* ==========================================================================
   FETCH MEDICATION ORDERS (Optimized Single Authoritative Query)
   ========================================================================== */
$sql = "
SELECT
    mo.MEDORDER_ID,
    p.NAME AS PATIENT_NAME,
    m.MEDICATION_NAME,
    mo.DOSAGE,
    mo.FREQUENCY,
    NVL(w.WARD_NAME, 'Pharmacy Counter') AS WARD_NAME,
    NVL(b.BED_NUMBER, '-') AS BED_NUMBER,
    NVL(pp.STATUS, 'Pending') AS STATUS,
    CASE 
        WHEN mo.ADMISSION_ID IS NOT NULL THEN 'Admission'
        WHEN mo.APPOINTMENT_ID IS NOT NULL THEN 'Appointment'
        WHEN mo.CONSULTATION_ID IS NOT NULL THEN 'Walk-In'
        ELSE 'Unknown'
    END AS ORDER_TYPE,
    CASE 
        WHEN mo.ADMISSION_ID IS NOT NULL THEN 'Nurse Pickup'
        ELSE 'Patient Pickup'
    END AS COLLECTION_METHOD
FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.PATIENT p ON mo.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID
LEFT JOIN SYARMIMI.ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
LEFT JOIN SYARMIMI.BED b ON a.BED_ID = b.BED_ID
LEFT JOIN SYARMIMI.WARD w ON b.WARD_ID = w.WARD_ID
LEFT JOIN SYARMIMI.PHARMACY_PREPARATION pp ON mo.MEDORDER_ID = pp.MEDORDER_ID
ORDER BY mo.MEDORDER_ID DESC
";

$orders = $conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prepare Medication</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if(isset($_GET['success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: 'success',
                title: 'Medication Ready',
                text: 'Medication has been prepared successfully and workflow status updated.',
                confirmButtonColor: '#198754'
            });
        });
    </script>
    <?php endif; ?>

    <?php if(isset($_GET['collected'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function(){
            Swal.fire({
                icon: 'success',
                title: 'Medication Collected',
                text: 'Medication has been marked as collected successfully.',
                confirmButtonColor: '#0d6efd'
            });
        });
    </script>
    <?php endif; ?>

    <style>
        body { background:#f4f6f9; }
        .box { background:white; padding:25px; border-radius:20px; box-shadow:0 10px 25px rgba(0,0,0,.08); }
        .badge { padding:8px 12px; border-radius:20px; font-size:12px; }
        .table td { vertical-align:middle; }
    </style>
</head>
<body>

<div class="d-flex">
    <?php include("../includes/sidebar_pharma.php"); ?>

    <div class="p-4 w-100">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold mb-0">💊 Prepare Medication</h3>
                <small class="text-muted">Manage medication preparation and pickup workflow</small>
            </div>
            <div>
                <span class="badge bg-warning fs-6 p-2">Pending: <?= $pendingCount ?></span>
            </div>
        </div>

        <?php if($pendingCount > 0): ?>
            <div class="alert alert-warning">🔔 <?= $pendingCount ?> medication(s) pending preparation</div>
        <?php endif; ?>

        <div class="box">
            <div class="row mb-3">
                <div class="col-md-4">
                    <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search patient or medication">
                </div>
                <div class="col-md-3">
                    <select id="typeFilter" class="form-select">
                        <option value="">All Types</option>
                        <option value="Walk-In">Walk-In</option>
                        <option value="Appointment">Appointment</option>
                        <option value="Admission">Admission</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="sortFilter" class="form-select">
                        <option value="desc">Newest First</option>
                        <option value="asc">Oldest First</option>
                    </select>
                </div>
            </div>

            <table id="medicationTable" class="table table-hover align-middle">
                <thead style="background:#0f172a; color:white;">
                    <tr>
                        <th>ID</th>
                        <th>Patient</th>
                        <th>Type</th>
                        <th>Collection</th>
                        <th>Location</th>
                        <th>Medication</th>
                        <th>Dosage</th>
                        <th>Frequency</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($orders as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['MEDORDER_ID']) ?></td>
                        <td><?= htmlspecialchars($row['PATIENT_NAME']) ?></td>
                        <td>
                            <?php if($row['ORDER_TYPE'] == 'Admission'): ?>
                                <span class='badge bg-danger'>Admission</span>
                            <?php elseif($row['ORDER_TYPE'] == 'Appointment'): ?>
                                <span class='badge bg-primary'>Appointment</span>
                            <?php else: ?>
                                <span class='badge bg-warning text-dark'>Walk-In</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['COLLECTION_METHOD'] == 'Nurse Pickup'): ?>
                                <span class='badge bg-info'>Nurse Pickup</span>
                            <?php else: ?>
                                <span class='badge bg-success'>Patient Pickup</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($row['WARD_NAME']) ?>
                            <?php if($row['BED_NUMBER'] != '-'): ?>
                                <br><small class='text-muted'>Bed <?= htmlspecialchars($row['BED_NUMBER']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['MEDICATION_NAME']) ?></td>
                        <td><?= htmlspecialchars($row['DOSAGE']) ?></td>
                        <td><?= htmlspecialchars($row['FREQUENCY']) ?></td>
                        <td>
                            <?php if($row['STATUS'] == 'Ready For Pickup'): ?>
                                <span class='badge bg-success'>Ready For Pickup</span>
                            <?php elseif($row['STATUS'] == 'Ready For Nurse Pickup'): ?>
                                <span class='badge bg-info'>Ready For Nurse Pickup</span>
                            <?php elseif($row['STATUS'] == 'Collected'): ?>
                                <span class='badge bg-primary'>Collected</span>
                            <?php else: ?>
                                <span class='badge bg-warning text-dark'>Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($row['STATUS'] == 'Pending'): ?>
                                <a href="?prepare=<?= $row['MEDORDER_ID'] ?>" class="btn btn-success btn-sm prepareBtn">Prepare</a>
                            <?php elseif($row['STATUS'] == 'Ready For Pickup'): ?>
                                <a href="?collect=<?= $row['MEDORDER_ID'] ?>" class="btn btn-primary btn-sm collectBtn">Collected</a>
                            <?php elseif($row['STATUS'] == 'Ready For Nurse Pickup'): ?>
                                <button class="btn btn-info btn-sm" disabled>Waiting Nurse</button>
                            <?php elseif($row['STATUS'] == 'Collected'): ?>
                                <button class="btn btn-secondary btn-sm" disabled>Completed</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function(){
    var table = $('#medicationTable').DataTable({
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        order: [[0, 'desc']]
    });

    $('#searchInput').on('keyup', function(){
        table.search(this.value).draw();
    });

    $('#typeFilter').on('change', function(){
        table.column(2).search(this.value).draw();
    });

    $('#sortFilter').on('change', function(){
        table.order([0, this.value]).draw();
    });
});

// SweetAlert confirmation logic
document.querySelectorAll('.prepareBtn').forEach(button => {
    button.addEventListener('click', function(e){
        e.preventDefault();
        let url = this.href;
        Swal.fire({
            title: 'Confirm Preparation',
            html: `<div class="text-start">Assigning workflow tracking:<br><br><b>Walk-In / Appointment</b> → Ready For Pickup<br><b>Admission</b> → Ready For Nurse Pickup</div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Confirm'
        }).then((result)=>{
            if(result.isConfirmed) window.location.href = url;
        });
    });
});

document.querySelectorAll('.collectBtn').forEach(button => {
    button.addEventListener('click', function(e){
        e.preventDefault();
        let url = this.href;
        Swal.fire({
            title: 'Confirm Collection',
            text: 'Medication has been handed over successfully.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0d6efd',
            confirmButtonText: 'Confirm'
        }).then((result)=>{
            if(result.isConfirmed) window.location.href = url;
        });
    });
});
</script>
</body>
</html>