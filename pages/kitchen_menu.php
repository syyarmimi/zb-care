<?php
session_start();
include("../config/config.php");

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'kitchen') {
    header("Location: ../auth/login.php");
    exit();
}

/* ================= ADD MENU ================= */
if(isset($_POST['add'])){
    $name = $_POST['name'];
    $diet = $_POST['diet'];

    $conn->prepare("
    INSERT INTO SYARMIMI.MENU_ITEM
    (MENUITEM_ID, FOOD_NAME, DIET_ID)
    VALUES (SYARMIMI.MENU_SEQ.NEXTVAL, :name, :diet)
    ")->execute([
        ':name'=>$name,
        ':diet'=>$diet
    ]);

    echo "<script>alert('Menu Added!'); window.location='kitchen_menu.php';</script>";
}

/* ================= FETCH ================= */
$menus = $conn->query("
SELECT m.MENUITEM_ID, m.FOOD_NAME, d.DIET_NAME
FROM SYARMIMI.MENU_ITEM m
JOIN SYARMIMI.DIET_TYPE d ON m.DIET_ID = d.DIET_ID
ORDER BY m.MENUITEM_ID DESC
");

$diets = $conn->query("SELECT * FROM SYARMIMI.DIET_TYPE")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
<title>Kitchen Menu</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { background: linear-gradient(135deg, #fff7ed, #ffedd5); }

.box {
    background:white;
    padding:25px;
    border-radius:20px;
    box-shadow:0 8px 20px rgba(0,0,0,0.08);
}
</style>
</head>

<body>

<div class="d-flex">
<?php include("../includes/sidebar_kitchen.php"); ?>

<div class="p-4 w-100">

<h3 class="mb-4">🍱 Manage Menu</h3>

<div class="box mb-4">

<form method="POST">

<input name="name" class="form-control mb-2" placeholder="Food Name" required>

<select name="diet" class="form-control mb-2" required>
<option value="">Select Diet</option>
<?php foreach($diets as $d): ?>
<option value="<?= $d['DIET_ID'] ?>">
<?= $d['DIET_NAME'] ?>
</option>
<?php endforeach; ?>
</select>

<button name="add" class="btn btn-success w-100">
➕ Add Menu
</button>

</form>

</div>

<div class="box">

<table class="table table-hover">

<thead>
<tr>
<th>🍲 Food</th>
<th>🥗 Diet Type</th>
</tr>
</thead>

<tbody>
<?php while($m = $menus->fetch(PDO::FETCH_ASSOC)): ?>
<tr>
<td><?= $m['FOOD_NAME'] ?></td>
<td><?= $m['DIET_NAME'] ?></td>
</tr>
<?php endwhile; ?>
</tbody>

</table>

</div>

</div>
</div>

</body>
</html>