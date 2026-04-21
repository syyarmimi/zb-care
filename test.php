<?php

$dsn = "odbc:OracleODBC";
$username = "SYARMIMI";
$password = "zaipolbahari";

try {
    $conn = new PDO($dsn, $username, $password);
    echo "✅ Connected successfully!";
} catch (PDOException $e) {
    echo "❌ ERROR: " . $e->getMessage();
}