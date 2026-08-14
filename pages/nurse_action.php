<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'nurse') {
    die("Access Denied");
}

$staff_id = $_SESSION['user_id'];

/* ================= GIVE MEDICATION ================= */
if(isset($_GET['give_med'])){

    $medOrderId = $_GET['give_med'];

    // Check if already administered
    $check = $conn->prepare("
        SELECT COUNT(*)
        FROM SYARMIMI.MEDICATION_ADMIN
        WHERE MEDORDER_ID = :id
    ");

    $check->execute([
        ':id' => $medOrderId
    ]);

    // Insert only if not yet given
    if($check->fetchColumn() == 0){

        $insert = $conn->prepare("
            INSERT INTO SYARMIMI.MEDICATION_ADMIN
            (
                ADMIN_ID,
                ADMIN_TIME,
                MEDORDER_ID,
                ACCOUNT_ID
            )
            VALUES
            (
                SYARMIMI.MEDADMIN_SEQ.NEXTVAL,
                SYSDATE,
                :med,
                :staff
            )
        ");

        $insert->execute([
            ':med'   => $medOrderId,
            ':staff' => $staff_id
        ]);
    }

    header("Location: nurse_medication.php?success=1");
    exit();
}
?>