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
    ORDER BY MEDORDER_ID DESC
    FETCH FIRST 1 ROWS ONLY
    ");

    $stmt->execute([':adm'=>$admission]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row){

        $med = $row['MEDORDER_ID'];

        $check = $conn->prepare("
        SELECT COUNT(*) FROM SYARMIMI.MEDICATION_ADMIN
        WHERE MEDORDER_ID = :id
        ");
        $check->execute([':id'=>$med]);

        if($check->fetchColumn() == 0){

            $conn->prepare("
            INSERT INTO SYARMIMI.MEDICATION_ADMIN
            (ADMIN_ID, ADMIN_TIME, MEDORDER_ID, STAFF_ID)
            VALUES (SYARMIMI.MEDADMIN_SEQ.NEXTVAL, SYSDATE, :med, :staff)
            ")->execute([
                ':med'=>$med,
                ':staff'=>$staff_id
            ]);

            echo "<script>alert('Medication Given'); window.location='nurse_patients.php';</script>";
        } else {
            echo "<script>alert('Already Given'); window.location='nurse_patients.php';</script>";
        }
    }
}

/* ================= GIVE MEAL ================= */
if(isset($_GET['give_meal'])){

    $admission = $_GET['give_meal'];

    $stmt = $conn->prepare("
    SELECT MEALPLAN_ID 
    FROM SYARMIMI.MEAL_PLAN
    WHERE ADMISSION_ID = :adm
    ORDER BY MEALPLAN_ID DESC
    FETCH FIRST 1 ROWS ONLY
    ");

    $stmt->execute([':adm'=>$admission]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($row){

        $meal = $row['MEALPLAN_ID'];

        $conn->prepare("
        INSERT INTO SYARMIMI.MEAL_DELIVERY
        (DELIVERY_ID, DELIVERY_TIME, MEALPLAN_ID, STAFF_ID)
        VALUES (SYARMIMI.MEALDEL_SEQ.NEXTVAL, SYSDATE, :meal, :staff)
        ")->execute([
            ':meal'=>$meal,
            ':staff'=>$staff_id
        ]);

        echo "<script>alert('Meal Delivered'); window.location='nurse_patients.php';</script>";
    }
}
?>