<?php

include("../config/config.php");
require_once("../includes/send_mail.php");

$message = "";
$messageType = "";


/* =========================================================
   HELPER FUNCTION
   Convert 08:00 AM -> 08:00
   Convert 02:00 PM -> 14:00
========================================================= */

function convertAppointmentTimeToSlot($time)
{
    $time = trim($time);

    $obj = DateTime::createFromFormat(
        'h:i A',
        $time
    );

    if ($obj) {
        return $obj->format('H:i');
    }

    return $time;
}


/* =========================================================
   BOOK APPOINTMENT
========================================================= */

if (isset($_POST['book'])) {

    /* =====================================================
       GET FORM VALUES
    ===================================================== */

    $patient_name =
        strtoupper(
            trim($_POST['patient_name'] ?? '')
        );

    $ic_number =
        trim($_POST['ic_number'] ?? '');

    $gender =
        trim($_POST['gender'] ?? '');

    $phone =
        trim($_POST['phone'] ?? '');

    $email =
        trim($_POST['email'] ?? '');

    $department =
        trim($_POST['department'] ?? '');

    $appointment_date =
        trim($_POST['appointment_date'] ?? '');

    $appointment_time =
        trim($_POST['appointment_time'] ?? '');

    $address =
        strtoupper(
            trim($_POST['address'] ?? '')
        );

    $notes =
        strtoupper(
            trim($_POST['notes'] ?? '')
        );


    /* =====================================================
       VALIDATION
    ===================================================== */

    if (
        $patient_name === '' ||
        $ic_number === '' ||
        $gender === '' ||
        $phone === '' ||
        $email === '' ||
        $department === '' ||
        $appointment_date === '' ||
        $appointment_time === '' ||
        $address === ''
    ) {

        $message =
            "Please complete all required fields.";

        $messageType =
            "danger";

    }
    elseif (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $message =
            "Please enter a valid email address.";

        $messageType =
            "danger";

    }
    elseif (
        strtotime($appointment_date)
        <
        strtotime(date('Y-m-d'))
    ) {

        $message =
            "Appointment date cannot be earlier than today.";

        $messageType =
            "danger";

    }
    else {

        try {

            /* =================================================
               START TRANSACTION
            ================================================= */

            $conn->beginTransaction();


            /* =================================================
               CONVERT SLOT TIME
            ================================================= */

            $slotTime =
                convertAppointmentTimeToSlot(
                    $appointment_time
                );


            /* =================================================
               DUPLICATE CHECK

               Same:
               - IC
               - Department
               - Date
               - Time

               Existing Approved/Pending appointment means
               another booking is not allowed.
            ================================================= */

            $duplicateStmt =
                $conn->prepare("

                    SELECT

                        APPOINTMENT_ID,
                        APPOINTMENT_DATE,
                        APPOINTMENT_TIME,
                        STATUS,
                        DOCTOR_NAME

                    FROM SYARMIMI.APPOINTMENT

                    WHERE

                        REPLACE(
                            TRIM(IC_NUMBER),
                            '-',
                            ''
                        )
                        =
                        REPLACE(
                            TRIM(:ic_number),
                            '-',
                            ''
                        )

                    AND

                        UPPER(
                            TRIM(DEPARTMENT)
                        )
                        =
                        UPPER(
                            TRIM(:department)
                        )

                    AND

                        TRIM(APPOINTMENT_TIME)
                        =
                        TRIM(:appointment_time)

                    AND

                        UPPER(
                            TRIM(STATUS)
                        )
                        IN (
                            'APPROVED',
                            'PENDING'
                        )

                    ORDER BY
                        APPOINTMENT_ID DESC

                ");


            $duplicateStmt->execute([

                ':ic_number' =>
                    $ic_number,

                ':department' =>
                    $department,

                ':appointment_time' =>
                    $appointment_time

            ]);


            $possibleDuplicates =
                $duplicateStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


            $existingAppointment =
                null;


            foreach (
                $possibleDuplicates
                as $possible
            ) {

                $databaseDate =
                    trim(
                        $possible[
                            'APPOINTMENT_DATE'
                        ] ?? ''
                    );


                $databaseTimestamp =
                    strtotime(
                        $databaseDate
                    );


                if (
                    $databaseTimestamp !== false
                    &&
                    date(
                        'Y-m-d',
                        $databaseTimestamp
                    )
                    ===
                    $appointment_date
                ) {

                    $existingAppointment =
                        $possible;

                    break;
                }
            }


            /* =================================================
               DUPLICATE FOUND
            ================================================= */

            if ($existingAppointment) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }


                $displayDate =
                    date(
                        'd M Y',
                        strtotime(
                            $appointment_date
                        )
                    );


                $message =

                    "You already have an appointment for " .

                    $department .

                    " on " .

                    $displayDate .

                    " at " .

                    $appointment_time;


                if (
                    !empty(
                        $existingAppointment[
                            'DOCTOR_NAME'
                        ]
                    )
                ) {

                    $message .=

                        ". Assigned Doctor: " .

                        $existingAppointment[
                            'DOCTOR_NAME'
                        ];
                }


                $message .= ".";


                $messageType =
                    "warning";

            }


            /* =================================================
               CONTINUE BOOKING
            ================================================= */

            else {


                /* =================================================
                   FIND AVAILABLE DOCTOR
                ================================================= */

                $doctorStmt =
                    $conn->prepare("

                        SELECT

                            HS.ACCOUNT_ID,

                            HS.USERNAME,

                            DS.SLOT_ID,

                            NVL(
                                DS.MAX_PATIENT,
                                1
                            ) AS MAX_PATIENT,

                            NVL(
                                DS.CURRENT_PATIENT,
                                0
                            ) AS CURRENT_PATIENT

                        FROM
                            SYARMIMI.HOSPITAL_STAFF HS

                        JOIN
                            SYARMIMI.DOCTOR_AVAILABILITY DA

                            ON
                            TO_CHAR(
                                HS.ACCOUNT_ID
                            )
                            =
                            TO_CHAR(
                                DA.ACCOUNT_ID
                            )

                        JOIN
                            SYARMIMI.DOCTOR_SLOT DS

                            ON
                            TO_CHAR(
                                HS.ACCOUNT_ID
                            )
                            =
                            TO_CHAR(
                                DS.ACCOUNT_ID
                            )

                        WHERE

                            UPPER(
                                TRIM(HS.ROLE)
                            )
                            =
                            'DOCTOR'

                        AND

                            UPPER(
                                TRIM(HS.DEPARTMENT)
                            )
                            =
                            UPPER(
                                TRIM(:department)
                            )

                        AND

                            TRUNC(
                                DA.AVAILABLE_DATE
                            )
                            =
                            TO_DATE(
                                :available_date,
                                'YYYY-MM-DD'
                            )

                        AND

                            UPPER(
                                TRIM(DA.STATUS)
                            )
                            =
                            'AVAILABLE'

                        AND

                            TRUNC(
                                DS.SLOT_DATE
                            )
                            =
                            TO_DATE(
                                :slot_date,
                                'YYYY-MM-DD'
                            )

                        AND

                            TRIM(
                                DS.SLOT_TIME
                            )
                            =
                            :slot_time

                        AND

                            UPPER(
                                TRIM(DS.STATUS)
                            )
                            =
                            'AVAILABLE'

                        AND

                            NVL(
                                DS.CURRENT_PATIENT,
                                0
                            )
                            <
                            NVL(
                                DS.MAX_PATIENT,
                                1
                            )

                        ORDER BY

                            NVL(
                                DS.CURRENT_PATIENT,
                                0
                            ) ASC,

                            HS.ACCOUNT_ID ASC

                    ");


                $doctorStmt->execute([

                    ':department' =>
                        $department,

                    ':available_date' =>
                        $appointment_date,

                    ':slot_date' =>
                        $appointment_date,

                    ':slot_time' =>
                        $slotTime

                ]);


                $doctorCandidates =
                    $doctorStmt->fetchAll(
                        PDO::FETCH_ASSOC
                    );


                $selectedDoctor =
                    null;


                /* =================================================
                   SELECT ONE DOCTOR ONLY
                ================================================= */

                foreach (
                    $doctorCandidates
                    as $candidate
                ) {

                    $lockStmt =
                        $conn->prepare("

                            SELECT

                                SLOT_ID,

                                NVL(
                                    MAX_PATIENT,
                                    1
                                ) AS MAX_PATIENT,

                                NVL(
                                    CURRENT_PATIENT,
                                    0
                                ) AS CURRENT_PATIENT,

                                STATUS

                            FROM SYARMIMI.DOCTOR_SLOT

                            WHERE
                                SLOT_ID =
                                :slot_id

                            FOR UPDATE

                        ");


                    $lockStmt->execute([

                        ':slot_id' =>
                            $candidate[
                                'SLOT_ID'
                            ]

                    ]);


                    $slot =
                        $lockStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (!$slot) {
                        continue;
                    }


                    $currentPatient =
                        (int)(
                            $slot[
                                'CURRENT_PATIENT'
                            ] ?? 0
                        );


                    $maxPatient =
                        (int)(
                            $slot[
                                'MAX_PATIENT'
                            ] ?? 1
                        );


                    $slotStatus =
                        strtoupper(
                            trim(
                                $slot[
                                    'STATUS'
                                ] ?? ''
                            )
                        );


                    if (
                        $slotStatus ===
                        'AVAILABLE'
                        &&
                        $currentPatient <
                        $maxPatient
                    ) {

                        $selectedDoctor =
                            $candidate;


                        $selectedDoctor[
                            'CURRENT_PATIENT'
                        ] =
                            $currentPatient;


                        $selectedDoctor[
                            'MAX_PATIENT'
                        ] =
                            $maxPatient;


                        /*
                         * STOP after ONE doctor is selected.
                         */
                        break;
                    }
                }


                /* =================================================
                   DOCTOR FOUND
                   AUTO APPROVE
                ================================================= */

                if ($selectedDoctor) {

                    $doctorAccountId =
                        $selectedDoctor[
                            'ACCOUNT_ID'
                        ];


                    $rawDoctorName =
                        trim(
                            $selectedDoctor[
                                'USERNAME'
                            ]
                        );


                    /* =============================================
                       PREVENT "Dr. Dr. ..."
                    ============================================= */

                    if (
                        stripos(
                            $rawDoctorName,
                            'Dr.'
                        ) === 0
                    ) {

                        $doctorName =
                            $rawDoctorName;

                    }
                    else {

                        $doctorName =
                            'Dr. ' .
                            $rawDoctorName;

                    }


                    /* =============================================
                       INSERT APPOINTMENT ONCE
                    ============================================= */

                    $insertStmt =
                        $conn->prepare("

                            INSERT INTO
                                SYARMIMI.APPOINTMENT
                            (
                                APPOINTMENT_ID,
                                PATIENT_NAME,
                                IC_NUMBER,
                                GENDER,
                                PHONE,
                                EMAIL,
                                DEPARTMENT,
                                APPOINTMENT_DATE,
                                NOTES,
                                STATUS,
                                DOCTOR_NAME,
                                ACCOUNT_ID,
                                APPOINTMENT_TIME,
                                ADDRESS
                            )

                            VALUES
                            (
                                SYARMIMI.APPOINTMENT_SEQ.NEXTVAL,

                                :patient_name,
                                :ic_number,
                                :gender,
                                :phone,
                                :email,
                                :department,

                                TO_DATE(
                                    :appointment_date,
                                    'YYYY-MM-DD'
                                ),

                                :notes,

                                'Approved',

                                :doctor_name,
                                :account_id,
                                :appointment_time,
                                :address
                            )

                        ");


                    $insertStmt->execute([

                        ':patient_name' =>
                            $patient_name,

                        ':ic_number' =>
                            $ic_number,

                        ':gender' =>
                            $gender,

                        ':phone' =>
                            $phone,

                        ':email' =>
                            $email,

                        ':department' =>
                            $department,

                        ':appointment_date' =>
                            $appointment_date,

                        ':notes' =>
                            $notes,

                        ':doctor_name' =>
                            $doctorName,

                        ':account_id' =>
                            $doctorAccountId,

                        ':appointment_time' =>
                            $appointment_time,

                        ':address' =>
                            $address

                    ]);


                    /* =============================================
                       GET NEW APPOINTMENT ID
                    ============================================= */

                    $newAppointmentId =
                        $conn->query("

                            SELECT
                                SYARMIMI.APPOINTMENT_SEQ.CURRVAL

                            FROM DUAL

                        ")->fetchColumn();


                    /* =============================================
                       UPDATE SELECTED SLOT ONLY
                    ============================================= */

                    $newCurrentPatient =
                        (int)$selectedDoctor[
                            'CURRENT_PATIENT'
                        ]
                        + 1;


                    $maxPatient =
                        (int)$selectedDoctor[
                            'MAX_PATIENT'
                        ];


                    $newSlotStatus =
                        (
                            $newCurrentPatient
                            >=
                            $maxPatient
                        )
                        ? 'Booked'
                        : 'Available';


                    $updateSlot =
                        $conn->prepare("

                            UPDATE
                                SYARMIMI.DOCTOR_SLOT

                            SET

                                CURRENT_PATIENT =
                                    :current_patient,

                                STATUS =
                                    :status,

                                APPOINTMENT_ID =
                                    :appointment

                            WHERE
                                SLOT_ID =
                                :slot

                        ");


                    $updateSlot->execute([

                        ':current_patient' =>
                            $newCurrentPatient,

                        ':status' =>
                            $newSlotStatus,

                        ':appointment' =>
                            $newAppointmentId,

                        ':slot' =>
                            $selectedDoctor[
                                'SLOT_ID'
                            ]

                    ]);


                    /* =============================================
                       DOCTOR NOTIFICATION
                    ============================================= */

                    $notificationStmt =
                        $conn->prepare("

                            INSERT INTO
                                SYARMIMI.APPOINTMENT_NOTIFICATION
                            (
                                NOTIFICATION_ID,
                                ACCOUNT_ID,
                                MESSAGE,
                                IS_READ,
                                CREATED_AT
                            )

                            VALUES
                            (
                                SYARMIMI.NOTIF_SEQ.NEXTVAL,

                                :doctor,

                                :message,

                                0,

                                SYSDATE
                            )

                        ");


                    $notificationMessage =

                        'New appointment assigned: ' .

                        $patient_name .

                        ' on ' .

                        date(
                            'd M Y',
                            strtotime(
                                $appointment_date
                            )
                        ) .

                        ' at ' .

                        $appointment_time;


                    $notificationStmt->execute([

                        ':doctor' =>
                            $doctorAccountId,

                        ':message' =>
                            $notificationMessage

                    ]);


                    /* =============================================
                       COMMIT DATABASE FIRST
                    ============================================= */

                    $conn->commit();


                    /* =============================================
                       SEND APPROVAL EMAIL
                       Uses old working 5-parameter function.
                    ============================================= */

                    $emailSent =
                        false;


                    try {

                        $emailSent =
                            sendAppointmentApprovalEmail(

                                $email,

                                $patient_name,

                                $doctorName,

                                $appointment_date,

                                $appointment_time

                            );

                    }
                    catch (
                        Throwable $mailError
                    ) {

                        error_log(

                            "Approval email error: " .

                            $mailError->getMessage()

                        );


                        $emailSent =
                            false;

                    }


                    /* =============================================
                       DISPLAY MESSAGE
                    ============================================= */

                    $displayDate =
                        date(
                            'd M Y',
                            strtotime(
                                $appointment_date
                            )
                        );


                    if ($emailSent) {

                        $message =

                            "Your appointment has been automatically approved. " .

                            "Assigned Doctor: " .

                            $doctorName .

                            ". Date: " .

                            $displayDate .

                            ". Time: " .

                            $appointment_time .

                            ". Confirmation email has been sent to " .

                            $email .

                            ".";

                    }
                    else {

                        $message =

                            "Your appointment has been automatically approved. " .

                            "Assigned Doctor: " .

                            $doctorName .

                            ". Date: " .

                            $displayDate .

                            ". Time: " .

                            $appointment_time .

                            ". However, the confirmation email could not be sent.";

                    }


                    $messageType =
                        "success";

                }


                /* =================================================
                   NO AVAILABLE DOCTOR
                   AUTO REJECT
                ================================================= */

                else {

                    $rejectStmt =
                        $conn->prepare("

                            INSERT INTO
                                SYARMIMI.APPOINTMENT
                            (
                                APPOINTMENT_ID,
                                PATIENT_NAME,
                                IC_NUMBER,
                                GENDER,
                                PHONE,
                                EMAIL,
                                DEPARTMENT,
                                APPOINTMENT_DATE,
                                NOTES,
                                STATUS,
                                DOCTOR_NAME,
                                ACCOUNT_ID,
                                APPOINTMENT_TIME,
                                ADDRESS
                            )

                            VALUES
                            (
                                SYARMIMI.APPOINTMENT_SEQ.NEXTVAL,

                                :patient_name,
                                :ic_number,
                                :gender,
                                :phone,
                                :email,
                                :department,

                                TO_DATE(
                                    :appointment_date,
                                    'YYYY-MM-DD'
                                ),

                                :notes,

                                'Rejected',

                                NULL,
                                NULL,

                                :appointment_time,
                                :address
                            )

                        ");


                    $rejectStmt->execute([

                        ':patient_name' =>
                            $patient_name,

                        ':ic_number' =>
                            $ic_number,

                        ':gender' =>
                            $gender,

                        ':phone' =>
                            $phone,

                        ':email' =>
                            $email,

                        ':department' =>
                            $department,

                        ':appointment_date' =>
                            $appointment_date,

                        ':notes' =>
                            $notes,

                        ':appointment_time' =>
                            $appointment_time,

                        ':address' =>
                            $address

                    ]);


                    /* =============================================
                       COMMIT REJECTION
                    ============================================= */

                    $conn->commit();


                    /* =============================================
                       REJECTION EMAIL
                    ============================================= */

                    $rejectionEmailSent =
                        false;


                    try {

                        $rejectionEmailSent =
                            sendAppointmentRejectedEmail(

                                $email,

                                $patient_name

                            );

                    }
                    catch (
                        Throwable $mailError
                    ) {

                        error_log(

                            "Rejection email error: " .

                            $mailError->getMessage()

                        );


                        $rejectionEmailSent =
                            false;

                    }


                    $displayDate =
                        date(
                            'd M Y',
                            strtotime(
                                $appointment_date
                            )
                        );


                    $message =

                        "No doctor is available in the " .

                        $department .

                        " department on " .

                        $displayDate .

                        " at " .

                        $appointment_time .

                        ". Please select another date or time.";


                    if ($rejectionEmailSent) {

                        $message .=

                            " An email notification has been sent to " .

                            $email .

                            ".";
                    }


                    $messageType =
                        "danger";
                }
            }

        }
        catch (
            Throwable $e
        ) {

            if (
                $conn->inTransaction()
            ) {

                $conn->rollBack();

            }


            $message =

                "Unable to process appointment: " .

                $e->getMessage();


            $messageType =
                "danger";
        }
    }
}


/* =========================================================
   FETCH DEPARTMENTS
========================================================= */

$departmentStmt =
    $conn->query("

        SELECT DISTINCT
            DEPARTMENT

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            UPPER(TRIM(ROLE))
            =
            'DOCTOR'

        AND
            DEPARTMENT
            IS NOT NULL

        ORDER BY
            DEPARTMENT

    ");


$departments =
    $departmentStmt->fetchAll(
        PDO::FETCH_ASSOC
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Make Appointment | ZB-CARE
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<style>

body {

    background:#f3f6fb;

    font-family:'Segoe UI', sans-serif;

}


/* =========================================================
   NAVBAR
========================================================= */

.navbar {

    background:white;

    padding:18px 0;

    box-shadow:
        0 2px 15px
        rgba(0,0,0,0.05);

}


.navbar-brand {

    font-size:30px;

    font-weight:800;

    color:#0d6efd !important;

    text-decoration:none;

}


.home-btn {

    background:#0d6efd;

    color:white;

    padding:10px 24px;

    border-radius:40px;

    text-decoration:none;

    font-weight:600;

}


.home-btn:hover {

    background:#2563eb;

    color:white;

}


/* =========================================================
   HERO
========================================================= */

.hero {

    background:

        linear-gradient(
            rgba(15,23,42,0.6),
            rgba(15,23,42,0.6)
        ),

        url(
            'https://images.unsplash.com/photo-1586773860418-d37222d8fce3?q=80&w=1600&auto=format&fit=crop'
        );

    background-size:cover;

    background-position:center;

    padding:100px 20px;

    text-align:center;

    color:white;

}


.hero h1 {

    font-size:60px;

    font-weight:800;

}


.hero p {

    margin-top:20px;

    font-size:20px;

    color:#e2e8f0;

}


/* =========================================================
   FORM BOX
========================================================= */

.form-box {

    background:white;

    padding:45px;

    border-radius:30px;

    margin-top:-60px;

    position:relative;

    z-index:10;

    box-shadow:
        0 10px 30px
        rgba(0,0,0,0.08);

}


.form-control,
.form-select {

    height:55px;

    border-radius:12px;

}


textarea.form-control {

    height:auto;

}


label {

    font-weight:600;

    margin-bottom:8px;

}


/* =========================================================
   AUTO DOCTOR
========================================================= */

.auto-doctor-box {

    height:55px;

    background:#f8fafc;

    border:1px solid #d1d5db;

    border-radius:12px;

    display:flex;

    align-items:center;

    padding:0 15px;

}


.auto-doctor-icon {

    width:34px;

    height:34px;

    border-radius:50%;

    background:#dbeafe;

    color:#2563eb;

    display:flex;

    align-items:center;

    justify-content:center;

    margin-right:10px;

}


/* =========================================================
   INFO BOX
========================================================= */

.auto-info {

    background:#eff6ff;

    color:#1e40af;

    border:1px solid #bfdbfe;

    border-radius:12px;

    padding:18px;

}


/* =========================================================
   BUTTON
========================================================= */

.btn-submit {

    background:#dc2626;

    color:white;

    border:none;

    padding:15px;

    border-radius:12px;

    font-weight:600;

    font-size:17px;

}


.btn-submit:hover {

    background:#b91c1c;

    color:white;

}


.btn-processing {

    pointer-events:none;

    opacity:0.7;

}


/* =========================================================
   FOOTER
========================================================= */

footer {

    background:#0f172a;

    color:white;

    padding:50px 0;

    margin-top:80px;

}

</style>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<nav class="navbar navbar-expand-lg">

<div class="container">


<a
    href="../index.php"
    class="navbar-brand"
>

🏥 ZB-CARE

</a>


<div class="ms-auto">

<a
    href="../index.php"
    class="home-btn"
>

← Back Home

</a>

</div>


</div>

</nav>


<!-- =====================================================
     HERO
===================================================== -->

<div class="hero">

<h1>
    Make An Appointment
</h1>

<p>
    Book your specialist consultation online easily.
</p>

</div>


<!-- =====================================================
     FORM
===================================================== -->

<div class="container">

<div class="row justify-content-center">

<div class="col-md-10">

<div class="form-box">


<!-- MESSAGE -->

<?php if ($message !== ''): ?>

<div
    class="
        alert
        alert-<?= htmlspecialchars(
            $messageType ?: 'info'
        ) ?>
        mb-4
    "
>

<?= htmlspecialchars($message) ?>

</div>

<?php endif; ?>


<form
    method="POST"
    id="appointmentForm"
>


<div class="row">


<!-- NAME -->

<div class="col-md-6 mb-4">

<label>
    Patient Name *
</label>

<input
    type="text"
    name="patient_name"
    class="form-control"
    style="text-transform:uppercase"
    oninput="this.value=this.value.toUpperCase()"
    required
>

</div>


<!-- PHONE -->

<div class="col-md-6 mb-4">

<label>
    Contact Number *
</label>

<input
    type="text"
    id="phone"
    name="phone"
    class="form-control"
    maxlength="13"
    required
>

</div>


<!-- EMAIL -->

<div class="col-md-6 mb-4">

<label>
    Email Address *
</label>

<input
    type="email"
    name="email"
    class="form-control"
    required
>

</div>


<!-- IC -->

<div class="col-md-6 mb-4">

<label>
    IC Number *
</label>

<input
    type="text"
    id="ic_number"
    name="ic_number"
    class="form-control"
    maxlength="14"
    required
>

</div>


<!-- GENDER -->

<div class="col-md-6 mb-4">

<label>
    Gender *
</label>

<select
    name="gender"
    class="form-select"
    required
>

<option value="">
    Select Gender
</option>

<option value="Male">
    Male
</option>

<option value="Female">
    Female
</option>

</select>

</div>


<!-- DEPARTMENT -->

<div class="col-md-6 mb-4">

<label>
    Specialist Department *
</label>

<select
    name="department"
    class="form-select"
    required
>

<option value="">
    Select Department
</option>


<?php foreach ($departments as $dep): ?>

<option
    value="<?= htmlspecialchars(
        $dep['DEPARTMENT']
    ) ?>"
>

<?= htmlspecialchars(
    $dep['DEPARTMENT']
) ?>

</option>

<?php endforeach; ?>


</select>

</div>


<!-- AUTO DOCTOR -->

<div class="col-md-6 mb-4">

<label>
    Doctor Assignment
</label>


<div class="auto-doctor-box">


<div class="auto-doctor-icon">

<i class="bi bi-person-check"></i>

</div>


<div>

<div class="fw-semibold text-dark">

Automatic Assignment

</div>

<small class="text-muted">

One available doctor will be assigned automatically.

</small>

</div>


</div>

</div>


<!-- DATE -->

<div class="col-md-6 mb-4">

<label>
    Appointment Date *
</label>

<input
    type="date"
    id="appointment_date"
    name="appointment_date"
    class="form-control"
    required
>

</div>


<!-- TIME -->

<div class="col-md-6 mb-4">

<label>
    Appointment Time *
</label>

<select
    name="appointment_time"
    class="form-select"
    required
>

<option value="">
    Select Appointment Time
</option>

<option value="08:00 AM">
    08:00 AM
</option>

<option value="09:00 AM">
    09:00 AM
</option>

<option value="10:00 AM">
    10:00 AM
</option>

<option value="11:00 AM">
    11:00 AM
</option>

<option value="12:00 PM">
    12:00 PM
</option>

<option value="02:00 PM">
    02:00 PM
</option>

<option value="03:00 PM">
    03:00 PM
</option>

<option value="04:00 PM">
    04:00 PM
</option>

</select>

</div>


<!-- ADDRESS -->

<div class="col-md-12 mb-4">

<label>
    Address *
</label>

<textarea
    name="address"
    rows="3"
    class="form-control"
    style="text-transform:uppercase"
    oninput="this.value=this.value.toUpperCase()"
    required
></textarea>

</div>


<!-- NOTES -->

<div class="col-md-12 mb-4">

<label>
    Remarks / Symptoms
</label>

<textarea
    name="notes"
    rows="4"
    class="form-control"
    style="text-transform:uppercase"
    oninput="this.value=this.value.toUpperCase()"
    placeholder="Describe symptoms or additional notes"
></textarea>

</div>


<!-- INFO -->

<div class="col-md-12 mb-4">

<div class="auto-info">

<strong>

<i class="bi bi-lightning-charge-fill"></i>

Automatic Appointment Confirmation

</strong>


<div class="mt-1">

The system will automatically check doctor availability
for the selected department, date and time.

If an available doctor is found, the appointment will
be approved immediately, one doctor will be assigned,
and a confirmation email will be sent automatically.

</div>

</div>

</div>


<!-- BUTTON -->

<div class="col-md-12">

<button
    type="submit"
    name="book"
    id="bookBtn"
    class="btn btn-submit w-100"
>

<i
    id="bookIcon"
    class="bi bi-calendar-check me-1"
></i>

<span id="bookBtnText">

Check Availability & Book Appointment

</span>

</button>

</div>


</div>

</form>


</div>

</div>

</div>

</div>


<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

<div class="container text-center">

<h4>
    🏥 ZB-CARE Specialist Hospital
</h4>

<p class="mt-3 text-light">

Orthopaedics • Paediatrics • Dietitian & Nutrition

</p>

<p class="text-secondary">

© 2026 ZB-CARE. All Rights Reserved.

</p>

</div>

</footer>


<script>


/* =========================================================
   MINIMUM DATE
========================================================= */

document
.getElementById(
    'appointment_date'
)
.min =
new Date()
.toISOString()
.split('T')[0];


/* =========================================================
   IC FORMAT
========================================================= */

document
.getElementById(
    'ic_number'
)
.addEventListener(
    'input',
    function()
    {

        let value =
            this.value
            .replace(
                /\D/g,
                ''
            );


        if (value.length > 6) {

            value =
                value.substring(0,6)
                +
                '-'
                +
                value.substring(6);

        }


        if (value.length > 9) {

            value =
                value.substring(0,9)
                +
                '-'
                +
                value.substring(9);

        }


        this.value =
            value;

    }
);


/* =========================================================
   PHONE FORMAT
========================================================= */

document
.getElementById(
    'phone'
)
.addEventListener(
    'input',
    function()
    {

        let value =
            this.value
            .replace(
                /\D/g,
                ''
            );


        if (value.length > 3) {

            value =
                value.substring(0,3)
                +
                '-'
                +
                value.substring(3);

        }


        if (value.length > 7) {

            value =
                value.substring(0,7)
                +
                '-'
                +
                value.substring(7);

        }


        this.value =
            value;

    }
);


/* =========================================================
   PREVENT DOUBLE SUBMIT
========================================================= */

let appointmentSubmitting =
    false;


document
.getElementById(
    'appointmentForm'
)
.addEventListener(
    'submit',
    function(event)
    {

        if (appointmentSubmitting) {

            event.preventDefault();

            return;

        }


        appointmentSubmitting =
            true;


        const button =
            document.getElementById(
                'bookBtn'
            );


        const icon =
            document.getElementById(
                'bookIcon'
            );


        const text =
            document.getElementById(
                'bookBtnText'
            );


        button.classList.add(
            'btn-processing'
        );


        icon.className =
            'spinner-border spinner-border-sm me-2';


        text.textContent =
            'Processing Appointment...';

    }
);


</script>


</body>

</html>