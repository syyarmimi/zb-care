<?php
session_start();
include("../config/config.php");

// ✅ ROLE CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'pharmacist') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   DELIVER ACTION
========================= */
if(isset($_GET['deliver'])){

    $medOrderId = $_GET['deliver'];
    $staffId = $_SESSION['user_id'];

    try {

        // ✅ INSERT DELIVERY RECORD (FIXED NO SEQUENCE)
        $sqlInsert = "INSERT INTO SYARMIMI.MEDICATION_DELIVERY
                      (MEDDELIVERY_ID, DELIVERY_TIME, STATUS, STAFF_ID)
                      VALUES (
                        (SELECT NVL(MAX(MEDDELIVERY_ID),0)+1 FROM SYARMIMI.MEDICATION_DELIVERY),
                        SYSDATE,
                        'Delivered',
                        :staff
                      )";

        $stmt = $conn->prepare($sqlInsert);
        $stmt->execute([
            ':staff' => $staffId
        ]);

        // ✅ UPDATE STATUS IN PREPARATION
        $sqlUpdate = "UPDATE SYARMIMI.PHARMACY_PREPARATION
                      SET STATUS = 'Delivered'
                      WHERE MEDORDER_ID = :id";

        $stmt = $conn->prepare($sqlUpdate);
        $stmt->execute([
            ':id' => $medOrderId
        ]);

        header("Location: med_delivery.php");
        exit();

    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}

/* =========================
   FETCH PREPARED MEDICATION
========================= */
$sql = "SELECT mo.MEDORDER_ID,
               mo.ADMISSION_ID,
               p.NAME,
               m.MEDICATION_NAME,
               mo.DOSAGE,
               mo.FREQUENCY,
               pp.STATUS

        FROM SYARMIMI.MEDICATION_ORDER mo

        JOIN SYARMIMI.PHARMACY_PREPARATION pp
        ON mo.MEDORDER_ID = pp.MEDORDER_ID

        JOIN SYARMIMI.ADMISSION a
        ON mo.ADMISSION_ID = a.ADMISSION_ID

        JOIN SYARMIMI.PATIENT p
        ON a.PATIENT_ID = p.PATIENT_ID

        JOIN SYARMIMI.MEDICATION m
        ON mo.MEDICATION_ID = m.MEDICATION_ID

        WHERE pp.STATUS = 'Prepared'
        ORDER BY mo.MEDORDER_ID DESC";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Delivery</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#f4f6f9; }
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="flex-grow-1 p-4">

<h3>🚚 Medication Delivery</h3>

<div class="card p-3 shadow-sm mt-3">

<h5>Prepared Medication List</h5>

<table class="table table-bordered table-striped mt-3">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Status</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>

<tr>
<td><?= $row['MEDORDER_ID']; ?></td>
<td><?= $row['NAME']; ?></td>
<td><?= $row['MEDICATION_NAME']; ?></td>
<td><?= $row['DOSAGE']; ?></td>
<td><?= $row['FREQUENCY']; ?></td>

<td>
<span class="badge bg-warning">
<?= $row['STATUS']; ?>
</span>
</td>

<td>
<a href="med_delivery.php?deliver=<?= $row['MEDORDER_ID']; ?>" 
   class="btn btn-success btn-sm"
   onclick="return confirm('Confirm delivery?')">
   Deliver
</a>
</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</body>
</html>