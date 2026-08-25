<?php

session_start();

date_default_timezone_set('Asia/Kuala_Lumpur');

include("../config/config.php");


/* =========================================================
   ROLE CHECK
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] != 'admin'
) {

    header("Location: /zb-care/auth/login.php");

    exit();

}


/* =========================================================
   HELPER FUNCTION
========================================================= */

function getCount($conn, $sql)
{

    $stmt = $conn->prepare($sql);

    $stmt->execute();

    return (int)$stmt->fetchColumn();

}


/* =========================================================
   TOTAL REGISTERED PATIENT
========================================================= */

$patients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.PATIENT

");


/* =========================================================
   TOTAL STAFF
========================================================= */

$staff = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.HOSPITAL_STAFF

");


/* =========================================================
   PATIENT FLOW COUNTS
========================================================= */

$totalWalkinPatients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.WALKIN_CONSULTATION

");


$totalAppointmentPatients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.APPOINTMENT

");


$totalAdmittedPatients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

");


$totalDischargedPatients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

    WHERE DISCHARGE_DATE IS NOT NULL

");


$currentInpatients = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

    WHERE DISCHARGE_DATE IS NULL

");


/* =========================================================
   APPOINTMENT COUNTS
========================================================= */

$appointments = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.APPOINTMENT

    WHERE UPPER(TRIM(STATUS)) = 'PENDING'

");


$approvedAppointments = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.APPOINTMENT

    WHERE UPPER(TRIM(STATUS)) = 'APPROVED'

");


$pendingAppointments = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.APPOINTMENT

    WHERE UPPER(TRIM(STATUS)) = 'PENDING'

");


/* =========================================================
   DOCTOR AVAILABILITY TODAY
========================================================= */

