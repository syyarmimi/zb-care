<?php 
session_start(); 
$role = $_GET['role'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center vh-100">

    <div class="card p-4 shadow" style="width: 350px;">
        
        <h4 class="text-center mb-2">🏥 Hospital Login</h4>

        <p class="text-center text-muted">
            Role: <b><?= strtoupper($role) ?></b>
        </p>

        <form action="process_login.php" method="POST">

            <!-- PASS ROLE -->
            <input type="hidden" name="role" value="<?= $role ?>">

            <div class="mb-3">
                <label>Username</label>
                <input type="text" name="username" class="form-control" required>
            </div>

            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <button class="btn btn-primary w-100">Login</button>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="text-danger mt-2">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

        </form>
    </div>

</div>

</body>
</html>