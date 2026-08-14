<?php
session_start();
session_destroy();

// ✅ Redirect to staff_portal.php
header("Location: ../pages/staff_portal.php");
exit();
?>