$availableDoctors = (int)$conn->query("

    SELECT COUNT(DISTINCT ACCOUNT_ID)

    FROM SYARMIMI.DOCTOR_AVAILABILITY

    WHERE TRUNC(AVAILABLE_DATE) = TRUNC(SYSDATE)

    AND UPPER(TRIM(STATUS)) = 'AVAILABLE'

")->fetchColumn();


$unavailableDoctors = (int)$conn->query("

    SELECT COUNT(DISTINCT ACCOUNT_ID)

    FROM SYARMIMI.DOCTOR_AVAILABILITY

    WHERE TRUNC(AVAILABLE_DATE) = TRUNC(SYSDATE)

    AND UPPER(TRIM(STATUS)) = 'UNAVAILABLE'

")->fetchColumn();


$doctorAvailabilityToday =
    $conn->query("

        SELECT

            H.ACCOUNT_ID,

            H.USERNAME,

            H.DEPARTMENT,

            D.STATUS,

            D.START_TIME,

            D.END_TIME

        FROM SYARMIMI.DOCTOR_AVAILABILITY D

        JOIN SYARMIMI.HOSPITAL_STAFF H

            ON D.ACCOUNT_ID = H.ACCOUNT_ID

        WHERE TRUNC(D.AVAILABLE_DATE)
              =
              TRUNC(SYSDATE)

        ORDER BY H.USERNAME

    ")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   BED
========================================================= */

$bed = $conn->query("

    SELECT

        COUNT(*) TOTAL,

        SUM(

            CASE

                WHEN UPPER(TRIM(STATUS)) = 'OCCUPIED'

                THEN 1

                ELSE 0

            END

        ) USED

    FROM SYARMIMI.BED

")->fetch(PDO::FETCH_ASSOC);


$bedTotal =
    (int)($bed['TOTAL'] ?? 0);


$bedUsed =
    (int)($bed['USED'] ?? 0);


$bedUsage =
    $bedTotal > 0
        ? round(($bedUsed / $bedTotal) * 100)
        : 0;


/* =========================================================
   PATIENT FLOW CHART
========================================================= */

$patientFlowLabels = [

    'Walk-In',

    'Appointment',

    'Admitted',

    'Discharged'

];


$patientFlowValues = [

    $totalWalkinPatients,

    $totalAppointmentPatients,

    $totalAdmittedPatients,

    $totalDischargedPatients

];


/* =========================================================
   GENDER DISTRIBUTION USING IC
========================================================= */

$genderData =
    $conn->query("

        SELECT

            CASE

                WHEN REGEXP_LIKE(IC_NUMBER, '[0-9]')

                THEN

                    CASE

                        WHEN MOD(

                            TO_NUMBER(

                                SUBSTR(

                                    REGEXP_REPLACE(
                                        IC_NUMBER,
                                        '[^0-9]',
                                        ''
                                    ),

                                    -1

                                )

                            ),

                            2

                        ) = 0

                        THEN 'Female'

                        ELSE 'Male'

                    END

                ELSE 'Unknown'

            END AS GENDER_TYPE,

            COUNT(*) AS TOTAL

        FROM SYARMIMI.PATIENT

        GROUP BY

            CASE

                WHEN REGEXP_LIKE(IC_NUMBER, '[0-9]')

                THEN

                    CASE

                        WHEN MOD(

                            TO_NUMBER(

                                SUBSTR(

                                    REGEXP_REPLACE(
                                        IC_NUMBER,
                                        '[^0-9]',
                                        ''
                                    ),

                                    -1

                                )

                            ),

                            2

                        ) = 0

                        THEN 'Female'

                        ELSE 'Male'

                    END

                ELSE 'Unknown'

            END

        ORDER BY GENDER_TYPE

    ")->fetchAll(PDO::FETCH_ASSOC);


$genderLabels = [];

$genderValues = [];


foreach ($genderData as $g) {

    $genderLabels[] =
        $g['GENDER_TYPE'];

    $genderValues[] =
        (int)$g['TOTAL'];

}


/* =========================================================
   TODAY HOSPITAL ACTIVITY
========================================================= */

$admittedToday = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

    WHERE TRUNC(ADMISSION_DATE)
          =
          TRUNC(SYSDATE)

");


$dischargedToday = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.ADMISSION

    WHERE TRUNC(DISCHARGE_DATE)
          =
          TRUNC(SYSDATE)

");


$walkinToday = getCount($conn, "

    SELECT COUNT(*)

    FROM SYARMIMI.WALKIN_CONSULTATION

    WHERE TRUNC(CONSULTATION_DATE)
          =
          TRUNC(SYSDATE)

");


/* =========================================================
   ADMISSION TREND
========================================================= */

$trend =
    $conn->query("

        SELECT

            TO_CHAR(
                ADMISSION_DATE,
                'DD Mon'
            ) DAY,

            COUNT(*) TOTAL

        FROM SYARMIMI.ADMISSION

        WHERE ADMISSION_DATE
              >=
              TRUNC(SYSDATE) - 7

        GROUP BY

            TO_CHAR(
                ADMISSION_DATE,
                'DD Mon'
            )

        ORDER BY

            MIN(ADMISSION_DATE)

    ")->fetchAll(PDO::FETCH_ASSOC);


$trendLabels = [];

$trendValues = [];


foreach ($trend as $t) {

    $trendLabels[] =
        $t['DAY'];

    $trendValues[] =
        (int)$t['TOTAL'];

}


/* =========================================================
   STAFF ROLE
========================================================= */

$staffRole =
    $conn->query("

        SELECT

            ROLE,

            COUNT(*) TOTAL

        FROM SYARMIMI.HOSPITAL_STAFF

        GROUP BY ROLE

        ORDER BY ROLE

    ")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   TOP MEDICATION
========================================================= */

$topMedication =
    $conn->query("

        SELECT

            M.MEDICATION_NAME,

            COUNT(*) TOTAL

        FROM SYARMIMI.MEDICATION_ORDER MO

        JOIN SYARMIMI.MEDICATION M

            ON MO.MEDICATION_ID
               =
               M.MEDICATION_ID

        GROUP BY

            M.MEDICATION_NAME

        ORDER BY

            TOTAL DESC

        FETCH FIRST 5 ROWS ONLY

    ")->fetchAll(PDO::FETCH_ASSOC);


$medLabels = [];

$medValues = [];


foreach ($topMedication as $m) {

    $medLabels[] =
        $m['MEDICATION_NAME'];

    $medValues[] =
        (int)$m['TOTAL'];

}


/* =========================================================
   RECENT ADMISSIONS
========================================================= */

$recentAdmissions =
    $conn->query("

        SELECT

            A.ADMISSION_ID,

            P.NAME

        FROM SYARMIMI.ADMISSION A

        JOIN SYARMIMI.PATIENT P

            ON A.PATIENT_ID
               =
               P.PATIENT_ID

        ORDER BY

            A.ADMISSION_ID DESC

    ")->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PATIENT LIST
========================================================= */

$patientsList =
    $conn->query("

        SELECT

            PATIENT_ID,

            NAME,

            GENDER,

            IC_NUMBER

        FROM SYARMIMI.PATIENT

        ORDER BY

            PATIENT_ID DESC

        FETCH FIRST 6 ROWS ONLY

    ")->fetchAll(PDO::FETCH_ASSOC);

?>


<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Admin Dashboard</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/chart.js">
</script>


<style>

:root {

    --bg:#f4f7fa;
    --white:#ffffff;

    --navy:#14243a;

    --text:#0f172a;

    --secondary:#475569;

    --muted:#64748b;

    --border:#e5eaf0;

    --blue:#1268f3;

    --blue-soft:#edf4ff;

    --green:#18a568;

    --green-soft:#eaf8f1;

    --orange:#dc8c21;

    --orange-soft:#fff5e7;

    --red:#ee4d5a;

    --red-soft:#fff0f2;

    --purple:#7c3aed;

}


/* =========================================================
   GENERAL
========================================================= */

* {

    box-sizing:border-box;

}


html,
body {

    min-height:100%;

}


body {

    margin:0;

    background:var(--bg);

    color:var(--text);

    font-family:
        "Segoe UI",
        Arial,
        sans-serif;

    overflow-x:hidden;

}


.dashboard-shell {

    min-height:100vh;

    display:flex;

}


.main-content {

    flex:1;

    min-width:0;

    width:calc(100% - 260px);

    padding:
        24px 26px 36px;

}


/* =========================================================
   HEADER
========================================================= */

.dashboard-header {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:20px;

    margin-bottom:24px;

}


.header-left {

    display:flex;

    align-items:center;

    gap:15px;

}


.menu-button {

    width:37px;

    height:37px;

    display:grid;

    place-items:center;

    border:0;

    background:transparent;

    border-radius:10px;

    font-size:19px;

    color:#334155;

}


.dashboard-title {

    margin:0;

    color:#0f172a;

    font-size:26px;

    line-height:1.1;

    font-weight:800;

    letter-spacing:-.4px;

}


.dashboard-breadcrumb {

    margin-top:5px;

    color:#64748b;

    font-size:11px;

}


.header-right {

    display:flex;

    align-items:center;

    gap:15px;

}


.notification-button {

    position:relative;

    width:37px;

    height:37px;

    display:grid;

    place-items:center;

    border-radius:50%;

    color:#475569;

    font-size:16px;

    text-decoration:none;

}


.notification-count {

    position:absolute;

    top:2px;

    right:1px;

    min-width:15px;

    height:15px;

    display:grid;

    place-items:center;

    padding:0 3px;

    background:#ef4444;

    color:#fff;

    border-radius:999px;

    font-size:7px;

    font-weight:800;

}


.admin-info {

    display:flex;

    align-items:center;

    gap:8px;

}


.admin-avatar {

    width:37px;

    height:37px;

    border-radius:50%;

    display:grid;

    place-items:center;

    background:#e5e7eb;

    color:#475569;

    font-size:17px;

}


.admin-name {

    color:#1e293b;

    font-size:11px;

    font-weight:700;

}


/* =========================================================
   KPI GRID
========================================================= */

.kpi-grid {

    display:grid;

    grid-template-columns:
        repeat(
            6,
            minmax(0,1fr)
        );

    gap:12px;

    align-items:stretch;

    margin-bottom:18px;

}


.kpi-card {

    height:205px;

    min-width:0;

    padding:
        13px 17px 15px;

    background:#fff;

    border:
        1px solid
        var(--border);

    border-radius:17px;

    box-shadow:
        0 6px 18px
        rgba(28,39,55,.04);

    display:flex;

    flex-direction:column;

    overflow:hidden;

}


/* =========================================================
   KPI HEADER
   FIXED: naikkan title + bagi ruang bawah
========================================================= */

.kpi-header {

    display:flex;

    align-items:center;

    gap:8px;

    min-height:32px;

    color:#334155;

    font-size:11px;

    font-weight:600;

    margin-bottom:10px;

}


.kpi-header.center {

    justify-content:center;

}


.kpi-icon {

    width:30px;

    height:30px;

    flex-shrink:0;

    display:grid;

    place-items:center;

    border-radius:9px;

    background:var(--blue-soft);

    color:var(--blue);

    font-size:13px;

}


.kpi-content {

    flex:1;

    display:flex;

    align-items:center;

    justify-content:center;

    text-align:center;

    min-height:0;

}


.big-number {

    font-size:34px;

    line-height:1;

    font-weight:500;

    color:#111827;

}


.small-description {

    margin-top:8px;

    color:#64748b;

    font-size:9px;

}


.kpi-divider {

    height:1px;

    background:#dce2e8;

    margin:
        9px 0 12px;

}


.kpi-footer {

    min-height:34px;

    display:flex;

    align-items:center;

    justify-content:center;

    flex-direction:column;

    text-align:center;

    color:#64748b;

    font-size:9px;

}


.kpi-footer strong {

    margin-bottom:3px;

    color:#0f172a;

    font-size:10px;

    font-weight:700;

}


.kpi-link {

    color:#0765f8;

    text-decoration:none;

    font-size:9px;

    font-weight:500;

}


/* =========================================================
   FIRST CARD
========================================================= */

.date-card-content {

    display:flex;

    align-items:flex-start;

    justify-content:space-between;

    gap:10px;

}


.date-text {

    color:#475569;

    font-size:10px;

}


.time-text {

    margin-top:8px;

    color:#0f172a;

    font-size:18px;

    line-height:1;

    font-weight:800;

}


.patient-count-side {

    text-align:right;

}


.patient-count-label {

    color:#475569;

    font-size:9px;

}


.patient-count-number {

    margin-top:7px;

    color:#0f172a;

    font-size:32px;

    line-height:1;

    font-weight:500;

}


/* =========================================================
   SPLIT STAT
========================================================= */

.split-stat {

    width:100%;

    display:grid;

    grid-template-columns:
        1fr 1px 1fr;

    align-items:center;

    gap:13px;

}


.split-divider {

    width:1px;

    height:50px;

    background:#dce2e8;

}


.split-label {

    display:block;

    margin-bottom:7px;

    color:#475569;

    font-size:10px;

}


.split-value {

    display:block;

    font-size:27px;

    line-height:1;

    font-weight:400;

}


/* =========================================================
   PATIENT FLOW KPI
========================================================= */

.patient-flow-kpi-grid {

    width:100%;

    display:grid;

    grid-template-columns:
        repeat(
            2,
            minmax(0,1fr)
        );

    gap:
        14px 8px;

    margin-top:1px;

}


.patient-flow-kpi-item {

    text-align:center;

}


.patient-flow-kpi-label {

    display:block;

    color:#475569;

    font-size:8px;

    margin-bottom:5px;

}


.patient-flow-kpi-value {

    display:block;

    font-size:21px;

    line-height:1;

    font-weight:500;

}


/* =========================================================
   COLORS
========================================================= */

.text-green {

    color:var(--green) !important;

}


.text-red {

    color:var(--red) !important;

}


.text-orange {

    color:var(--orange) !important;

}


.text-blue {

    color:#075dbf !important;

}


.text-purple {

    color:var(--purple) !important;

}


/* =========================================================
   QUICK ACTION
========================================================= */

.quick-section {

    display:grid;

    grid-template-columns:
        repeat(
            6,
            minmax(0,1fr)
        );

    gap:12px;

    margin-bottom:18px;

}


.quick-action {

    display:flex;

    align-items:center;

    gap:10px;

    min-height:72px;

    padding:13px;

    background:#fff;

    border:
        1px solid
        var(--border);

    border-radius:14px;

    color:#344054;

    text-decoration:none;

    box-shadow:
        0 5px 14px
        rgba(31,41,55,.03);

    transition:.15s ease;

}


.quick-action:hover {

    transform:
        translateY(-1px);

    border-color:#d5dce5;

    color:#172033;

}


.quick-icon {

    width:38px;

    height:38px;

    flex-shrink:0;

    display:grid;

    place-items:center;

    border-radius:11px;

    background:var(--blue-soft);

    color:var(--blue);

    font-size:15px;

}


.quick-text strong {

    display:block;

    font-size:11px;

    font-weight:700;

    color:#1e293b;

}


.quick-text span {

    display:block;

    margin-top:4px;

    color:#64748b;

    font-size:9px;

}


/* =========================================================
   PANEL
========================================================= */

.panel {

    background:#fff;

    border:
        1px solid
        var(--border);

    border-radius:17px;

    box-shadow:
        0 5px 18px
        rgba(31,41,55,.035);

    overflow:hidden;

}


.panel-header {

    min-height:60px;

    padding:
        15px 18px;

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

}


.panel-title-wrap {

    display:flex;

    align-items:center;

    gap:10px;

}


.panel-icon {

    width:32px;

    height:32px;

    flex-shrink:0;

    display:grid;

    place-items:center;

    border-radius:9px;

    background:var(--blue-soft);

    color:var(--blue);

    font-size:13px;

}


.panel-title {

    margin:0;

    color:#0f172a;

    font-size:14px;

    font-weight:750;

}


.panel-subtitle {

    margin-top:3px;

    color:#64748b;

    font-size:9px;

}


.panel-link {

    padding:
        6px 10px;

    border:
        1px solid
        #dce4ed;

    border-radius:8px;

    color:#0b63e8;

    background:#fff;

    text-decoration:none;

    font-size:9px;

    font-weight:700;

}


/* =========================================================
   MAIN GRID
========================================================= */

.main-grid {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:14px;

    margin-bottom:18px;

}


/* =========================================================
   TODAY ACTIVITY
========================================================= */

.today-grid {

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:9px;

    padding:
        0 16px 16px;

}


.today-card {

    min-height:125px;

    padding:13px;

    border:
        1px solid
        var(--border);

    border-radius:12px;

    background:#fff;

}


.today-card-top {

    display:flex;

    align-items:center;

    gap:9px;

}


.today-icon {

    width:35px;

    height:35px;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:var(--blue-soft);

    color:var(--blue);

    font-size:13px;

}


.today-label {

    color:#64748b;

    font-size:9px;

    font-weight:500;

}


.today-number {

    margin-top:4px;

    color:#0f172a;

    font-size:22px;

    font-weight:700;

}


.today-note {

    margin-top:16px;

    color:#64748b;

    font-size:9px;

}


/* =========================================================
   DOCTOR TABLE
========================================================= */

.availability-wrapper {

    padding:
        0 16px 16px;

    overflow-x:auto;

}


.availability-table {

    width:100%;

    min-width:520px;

    border-collapse:collapse;

}


.availability-table th {

    padding:
        9px 8px;

    background:#f8fafc;

    border-bottom:
        1px solid
        var(--border);

    color:#334155;

    font-size:9px;

    font-weight:700;

    white-space:nowrap;

}


.availability-table td {

    padding:
        10px 8px;

    border-bottom:
        1px solid
        #edf0f3;

    color:#475569;

    font-size:9px;

}


.availability-table td strong {

    color:#1e293b;

    font-weight:700;

}


.availability-table tr:last-child td {

    border-bottom:none;

}


.status-badge {

    display:inline-flex;

    padding:
        5px 7px;

    border-radius:999px;

    font-size:8px;

    font-weight:700;

}


.status-available {

    background:var(--green-soft);

    color:var(--green);

}


.status-unavailable {

    background:var(--red-soft);

    color:var(--red);

}


/* =========================================================
   CHART
========================================================= */

.chart-section {

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:18px;

}


.chart-full {

    margin-bottom:18px;

}


.chart-body {

    height:230px;

    padding:
        5px 18px 18px;

    position:relative;

}


.chart-body.large {

    height:250px;

}


.chart-body canvas {

    max-height:100% !important;

}


/* =========================================================
   BOTTOM
========================================================= */

.bottom-grid {

    display:grid;

    grid-template-columns:
        1fr 1fr;

    gap:14px;

}


/* =========================================================
   RECENT
========================================================= */

.recent-list {

    padding:
        0 16px 16px;

}


.recent-item {

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:12px;

    padding:
        11px 0;

    border-bottom:
        1px solid
        #edf0f3;

}


.recent-item:last-child {

    border-bottom:none;

}


.recent-avatar {

    width:34px;

    height:34px;

    display:grid;

    place-items:center;

    flex-shrink:0;

    border-radius:10px;

    background:var(--blue-soft);

    color:var(--blue);

}


.recent-info {

    flex:1;

    min-width:0;

}


.recent-name {

    color:#1e293b;

    font-size:10px;

    font-weight:700;

}


.recent-meta {

    margin-top:3px;

    color:#64748b;

    font-size:9px;

}


/* =========================================================
   PATIENT RECORD
========================================================= */

.patient-grid {

    display:grid;

    grid-template-columns:
        repeat(
            3,
            minmax(0,1fr)
        );

    gap:9px;

    padding:
        0 16px 16px;

}


.patient-card {

    min-height:120px;

    padding:12px;

    border:
        1px solid
        var(--border);

    border-radius:12px;

    text-align:center;

}


.patient-avatar {

    width:42px;

    height:42px;

    margin:auto;

    display:grid;

    place-items:center;

    border-radius:50%;

    background:var(--blue-soft);

    color:var(--blue);

    font-size:16px;

}


.patient-name {

    margin-top:8px;

    color:#1e293b;

    font-size:10px;

    font-weight:700;

}


.patient-gender {

    display:inline-flex;

    margin-top:6px;

    padding:
        4px 7px;

    border-radius:999px;

    background:#f1f3f6;

    color:#475569;

    font-size:8px;

}


.patient-id {

    display:block;

    margin-top:8px;

    color:#64748b;

    font-size:8px;

}


/* =========================================================
   MODAL
========================================================= */

.modal-content {

    border:0;

    border-radius:16px;

    overflow:hidden;

}


.modal-header {

    border-bottom:
        1px solid
        var(--border);

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1450px) {

    .kpi-grid {

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );

    }


    .quick-section {

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );

    }

}


@media(max-width:1100px) {

    .main-grid,
    .bottom-grid {

        grid-template-columns:1fr;

    }


    .chart-section {

        grid-template-columns:1fr;

    }

}


@media(max-width:820px) {

    .main-content {

        width:100%;

        padding:
            18px 14px 28px;

    }


    .kpi-grid {

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );

    }


    .quick-section {

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );

    }


    .today-grid {

        grid-template-columns:1fr;

    }


    .patient-grid {

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );

    }


    .admin-name {

        display:none;

    }

}


@media(max-width:520px) {

    .dashboard-header {

        margin-bottom:18px;

    }


    .menu-button {

        display:none;

    }


    .dashboard-title {

        font-size:20px;

    }


    .kpi-grid,
    .quick-section,
    .patient-grid {

        grid-template-columns:1fr;

    }


    .kpi-card {

        height:190px;

    }

}

</style>

</head>


<body>


<div class="dashboard-shell">


<?php include("../includes/sidebar_admin.php"); ?>


<main class="main-content">


<!-- =========================================================
     HEADER
========================================================= -->

<header class="dashboard-header">


<div class="header-left">


<button
    type="button"
    class="menu-button"
>

    <i class="bi bi-list"></i>

</button>


<div>


<h1 class="dashboard-title">

    Admin Dashboard

</h1>


<div class="dashboard-breadcrumb">

    Dashboard &gt; Overview

</div>


</div>


</div>


<div class="header-right">


<a
    href="../pages/admin_appointment.php"
    class="notification-button"
>


<i class="bi bi-bell"></i>


<?php if ($appointments > 0): ?>


<span class="notification-count">

    <?= $appointments ?>

</span>


<?php endif; ?>


</a>


<div class="admin-info">


<div class="admin-avatar">

    <i class="bi bi-person-fill"></i>

</div>


<span class="admin-name">

    <?= htmlspecialchars(
        $_SESSION['user'] ?? 'Admin'
    ) ?>

</span>


<i
    class="bi bi-chevron-down"
    style="font-size:8px;color:#64748b;"
></i>


</div>


</div>


</header>


<!-- =========================================================
     KPI
========================================================= -->

<section class="kpi-grid">


<!-- TOTAL PATIENT -->

<div class="kpi-card">


<div class="date-card-content">


<div>


<div class="date-text">

    <?= date('d M Y') ?>

</div>


<div
    class="time-text"
    id="liveTime"
>

    <?= date('h:i A') ?>

</div>


</div>


<div class="patient-count-side">


<div class="patient-count-label">

    Total Patients

</div>


<div class="patient-count-number">

    <?= $patients ?>

</div>


</div>


</div>


<div class="kpi-content"></div>


<div class="kpi-divider"></div>


<div class="kpi-footer">


<strong>

    Registered hospital patients

</strong>


Real-time patient statistics


</div>


</div>


<!-- APPOINTMENT -->

<div class="kpi-card">


<div class="kpi-header center">


<span class="kpi-icon">

    <i class="bi bi-bar-chart-line"></i>

</span>


Appointment Statistics


</div>


<div class="kpi-content">


<div class="split-stat">


<div>


<span class="split-label">

    Approved

</span>


<span class="split-value text-green">

    <?= $approvedAppointments ?>

</span>


</div>


<div class="split-divider"></div>


<div>


<span class="split-label">

    Pending

</span>


<span class="split-value text-orange">

    <?= $pendingAppointments ?>

</span>


</div>


</div>


</div>


<div class="kpi-divider"></div>


<div class="kpi-footer">

    Appointment Activity

</div>


</div>


<!-- PATIENT FLOW -->

<div class="kpi-card">


<div class="kpi-header center">


<span class="kpi-icon">

    <i class="bi bi-diagram-3"></i>

</span>


Patient Flow


</div>


<div class="kpi-content">


<div class="patient-flow-kpi-grid">


<div class="patient-flow-kpi-item">


<span class="patient-flow-kpi-label">

    Walk-In

</span>


<span class="patient-flow-kpi-value text-blue">

    <?= $totalWalkinPatients ?>

</span>


</div>


<div class="patient-flow-kpi-item">


<span class="patient-flow-kpi-label">

    Appointment

</span>


<span class="patient-flow-kpi-value text-purple">

    <?= $totalAppointmentPatients ?>

</span>


</div>


<div class="patient-flow-kpi-item">


<span class="patient-flow-kpi-label">

    Admitted

</span>


<span class="patient-flow-kpi-value text-orange">

    <?= $totalAdmittedPatients ?>

</span>


</div>


<div class="patient-flow-kpi-item">


<span class="patient-flow-kpi-label">

    Discharged

</span>


<span class="patient-flow-kpi-value text-green">

    <?= $totalDischargedPatients ?>

</span>


</div>


</div>


</div>


<div class="kpi-divider"></div>


<div class="kpi-footer">

    Overall Patient Activity

</div>


</div>


<!-- STAFF -->

<div
    class="kpi-card"
    style="cursor:pointer;"
    data-bs-toggle="modal"
    data-bs-target="#staffModal"
>


<div class="kpi-header center">


<span class="kpi-icon">

    <i class="bi bi-person-badge"></i>

</span>


Hospital Staff


</div>


<div class="kpi-content">


<div>


<div class="big-number text-blue">

    <?= $staff ?>

</div>


<div class="small-description">


<span class="kpi-link">

    Click to View Staff List

</span>


</div>


</div>


</div>


<div class="kpi-divider"></div>


<div class="kpi-footer">

    Staff Management

</div>


</div>


<!-- WALK IN -->

<div class="kpi-card">


<div class="kpi-header center">


<span class="kpi-icon">

    <i class="bi bi-person-walking"></i>

</span>


Walk-In Consultation


</div>


<div class="kpi-content">


<div>


<div class="big-number text-blue">

    <?= $totalWalkinPatients ?>

</div>


<div class="small-description">

    Total walk-in consultations

</div>


</div>


</div>


<div class="kpi-divider"></div>


<div class="kpi-footer">

    Consultation Activity

</div>


</div>


<!-- DOCTOR -->

<div
    class="kpi-card"
    style="cursor:pointer;"
    data-bs-toggle="modal"
    data-bs-target="#doctorAvailabilityModal"
>


<div class="kpi-header center">


<span class="kpi-icon">

    <i class="bi bi-calendar3"></i>

</span>


Doctor Availability


</div>


<div class="kpi-content">


<div class="split-stat">


<div>


<span class="split-label">

    Available

</span>


<span class="split-value text-green">

    <?= $availableDoctors ?>

</span>


</div>


<div class="split-divider"></div>


<div>


<span class="split-label">

    Unavailable

</span>


<span class="split-value text-red">

    <?= $unavailableDoctors ?>

</span>


</div>


</div>


</div>


<div class="kpi-divider"></div>


<div class="kpi-footer">


<span class="kpi-link">

    Click to View Availability

</span>


</div>


</div>


</section>


<!-- =========================================================
     QUICK ACTION
========================================================= -->

<section class="quick-section">


<a
    href="../pages/patient.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-person-plus"></i>
</div>

<div class="quick-text">

<strong>Register Patient</strong>

<span>Add patient record</span>

</div>

</a>


<a
    href="../pages/walkin_consultation.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-clipboard2-pulse"></i>
</div>

<div class="quick-text">

<strong>Walk-In Consultation</strong>

<span>Register walk-in patient</span>

</div>

</a>


<a
    href="../pages/admission.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-hospital"></i>
</div>

<div class="quick-text">

<strong>Admission</strong>

<span>Manage admissions</span>

</div>

</a>


<a
    href="../pages/staff.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-person-badge"></i>
</div>

<div class="quick-text">

<strong>Add Staff</strong>

<span>Manage hospital staff</span>

</div>

</a>


<a
    href="../pages/med_order.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-capsule"></i>
</div>

<div class="quick-text">

<strong>Medication</strong>

<span>Medication orders</span>

</div>

</a>


<a
    href="../pages/admin_appointment.php"
    class="quick-action"
>

<div class="quick-icon">
    <i class="bi bi-calendar3"></i>
</div>

<div class="quick-text">

<strong>Appointments</strong>

<span>

<?php if ($appointments > 0): ?>

<?= $appointments ?> pending

<?php else: ?>

Appointment records

<?php endif; ?>

</span>

</div>

</a>


</section>


<!-- =========================================================
     TODAY ACTIVITY + DOCTOR
========================================================= -->

<section class="main-grid">


<div class="panel">


<div class="panel-header">


<div class="panel-title-wrap">


<div class="panel-icon">

    <i class="bi bi-activity"></i>

</div>


<div>


<h2 class="panel-title">

    Today's Hospital Activity

</h2>


<div class="panel-subtitle">

    Current patient movement today

</div>


</div>


</div>


</div>


<div class="today-grid">


<div class="today-card">


<div class="today-card-top">

<div class="today-icon">
    <i class="bi bi-box-arrow-in-right"></i>
</div>

<div>

<div class="today-label">
    Admitted Today
</div>

<div class="today-number">
    <?= $admittedToday ?>
</div>

</div>

</div>


<div class="today-note">

    New inpatient admissions

</div>


</div>


<div class="today-card">


<div class="today-card-top">

<div class="today-icon">
    <i class="bi bi-box-arrow-right"></i>
</div>

<div>

<div class="today-label">
    Discharged Today
</div>

<div class="today-number">
    <?= $dischargedToday ?>
</div>

</div>

</div>


<div class="today-note">

    Completed inpatient stays

</div>


</div>


<div class="today-card">


<div class="today-card-top">

<div class="today-icon">
    <i class="bi bi-person-walking"></i>
</div>

<div>

<div class="today-label">
    Walk-In Today
</div>

<div class="today-number">
    <?= $walkinToday ?>
</div>

</div>

</div>


<div class="today-note">

    Today's walk-in consultations

</div>


</div>


</div>


</div>


<!-- DOCTOR -->

<div class="panel">


<div class="panel-header">


<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-calendar-check"></i>
</div>

<div>

<h2 class="panel-title">
    Today's Doctor Availability
</h2>

<div class="panel-subtitle">
    Doctor schedule and availability today
</div>

</div>

</div>


<button
    type="button"
    class="panel-link"
    data-bs-toggle="modal"
    data-bs-target="#doctorAvailabilityModal"
>

View All

</button>


</div>


<div class="availability-wrapper">


<table class="availability-table">


<thead>

<tr>

<th>Doctor</th>
<th>Department</th>
<th>Status</th>
<th>Time</th>

</tr>

</thead>


<tbody>


<?php if (count($doctorAvailabilityToday) > 0): ?>


<?php

$previewDoctors =
    array_slice(
        $doctorAvailabilityToday,
        0,
        4
    );

?>


<?php foreach ($previewDoctors as $doctor): ?>


<?php

$doctorStatus =
    strtoupper(
        trim(
            $doctor['STATUS'] ?? ''
        )
    );

?>


<tr>


<td>

<strong>

Dr. <?= htmlspecialchars(
    $doctor['USERNAME'] ?? ''
) ?>

</strong>

</td>


<td>

<?= htmlspecialchars(
    $doctor['DEPARTMENT'] ?? '-'
) ?>

</td>


<td>


<?php if ($doctorStatus === 'AVAILABLE'): ?>


<span class="status-badge status-available">

Available

</span>


<?php else: ?>


<span class="status-badge status-unavailable">

<?= htmlspecialchars(
    $doctor['STATUS'] ?? 'Unavailable'
) ?>

</span>


<?php endif; ?>


</td>


<td>


<?php if ($doctorStatus === 'AVAILABLE'): ?>


<?= htmlspecialchars(
    $doctor['START_TIME'] ?? '-'
) ?>

-

<?= htmlspecialchars(
    $doctor['END_TIME'] ?? '-'
) ?>


<?php else: ?>


-


<?php endif; ?>


</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="4"
    class="text-center text-muted py-4"
>

No doctor availability recorded today.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</section>


<!-- =========================================================
     ADMISSION TREND
========================================================= -->

<section class="panel chart-full">


<div class="panel-header">


<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-graph-up"></i>
</div>

<div>

<h2 class="panel-title">
    Admission Trend
</h2>

<div class="panel-subtitle">
    Admissions recorded over the last 7 days
</div>

</div>

</div>


</div>


<div class="chart-body large">

<canvas id="trendChart"></canvas>

</div>


</section>


<!-- =========================================================
     CHARTS
========================================================= -->

<section class="chart-section">


<div class="panel">

<div class="panel-header">

<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-gender-ambiguous"></i>
</div>

<div>

<h2 class="panel-title">
    Gender Distribution
</h2>

<div class="panel-subtitle">
    Calculated from patient's IC number
</div>

</div>

</div>

</div>


<div class="chart-body">

<canvas id="genderChart"></canvas>

</div>

</div>


<div class="panel">

<div class="panel-header">

<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-diagram-3"></i>
</div>

<div>

<h2 class="panel-title">
    Patient Flow
</h2>

<div class="panel-subtitle">
    Overall patient activity and admission history
</div>

</div>

</div>

</div>


<div class="chart-body">

<canvas id="flowChart"></canvas>

</div>

</div>


<div class="panel">

<div class="panel-header">

<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-capsule"></i>
</div>

<div>

<h2 class="panel-title">
    Top 5 Medications
</h2>

<div class="panel-subtitle">
    Most ordered medications
</div>

</div>

</div>

</div>


<div class="chart-body">

<canvas id="medChart"></canvas>

</div>

</div>


</section>


<!-- =========================================================
     BOTTOM
========================================================= -->

<section class="bottom-grid">


<!-- RECENT ADMISSIONS -->

<div class="panel">


<div class="panel-header">


<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-hospital"></i>
</div>

<div>

<h2 class="panel-title">
    Recent Admissions
</h2>

<div class="panel-subtitle">
    Latest patient admissions
</div>

</div>

</div>


<a
    href="admission.php"
    class="panel-link"
>

View All

</a>


</div>


<div class="recent-list">


<?php foreach (
    array_slice(
        $recentAdmissions,
        0,
        5
    )
    as $row
): ?>


<div class="recent-item">

<div class="recent-avatar">
    <i class="bi bi-person"></i>
</div>

<div class="recent-info">

<div class="recent-name">

<?= htmlspecialchars(
    $row['NAME'] ?? ''
) ?>

</div>

<div class="recent-meta">

Admission #<?= (int)$row['ADMISSION_ID'] ?>

</div>

</div>

<i
    class="bi bi-chevron-right"
    style="font-size:9px;color:#64748b;"
></i>

</div>


<?php endforeach; ?>


</div>


</div>


<!-- PATIENT -->

<div class="panel">


<div class="panel-header">


<div class="panel-title-wrap">

<div class="panel-icon">
    <i class="bi bi-people"></i>
</div>

<div>

<h2 class="panel-title">
    Patient Records
</h2>

<div class="panel-subtitle">
    Recently registered patients
</div>

</div>

</div>


<a
    href="patient.php"
    class="panel-link"
>

View All

</a>


</div>


<div class="patient-grid">


<?php foreach ($patientsList as $p): ?>


<?php

$patientGender = '-';


$cleanIc =
    preg_replace(
        '/\D/',
        '',
        $p['IC_NUMBER'] ?? ''
    );


if ($cleanIc !== '') {

    $lastDigit =
        (int)substr(
            $cleanIc,
            -1
        );


    if ($lastDigit % 2 === 0) {

        $patientGender =
            'Female';

    } else {

        $patientGender =
            'Male';

    }

}

?>


<div class="patient-card">


<div class="patient-avatar">
    <i class="bi bi-person"></i>
</div>


<div class="patient-name">

<?= htmlspecialchars(
    $p['NAME'] ?? ''
) ?>

</div>


<span class="patient-gender">

<?= htmlspecialchars(
    $patientGender
) ?>

</span>


<span class="patient-id">

Patient ID:

<strong>

#<?= (int)$p['PATIENT_ID'] ?>

</strong>

</span>


</div>


<?php endforeach; ?>


</div>


</div>


</section>


</main>


</div>


<!-- =========================================================
     STAFF MODAL
========================================================= -->

<div
    class="modal fade"
    id="staffModal"
    tabindex="-1"
>


<div class="modal-dialog modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Staff Department List

</h5>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div class="modal-body">


<table class="table">


<thead>

<tr>
<th>Role</th>
<th>Total</th>
</tr>

</thead>


<tbody>


<?php foreach ($staffRole as $s): ?>


<tr>

<td>

<?= htmlspecialchars(
    $s['ROLE'] ?? '-'
) ?>

</td>

<td>

<?= (int)$s['TOTAL'] ?>

</td>

</tr>


<?php endforeach; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>


<!-- =========================================================
     DOCTOR MODAL
========================================================= -->

<div
    class="modal fade"
    id="doctorAvailabilityModal"
    tabindex="-1"
>


<div class="modal-dialog modal-lg modal-dialog-centered">


<div class="modal-content">


<div class="modal-header">


<h5 class="modal-title">

Doctor Availability Today

</h5>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div class="modal-body">


<div class="table-responsive">


<table class="table">


<thead>

<tr>
<th>Doctor</th>
<th>Department</th>
<th>Status</th>
<th>Time Slot</th>
</tr>

</thead>


<tbody>


<?php if (count($doctorAvailabilityToday) > 0): ?>


<?php foreach ($doctorAvailabilityToday as $doc): ?>


<tr>


<td>

Dr. <?= htmlspecialchars(
    $doc['USERNAME'] ?? ''
) ?>

</td>


<td>

<?= htmlspecialchars(
    $doc['DEPARTMENT'] ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $doc['STATUS'] ?? '-'
) ?>

</td>


<td>

<?= htmlspecialchars(
    $doc['START_TIME'] ?? '-'
) ?>

-

<?= htmlspecialchars(
    $doc['END_TIME'] ?? '-'
) ?>

</td>


</tr>


<?php endforeach; ?>


<?php else: ?>


<tr>

<td
    colspan="4"
    class="text-center text-muted"
>

No availability records today.

</td>

</tr>


<?php endif; ?>


</tbody>


</table>


</div>


</div>


</div>


</div>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   CLOCK
========================================================= */

function updateClock() {

    const now =
        new Date();


    document.getElementById(
        'liveTime'
    ).textContent =
        now.toLocaleTimeString(
            'en-US',
            {
                hour:'2-digit',
                minute:'2-digit',
                hour12:true
            }
        );

}


updateClock();


setInterval(
    updateClock,
    1000
);


/* =========================================================
   CHART DEFAULT
========================================================= */

Chart.defaults.font.size = 11;

Chart.defaults.color =
    '#475569';


const commonOptions = {

    responsive:true,

    maintainAspectRatio:false,

    plugins: {

        legend: {

            position:'bottom',

            labels: {

                boxWidth:10,

                boxHeight:10,

                padding:14,

                color:'#475569',

                font: {

                    size:11

                }

            }

        }

    }

};


/* =========================================================
   TREND
========================================================= */

new Chart(

    document.getElementById(
        'trendChart'
    ),

    {

        type:'line',

        data: {

            labels:
                <?= json_encode(
                    $trendLabels
                ) ?>,

            datasets: [

                {

                    label:'Admissions',

                    data:
                        <?= json_encode(
                            $trendValues
                        ) ?>,

                    borderWidth:2,

                    tension:.35,

                    fill:false

                }

            ]

        },

        options: {

            responsive:true,

            maintainAspectRatio:false,

            scales: {

                y: {

                    beginAtZero:true,

                    ticks: {

                        precision:0,

                        color:'#475569',

                        font: {

                            size:11

                        }

                    }

                },

                x: {

                    grid: {

                        display:false

                    },

                    ticks: {

                        color:'#475569',

                        font: {

                            size:11

                        }

                    }

                }

            },

            plugins: {

                legend: {

                    display:false

                }

            }

        }

    }

);


/* =========================================================
   GENDER
========================================================= */

new Chart(

    document.getElementById(
        'genderChart'
    ),

    {

        type:'pie',

        data: {

            labels:
                <?= json_encode(
                    $genderLabels
                ) ?>,

            datasets: [

                {

                    data:
                        <?= json_encode(
                            $genderValues
                        ) ?>

                }

            ]

        },

        options:commonOptions

    }

);


/* =========================================================
   PATIENT FLOW
========================================================= */

new Chart(

    document.getElementById(
        'flowChart'
    ),

    {

        type:'pie',

        data: {

            labels:
                <?= json_encode(
                    $patientFlowLabels
                ) ?>,

            datasets: [

                {

                    data:
                        <?= json_encode(
                            $patientFlowValues
                        ) ?>

                }

            ]

        },

        options:commonOptions

    }

);


/* =========================================================
   MEDICATION
========================================================= */

new Chart(

    document.getElementById(
        'medChart'
    ),

    {

        type:'doughnut',

        data: {

            labels:
                <?= json_encode(
                    $medLabels
                ) ?>,

            datasets: [

                {

                    data:
                        <?= json_encode(
                            $medValues
                        ) ?>

                }

            ]

        },

        options: {

            ...commonOptions,

            cutout:'62%'

        }

    }

);

</script>


</body>

</html>