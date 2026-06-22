<?php
session_start();
include("../config/config.php");

if ($_SESSION['role'] != 'nurse') {
    die("Access Denied");
}

$staff_id = $_SESSION['user_id'];

/* ================= GIVE MEDICATION ================= */
if(isset($_GET['give_med'])){

    $admission = $_GET['give_med'];

    $stmt = $conn->prepare("
    SELECT MEDORDER_ID
FROM SYARMIMI.MEDICATION_ORDER
WHERE ADMISSION_ID = :adm
    ");

    $stmt->execute([':adm'=>$admission]);
   $medications = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($medications as $row){

    $med = $row['MEDORDER_ID'];

    $check = $conn->prepare("
    SELECT COUNT(*)
    FROM SYARMIMI.MEDICATION_ADMIN
    WHERE MEDORDER_ID = :id
    ");

    $check->execute([
        ':id'=>$med
    ]);

    if($check->fetchColumn() == 0){

        $conn->prepare("
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
        ")->execute([
            ':med'=>$med,
            ':staff'=>$staff_id
        ]);

    }
}
        header("Location: nurse_medication.php?success=1");
exit();
}

?>