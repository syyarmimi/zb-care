<?php
session_start();
include("../config/config.php");

/* ================= ROLE ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

$staff_id = $_SESSION['user_id'] ?? 0;

/* ================= FETCH ================= */
$sql = "
SELECT 
mo.MEDORDER_ID,
p.NAME,
m.MEDICATION_NAME,
mo.DOSAGE,
mo.FREQUENCY,

hs.USERNAME AS DELIVERED_BY,

CASE 
    WHEN md.MEDORDER_ID IS NOT NULL THEN 'Delivered'
    ELSE 'Pending'
END AS STATUS,

md.DELIVERY_TIME

FROM SYARMIMI.MEDICATION_ORDER mo
JOIN SYARMIMI.ADMISSION a ON mo.ADMISSION_ID = a.ADMISSION_ID
JOIN SYARMIMI.PATIENT p ON a.PATIENT_ID = p.PATIENT_ID
JOIN SYARMIMI.MEDICATION m ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.MEDICATION_DELIVERY md 
ON mo.MEDORDER_ID = md.MEDORDER_ID

LEFT JOIN SYARMIMI.HOSPITAL_STAFF hs
ON md.ACCOUNT_ID = hs.ACCOUNT_ID

ORDER BY mo.MEDORDER_ID DESC
";

$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Delivery</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background:#eef2f7; }

.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}

.sidebar{
width:260px !important;
min-width:260px !important;
max-width:260px !important;
height:100vh;
flex-shrink:0;
}

/* ===== REPAIR PART (CSS UNTUK ALIGNMENT) ===== */
.dataTables_wrapper .row:first-child {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    margin-bottom: 15px;
    width: 100%;
}

/* Kecilkan kotak dropdown 'Show entries' */
.dataTables_length select {
    width: auto !important;
    display: inline-block !important;
    margin: 0 5px !important;
    padding-right: 30px !important;
}

/* Search ke kanan */
.dataTables_filter {
    text-align: right !important;
}

.dataTables_filter input {
    width: 200px !important;
    display: inline-block !important;
    margin-left: 10px !important;
}

/* PAGINATION & INFO KE KANAN */
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
/* ============================================= */
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-3">💊 Medication Delivery</h4>

<div class="box mb-4">

<div class="row">

<div class="col-md-4">
<input
type="text"
id="deliverySearch"
class="form-control"
placeholder="🔍 Search Patient / Medication">
</div>

<div class="col-md-4">
<select
id="statusFilter"
class="form-select">

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

<div class="col-md-4">
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

</div>

<div class="box">

<div class="table-responsive">

<table
id="deliveryTable"
class="table table-bordered table-hover">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>Patient</th>
<th>Medication</th>
<th>Dosage</th>
<th>Frequency</th>
<th>Status</th>
<th>Delivered By</th>
<th>Delivered Time</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)): ?>

<tr>

<td><?= $row['MEDORDER_ID'] ?></td>
<td><?= $row['NAME'] ?></td>
<td><?= $row['MEDICATION_NAME'] ?></td>
<td><?= $row['DOSAGE'] ?></td>
<td><?= $row['FREQUENCY'] ?></td>

<td>
<?php if($row['STATUS']=='Delivered'): ?>
<span class="badge bg-success">Delivered</span>
<?php else: ?>
<span class="badge bg-warning text-dark">Pending</span>
<?php endif; ?>
</td>

<td>
<?= $row['DELIVERED_BY'] ?? '-' ?>
</td>

<td>
<?= $row['DELIVERY_TIME'] ?? '-' ?>
</td>

<td>
<a href="medication_order_details.php?id=<?= $row['MEDORDER_ID'] ?>"
class="btn btn-primary btn-sm">

<i class="bi bi-eye"></i>
View

</a>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

</div> <!-- table-responsive -->

</div> <!-- box -->

</div> <!-- p-4 w-100 -->

</div> <!-- d-flex -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<script>

$(document).ready(function(){

var table = $('#deliveryTable').DataTable({

    // ===== REPAIR PART (DOM UNTUK TOLAK INFO & PAGINATION KE KANAN) =====
    dom: '<"row"<"col-sm-6"l><"col-sm-6 text-end"f>>t<"row"<"col-sm-12 text-end"i><"col-sm-12 text-end"p>>',
    // ===================================================================

    pageLength:10,

    order:[[0,'desc']],

    lengthMenu:[
        [10,25,50,100],
        [10,25,50,100]
    ],

    language: {
        lengthMenu: "Show _MENU_ entries",
        search: "Search:",
    }

});

    $('#deliverySearch').on('keyup', function(){

        table.search(this.value).draw();

    });

    $('#statusFilter').on('change', function(){

        table.column(5)
             .search(this.value)
             .draw();

    });

    $('#sortFilter').on('change', function(){

        if(this.value == 'asc')
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

</body>
</html>