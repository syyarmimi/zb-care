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

       header("Location: med_delivery.php?success=1");
        exit();

    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}

/* =========================
   FETCH PREPARED MEDICATION
========================= */
$sql = "

SELECT

mo.MEDORDER_ID,

p.NAME,

m.MEDICATION_NAME,

mo.DOSAGE,

mo.FREQUENCY,

pp.STATUS,

CASE

WHEN mo.ADMISSION_ID IS NOT NULL
THEN 'Admission'

WHEN mo.APPOINTMENT_ID IS NOT NULL
THEN 'Appointment'

WHEN mo.CONSULTATION_ID IS NOT NULL
THEN 'Walk-In'

ELSE 'Unknown'

END AS ORDER_TYPE,

CASE
WHEN mo.ADMISSION_ID IS NOT NULL
THEN 'Nurse Pickup'
ELSE 'Patient Pickup'
END AS PICKUP_METHOD

FROM SYARMIMI.MEDICATION_ORDER mo

JOIN SYARMIMI.PHARMACY_PREPARATION pp
ON mo.MEDORDER_ID = pp.MEDORDER_ID

JOIN SYARMIMI.PATIENT p
ON mo.PATIENT_ID = p.PATIENT_ID

JOIN SYARMIMI.MEDICATION m
ON mo.MEDICATION_ID = m.MEDICATION_ID

WHERE pp.STATUS = 'Prepared'

ORDER BY mo.MEDORDER_ID DESC

";

$deliveryCount = $conn->query("

SELECT COUNT(*)

FROM SYARMIMI.PHARMACY_PREPARATION

WHERE STATUS = 'Prepared'

")->fetchColumn();

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Delivery</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if(isset($_GET['success'])): ?>

<script>

document.addEventListener('DOMContentLoaded', function(){

Swal.fire({

icon:'success',

title:'Medication Delivered',

text:'Medication delivery has been completed successfully.',

confirmButtonColor:'#198754'

});

});

</script>

<?php endif; ?>

<style>
body { background:#f4f6f9; }
</style>
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_pharma.php"); ?>

<div class="flex-grow-1 p-4">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h3 class="fw-bold mb-0">
🚚 Medication Delivery
</h3>

<small class="text-muted">
Manage medication pickup and delivery workflow
</small>

</div>

<div>

<span class="badge bg-primary fs-6 p-2">
Ready: <?= $deliveryCount ?>
</span>

</div>

</div>

<div class="card p-3 shadow-sm mt-3">

<h5>Prepared Medication List</h5>

<div class="row mb-3">

<div class="col-md-4">

<input
type="text"
id="searchInput"
class="form-control"
placeholder="🔍 Search patient or medication">

</div>

<div class="col-md-3">

<select
id="typeFilter"
class="form-select">

<option value="">
All Types
</option>

<option value="Walk-In">
Walk-In
</option>

<option value="Appointment">
Appointment
</option>

<option value="Admission">
Admission
</option>

</select>

</div>

<div class="col-md-3">

<select
id="sortFilter"
class="form-select">

<option value="desc">
Newest First
</option>

<option value="asc">
Oldest First
</option>

</select>

</div>

</div>

<table
id="deliveryTable"
class="table table-hover align-middle">

<thead class="table-dark">
<tr>

<th>ID</th>
<th>Patient</th>
<th>Type</th>
<th>Pickup Method</th>
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

<td>

<?php

if($row['ORDER_TYPE']=='Admission')
{
echo "<span class='badge bg-danger'>Admission</span>";
}
elseif($row['ORDER_TYPE']=='Appointment')
{
echo "<span class='badge bg-primary'>Appointment</span>";
}
else
{
echo "<span class='badge bg-warning text-dark'>Walk-In</span>";
}

?>

</td>

<td>

<?php

if($row['PICKUP_METHOD']=='Nurse Pickup')
{
    echo "<span class='badge bg-info'>Nurse Pickup</span>";
}
else
{
    echo "<span class='badge bg-success'>Patient Pickup</span>";
}

?>

</td>

<td><?= $row['MEDICATION_NAME']; ?></td>

<td><?= $row['DOSAGE']; ?></td>

<td><?= $row['FREQUENCY']; ?></td>

<td>

<span class="badge bg-warning text-dark">
Prepared
</span>

</td>

<td>

<a href="med_delivery.php?deliver=<?= $row['MEDORDER_ID']; ?>"
class="btn btn-success btn-sm deliverBtn">

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

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

var table =
$('#deliveryTable').DataTable({

pageLength:10,

lengthMenu:[
[10,25,50,100],
[10,25,50,100]
],

order:[[0,'desc']],

dom:'t'

});

$('#searchInput').on('keyup', function(){

table.search(this.value).draw();

});

$('#typeFilter').on('change', function(){

table.column(2)
.search(this.value)
.draw();

});

$('#sortFilter').on('change', function(){

if(this.value=='asc')
{
table.order([0,'asc']).draw();
}
else
{
table.order([0,'desc']).draw();
}

});

});

</script>

<script>

document.querySelectorAll('.deliverBtn').forEach(button => {

button.addEventListener('click', function(e){

e.preventDefault();

let url = this.href;

Swal.fire({

title:'Confirm Delivery',

html:`

Medication will be delivered to patient.

<br><br>

This action cannot be undone.

`,

icon:'question',

showCancelButton:true,

confirmButtonColor:'#198754',

cancelButtonColor:'#6c757d',

confirmButtonText:'Deliver',

cancelButtonText:'Cancel'

})
.then((result)=>{

if(result.isConfirmed)
{
window.location.href=url;
}

});

});

});

</script>

</body>
</html>