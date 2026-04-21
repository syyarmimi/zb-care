<?php
session_start();
session_destroy();

// ✅ go back to root index.php
header("Location: ../index.php");
exit();
?>