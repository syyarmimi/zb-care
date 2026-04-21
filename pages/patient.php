<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= SORT ================= */
$sort = $_GET['sort'] ?? 'desc';
$order = ($sort === 'asc') ? 'ASC' : 'DESC';

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM SYARMIMI.PATIENT WHERE PATIENT_ID = ?");
    $stmt->execute([$id]);

    header("Location: patient.php");
    exit();
}

/* ================= EDIT FETCH ================= */
$editData = null;

if(isset($_GET['edit'])){
    $id = $_GET['edit'];

    $stmt = $conn->prepare("SELECT * FROM SYARMIMI.PATIENT WHERE PATIENT_ID = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){
    $id      = $_POST['id'];
    $ic      = $_POST['ic'];
    $name    = $_POST['name'];
    $age     = $_POST['age'];
    $gender  = $_POST['gender'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];

    $sql = "UPDATE SYARMIMI.PATIENT 
            SET IC_NUMBER=?, NAME=?, AGE=?, GENDER=?, PHONE=?, ADDRESS=?
            WHERE PATIENT_ID=?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$ic,$name,$age,$gender,$phone,$address,$id]);

    header("Location: patient.php");
    exit();
}

/* ================= ADD ================= */
if(isset($_POST['add'])){
    $ic      = $_POST['ic'];
    $name    = $_POST['name'];
    $age     = $_POST['age'];
    $gender  = $_POST['gender'];
    $phone   = $_POST['phone'];
    $address = $_POST['address'];

    try {
        // manual ID (no sequence)
        $sql = "SELECT NVL(MAX(PATIENT_ID),0)+1 AS NEW_ID FROM SYARMIMI.PATIENT";
        $stmt = $conn->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $newId = $row['NEW_ID'];

        $sql = "INSERT INTO SYARMIMI.PATIENT 
                (PATIENT_ID, IC_NUMBER, NAME, AGE, GENDER, PHONE, ADDRESS)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $newId, $ic, $name, $age, $gender, $phone, $address
        ]);

        header("Location: patient.php");
        exit();

    } catch(PDOException $e){
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Patient Management</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
.card { border-radius: 12px; }
.btn { border-radius: 8px; }
</style>

</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<h3 class="mb-3">👤 Patient Management</h3>

<!-- FORM -->
<div class="card p-3 mb-3 shadow-sm">

<form method="POST">

<input type="hidden" name="id" value="<?= $editData['PATIENT_ID'] ?? '' ?>">

<div class="row mb-2">
<div class="col-md-4">
<input type="text" name="ic" class="form-control"
value="<?= $editData['IC_NUMBER'] ?? '' ?>" placeholder="IC Number" required>
</div>

<div class="col-md-4">
<input type="text" name="name" class="form-control"
value="<?= $editData['NAME'] ?? '' ?>" placeholder="Name" required>
</div>

<div class="col-md-2">
<input type="number" name="age" class="form-control"
value="<?= $editData['AGE'] ?? '' ?>" placeholder="Age" required>
</div>

<div class="col-md-2">
<select name="gender" class="form-control" required>
    <option disabled <?= !$editData ? 'selected' : '' ?>>Gender</option>
    <option value="Male" <?= (($editData['GENDER'] ?? '')=='Male')?'selected':'' ?>>Male</option>
    <option value="Female" <?= (($editData['GENDER'] ?? '')=='Female')?'selected':'' ?>>Female</option>
</select>
</div>
</div>

<div class="row mb-2">
<div class="col-md-4">
<input type="text" name="phone" class="form-control"
value="<?= $editData['PHONE'] ?? '' ?>" placeholder="Phone" required>
</div>

<div class="col-md-8">
<input type="text" name="address" class="form-control"
value="<?= $editData['ADDRESS'] ?? '' ?>" placeholder="Address" required>
</div>
</div>

<?php if($editData){ ?>
    <button class="btn btn-warning" name="update">Update Patient</button>
    <a href="patient.php" class="btn btn-secondary">Cancel</a>
<?php } else { ?>
    <button class="btn btn-primary" name="add">Add Patient</button>
<?php } ?>

</form>

</div>

<!-- TABLE -->
<div class="card p-3 shadow-sm">

<h5>📋 Patient List</h5>

<!-- SORT DROPDOWN -->
<form method="GET" class="mb-3">
<select name="sort" class="form-control w-25" onchange="this.form.submit()">
    <option disabled <?= !isset($_GET['sort']) ? 'selected' : '' ?>>Sort Order</option>
    <option value="asc" <?= ($sort=='asc')?'selected':'' ?>>Ascending (Oldest First)</option>
    <option value="desc" <?= ($sort=='desc')?'selected':'' ?>>Descending (Newest First)</option>
</select>
</form>

<table class="table table-hover table-bordered">

<thead class="table-dark">
<tr>
<th>ID</th>
<th>IC</th>
<th>Name</th>
<th>Age</th>
<th>Gender</th>
<th>Phone</th>
<th>Address</th>
<th>Action</th>
</tr>
</thead>

<tbody>

<?php
$stmt = $conn->query("SELECT * FROM SYARMIMI.PATIENT ORDER BY PATIENT_ID $order");

while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
?>
<tr>
<td><?= $row['PATIENT_ID'] ?></td>
<td><?= $row['IC_NUMBER'] ?></td>
<td><?= $row['NAME'] ?></td>
<td><?= $row['AGE'] ?></td>
<td><?= $row['GENDER'] ?></td>
<td><?= $row['PHONE'] ?></td>
<td><?= $row['ADDRESS'] ?></td>

<td>
    <a href="?edit=<?= $row['PATIENT_ID'] ?>" class="btn btn-warning btn-sm">Edit</a>
    <a href="?delete=<?= $row['PATIENT_ID'] ?>"
       class="btn btn-danger btn-sm"
       onclick="return confirm('Delete this patient?')">
       Delete
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