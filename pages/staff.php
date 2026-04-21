<?php
session_start();
include("../config/config.php");

// 🔐 SESSION CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

/* =========================
   ADD STAFF (FIXED)
========================= */
if(isset($_POST['add'])){
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // 🔐 HASH PASSWORD
    $role     = $_POST['role'];

    try {
        // ✅ NO ACCOUNT_ID HERE (Oracle auto generate)
        $sql = "INSERT INTO SYARMIMI.HOSPITAL_STAFF 
                (USERNAME, PASSWORD, ROLE) 
                VALUES (?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $password, $role]);

    } catch (PDOException $e) {
        die("Insert Error: " . $e->getMessage());
    }
}

/* =========================
   DELETE STAFF
========================= */
if(isset($_GET['delete'])){
    $id = $_GET['delete'];

    $sql = "DELETE FROM SYARMIMI.HOSPITAL_STAFF WHERE ACCOUNT_ID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$id]);
}

/* =========================
   FETCH STAFF
========================= */
$sql = "SELECT * FROM SYARMIMI.HOSPITAL_STAFF ORDER BY ACCOUNT_ID";
$stmt = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Staff</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<div class="d-flex">

<?php include("../includes/sidebar_admin.php"); ?>

<div class="flex-grow-1 p-4">

<div class="container-fluid">

    <h3>👥 Manage Staff</h3>

    <!-- ADD STAFF FORM -->
    <div class="card p-3 mb-4 shadow-sm">
        <h5>Add New Staff</h5>

        <form method="POST">
            <div class="row">
                <div class="col-md-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>

                <div class="col-md-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>

                <div class="col-md-3">
                    <select name="role" class="form-control" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="doctor">Doctor</option>
                        <option value="nurse">Nurse</option>
                        <option value="pharmacist">Pharmacist</option>
                        <option value="kitchen">Kitchen</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <button type="submit" name="add" class="btn btn-primary w-100">
                        Add Staff
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- STAFF TABLE -->
    <div class="card p-3 shadow-sm">
        <h5>Staff List</h5>

        <table class="table table-bordered table-striped mt-3">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
                    <tr>
                        <td><?= $row['ACCOUNT_ID']; ?></td>

                        <!-- 🔥 ONLY CHANGE IS HERE -->
                        <td>
                            <?= ($row['ROLE'] == 'doctor') 
                                ? 'DR. '.$row['USERNAME'] 
                                : $row['USERNAME']; ?>
                        </td>

                        <td><?= strtoupper($row['ROLE']); ?></td>

                        <td>
                            <a href="staff.php?delete=<?= $row['ACCOUNT_ID']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Delete this staff?')">
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

</div>

</body>
</html>