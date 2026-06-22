<?php
session_start();
include("../config/config.php");

/* ================= SECURITY ================= */
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access Denied");
}

/* ================= INSERT ================= */
if(isset($_POST['add'])){

    $name   = $_POST['name'] ?? '';
    $desc   = $_POST['desc'] ?? '';
    $dosage = $_POST['dosage'] ?? '';

    if(!empty($name) && !empty($desc) && !empty($dosage)){

        $sql = "INSERT INTO SYARMIMI.MEDICATION 
                (Medication_ID, Medication_Name, Description, Dosage_Form, STOCK)
                VALUES (SYARMIMI.MEDICATION_SEQ.NEXTVAL, :name, :desc, :dosage, 0)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':desc' => $desc,
            ':dosage' => $dosage
        ]);

        echo "<script>alert('Medication Added');</script>";
    }
}

/* ================= DELETE ================= */
if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM SYARMIMI.MEDICATION WHERE MEDICATION_ID=:id");
    $stmt->execute([':id'=>$id]);

    echo "<script>alert('Deleted'); window.location='medication.php';</script>";
}

/* ================= UPDATE ================= */
if(isset($_POST['update'])){
    $id     = $_POST['id'];
    $name   = $_POST['name'];
    $desc   = $_POST['desc'];
    $dosage = $_POST['dosage'];
    $stock  = $_POST['stock']; // ✅ added

    $stmt = $conn->prepare("
        UPDATE SYARMIMI.MEDICATION 
        SET MEDICATION_NAME=:name,
            DESCRIPTION=:desc,
            DOSAGE_FORM=:dosage,
            STOCK=:stock
        WHERE MEDICATION_ID=:id
    ");

    $stmt->execute([
        ':name'=>$name,
        ':desc'=>$desc,
        ':dosage'=>$dosage,
        ':stock'=>$stock,
        ':id'=>$id
    ]);

    echo "<script>alert('Updated'); window.location='medication.php';</script>";
}

/* ================= FETCH ================= */

$search = $_GET['search'] ?? '';

$sort = ($_GET['sort'] ?? 'ASC') == 'DESC'
    ? 'DESC'
    : 'ASC';

$stmt = $conn->prepare("
SELECT *
FROM SYARMIMI.MEDICATION
WHERE LOWER(MEDICATION_NAME)
LIKE LOWER(:search)
ORDER BY MEDICATION_NAME $sort
");

$stmt->execute([
    ':search' => '%' . $search . '%'
]);

$medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>
<title>Medication</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body { background:#f1f5f9; }

.box {
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,0.05);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_admin.php"); ?>

<div class="p-4 w-100">

<h4 class="mb-4">💊 Medication Management & Inventory</h4>

<!-- ADD -->
<div class="box mb-4">
<form method="POST">

<input name="name" class="form-control mb-2" placeholder="Medication Name" required>
<input name="desc" class="form-control mb-2" placeholder="Description" required>
<input name="dosage" class="form-control mb-2" placeholder="Dosage Form" required>

<button name="add" class="btn btn-primary w-100">
Add Medication
</button>

</form>
</div>

<div class="box mb-4">

<form method="GET">

<div class="row">

<div class="col-md-8">

<input
type="text"
name="search"
class="form-control"
placeholder="Search Medication..."
value="<?= htmlspecialchars($search) ?>">

</div>

<div class="col-md-2">

<select name="sort" class="form-control">

<option value="ASC"
<?= ($sort=='ASC') ? 'selected' : '' ?>>
Ascending
</option>

<option value="DESC"
<?= ($sort=='DESC') ? 'selected' : '' ?>>
Descending
</option>

</select>

</div>

<div class="col-md-2">

<button class="btn btn-primary w-100">
Search
</button>

</div>

</div>

</form>

</div>

<!-- TABLE -->
<div class="box">

<table class="table table-hover">

<thead>
<tr>
<th>ID</th>
<th>Name</th>
<th>Description</th>
<th>Dosage</th>
<th>Stock</th> <!-- ✅ added -->
<th>Action</th>
</tr>
</thead>

<tbody>

<?php foreach($medications as $m): ?>

<tr>

<form method="POST">

<td><?= $m['MEDICATION_ID'] ?></td>

<td>
<input name="name" value="<?= $m['MEDICATION_NAME'] ?>" class="form-control">
</td>

<td>
<input name="desc" value="<?= $m['DESCRIPTION'] ?>" class="form-control">
</td>

<td>
<input name="dosage" value="<?= $m['DOSAGE_FORM'] ?>" class="form-control">
</td>

<td>
<input type="number" name="stock" value="<?= $m['STOCK'] ?>" class="form-control">
</td>

<td>

<input type="hidden" name="id" value="<?= $m['MEDICATION_ID'] ?>">

<button name="update" class="btn btn-warning btn-sm">
Update
</button>

<a href="?delete=<?= $m['MEDICATION_ID'] ?>" 
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this medication?')">
Delete
</a>

</td>

</form>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

</div>
</div>

</body>
</html>