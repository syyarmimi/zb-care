<?php
session_start();
include("../config/config.php");

/* ✅ ADD ROLE CHECK */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

if(isset($_POST['update'])){
    $id = $_POST['id'];
    $stock = $_POST['stock'];

    $conn->prepare("
    UPDATE SYARMIMI.MEDICATION
    SET STOCK = :s
    WHERE MEDICATION_ID = :id
    ")->execute([
        ':s'=>$stock,
        ':id'=>$id
    ]);
}

$meds = $conn->query("SELECT * FROM SYARMIMI.MEDICATION");
?>

<!DOCTYPE html>
<html>
<head>
<title>Medication Inventory</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="d-flex">

<!-- ✅ ADD SIDEBAR -->
<?php include("../includes/sidebar_admin.php"); ?>

<!-- ✅ CONTENT -->
<div class="p-4 w-100">

<h3>💊 Medication Inventory</h3>

<table class="table table-bordered">

<tr>
<th>Name</th>
<th>Stock</th>
<th>Update</th>
</tr>

<?php while($m = $meds->fetch(PDO::FETCH_ASSOC)): ?>
<tr>

<td><?= $m['MEDICATION_NAME'] ?></td>

<td>
<form method="POST">
<input type="hidden" name="id" value="<?= $m['MEDICATION_ID'] ?>">
<input type="number" name="stock" value="<?= $m['STOCK'] ?>" class="form-control">
</td>

<td>
<button name="update" class="btn btn-primary btn-sm">Update</button>
</form>
</td>

</tr>
<?php endwhile; ?>

</table>

</div>
</div>

</body>
</html>