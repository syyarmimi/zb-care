<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;


/* =========================================================
   MANUAL PHPMAILER
   NO COMPOSER
========================================================= */

require_once __DIR__ . '/../src/PHPMailer.php';
require_once __DIR__ . '/../src/SMTP.php';
require_once __DIR__ . '/../src/Exception.php';


/* =========================================================
   CREATE MAILER
========================================================= */

function createMailer()
{
    $mail = new PHPMailer(true);

    /* SMTP */

    $mail->isSMTP();

    $mail->Host = 'smtp.gmail.com';

    $mail->SMTPAuth = true;


    /* =====================================================
       GMAIL

       IMPORTANT:
       Use NEW Google App Password.
       Do not use normal Gmail password.
    ===================================================== */

    $mail->Username = 'zbcarehealth@gmail.com';

    $mail->Password = 'zgyezslgkmwwbhyn';


    /* =====================================================
       SECURITY
    ===================================================== */

    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = 587;


    /* =====================================================
       ENCODING
    ===================================================== */

    $mail->CharSet = 'UTF-8';


    /* =====================================================
       SENDER
    ===================================================== */

    $mail->setFrom(
        'zbcarehealth@gmail.com',
        'ZB-CARE Specialist Hospital'
    );


    /* =====================================================
       HTML
    ===================================================== */

    $mail->isHTML(true);


    /* =====================================================
       DEBUG

       0 = normal
       2 = show SMTP error during testing
    ===================================================== */

    $mail->SMTPDebug = 0;


    return $mail;
}


/* =========================================================
   APPROVAL EMAIL
========================================================= */

