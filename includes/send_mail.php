<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';
require_once __DIR__ . '/../src/Exception.php';

/* =====================================
   COMMON MAIL CONFIG
===================================== */

function createMailer()
{
    $mail = new PHPMailer(true);

    $mail->isSMTP();

    // DEBUG MODE
   $mail->SMTPDebug = 0;

    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;

    $mail->Username = 'zbcarehealth@gmail.com';
    $mail->Password = 'zgyezslgkmwwbhyn';

    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;

    $mail->setFrom(
        'zbcarehealth@gmail.com',
        'ZB-CARE Hospital'
    );

    $mail->isHTML(true);

    return $mail;
}

/* =====================================
   APPROVED EMAIL
===================================== */

function sendAppointmentApprovalEmail(
    $toEmail,
    $patientName,
    $doctor,
    $date,
    $time
){

    try{

        $mail = createMailer();

        $mail->addAddress($toEmail);

        $mail->Subject =
        'Appointment Approved - ZB-CARE Hospital';

        $mail->Body = "

        <h2 style='color:green'>
        Appointment Approved
        </h2>

        Dear <b>$patientName</b>,<br><br>

        Your appointment request has been approved.<br><br>

        <b>Doctor:</b> $doctor <br>
        <b>Date:</b> $date <br>
        <b>Time:</b> $time <br><br>

        Please arrive at least
        <b>15 minutes earlier</b>.<br><br>

        Regards,<br>
        <b>ZB-CARE Hospital</b>

        ";

        $mail->send();

        return true;

    }catch(Exception $e){

    echo "<pre>";
    echo "ERROR INFO:\n";
    echo $mail->ErrorInfo . "\n\n";

    echo "EXCEPTION:\n";
    echo $e->getMessage();
    echo "</pre>";

    exit();

}

}

/* =====================================
   REJECTED EMAIL
===================================== */

function sendAppointmentRejectedEmail(
    $toEmail,
    $patientName
){

    try{

        $mail = createMailer();

        $mail->addAddress($toEmail);

        $mail->Subject =
        'Appointment Rejected - ZB-CARE Hospital';

        $mail->Body = "

        <h2 style='color:red'>
        Appointment Rejected
        </h2>

        Dear <b>$patientName</b>,<br><br>

        We regret to inform you that your
        appointment request has been rejected.<br><br>

        This may be due to doctor availability
        or scheduling conflicts.<br><br>

        Please submit another appointment request
        or contact the hospital for assistance.<br><br>

        Regards,<br>
        <b>ZB-CARE Hospital</b>

        ";

        $mail->send();

        return true;

    }catch(Exception $e){

    echo "<pre>";
    echo "ERROR INFO:\n";
    echo $mail->ErrorInfo . "\n\n";

    echo "EXCEPTION:\n";
    echo $e->getMessage();
    echo "</pre>";

    exit();

}

}