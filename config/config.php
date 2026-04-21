<?php

$dsn = "odbc:OracleODBC";
$username = "SYARMIMI";
$password = "zaipolbahari";

try {
    $conn = new PDO($dsn, $username, $password);

    // Set error modes
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Optional: default fetch mode
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Better error message (safe)
    die("Database connection failed.");
}
?>