function sendAppointmentApprovalEmail(
    $toEmail,
    $patientName,
    $doctor,
    $date,
    $time
) {

    try {

        $mail = createMailer();


        /* =================================================
           RECIPIENT
        ================================================= */

        $mail->addAddress(
            $toEmail,
            $patientName
        );


        /* =================================================
           SUBJECT
        ================================================= */

        $mail->Subject =
            'Appointment Approved - ZB-CARE Specialist Hospital';


        /* =================================================
           FORMAT DATE
        ================================================= */

        $displayDate = $date;

        $timestamp = strtotime($date);

        if ($timestamp !== false) {

            $displayDate =
                date(
                    'd F Y',
                    $timestamp
                );

        }


        /* =================================================
           SAFE VALUES
        ================================================= */

        $safePatient =
            htmlspecialchars(
                $patientName,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeDoctor =
            htmlspecialchars(
                $doctor,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeDate =
            htmlspecialchars(
                $displayDate,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeTime =
            htmlspecialchars(
                $time,
                ENT_QUOTES,
                'UTF-8'
            );


        /* =================================================
           HTML EMAIL
        ================================================= */

        $mail->Body = "

        <div style='
            margin:0;
            padding:35px 15px;
            background:#f4f6f9;
            font-family:Arial,Helvetica,sans-serif;
            color:#1f2937;
        '>

            <div style='
                max-width:600px;
                margin:auto;
                background:#ffffff;
                border:1px solid #e5e7eb;
                border-radius:14px;
                overflow:hidden;
            '>


                <!-- HEADER -->

                <div style='
                    background:#172033;
                    color:#ffffff;
                    padding:26px 30px;
                '>

                    <div style='
                        font-size:24px;
                        font-weight:700;
                    '>

                        ZB-CARE

                    </div>

                    <div style='
                        font-size:13px;
                        color:#cbd5e1;
                        margin-top:4px;
                    '>

                        Specialist Hospital

                    </div>

                </div>


                <!-- BODY -->

                <div style='padding:32px;'>


                    <div style='text-align:center;'>


                        <div style='
                            width:60px;
                            height:60px;
                            line-height:60px;
                            margin:auto;
                            border-radius:50%;
                            background:#dcfce7;
                            color:#16a34a;
                            font-size:30px;
                            font-weight:bold;
                        '>

                            ✓

                        </div>


                        <h2 style='
                            margin:16px 0 5px;
                            color:#111827;
                        '>

                            Appointment Approved

                        </h2>


                        <div style='
                            color:#6b7280;
                            font-size:14px;
                        '>

                            Your appointment has been successfully confirmed.

                        </div>


                    </div>


                    <p style='
                        margin-top:30px;
                        font-size:15px;
                        line-height:1.7;
                    '>

                        Dear <strong>{$safePatient}</strong>,

                    </p>


                    <p style='
                        color:#4b5563;
                        font-size:15px;
                        line-height:1.7;
                    '>

                        Your appointment request has been automatically
                        approved by the ZB-CARE appointment system.
                        Please refer to your appointment details below.

                    </p>


                    <!-- DETAILS -->

                    <div style='
                        margin-top:25px;
                        border:1px solid #e5e7eb;
                        border-radius:10px;
                        overflow:hidden;
                    '>


                        <div style='
                            padding:15px 18px;
                            background:#f8fafc;
                            border-bottom:1px solid #e5e7eb;
                        '>

                            <span style='
                                width:140px;
                                display:inline-block;
                                color:#6b7280;
                            '>

                                Doctor

                            </span>

                            <strong>

                                {$safeDoctor}

                            </strong>

                        </div>


                        <div style='
                            padding:15px 18px;
                            border-bottom:1px solid #e5e7eb;
                        '>

                            <span style='
                                width:140px;
                                display:inline-block;
                                color:#6b7280;
                            '>

                                Date

                            </span>

                            <strong>

                                {$safeDate}

                            </strong>

                        </div>


                        <div style='
                            padding:15px 18px;
                            background:#f8fafc;
                        '>

                            <span style='
                                width:140px;
                                display:inline-block;
                                color:#6b7280;
                            '>

                                Time

                            </span>

                            <strong>

                                {$safeTime}

                            </strong>

                        </div>


                    </div>


                    <!-- NOTICE -->

                    <div style='
                        margin-top:24px;
                        background:#eff6ff;
                        color:#1e40af;
                        border-left:4px solid #3b82f6;
                        padding:15px 18px;
                        font-size:13px;
                        line-height:1.6;
                    '>

                        Please arrive at least
                        <strong>15 minutes before</strong>
                        your scheduled appointment.

                    </div>


                    <p style='
                        margin-top:28px;
                        font-size:14px;
                        line-height:1.6;
                    '>

                        Thank you,

                        <br>

                        <strong>
                            ZB-CARE Specialist Hospital
                        </strong>

                    </p>


                </div>


                <!-- FOOTER -->

                <div style='
                    background:#f8fafc;
                    border-top:1px solid #e5e7eb;
                    padding:18px;
                    text-align:center;
                    color:#9ca3af;
                    font-size:11px;
                '>

                    This is an automatically generated email from
                    the ZB-CARE Hospital Management System.

                </div>


            </div>

        </div>

        ";


        /* =================================================
           TEXT FALLBACK
        ================================================= */

        $mail->AltBody =

            "ZB-CARE Appointment Approved\n\n" .

            "Dear {$patientName},\n\n" .

            "Your appointment has been approved.\n\n" .

            "Doctor: {$doctor}\n" .

            "Date: {$displayDate}\n" .

            "Time: {$time}\n\n" .

            "Please arrive at least 15 minutes earlier.\n\n" .

            "ZB-CARE Specialist Hospital";


        /* =================================================
           SEND
        ================================================= */

        if ($mail->send()) {

            return true;

        }


        return false;

    }
    catch (Exception $e) {

        error_log(
            "ZB-CARE approval email failed: " .
            $e->getMessage() .
            " | PHPMailer: " .
            (
                isset($mail)
                ? $mail->ErrorInfo
                : 'Mailer not initialized'
            )
        );

        return false;
    }
}


/* =========================================================
   REJECTION EMAIL
========================================================= */

function sendAppointmentRejectedEmail(
    $toEmail,
    $patientName
) {

    try {

        $mail = createMailer();


        /* RECIPIENT */

        $mail->addAddress(
            $toEmail,
            $patientName
        );


        /* SUBJECT */

        $mail->Subject =
            'Appointment Slot Unavailable - ZB-CARE Specialist Hospital';


        $safePatient =
            htmlspecialchars(
                $patientName,
                ENT_QUOTES,
                'UTF-8'
            );


        /* =================================================
           BODY
        ================================================= */

        $mail->Body = "

        <div style='
            padding:35px 15px;
            background:#f4f6f9;
            font-family:Arial,Helvetica,sans-serif;
            color:#1f2937;
        '>

            <div style='
                max-width:600px;
                margin:auto;
                background:#ffffff;
                border-radius:14px;
                overflow:hidden;
                border:1px solid #e5e7eb;
            '>


                <div style='
                    background:#172033;
                    color:white;
                    padding:26px 30px;
                '>

                    <div style='
                        font-size:24px;
                        font-weight:bold;
                    '>

                        ZB-CARE

                    </div>

                    <div style='
                        color:#cbd5e1;
                        font-size:13px;
                        margin-top:4px;
                    '>

                        Specialist Hospital

                    </div>

                </div>


                <div style='padding:32px;'>


                    <div style='text-align:center;'>


                        <div style='
                            width:60px;
                            height:60px;
                            line-height:60px;
                            margin:auto;
                            border-radius:50%;
                            background:#fee2e2;
                            color:#dc2626;
                            font-size:28px;
                            font-weight:bold;
                        '>

                            !

                        </div>


                        <h2 style='
                            margin:16px 0 5px;
                            color:#111827;
                        '>

                            Appointment Slot Unavailable

                        </h2>


                    </div>


                    <p style='
                        margin-top:30px;
                        font-size:15px;
                        line-height:1.7;
                    '>

                        Dear <strong>{$safePatient}</strong>,

                    </p>


                    <p style='
                        color:#4b5563;
                        font-size:15px;
                        line-height:1.7;
                    '>

                        Unfortunately, there is currently no available
                        doctor or appointment slot for your selected
                        date and time.

                    </p>


                    <div style='
                        margin-top:22px;
                        padding:15px 18px;
                        background:#fff7ed;
                        color:#9a3412;
                        border-left:4px solid #f97316;
                        font-size:13px;
                        line-height:1.6;
                    '>

                        Please return to the ZB-CARE appointment page
                        and select another available appointment
                        date or time.

                    </div>


                    <p style='
                        margin-top:28px;
                        font-size:14px;
                    '>

                        Thank you,

                        <br>

                        <strong>
                            ZB-CARE Specialist Hospital
                        </strong>

                    </p>


                </div>


                <div style='
                    background:#f8fafc;
                    border-top:1px solid #e5e7eb;
                    padding:18px;
                    text-align:center;
                    color:#9ca3af;
                    font-size:11px;
                '>

                    This is an automatically generated email
                    from the ZB-CARE Hospital Management System.

                </div>


            </div>

        </div>

        ";


        $mail->AltBody =

            "ZB-CARE Appointment Slot Unavailable\n\n" .

            "Dear {$patientName},\n\n" .

            "Unfortunately, there is no available doctor or slot " .

            "for your requested appointment.\n\n" .

            "Please select another date or time.\n\n" .

            "ZB-CARE Specialist Hospital";


        if ($mail->send()) {

            return true;

        }


        return false;

    }
    catch (Exception $e) {

        error_log(
            "ZB-CARE rejection email failed: " .
            $e->getMessage() .
            " | PHPMailer: " .
            (
                isset($mail)
                ? $mail->ErrorInfo
                : 'Mailer not initialized'
            )
        );

        return false;
    }
}

?>