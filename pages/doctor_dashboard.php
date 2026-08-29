<?php

session_start();
include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'doctor'
) {
    header("Location: ../auth/login.php");
    exit();
}


/* =========================================================
   TIMEZONE
========================================================= */

date_default_timezone_set('Asia/Kuala_Lumpur');


/* =========================================================
   CURRENT DOCTOR
========================================================= */

$doctor_id = (int)($_SESSION['user_id'] ?? 0);

$doctor_name =
    trim(
        $_SESSION['user']
        ?? ''
    );


if ($doctor_id <= 0) {
    die("Invalid doctor account.");
}


/* =========================================================
   SAFE OUTPUT
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   DOCTOR INFORMATION
========================================================= */

$doctorInfoStmt =
    $conn->prepare("

        SELECT

            USERNAME,
            DEPARTMENT

        FROM
            SYARMIMI.HOSPITAL_STAFF

        WHERE
            ACCOUNT_ID = :doctor

    ");


$doctorInfoStmt->execute([
    ':doctor' => $doctor_id
]);


$doctorInfo =
    $doctorInfoStmt->fetch(
        PDO::FETCH_ASSOC
    );


$doctorUsername =
    trim(
        $doctorInfo['USERNAME']
        ??
        $doctor_name
        ??
        'Doctor'
    );


if (
    stripos(
        $doctorUsername,
        'Dr.'
    )
    ===
    0
) {

    $doctorDisplayName =
        $doctorUsername;

}
else {

    $doctorDisplayName =
        'Dr. '
        .
        $doctorUsername;
}


$doctorDepartment =
    $doctorInfo['DEPARTMENT']
    ??
    '';


/* =========================================================
   SUCCESS MESSAGE FROM TREATMENT
========================================================= */

$successTitle =
    $_SESSION['success_title']
    ??
    '';


$successMessage =
    $_SESSION['success_message']
    ??
    '';


unset(
    $_SESSION['success_title']
);


unset(
    $_SESSION['success_message']
);


/* =========================================================
   NOTIFICATIONS
========================================================= */

$notifications = [];


try {

    $notificationStmt =
        $conn->prepare("

            SELECT
                *

            FROM
                SYARMIMI.APPOINTMENT_NOTIFICATION

            WHERE
                ACCOUNT_ID = :doctor

            AND
                IS_READ = 0

            ORDER BY
                NOTIFICATION_ID DESC

        ");


    $notificationStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $notifications =
        $notificationStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    /* =====================================================
       MARK NOTIFICATIONS AS READ
    ===================================================== */

    $markNotificationStmt =
        $conn->prepare("

            UPDATE
                SYARMIMI.APPOINTMENT_NOTIFICATION

            SET
                IS_READ = 1

            WHERE
                ACCOUNT_ID = :doctor

            AND
                IS_READ = 0

        ");


    $markNotificationStmt->execute([
        ':doctor' => $doctor_id
    ]);


}
catch (Exception $e) {

    $notifications = [];
}


/* =========================================================
   TODAY AVAILABILITY
========================================================= */

$todayAvailability =
    'Not Set';


try {

    $availabilityStmt =
        $conn->prepare("

            SELECT
                STATUS

            FROM
                SYARMIMI.DOCTOR_AVAILABILITY

            WHERE
                ACCOUNT_ID = :doctor

            AND
                TRUNC(AVAILABLE_DATE)
                =
                TRUNC(SYSDATE)

        ");


    $availabilityStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $availabilityResult =
        $availabilityStmt
            ->fetchColumn();


    if ($availabilityResult) {

        $todayAvailability =
            $availabilityResult;
    }


}
catch (Exception $e) {

    $todayAvailability =
        'Not Set';
}


/* =========================================================
   NEW ADMISSION COUNT
========================================================= */

$newPatients = 0;


try {

    $newCountStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.ADMISSION

            WHERE
                ACCOUNT_ID = :doctor

            AND
                IS_SEEN = 0

            AND
                DISCHARGE_DATE
                IS NULL

        ");


    $newCountStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $newPatients =
        (int)$newCountStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $newPatients = 0;
}


/* =========================================================
   ACTIVE ADMITTED PATIENTS
========================================================= */

$patients = [];


try {

    $patientStmt =
        $conn->prepare("

            SELECT

                A.ADMISSION_ID,
                A.PATIENT_ID,
                A.IS_SEEN,
                A.ADMISSION_DATE,
                A.EXPECTED_DISCHARGE_DATE,

                P.NAME,

                W.WARD_NAME,

                B.BED_NUMBER,


                /* =========================================
                   DIAGNOSIS STATUS
                ========================================= */

                CASE

                    WHEN EXISTS
                    (
                        SELECT
                            1

                        FROM
                            SYARMIMI.DIAGNOSIS D

                        WHERE
                        (
                            D.ADMISSION_ID =
                            A.ADMISSION_ID

                            OR

                            D.PATIENT_ID =
                            A.PATIENT_ID
                        )

                        AND
                            D.ACCOUNT_ID =
                            A.ACCOUNT_ID
                    )

                    THEN
                        'Diagnosed'

                    ELSE
                        'No Diagnosis'

                END
                AS DIAG_STATUS,


                /* =========================================
                   TOTAL MEDICATION ORDERS
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_ORDER MO

                    WHERE
                        MO.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                    (
                        MO.ORDER_TYPE
                        IS NULL

                        OR

                        UPPER(
                            TRIM(
                                MO.ORDER_TYPE
                            )
                        )
                        =
                        'INPATIENT'
                    )

                )
                AS TOTAL_MEDICATION_ORDER,


                /* =========================================
                   TOTAL SCHEDULED DOSES

                   CANCELLED DOSES EXCLUDED
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    JOIN
                        SYARMIMI.MEDICATION_ORDER MO2

                        ON
                            MS.MEDORDER_ID =
                            MO2.MEDORDER_ID

                    WHERE
                        MO2.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                        UPPER(
                            TRIM(
                                NVL(
                                    MS.STATUS,
                                    'PENDING PREPARATION'
                                )
                            )
                        )
                        NOT IN
                        (
                            'CANCELLED',
                            'CANCELLED - DISCHARGED'
                        )

                )
                AS TOTAL_SCHEDULED_DOSES,


                /* =========================================
                   GIVEN DOSES
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    JOIN
                        SYARMIMI.MEDICATION_ORDER MO3

                        ON
                            MS.MEDORDER_ID =
                            MO3.MEDORDER_ID

                    WHERE
                        MO3.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                        UPPER(
                            TRIM(
                                MS.STATUS
                            )
                        )
                        IN
                        (
                            'GIVEN',
                            'DELIVERED',
                            'COMPLETED',
                            'ADMINISTERED'
                        )

                )
                AS GIVEN_DOSES,


                /* =========================================
                   READY DOSES
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    JOIN
                        SYARMIMI.MEDICATION_ORDER MO4

                        ON
                            MS.MEDORDER_ID =
                            MO4.MEDORDER_ID

                    WHERE
                        MO4.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                        UPPER(
                            TRIM(
                                MS.STATUS
                            )
                        )
                        IN
                        (
                            'READY',
                            'READY FOR NURSE',
                            'READY FOR NURSE PICKUP',
                            'PREPARED',
                            'COLLECTED BY NURSE'
                        )

                )
                AS READY_DOSES,


                /* =========================================
                   TODAY TOTAL SCHEDULE

                   CANCELLED EXCLUDED
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    JOIN
                        SYARMIMI.MEDICATION_ORDER MO5

                        ON
                            MS.MEDORDER_ID =
                            MO5.MEDORDER_ID

                    WHERE
                        MO5.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                        TRUNC(
                            MS.SCHEDULE_DATE
                        )
                        =
                        TRUNC(
                            SYSDATE
                        )

                    AND
                        UPPER(
                            TRIM(
                                NVL(
                                    MS.STATUS,
                                    'PENDING PREPARATION'
                                )
                            )
                        )
                        NOT IN
                        (
                            'CANCELLED',
                            'CANCELLED - DISCHARGED'
                        )

                )
                AS TODAY_TOTAL_DOSES,


                /* =========================================
                   TODAY GIVEN
                ========================================= */

                (
                    SELECT
                        COUNT(*)

                    FROM
                        SYARMIMI.MEDICATION_SCHEDULE MS

                    JOIN
                        SYARMIMI.MEDICATION_ORDER MO6

                        ON
                            MS.MEDORDER_ID =
                            MO6.MEDORDER_ID

                    WHERE
                        MO6.ADMISSION_ID =
                        A.ADMISSION_ID

                    AND
                        TRUNC(
                            MS.SCHEDULE_DATE
                        )
                        =
                        TRUNC(
                            SYSDATE
                        )

                    AND
                        UPPER(
                            TRIM(
                                MS.STATUS
                            )
                        )
                        IN
                        (
                            'GIVEN',
                            'DELIVERED',
                            'COMPLETED',
                            'ADMINISTERED'
                        )

                )
                AS TODAY_GIVEN_DOSES


            FROM
                SYARMIMI.ADMISSION A


            JOIN
                SYARMIMI.PATIENT P

                ON
                    A.PATIENT_ID =
                    P.PATIENT_ID


            JOIN
                SYARMIMI.BED B

                ON
                    A.BED_ID =
                    B.BED_ID


            JOIN
                SYARMIMI.WARD W

                ON
                    B.WARD_ID =
                    W.WARD_ID


            WHERE
                A.ACCOUNT_ID = :doctor

            AND
                A.DISCHARGE_DATE
                IS NULL


            ORDER BY
                A.ADMISSION_ID DESC

        ");


    $patientStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $patients =
        $patientStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


}
catch (Exception $e) {

    $patients = [];
}


/* =========================================================
   UPCOMING APPOINTMENTS

   Approved only.

   Once doctor marks No Show, status becomes NO SHOW,
   therefore appointment automatically disappears here.
========================================================= */

$appointments = [];


try {

    $appStmt =
        $conn->prepare("

            SELECT

                A.APPOINTMENT_ID,
                A.PATIENT_NAME,

                TO_CHAR(
                    DS.SLOT_DATE,
                    'DD-MON-YYYY'
                )
                AS APPOINTMENT_DATE,

                TO_CHAR(
                    DS.SLOT_DATE,
                    'YYYY-MM-DD'
                )
                AS APPOINTMENT_DATE_VALUE,

                DS.SLOT_TIME
                AS APPOINTMENT_TIME,

                A.STATUS


            FROM
                SYARMIMI.DOCTOR_SLOT DS


            JOIN
                SYARMIMI.APPOINTMENT A

                ON
                    DS.APPOINTMENT_ID =
                    A.APPOINTMENT_ID


            WHERE
                DS.ACCOUNT_ID = :doctor

            AND
                DS.APPOINTMENT_ID
                IS NOT NULL

            AND
                UPPER(
                    TRIM(
                        A.STATUS
                    )
                )
                =
                'APPROVED'

            AND
                TRUNC(
                    DS.SLOT_DATE
                )
                >=
                TRUNC(
                    SYSDATE
                )

            AND NOT EXISTS
            (
                SELECT
                    1

                FROM
                    SYARMIMI.DIAGNOSIS D

                WHERE
                    D.APPOINTMENT_ID =
                    A.APPOINTMENT_ID
            )


            ORDER BY

                DS.SLOT_DATE ASC,

                DS.SLOT_TIME ASC,

                A.APPOINTMENT_ID DESC

        ");


    $appStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $appointments =
        $appStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


}
catch (Exception $e) {

    $appointments = [];
}


$totalAppointmentPatients =
    count(
        $appointments
    );


/* =========================================================
   TODAY APPOINTMENT + WALK-IN

   IMPORTANT:
   Approved appointments only.

   No Show automatically disappears because its status
   changes from Approved -> No Show.
========================================================= */

$todayList = [];


try {

    $todayAppointmentsStmt =
        $conn->prepare("

            SELECT

                CAST(
                    A.PATIENT_NAME
                    AS VARCHAR2(200)
                )
                AS PATIENT_NAME,

                CAST(
                    DS.SLOT_TIME
                    AS VARCHAR2(50)
                )
                AS APPOINTMENT_TIME,

                CAST(
                    A.APPOINTMENT_ID
                    AS VARCHAR2(50)
                )
                AS RECORD_ID,

                CAST(
                    'Appointment'
                    AS VARCHAR2(50)
                )
                AS TYPE


            FROM
                SYARMIMI.DOCTOR_SLOT DS


            JOIN
                SYARMIMI.APPOINTMENT A

                ON
                    DS.APPOINTMENT_ID =
                    A.APPOINTMENT_ID


            WHERE
                DS.ACCOUNT_ID =
                :doctor1

            AND
                DS.APPOINTMENT_ID
                IS NOT NULL

            AND
                TRUNC(
                    DS.SLOT_DATE
                )
                =
                TRUNC(
                    SYSDATE
                )

            AND
                UPPER(
                    TRIM(
                        A.STATUS
                    )
                )
                =
                'APPROVED'

            AND NOT EXISTS
            (
                SELECT
                    1

                FROM
                    SYARMIMI.DIAGNOSIS D

                WHERE
                    D.APPOINTMENT_ID =
                    A.APPOINTMENT_ID
            )


            UNION ALL


            SELECT

                CAST(
                    P.NAME
                    AS VARCHAR2(200)
                )
                AS PATIENT_NAME,

                CAST(
                    'Walk-In'
                    AS VARCHAR2(50)
                )
                AS APPOINTMENT_TIME,

                CAST(
                    W.CONSULTATION_ID
                    AS VARCHAR2(50)
                )
                AS RECORD_ID,

                CAST(
                    'Walk-In'
                    AS VARCHAR2(50)
                )
                AS TYPE


            FROM
                SYARMIMI.WALKIN_CONSULTATION W


            JOIN
                SYARMIMI.PATIENT P

                ON
                    W.PATIENT_ID =
                    P.PATIENT_ID


            WHERE
                W.ACCOUNT_ID =
                :doctor2

            AND
                UPPER(
                    TRIM(
                        W.STATUS
                    )
                )
                =
                'ASSIGNED'

            AND NOT EXISTS
            (
                SELECT
                    1

                FROM
                    SYARMIMI.DIAGNOSIS D

                WHERE
                    D.CONSULTATION_ID =
                    W.CONSULTATION_ID
            )


            ORDER BY
                2

        ");


    $todayAppointmentsStmt->execute([

        ':doctor1' => $doctor_id,

        ':doctor2' => $doctor_id

    ]);


    $todayList =
        $todayAppointmentsStmt
            ->fetchAll(
                PDO::FETCH_ASSOC
            );


}
catch (Exception $e) {

    $todayList = [];
}


/* =========================================================
   INPATIENT COUNT
========================================================= */

$inpatientCount = 0;


try {

    $inpatientStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.ADMISSION

            WHERE
                ACCOUNT_ID =
                :doctor

            AND
                DISCHARGE_DATE
                IS NULL

        ");


    $inpatientStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $inpatientCount =
        (int)$inpatientStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $inpatientCount = 0;
}


/* =========================================================
   OUTPATIENT COUNT

   Current approved, untreated appointments.
   No Show excluded automatically.
========================================================= */

$outpatientCount = 0;


try {

    $outpatientStmt =
        $conn->prepare("

            SELECT
                COUNT(
                    DISTINCT
                    A.APPOINTMENT_ID
                )

            FROM
                SYARMIMI.DOCTOR_SLOT DS


            JOIN
                SYARMIMI.APPOINTMENT A

                ON
                    DS.APPOINTMENT_ID =
                    A.APPOINTMENT_ID


            WHERE
                DS.ACCOUNT_ID =
                :doctor

            AND
                DS.APPOINTMENT_ID
                IS NOT NULL

            AND
                UPPER(
                    TRIM(
                        A.STATUS
                    )
                )
                =
                'APPROVED'

            AND NOT EXISTS
            (
                SELECT
                    1

                FROM
                    SYARMIMI.DIAGNOSIS D

                WHERE
                    D.APPOINTMENT_ID =
                    A.APPOINTMENT_ID
            )

        ");


    $outpatientStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $outpatientCount =
        (int)$outpatientStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $outpatientCount = 0;
}


/* =========================================================
   TOTAL NO SHOW

   NEW:
   Show doctor how many assigned appointments
   were marked as No Show.
========================================================= */

$totalNoShow = 0;


try {

    $noShowCountStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.APPOINTMENT

            WHERE
                ACCOUNT_ID =
                :doctor

            AND
                UPPER(
                    TRIM(
                        STATUS
                    )
                )
                =
                'NO SHOW'

        ");


    $noShowCountStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $totalNoShow =
        (int)$noShowCountStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $totalNoShow = 0;
}


/* =========================================================
   TOTAL DIAGNOSIS
========================================================= */

$totalDiagnosis = 0;


try {

    $totalDiagnosisStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.DIAGNOSIS

            WHERE
                ACCOUNT_ID =
                :doctor

        ");


    $totalDiagnosisStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $totalDiagnosis =
        (int)$totalDiagnosisStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $totalDiagnosis = 0;
}


/* =========================================================
   TOTAL MEDICATION ORDERS
========================================================= */

$totalMedication = 0;


try {

    $totalMedicationStmt =
        $conn->prepare("

            SELECT
                COUNT(*)

            FROM
                SYARMIMI.MEDICATION_ORDER

            WHERE
                ACCOUNT_ID =
                :doctor

        ");


    $totalMedicationStmt->execute([
        ':doctor' => $doctor_id
    ]);


    $totalMedication =
        (int)$totalMedicationStmt
            ->fetchColumn();


}
catch (Exception $e) {

    $totalMedication = 0;
}


/* =========================================================
   AUTO MARK ADMISSION AS SEEN
========================================================= */

try {

    $seenStmt =
        $conn->prepare("

            UPDATE
                SYARMIMI.ADMISSION

            SET
                IS_SEEN = 1

            WHERE
                ACCOUNT_ID =
                :doctor

            AND
                IS_SEEN = 0

        ");


    $seenStmt->execute([
        ':doctor' => $doctor_id
    ]);


}
catch (Exception $e) {

    /* Ignore */
}

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
Doctor Dashboard
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
</script>


<style>

/* =========================================================
   GLOBAL
========================================================= */

*{
    box-sizing:border-box;
}


body{

    margin:0;

    background:#f5f7fa;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;

    color:#1f2937;
}


/* =========================================================
   CONTENT
========================================================= */

.content{

    flex:1;

    min-width:0;

    padding:28px;

    min-height:100vh;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-header{

    margin-bottom:24px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:30px;

    font-weight:700;
}


.page-subtitle{

    margin-top:6px;

    color:#8a94a3;

    font-size:14px;
}


/* =========================================================
   ALERT
========================================================= */

.dashboard-alert{

    display:flex;

    align-items:flex-start;

    gap:10px;

    padding:14px 16px;

    border-radius:10px;

    font-size:14px;
}


/* =========================================================
   REAL TIME CARD
========================================================= */

.realtime-card{

    height:100%;

    min-height:155px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:14px;

    position:relative;
}


.realtime-top{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;
}


.realtime-date{

    color:#64748b;

    font-size:13px;

    font-weight:600;
}


.realtime-clock{

    margin-top:5px;

    color:#111827;

    font-size:27px;

    line-height:1;

    font-weight:750;
}


.realtime-label{

    color:#94a3b8;

    font-size:12px;
}


.realtime-patient-number{

    margin-top:4px;

    color:#111827;

    font-size:32px;

    line-height:1;

    font-weight:700;
}


.realtime-divider{

    margin:30px 0 12px;

    border-top:1px solid #e5e7eb;
}


.realtime-footer{

    text-align:center;
}


.realtime-footer-title{

    color:#374151;

    font-size:12px;

    font-weight:600;
}


.realtime-footer-text{

    margin-top:2px;

    color:#94a3b8;

    font-size:11px;
}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card{

    height:100%;

    padding:18px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;

    transition:.2s;
}


.stat-card:hover{

    border-color:#d7dce3;

    transform:translateY(-2px);
}


.stat-label{

    color:#8a94a3;

    font-size:13px;

    font-weight:600;
}


.stat-number{

    margin-top:5px;

    color:#111827;

    font-size:29px;

    font-weight:700;
}


.stat-description{

    margin-top:2px;

    margin-bottom:0;

    color:#94a3b8;

    font-size:12px;
}


.stat-icon{

    width:40px;

    height:40px;

    min-width:40px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:9px;

    font-size:17px;
}


.icon-availability{

    background:#eff6ff;

    color:#2563eb;
}


.icon-inpatient{

    background:#f1f5f9;

    color:#475569;
}


.icon-outpatient{

    background:#faf5ff;

    color:#7c3aed;
}


.icon-upcoming{

    background:#eff6ff;

    color:#2563eb;
}


.icon-diagnosis{

    background:#ecfdf5;

    color:#16a34a;
}


.icon-medication{

    background:#fff7ed;

    color:#ea580c;
}


.icon-noshow{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   AVAILABILITY
========================================================= */

.availability-link{

    display:block;

    height:100%;

    color:inherit;

    text-decoration:none;
}


.availability-status{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:6px 9px;

    border-radius:6px;

    font-size:12px;

    font-weight:650;
}


.availability-available{

    background:#ecfdf5;

    color:#15803d;
}


.availability-unavailable{

    background:#fff1f2;

    color:#dc2626;
}


.availability-notset{

    background:#f3f4f6;

    color:#64748b;
}


/* =========================================================
   SECTION
========================================================= */

.section-card{

    margin-top:22px;

    padding:20px;

    background:#fff;

    border:1px solid #e7eaee;

    border-radius:12px;
}


.section-header{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    margin-bottom:16px;
}


.section-title{

    margin:0;

    color:#1f2937;

    font-size:17px;

    font-weight:650;
}


.section-subtitle{

    margin-top:4px;

    color:#94a3b8;

    font-size:13px;
}


/* =========================================================
   TABLE
========================================================= */

.table-responsive{

    border-radius:9px;

    overflow-x:auto;
}


.table{

    margin-bottom:0;

    vertical-align:middle;
}


.table thead th{

    padding:12px 12px;

    background:#f8fafc;

    border-top:1px solid #edf0f3;

    border-bottom:1px solid #e5e7eb;

    color:#64748b;

    font-size:12px;

    font-weight:650;

    text-transform:uppercase;

    white-space:nowrap;
}


.table tbody td{

    padding:14px 12px;

    border-color:#eef1f4;

    color:#374151;

    font-size:14px;
}


.table tbody tr:hover td{

    background:#fafbfc;
}


.patient-name{

    color:#1f2937;

    font-weight:650;
}


/* =========================================================
   BADGES
========================================================= */

.small-badge{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:6px 9px;

    border-radius:6px;

    font-size:12px;

    font-weight:650;

    white-space:nowrap;
}


.badge-appointment{

    background:#eff6ff;

    color:#2563eb;
}


.badge-walkin{

    background:#fff7ed;

    color:#c2410c;
}


.badge-good{

    background:#ecfdf5;

    color:#15803d;
}


.badge-pending{

    background:#fff7ed;

    color:#c2410c;
}


.badge-ready{

    background:#eff6ff;

    color:#2563eb;
}


.badge-neutral{

    background:#f3f4f6;

    color:#64748b;
}


.badge-danger-soft{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   BUTTON
========================================================= */

.action-btn{

    padding:7px 11px;

    border-radius:7px;

    font-size:12px;

    font-weight:600;
}


.appointment-actions{

    display:flex;

    align-items:center;

    gap:6px;

    flex-wrap:wrap;
}


.appointment-actions form{

    margin:0;
}


.btn-no-show{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:7px 11px;

    border:1px solid #fecaca;

    border-radius:7px;

    background:#fff;

    color:#dc2626;

    font-size:12px;

    font-weight:600;
}


.btn-no-show:hover{

    border-color:#fca5a5;

    background:#fef2f2;

    color:#b91c1c;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{

    padding:35px 20px;

    text-align:center;

    color:#94a3b8;
}


.empty-state i{

    display:block;

    margin-bottom:8px;

    color:#cbd5e1;

    font-size:27px;
}


/* =========================================================
   UPCOMING
========================================================= */

.upcoming-table-wrap{

    max-height:390px;

    overflow:auto;
}


/* =========================================================
   SWEETALERT
========================================================= */

.swal2-popup{

    border-radius:16px !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:768px){

    .content{

        padding:18px;
    }


    .realtime-clock{

        font-size:22px;
    }


    .section-header{

        align-items:flex-start;

        flex-direction:column;
    }


    .appointment-actions{

        align-items:stretch;

        flex-direction:column;
    }


    .appointment-actions a,
    .appointment-actions button{

        width:100%;

        justify-content:center;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php

include(
    "../includes/sidebar_doctor.php"
);

?>


<div class="content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<h1 class="page-title">

Doctor Dashboard

</h1>


<div class="page-subtitle">

Welcome back,

<?= h(
    $doctorDisplayName
) ?>


<?php if (
    !empty(
        $doctorDepartment
    )
): ?>


•

<?= h(
    $doctorDepartment
) ?>


<?php endif; ?>


</div>


</div>


<!-- =====================================================
     NOTIFICATIONS
===================================================== -->

<?php foreach (
    $notifications
    as
    $notification
): ?>


<div class="alert alert-warning dashboard-alert">


<i class="bi bi-bell"></i>


<div>

<?= h(
    $notification[
        'MESSAGE'
    ]
    ?? ''
) ?>

</div>


</div>


<?php endforeach; ?>


<!-- =====================================================
     NEW PATIENT ALERT
===================================================== -->

<?php if (
    $newPatients > 0
): ?>


<div class="alert alert-danger dashboard-alert">


<i class="bi bi-exclamation-circle"></i>


<div>


<strong>

<?= $newPatients ?>

new admitted patient(s)

</strong>


under your care.


</div>


</div>


<?php endif; ?>


<!-- =====================================================
     TOP CARDS
===================================================== -->

<div class="row g-3">


<!-- REAL TIME -->

<div class="col-xl-4 col-lg-6">


<div class="realtime-card">


<div class="realtime-top">


<div>


<div
    id="liveDate"
    class="realtime-date"
></div>


<div
    id="liveClock"
    class="realtime-clock"
></div>


</div>


<div class="text-end">


<div class="realtime-label">

Total Patients

</div>


<div class="realtime-patient-number">

<?= $inpatientCount + $outpatientCount ?>

</div>


</div>


</div>


<div class="realtime-divider"></div>


<div class="realtime-footer">


<div class="realtime-footer-title">

Patients under your care

</div>


<div class="realtime-footer-text">

Real-time patient statistics

</div>


</div>


</div>


</div>


<!-- AVAILABILITY -->

<div class="col-xl-4 col-lg-6">


<a
    href="doctor_availability.php"
    class="availability-link"
>


<div class="stat-card">


<div class="d-flex justify-content-between align-items-start">


<div>


<div class="stat-label">

Today's Availability

</div>


<div class="mt-2">


<?php

$availabilityUpper =
    strtoupper(
        trim(
            $todayAvailability
        )
    );

?>


<?php if (
    $availabilityUpper
    ===
    'AVAILABLE'
): ?>


<span class="availability-status availability-available">


<i class="bi bi-check-circle"></i>

Available


</span>


<?php elseif (
    $availabilityUpper
    ===
    'UNAVAILABLE'
): ?>


<span class="availability-status availability-unavailable">


<i class="bi bi-x-circle"></i>

Unavailable


</span>


<?php else: ?>


<span class="availability-status availability-notset">


<i class="bi bi-dash-circle"></i>

Not Set


</span>


<?php endif; ?>


</div>


</div>


<div class="stat-icon icon-availability">


<i class="bi bi-calendar2-check"></i>


</div>


</div>


<p class="stat-description mt-3">

Click to manage your schedule

</p>


</div>


</a>


</div>


<!-- UPCOMING -->

<div class="col-xl-4 col-lg-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

Upcoming Appointments

</div>


<div class="stat-number">

<?= $totalAppointmentPatients ?>

</div>


<p class="stat-description">

Approved & untreated

</p>


</div>


<div class="stat-icon icon-upcoming">


<i class="bi bi-calendar-event"></i>


</div>


</div>


</div>


</div>


</div>


<!-- =====================================================
     SECOND KPI ROW
===================================================== -->

<div class="row g-3 mt-1">


<!-- INPATIENT -->

<div class="col-xl col-md-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

Inpatient

</div>


<div class="stat-number">

<?= $inpatientCount ?>

</div>


<p class="stat-description">

Currently admitted

</p>


</div>


<div class="stat-icon icon-inpatient">


<i class="bi bi-hospital"></i>


</div>


</div>


</div>


</div>


<!-- OUTPATIENT -->

<div class="col-xl col-md-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

Outpatient

</div>


<div class="stat-number">

<?= $outpatientCount ?>

</div>


<p class="stat-description">

Waiting treatment

</p>


</div>


<div class="stat-icon icon-outpatient">


<i class="bi bi-person"></i>


</div>


</div>


</div>


</div>


<!-- DIAGNOSIS -->

<div class="col-xl col-md-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

Diagnosis

</div>


<div class="stat-number">

<?= $totalDiagnosis ?>

</div>


<p class="stat-description">

Completed diagnoses

</p>


</div>


<div class="stat-icon icon-diagnosis">


<i class="bi bi-clipboard2-pulse"></i>


</div>


</div>


</div>


</div>


<!-- MEDICATION -->

<div class="col-xl col-md-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

Medication

</div>


<div class="stat-number">

<?= $totalMedication ?>

</div>


<p class="stat-description">

Medication prescriptions

</p>


</div>


<div class="stat-icon icon-medication">


<i class="bi bi-capsule"></i>


</div>


</div>


</div>


</div>


<!-- NO SHOW -->

<div class="col-xl col-md-6">


<div class="stat-card">


<div class="d-flex justify-content-between">


<div>


<div class="stat-label">

No Show

</div>


<div class="stat-number">

<?= $totalNoShow ?>

</div>


<p class="stat-description">

Missed appointments

</p>


</div>


<div class="stat-icon icon-noshow">


<i class="bi bi-person-x"></i>


</div>


</div>


</div>


</div>


</div>


<!-- =====================================================
     TODAY APPOINTMENT + WALK-IN
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Today's Appointments & Walk-In Patients

</h5>


<div class="section-subtitle">

Patients waiting for consultation today

</div>


</div>


<span class="badge text-bg-light">

<?= count(
    $todayList
) ?>

waiting

</span>


</div>


<?php if (
    count(
        $todayList
    )
    >
    0
): ?>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>No.</th>

<th>Type</th>

<th>Time</th>

<th>Patient</th>

<th>Action</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $todayList
    as
    $index => $todayPatient
): ?>


<tr>


<td>

<?= $index + 1 ?>

</td>


<td>


<?php if (
    $todayPatient[
        'TYPE'
    ]
    ===
    'Walk-In'
): ?>


<span class="small-badge badge-walkin">


<i class="bi bi-person-walking"></i>

Walk-In


</span>


<?php else: ?>


<span class="small-badge badge-appointment">


<i class="bi bi-calendar3"></i>

Appointment


</span>


<?php endif; ?>


</td>


<td>


<?= h(
    $todayPatient[
        'APPOINTMENT_TIME'
    ]
    ?? '-'
) ?>


</td>


<td>


<span class="patient-name">


<?= h(
    $todayPatient[
        'PATIENT_NAME'
    ]
    ?? ''
) ?>


</span>


</td>


<td>


<?php if (
    $todayPatient[
        'TYPE'
    ]
    ===
    'Walk-In'
): ?>


<a
    href="treatment.php?type=walkin&id=<?= urlencode(
        $todayPatient[
            'RECORD_ID'
        ]
    ) ?>"
    class="btn btn-outline-warning action-btn"
>


<i class="bi bi-clipboard2-pulse me-1"></i>

Diagnose


</a>


<?php else: ?>


<div class="appointment-actions">


<!-- DIAGNOSE -->

<a
    href="treatment.php?type=appointment&id=<?= urlencode(
        $todayPatient[
            'RECORD_ID'
        ]
    ) ?>"
    class="btn btn-primary action-btn"
>


<i class="bi bi-clipboard2-pulse me-1"></i>

Diagnose


</a>


<!-- NO SHOW -->

<form
    method="POST"
    action="treatment.php"
    class="no-show-form"
    data-patient="<?= h(
        $todayPatient[
            'PATIENT_NAME'
        ]
        ?? 'Patient'
    ) ?>"
>


<input
    type="hidden"
    name="mark_no_show"
    value="1"
>


<input
    type="hidden"
    name="appointment_id"
    value="<?= (int)$todayPatient['RECORD_ID'] ?>"
>


<button
    type="submit"
    class="btn-no-show"
>


<i class="bi bi-person-x"></i>

No Show


</button>


</form>


</div>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-state">


<i class="bi bi-calendar-check"></i>


<div class="fw-semibold">

No patients waiting for treatment

</div>


<div class="small mt-1">

There are no untreated appointments or walk-in patients assigned for today.

</div>


</div>


<?php endif; ?>


</div>


<!-- =====================================================
     MY ADMITTED PATIENTS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

My Admitted Patients

</h5>


<div class="section-subtitle">

Current inpatient treatment and medication progress

</div>


</div>


<span class="badge text-bg-light">

<?= count(
    $patients
) ?>

admitted

</span>


</div>


<?php if (
    count(
        $patients
    )
    >
    0
): ?>


<div class="table-responsive">


<table class="table">


<thead>


<tr>

<th>No.</th>

<th>Patient</th>

<th>Ward</th>

<th>Bed</th>

<th>Admission Date</th>

<th>Expected Discharge</th>

<th>Diagnosis</th>

<th>Medication</th>

<th>Status</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $patients
    as
    $index => $patient
): ?>


<?php


/* =========================================================
   CALCULATE MEDICATION DISPLAY STATUS
========================================================= */

$totalOrders =
    (int)(
        $patient[
            'TOTAL_MEDICATION_ORDER'
        ]
        ??
        0
    );


$totalScheduled =
    (int)(
        $patient[
            'TOTAL_SCHEDULED_DOSES'
        ]
        ??
        0
    );


$givenDoses =
    (int)(
        $patient[
            'GIVEN_DOSES'
        ]
        ??
        0
    );


$readyDoses =
    (int)(
        $patient[
            'READY_DOSES'
        ]
        ??
        0
    );


$todayTotal =
    (int)(
        $patient[
            'TODAY_TOTAL_DOSES'
        ]
        ??
        0
    );


$todayGiven =
    (int)(
        $patient[
            'TODAY_GIVEN_DOSES'
        ]
        ??
        0
    );


$medText =
    'No Medication';


$medClass =
    'badge-neutral';


$medIcon =
    'bi-dash-circle';


/* =========================================================
   NO PRESCRIPTION
========================================================= */

if (
    $totalOrders <= 0
) {

    $medText =
        'No Medication';

    $medClass =
        'badge-neutral';

    $medIcon =
        'bi-dash-circle';

}


/* =========================================================
   ORDER EXISTS BUT NO SCHEDULE
========================================================= */

elseif (
    $totalScheduled <= 0
) {

    $medText =
        'Pending Schedule';

    $medClass =
        'badge-pending';

    $medIcon =
        'bi-clock';

}


/* =========================================================
   SOME DOSES READY
========================================================= */

elseif (
    $readyDoses > 0
    &&
    $givenDoses == 0
) {

    $medText =
        'Ready for Nurse';

    $medClass =
        'badge-ready';

    $medIcon =
        'bi-box-seam';

}


/* =========================================================
   TODAY HAS SCHEDULE
========================================================= */

elseif (
    $todayTotal > 0
) {


    if (
        $todayGiven <= 0
    ) {

        $medText =
            'Pending Medication';

        $medClass =
            'badge-pending';

        $medIcon =
            'bi-clock-history';

    }


    elseif (
        $todayGiven <
        $todayTotal
    ) {

        $medText =
            $todayGiven
            .
            ' / '
            .
            $todayTotal
            .
            ' Given Today';


        $medClass =
            'badge-ready';


        $medIcon =
            'bi-capsule';

    }


    else {

        $medText =
            $todayGiven
            .
            ' / '
            .
            $todayTotal
            .
            ' Given Today';


        $medClass =
            'badge-good';


        $medIcon =
            'bi-check-circle';
    }

}


/* =========================================================
   PREVIOUS GIVEN
========================================================= */

elseif (
    $givenDoses > 0
) {

    $medText =
        $givenDoses
        .
        ' / '
        .
        $totalScheduled
        .
        ' Total Given';


    $medClass =
        'badge-good';


    $medIcon =
        'bi-check-circle';

}


/* =========================================================
   DEFAULT
========================================================= */

else {

    $medText =
        'Pending Preparation';

    $medClass =
        'badge-pending';

    $medIcon =
        'bi-hourglass-split';

}

?>


<tr>


<td>

<?= $index + 1 ?>

</td>


<td>


<span class="patient-name">


<?= h(
    $patient[
        'NAME'
    ]
    ?? ''
) ?>


</span>


</td>


<td>


<?= h(
    $patient[
        'WARD_NAME'
    ]
    ?? '-'
) ?>


</td>


<td>


<?= h(
    $patient[
        'BED_NUMBER'
    ]
    ?? '-'
) ?>


</td>


<td>


<?php


if (
    !empty(
        $patient[
            'ADMISSION_DATE'
        ]
    )
) {

    $timestamp =
        strtotime(
            $patient[
                'ADMISSION_DATE'
            ]
        );


    if (
        $timestamp !== false
    ) {

        echo strtoupper(
            date(
                'd-M-y',
                $timestamp
            )
        );

    }
    else {

        echo h(
            $patient[
                'ADMISSION_DATE'
            ]
        );
    }

}
else {

    echo '-';
}

?>


</td>


<td>


<?php


if (
    !empty(
        $patient[
            'EXPECTED_DISCHARGE_DATE'
        ]
    )
) {

    $expectedTimestamp =
        strtotime(
            $patient[
                'EXPECTED_DISCHARGE_DATE'
            ]
        );


    if (
        $expectedTimestamp
        !==
        false
    ) {

        echo strtoupper(
            date(
                'd-M-y',
                $expectedTimestamp
            )
        );

    }
    else {

        echo h(
            $patient[
                'EXPECTED_DISCHARGE_DATE'
            ]
        );
    }

}
else {

    echo '-';
}

?>


</td>


<!-- DIAGNOSIS -->

<td>


<?php if (
    (
        $patient[
            'DIAG_STATUS'
        ]
        ??
        ''
    )
    ===
    'Diagnosed'
): ?>


<span class="small-badge badge-good">


<i class="bi bi-check-circle"></i>

Diagnosed


</span>


<?php else: ?>


<span class="small-badge badge-danger-soft">


<i class="bi bi-exclamation-circle"></i>

No Diagnosis


</span>


<?php endif; ?>


</td>


<!-- MEDICATION -->

<td>


<span class="small-badge <?= $medClass ?>">


<i class="bi <?= $medIcon ?>"></i>


<?= h(
    $medText
) ?>


</span>


</td>


<!-- STATUS -->

<td>


<span class="small-badge badge-appointment">


<i class="bi bi-hospital"></i>

Admitted


</span>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-state">


<i class="bi bi-hospital"></i>


<div class="fw-semibold">

No admitted patients

</div>


<div class="small mt-1">

There are currently no active admitted patients under your care.

</div>


</div>


<?php endif; ?>


</div>


<!-- =====================================================
     UPCOMING APPOINTMENTS
===================================================== -->

<div class="section-card">


<div class="section-header">


<div>


<h5 class="section-title">

Upcoming Appointments

</h5>


<div class="section-subtitle">

Your upcoming approved appointment schedule

</div>


</div>


<div class="d-flex align-items-center gap-2">


<span class="badge text-bg-light">

<?= count(
    $appointments
) ?>

appointment(s)

</span>


<a
    href="doctor_appointments.php"
    class="btn btn-outline-primary action-btn"
>


View All


<i class="bi bi-arrow-right ms-1"></i>


</a>


</div>


</div>


<?php if (
    count(
        $appointments
    )
    >
    0
): ?>


<div class="table-responsive upcoming-table-wrap">


<table class="table">


<thead>


<tr>

<th>No.</th>

<th>Patient</th>

<th>Date</th>

<th>Time</th>

<th>Status</th>

<th>Action</th>

</tr>


</thead>


<tbody>


<?php foreach (
    $appointments
    as
    $index => $appointment
): ?>


<?php


$appointmentDate =
    $appointment[
        'APPOINTMENT_DATE_VALUE'
    ]
    ??
    '';


$isTodayAppointment =
    $appointmentDate
    ===
    date(
        'Y-m-d'
    );


?>


<tr>


<td>

<?= $index + 1 ?>

</td>


<td>


<span class="patient-name">


<?= h(
    $appointment[
        'PATIENT_NAME'
    ]
    ?? ''
) ?>


</span>


</td>


<td>


<?= h(
    $appointment[
        'APPOINTMENT_DATE'
    ]
    ?? '-'
) ?>


</td>


<td>


<?= h(
    $appointment[
        'APPOINTMENT_TIME'
    ]
    ?? '-'
) ?>


</td>


<td>


<?php if (
    $isTodayAppointment
): ?>


<span class="small-badge badge-good">


<i class="bi bi-calendar-check"></i>

Today


</span>


<?php else: ?>


<span class="small-badge badge-appointment">


<i class="bi bi-calendar-event"></i>

Upcoming


</span>


<?php endif; ?>


</td>


<td>


<?php if (
    $isTodayAppointment
): ?>


<div class="appointment-actions">


<!-- DIAGNOSE -->

<a
    href="treatment.php?type=appointment&id=<?= urlencode(
        $appointment[
            'APPOINTMENT_ID'
        ]
    ) ?>"
    class="btn btn-primary action-btn"
>


<i class="bi bi-clipboard2-pulse me-1"></i>

Diagnose


</a>


<!-- NO SHOW -->

<form
    method="POST"
    action="treatment.php"
    class="no-show-form"
    data-patient="<?= h(
        $appointment[
            'PATIENT_NAME'
        ]
        ?? 'Patient'
    ) ?>"
>


<input
    type="hidden"
    name="mark_no_show"
    value="1"
>


<input
    type="hidden"
    name="appointment_id"
    value="<?= (int)$appointment['APPOINTMENT_ID'] ?>"
>


<button
    type="submit"
    class="btn-no-show"
>


<i class="bi bi-person-x"></i>

No Show


</button>


</form>


</div>


<?php else: ?>


<a
    href="doctor_appointments.php"
    class="btn btn-outline-primary action-btn"
>


<i class="bi bi-eye me-1"></i>

View


</a>


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


<?php else: ?>


<div class="empty-state">


<i class="bi bi-calendar-x"></i>


<div class="fw-semibold">

No upcoming appointments

</div>


<div class="small mt-1">

There are currently no untreated upcoming appointments assigned to you.

</div>


</div>


<?php endif; ?>


</div>


</div>


</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
</script>


<script>

/* =========================================================
   SUCCESS POPUP
========================================================= */

<?php if (
    $successMessage !== ''
): ?>


document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        Swal.fire({

            icon:
                'success',

            title:
                <?= json_encode(
                    $successTitle !== ''
                    ?
                    $successTitle
                    :
                    'Success'
                ) ?>,

            text:
                <?= json_encode(
                    $successMessage
                ) ?>,

            confirmButtonText:
                'OK',

            confirmButtonColor:
                '#2563eb'

        });

    }
);


<?php endif; ?>


/* =========================================================
   NO SHOW CONFIRMATION
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        const noShowForms =
            document.querySelectorAll(
                '.no-show-form'
            );


        noShowForms.forEach(
            function(form)
            {

                form.addEventListener(
                    'submit',
                    function(event)
                    {

                        event.preventDefault();


                        const patientName =
                            form.dataset.patient
                            ||
                            'this patient';


                        Swal.fire({

                            icon:
                                'warning',

                            title:
                                'Mark as No Show?',

                            html:

                                '<strong>'
                                +
                                escapeHtml(
                                    patientName
                                )
                                +
                                '</strong> did not attend the scheduled appointment.<br><br>'
                                +
                                'The appointment will be closed without consultation.',

                            showCancelButton:
                                true,

                            reverseButtons:
                                true,

                            confirmButtonText:
                                'Yes, Mark No Show',

                            cancelButtonText:
                                'Cancel',

                            confirmButtonColor:
                                '#dc2626',

                            cancelButtonColor:
                                '#64748b'

                        })
                        .then(
                            function(result)
                            {

                                if (
                                    result.isConfirmed
                                ) {

                                    Swal.fire({

                                        title:
                                            'Updating Appointment',

                                        text:
                                            'Please wait...',

                                        allowOutsideClick:
                                            false,

                                        allowEscapeKey:
                                            false,

                                        didOpen:
                                            function()
                                            {

                                                Swal.showLoading();
                                            }

                                    });


                                    form.submit();
                                }
                            }
                        );
                    }
                );
            }
        );
    }
);


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value)
{
    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        value
        ??
        '';


    return div.innerHTML;
}


/* =========================================================
   REAL-TIME MALAYSIA CLOCK
========================================================= */

function updateLiveClock()
{
    const now =
        new Date();


    const dateFormatter =
        new Intl.DateTimeFormat(
            'en-GB',
            {

                timeZone:
                    'Asia/Kuala_Lumpur',

                day:
                    '2-digit',

                month:
                    'short',

                year:
                    'numeric'

            }
        );


    const timeFormatter =
        new Intl.DateTimeFormat(
            'en-US',
            {

                timeZone:
                    'Asia/Kuala_Lumpur',

                hour:
                    '2-digit',

                minute:
                    '2-digit',

                second:
                    '2-digit',

                hour12:
                    true

            }
        );


    const liveDate =
        document.getElementById(
            'liveDate'
        );


    const liveClock =
        document.getElementById(
            'liveClock'
        );


    if (liveDate) {

        liveDate.textContent =
            dateFormatter.format(
                now
            );
    }


    if (liveClock) {

        liveClock.textContent =
            timeFormatter.format(
                now
            );
    }
}


updateLiveClock();


setInterval(
    updateLiveClock,
    1000
);

</script>


</body>

</html>