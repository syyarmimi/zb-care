<?php

session_start();

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

include("../config/config.php");


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
   REDIRECT HELPER
========================================================= */

function redirectWard(
    $ward = 'All',
    $message = '',
    $type = 'success'
) {

    $url =
        'ward.php?ward=' .
        urlencode($ward);

    if ($message !== '') {

        $url .=
            '&msg=' .
            urlencode($message);

        $url .=
            '&type=' .
            urlencode($type);
    }

    header("Location: " . $url);
    exit();
}


/* =========================================================
   CURRENT WARD FILTER
========================================================= */

$ward_id =
    $_GET['ward']
    ?? 'All';


/* =========================================================
   DELETE BED
========================================================= */

if (isset($_POST['delete_bed'])) {

    $bed_id =
        trim(
            $_POST['delete_bed']
        );

    $current_ward =
        $_POST['current_ward']
        ?? 'All';


    if ($bed_id === '') {

        redirectWard(
            $current_ward,
            'Invalid bed selected.',
            'error'
        );
    }


    try {

        /* =================================================
           CHECK BED
        ================================================= */

        $checkBed =
            $conn->prepare("

                SELECT
                    BED_ID,
                    BED_NUMBER,
                    STATUS,
                    WARD_ID

                FROM
                    SYARMIMI.BED

                WHERE
                    BED_ID = :bed_id

            ");


        $checkBed->execute([
            ':bed_id' =>
                $bed_id
        ]);


        $bed =
            $checkBed->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$bed) {

            redirectWard(
                $current_ward,
                'The selected bed does not exist.',
                'error'
            );
        }


        /* =================================================
           OCCUPIED BED CANNOT DELETE
        ================================================= */

        if (
            strtolower(
                trim(
                    $bed['STATUS']
                )
            )
            ===
            'occupied'
        ) {

            redirectWard(
                $current_ward,
                'This bed is currently occupied and cannot be deleted.',
                'error'
            );
        }


        /* =================================================
           CHECK ADMISSION HISTORY
        ================================================= */

        $historyCheck =
            $conn->prepare("

                SELECT
                    COUNT(*)
                    AS TOTAL_HISTORY

                FROM
                    SYARMIMI.ADMISSION

                WHERE
                    BED_ID = :bed_id

            ");


        $historyCheck->execute([
            ':bed_id' =>
                $bed_id
        ]);


        $history =
            $historyCheck->fetch(
                PDO::FETCH_ASSOC
            );


        $totalHistory =
            (int)(
                $history[
                    'TOTAL_HISTORY'
                ]
                ?? 0
            );


        if ($totalHistory > 0) {

            redirectWard(
                $current_ward,
                'This bed cannot be deleted because it has admission history.',
                'warning'
            );
        }


        /* =================================================
           DELETE BED
        ================================================= */

        $delete =
            $conn->prepare("

                DELETE FROM
                    SYARMIMI.BED

                WHERE
                    BED_ID = :bed_id

            ");


        $delete->execute([
            ':bed_id' =>
                $bed_id
        ]);


        redirectWard(
            $current_ward,
            'Bed ' .
            $bed['BED_NUMBER'] .
            ' has been deleted successfully.',
            'success'
        );


    } catch (PDOException $e) {

        redirectWard(
            $current_ward,
            'Unable to delete the bed. Please check the database relationship.',
            'error'
        );
    }
}


/* =========================================================
   TRANSFER PATIENT
========================================================= */

if (isset($_POST['transfer'])) {

    $admission_id =
        trim(
            $_POST['admission_id']
            ?? ''
        );

    $new_bed =
        trim(
            $_POST['new_bed']
            ?? ''
        );

    $current_ward =
        $_POST['current_ward']
        ?? 'All';


    if (
        $admission_id === ''
        ||
        $new_bed === ''
    ) {

        redirectWard(
            $current_ward,
            'Please select a new bed.',
            'warning'
        );
    }


    try {

        $conn->beginTransaction();


        /* =================================================
           CURRENT ADMISSION
        ================================================= */

        $stmt =
            $conn->prepare("

                SELECT
                    ADMISSION_ID,
                    BED_ID

                FROM
                    SYARMIMI.ADMISSION

                WHERE
                    ADMISSION_ID =
                    :admission_id

                AND
                    DISCHARGE_DATE
                    IS NULL

                FOR UPDATE

            ");


        $stmt->execute([
            ':admission_id' =>
                $admission_id
        ]);


        $old =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$old) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'Active admission could not be found.',
                'error'
            );
        }


        /* =================================================
           CHECK NEW BED
        ================================================= */

        $newBedCheck =
            $conn->prepare("

                SELECT
                    BED_ID,
                    BED_NUMBER,
                    STATUS

                FROM
                    SYARMIMI.BED

                WHERE
                    BED_ID =
                    :bed_id

                FOR UPDATE

            ");


        $newBedCheck->execute([
            ':bed_id' =>
                $new_bed
        ]);


        $newBedData =
            $newBedCheck->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$newBedData) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'Selected bed does not exist.',
                'error'
            );
        }


        if (
            strtolower(
                trim(
                    $newBedData[
                        'STATUS'
                    ]
                )
            )
            !==
            'available'
        ) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'The selected bed is no longer available.',
                'warning'
            );
        }


        if (
            (string)$old['BED_ID']
            ===
            (string)$new_bed
        ) {

            $conn->rollBack();

            redirectWard(
                $current_ward,
                'The patient is already assigned to this bed.',
                'warning'
            );
        }


        /* =================================================
           UPDATE ADMISSION
        ================================================= */

        $updateAdmission =
            $conn->prepare("

                UPDATE
                    SYARMIMI.ADMISSION

                SET
                    BED_ID =
                    :new_bed

                WHERE
                    ADMISSION_ID =
                    :admission_id

            ");


        $updateAdmission->execute([
            ':new_bed' =>
                $new_bed,

            ':admission_id' =>
                $admission_id
        ]);


        /* =================================================
           FREE OLD BED
        ================================================= */

        $freeOldBed =
            $conn->prepare("

                UPDATE
                    SYARMIMI.BED

                SET
                    STATUS =
                    'Available'

                WHERE
                    BED_ID =
                    :bed_id

            ");


        $freeOldBed->execute([
            ':bed_id' =>
                $old['BED_ID']
        ]);


        /* =================================================
           OCCUPY NEW BED
        ================================================= */

        $occupyNewBed =
            $conn->prepare("

                UPDATE
                    SYARMIMI.BED

                SET
                    STATUS =
                    'Occupied'

                WHERE
                    BED_ID =
                    :bed_id

            ");


        $occupyNewBed->execute([
            ':bed_id' =>
                $new_bed
        ]);


        $conn->commit();


        redirectWard(
            $current_ward,
            'Patient transferred successfully.',
            'success'
        );


    } catch (PDOException $e) {

        if (
            $conn->inTransaction()
        ) {

            $conn->rollBack();
        }


        redirectWard(
            $current_ward,
            'Transfer failed. Please try again.',
            'error'
        );
    }
}


/* =========================================================
   ADD BED
========================================================= */

if (isset($_POST['add_bed'])) {

    $new_ward_id =
        trim(
            $_POST['ward_id']
            ?? ''
        );

    $bed_no =
        trim(
            $_POST['bed_number']
            ?? ''
        );

    $current_ward =
        $_POST['current_ward']
        ?? 'All';


    if (
        $new_ward_id === ''
        ||
        $bed_no === ''
    ) {

        redirectWard(
            $current_ward,
            'Please select a ward and enter a bed number.',
            'warning'
        );
    }


    try {

        /* =================================================
           CHECK WARD
        ================================================= */

        $wardCheck =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.WARD

                WHERE
                    WARD_ID =
                    :ward_id

            ");


        $wardCheck->execute([
            ':ward_id' =>
                $new_ward_id
        ]);


        if (
            (int)$wardCheck
                ->fetchColumn()
            === 0
        ) {

            redirectWard(
                $current_ward,
                'Selected ward does not exist.',
                'error'
            );
        }


        /* =================================================
           DUPLICATE BED
        ================================================= */

        $check =
            $conn->prepare("

                SELECT
                    COUNT(*)

                FROM
                    SYARMIMI.BED

                WHERE
                    UPPER(
                        TRIM(
                            BED_NUMBER
                        )
                    )
                    =
                    UPPER(
                        TRIM(
                            :bed_number
                        )
                    )

                AND
                    WARD_ID =
                    :ward_id

            ");


        $check->execute([
            ':bed_number' =>
                $bed_no,

            ':ward_id' =>
                $new_ward_id
        ]);


        if (
            (int)$check
                ->fetchColumn()
            > 0
        ) {

            redirectWard(
                $current_ward,
                'Bed number ' .
                $bed_no .
                ' already exists in this ward.',
                'warning'
            );
        }


        /* =================================================
           NEW BED ID
        ================================================= */

        $idStmt =
            $conn->query("

                SELECT
                    NVL(
                        MAX(BED_ID),
                        0
                    ) + 1
                    AS NEW_ID

                FROM
                    SYARMIMI.BED

            ");


        $idRow =
            $idStmt->fetch(
                PDO::FETCH_ASSOC
            );


        $newId =
            $idRow[
                'NEW_ID'
            ];


        /* =================================================
           INSERT BED
        ================================================= */

        $insert =
            $conn->prepare("

                INSERT INTO
                    SYARMIMI.BED
                (
                    BED_ID,
                    BED_NUMBER,
                    STATUS,
                    WARD_ID
                )

                VALUES
                (
                    :bed_id,
                    :bed_number,
                    'Available',
                    :ward_id
                )

            ");


        $insert->execute([
            ':bed_id' =>
                $newId,

            ':bed_number' =>
                strtoupper(
                    $bed_no
                ),

            ':ward_id' =>
                $new_ward_id
        ]);


        redirectWard(
            $current_ward,
            'Bed ' .
            strtoupper($bed_no) .
            ' added successfully.',
            'success'
        );


    } catch (PDOException $e) {

        redirectWard(
            $current_ward,
            'Unable to add the bed. Please try again.',
            'error'
        );
    }
}


/* =========================================================
   WARD SUMMARY
========================================================= */

$wardSummaryStmt =
    $conn->query("

        SELECT

            W.WARD_ID,

            W.WARD_NAME,

            COUNT(
                B.BED_ID
            )
            AS TOTAL_BED,

            SUM(
                CASE
                    WHEN
                        UPPER(
                            TRIM(
                                B.STATUS
                            )
                        )
                        =
                        'OCCUPIED'
                    THEN 1
                    ELSE 0
                END
            )
            AS OCCUPIED,

            SUM(
                CASE
                    WHEN
                        UPPER(
                            TRIM(
                                B.STATUS
                            )
                        )
                        =
                        'AVAILABLE'
                    THEN 1
                    ELSE 0
                END
            )
            AS AVAILABLE_BEDS

        FROM
            SYARMIMI.WARD W

        LEFT JOIN
            SYARMIMI.BED B

            ON
            W.WARD_ID =
            B.WARD_ID

        GROUP BY
            W.WARD_ID,
            W.WARD_NAME

        ORDER BY
            W.WARD_ID

    ");


$wardSummary =
    $wardSummaryStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   OVERALL COUNTS
========================================================= */

$totalBeds =
    (int)$conn->query("

        SELECT COUNT(*)
        FROM SYARMIMI.BED

    ")->fetchColumn();


$totalOccupied =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.BED

        WHERE
            UPPER(
                TRIM(
                    STATUS
                )
            )
            =
            'OCCUPIED'

    ")->fetchColumn();


$totalAvailable =
    (int)$conn->query("

        SELECT COUNT(*)

        FROM SYARMIMI.BED

        WHERE
            UPPER(
                TRIM(
                    STATUS
                )
            )
            =
            'AVAILABLE'

    ")->fetchColumn();


$totalWards =
    count(
        $wardSummary
    );


/* =========================================================
   AVAILABLE BEDS
========================================================= */

$availableBedsStmt =
    $conn->query("

        SELECT
            B.BED_ID,
            B.BED_NUMBER,
            B.WARD_ID,
            W.WARD_NAME

        FROM
            SYARMIMI.BED B

        JOIN
            SYARMIMI.WARD W

            ON
            B.WARD_ID =
            W.WARD_ID

        WHERE
            UPPER(
                TRIM(
                    B.STATUS
                )
            )
            =
            'AVAILABLE'

        ORDER BY
            W.WARD_NAME,
            B.BED_NUMBER

    ");


$availableBeds =
    $availableBedsStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   BED + ACTIVE PATIENT
========================================================= */

$sql = "

    SELECT

        B.BED_ID,

        B.BED_NUMBER,

        B.STATUS,

        W.WARD_ID,

        W.WARD_NAME,

        A.ADMISSION_ID,

        P.NAME,

        P.AGE,

        P.GENDER,

        (
            SELECT
                COUNT(*)

            FROM
                SYARMIMI.ADMISSION A2

            WHERE
                A2.BED_ID =
                B.BED_ID
        )
        AS TOTAL_HISTORY

    FROM
        SYARMIMI.BED B

    JOIN
        SYARMIMI.WARD W

        ON
        B.WARD_ID =
        W.WARD_ID

    LEFT JOIN
        SYARMIMI.ADMISSION A

        ON
        B.BED_ID =
        A.BED_ID

        AND
        A.DISCHARGE_DATE
        IS NULL

    LEFT JOIN
        SYARMIMI.PATIENT P

        ON
        A.PATIENT_ID =
        P.PATIENT_ID

    WHERE
        1 = 1

";


$params = [];


if ($ward_id !== 'All') {

    $sql .= "

        AND
            B.WARD_ID =
            :ward_id

    ";


    $params[
        ':ward_id'
    ] =
        $ward_id;
}


$sql .= "

    ORDER BY
        W.WARD_ID,
        B.BED_NUMBER

";


$bedStmt =
    $conn->prepare(
        $sql
    );


$bedStmt->execute(
    $params
);


$result =
    $bedStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


/* =========================================================
   WARD LIST
========================================================= */

$wardsStmt =
    $conn->query("

        SELECT
            WARD_ID,
            WARD_NAME

        FROM
            SYARMIMI.WARD

        ORDER BY
            WARD_ID

    ");


$wards =
    $wardsStmt
        ->fetchAll(
            PDO::FETCH_ASSOC
        );


/* =========================================================
   SELECTED WARD NAME
========================================================= */

$selectedWardName =
    'All Wards';


if ($ward_id !== 'All') {

    foreach (
        $wards
        as
        $ward
    ) {

        if (
            (string)$ward[
                'WARD_ID'
            ]
            ===
            (string)$ward_id
        ) {

            $selectedWardName =
                $ward[
                    'WARD_NAME'
                ];

            break;
        }
    }
}


/* =========================================================
   REDIRECT MESSAGE
========================================================= */

$message =
    $_GET['msg']
    ?? '';


$messageType =
    $_GET['type']
    ?? 'success';

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
Ward Management
</title>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>


<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>


<script
    src="https://cdn.jsdelivr.net/npm/sweetalert2@11"
></script>


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

    color:#1f2937;

    font-family:
        'Segoe UI',
        Arial,
        sans-serif;
}


.main-content{

    flex:1;

    min-width:0;

    min-height:100vh;

    padding:30px;
}


/* =========================================================
   HEADER
========================================================= */

.page-header{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

    margin-bottom:24px;
}


.page-title{

    margin:0;

    color:#111827;

    font-size:30px;

    font-weight:750;
}


.page-subtitle{

    margin-top:6px;

    color:#64748b;

    font-size:14px;
}


.header-badge{

    display:inline-flex;

    align-items:center;

    gap:7px;

    padding:9px 12px;

    background:#eff6ff;

    border:1px solid #dbeafe;

    border-radius:8px;

    color:#2563eb;

    font-size:12px;

    font-weight:650;
}


/* =========================================================
   OVERALL SUMMARY
========================================================= */

.overall-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:22px;
}


.overall-card{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:14px;

    padding:18px 19px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:13px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.035);
}


.overall-label{

    color:#64748b;

    font-size:12px;

    font-weight:600;
}


.overall-number{

    margin-top:5px;

    color:#111827;

    font-size:28px;

    font-weight:750;

    line-height:1;
}


.overall-icon{

    width:44px;

    height:44px;

    min-width:44px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:11px;

    font-size:18px;
}


.icon-wards{

    background:#f5f3ff;

    color:#7c3aed;
}


.icon-beds{

    background:#eff6ff;

    color:#2563eb;
}


.icon-occupied{

    background:#fff1f2;

    color:#e11d48;
}


.icon-available{

    background:#ecfdf5;

    color:#15803d;
}


/* =========================================================
   CONTENT CARD
========================================================= */

.content-card{

    margin-bottom:20px;

    padding:22px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:14px;

    box-shadow:
        0 3px 12px
        rgba(15,23,42,.04);
}


.card-heading{

    display:flex;

    align-items:center;

    justify-content:space-between;

    gap:15px;

    margin-bottom:18px;
}


.card-heading-left{

    display:flex;

    align-items:center;

    gap:11px;
}


.card-icon{

    width:38px;

    height:38px;

    min-width:38px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:16px;
}


.icon-add{

    background:#ecfdf5;

    color:#15803d;
}


.icon-layout{

    background:#eff6ff;

    color:#2563eb;
}


.card-title-clean{

    margin:0;

    color:#1f2937;

    font-size:17px;

    font-weight:700;
}


.card-subtitle{

    margin-top:3px;

    color:#94a3b8;

    font-size:12px;
}


/* =========================================================
   FORM
========================================================= */

.form-label{

    margin-bottom:7px;

    color:#475569;

    font-size:12px;

    font-weight:650;
}


.form-control,
.form-select{

    min-height:45px;

    border:1px solid #dfe3e8;

    border-radius:9px;

    color:#374151;

    font-size:13px;
}


.form-control:focus,
.form-select:focus{

    border-color:#93c5fd;

    box-shadow:
        0 0 0 3px
        rgba(37,99,235,.07);
}


.btn-main{

    min-height:45px;

    border-radius:9px;

    font-size:12px;

    font-weight:650;
}


.btn-add{

    background:#16a34a;

    border-color:#16a34a;

    color:#fff;
}


.btn-add:hover{

    background:#15803d;

    border-color:#15803d;

    color:#fff;
}


/* =========================================================
   WARD SUMMARY
========================================================= */

.ward-summary-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;

    margin-bottom:22px;
}


.ward-summary-card{

    position:relative;

    padding:18px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:13px;

    overflow:hidden;

    transition:.2s;
}


.ward-summary-card:hover{

    transform:translateY(-2px);

    border-color:#d8dee6;

    box-shadow:
        0 5px 14px
        rgba(15,23,42,.05);
}


.ward-summary-card.warning{

    border-color:#fde68a;
}


.ward-summary-card.critical{

    border-color:#fecaca;
}


.ward-name{

    color:#1f2937;

    font-size:14px;

    font-weight:700;
}


.ward-status-row{

    display:flex;

    justify-content:space-between;

    align-items:center;

    margin-top:15px;

    gap:10px;
}


.ward-total-label{

    color:#94a3b8;

    font-size:10px;

    text-transform:uppercase;

    font-weight:650;
}


.ward-total-number{

    margin-top:2px;

    color:#111827;

    font-size:20px;

    font-weight:700;
}


.ward-mini-stat{

    text-align:right;
}


.ward-mini-value{

    font-size:13px;

    font-weight:700;
}


.text-available{

    color:#15803d;
}


.text-critical{

    color:#dc2626;
}


.text-warning-custom{

    color:#d97706;
}


/* =========================================================
   PROGRESS
========================================================= */

.occupancy-progress{

    height:6px;

    margin-top:14px;

    overflow:hidden;

    background:#f1f5f9;

    border-radius:20px;
}


.occupancy-bar{

    height:100%;

    border-radius:20px;
}


.occupancy-low{

    background:#22c55e;
}


.occupancy-medium{

    background:#f59e0b;
}


.occupancy-high{

    background:#ef4444;
}


.ward-footer{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    margin-top:11px;
}


.occupancy-text{

    color:#94a3b8;

    font-size:10px;
}


.capacity-alert{

    display:inline-flex;

    align-items:center;

    gap:4px;

    padding:5px 7px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;
}


.capacity-ok{

    background:#ecfdf5;

    color:#15803d;
}


.capacity-warning{

    background:#fffbeb;

    color:#d97706;
}


.capacity-critical{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box{

    margin-bottom:20px;

    padding:15px;

    background:#f8fafc;

    border:1px solid #e5e7eb;

    border-radius:10px;
}


.filter-title{

    margin-bottom:10px;

    color:#64748b;

    font-size:11px;

    font-weight:700;

    text-transform:uppercase;

    letter-spacing:.3px;
}


/* =========================================================
   BED GRID
========================================================= */

.bed-grid{

    display:grid;

    grid-template-columns:
        repeat(
            4,
            minmax(0,1fr)
        );

    gap:14px;
}


/* =========================================================
   BED CARD
========================================================= */

.bed-card{

    position:relative;

    min-height:205px;

    padding:17px;

    background:#fff;

    border:1px solid #e5e7eb;

    border-radius:13px;

    transition:.2s;
}


.bed-card:hover{

    transform:translateY(-2px);

    box-shadow:
        0 5px 15px
        rgba(15,23,42,.06);
}


.bed-card.available-card{

    border-top:
        3px solid #22c55e;
}


.bed-card.occupied-card{

    border-top:
        3px solid #ef4444;
}


.bed-card-header{

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:10px;

    margin-bottom:15px;
}


.bed-icon{

    width:42px;

    height:42px;

    display:flex;

    align-items:center;

    justify-content:center;

    border-radius:10px;

    font-size:19px;
}


.available-card .bed-icon{

    background:#ecfdf5;

    color:#15803d;
}


.occupied-card .bed-icon{

    background:#fff1f2;

    color:#dc2626;
}


.bed-number{

    color:#111827;

    font-size:17px;

    font-weight:750;
}


.bed-ward{

    margin-top:2px;

    color:#94a3b8;

    font-size:10px;
}


.status-pill{

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:5px 8px;

    border-radius:6px;

    font-size:9px;

    font-weight:700;
}


.status-available{

    background:#ecfdf5;

    color:#15803d;
}


.status-occupied{

    background:#fff1f2;

    color:#dc2626;
}


/* =========================================================
   EMPTY BED
========================================================= */

.empty-bed{

    min-height:95px;

    display:flex;

    flex-direction:column;

    align-items:center;

    justify-content:center;

    color:#94a3b8;

    text-align:center;

    font-size:11px;
}


.empty-bed i{

    margin-bottom:7px;

    font-size:20px;

    color:#cbd5e1;
}


/* =========================================================
   PATIENT
========================================================= */

.patient-box{

    margin-bottom:12px;

    padding:11px;

    background:#f8fafc;

    border-radius:8px;
}


.patient-name{

    color:#111827;

    font-size:13px;

    font-weight:700;
}


.patient-meta{

    display:flex;

    gap:12px;

    margin-top:5px;

    color:#64748b;

    font-size:10px;
}


/* =========================================================
   TRANSFER
========================================================= */

.transfer-box{

    margin-top:11px;

    padding-top:11px;

    border-top:1px solid #eef1f4;
}


.transfer-label{

    display:block;

    margin-bottom:6px;

    color:#64748b;

    font-size:10px;

    font-weight:650;
}


.transfer-row{

    display:flex;

    gap:6px;
}


.transfer-row .form-select{

    min-height:35px;

    font-size:10px;

    border-radius:7px;
}


.btn-transfer{

    flex:0 0 auto;

    min-height:35px;

    padding:0 9px;

    background:#fff7ed;

    border:1px solid #fed7aa;

    border-radius:7px;

    color:#c2410c;

    font-size:10px;

    font-weight:650;
}


.btn-transfer:hover{

    background:#f97316;

    border-color:#f97316;

    color:#fff;
}


/* =========================================================
   HISTORY / DELETE
========================================================= */

.bed-footer{

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:8px;

    margin-top:12px;

    padding-top:11px;

    border-top:1px solid #eef1f4;
}


.history-text{

    color:#94a3b8;

    font-size:9px;
}


.history-text strong{

    color:#64748b;
}


.btn-delete-bed{

    padding:5px 8px;

    border:1px solid #fecaca;

    background:#fff;

    border-radius:6px;

    color:#dc2626;

    font-size:9px;

    font-weight:650;
}


.btn-delete-bed:hover{

    background:#dc2626;

    border-color:#dc2626;

    color:#fff;
}


.btn-delete-bed:disabled{

    border-color:#e5e7eb;

    background:#f3f4f6;

    color:#9ca3af;

    opacity:1;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state{

    grid-column:1 / -1;

    padding:45px 20px;

    background:#f8fafc;

    border:1px dashed #d8dee6;

    border-radius:12px;

    color:#94a3b8;

    text-align:center;
}


.empty-state i{

    display:block;

    margin-bottom:10px;

    font-size:30px;
}


/* =========================================================
   MODAL
========================================================= */

.modal-content{

    border:0;

    border-radius:14px;

    overflow:hidden;

    box-shadow:
        0 25px 60px
        rgba(15,23,42,.20);
}


.modal-header{

    padding:18px 20px;

    background:#fff;

    border-bottom:1px solid #e5e7eb;
}


.modal-title{

    color:#111827;

    font-size:17px;

    font-weight:700;
}


.modal-body{

    padding:21px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1200px){

    .overall-grid,
    .ward-summary-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }


    .bed-grid{

        grid-template-columns:
            repeat(
                3,
                minmax(0,1fr)
            );
    }

}


@media(max-width:900px){

    .main-content{

        padding:18px;
    }


    .page-header{

        flex-direction:column;
    }


    .bed-grid{

        grid-template-columns:
            repeat(
                2,
                minmax(0,1fr)
            );
    }

}


@media(max-width:600px){

    .overall-grid,
    .ward-summary-grid,
    .bed-grid{

        grid-template-columns:1fr;
    }


    .page-title{

        font-size:24px;
    }

}

</style>

</head>


<body>


<div class="d-flex">


<?php
include("../includes/sidebar_admin.php");
?>


<div class="main-content">


<!-- =====================================================
     HEADER
===================================================== -->

<div class="page-header">


<div>


<h1 class="page-title">

Ward Management

</h1>


<div class="page-subtitle">

Monitor ward capacity, hospital beds and patient bed assignments.

</div>


</div>


<div class="header-badge">

<i class="bi bi-hospital"></i>

<?= h(
    $selectedWardName
) ?>

</div>


</div>



<!-- =====================================================
     OVERALL SUMMARY
===================================================== -->

<div class="overall-grid">


<div class="overall-card">


<div>

<div class="overall-label">

Total Wards

</div>

<div class="overall-number">

<?= $totalWards ?>

</div>

</div>


<div class="overall-icon icon-wards">

<i class="bi bi-building"></i>

</div>


</div>



<div class="overall-card">


<div>

<div class="overall-label">

Total Beds

</div>

<div class="overall-number">

<?= $totalBeds ?>

</div>

</div>


<div class="overall-icon icon-beds">

<i class="bi bi-hospital"></i>

</div>


</div>



<div class="overall-card">


<div>

<div class="overall-label">

Occupied Beds

</div>

<div class="overall-number">

<?= $totalOccupied ?>

</div>

</div>


<div class="overall-icon icon-occupied">

<i class="bi bi-person-fill"></i>

</div>


</div>



<div class="overall-card">


<div>

<div class="overall-label">

Available Beds

</div>

<div class="overall-number">

<?= $totalAvailable ?>

</div>

</div>


<div class="overall-icon icon-available">

<i class="bi bi-check-circle"></i>

</div>


</div>


</div>



<!-- =====================================================
     ADD BED
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-add">

<i class="bi bi-plus-lg"></i>

</div>


<div>

<h5 class="card-title-clean">

Add New Bed

</h5>

<div class="card-subtitle">

Add a new bed to one of the existing hospital wards.

</div>

</div>


</div>


</div>


<form method="POST">


<input
    type="hidden"
    name="current_ward"
    value="<?= h(
        $ward_id
    ) ?>"
>


<div class="row g-3 align-items-end">


<div class="col-lg-5">


<label class="form-label">

Ward

</label>


<select
    name="ward_id"
    class="form-select"
    required
>


<option value="">

Select Ward

</option>


<?php foreach (
    $wards
    as
    $ward
): ?>


<option
    value="<?= h(
        $ward[
            'WARD_ID'
        ]
    ) ?>"
>

<?= h(
    $ward[
        'WARD_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


</div>



<div class="col-lg-5">


<label class="form-label">

Bed Number

</label>


<input
    type="text"
    name="bed_number"
    class="form-control"
    placeholder="Example: B101"
    required
>


</div>



<div class="col-lg-2">


<button
    type="submit"
    name="add_bed"
    class="btn btn-add btn-main w-100"
>

<i class="bi bi-plus-circle me-1"></i>

Add Bed

</button>


</div>


</div>


</form>


</div>



<!-- =====================================================
     WARD SUMMARY
===================================================== -->

<div class="ward-summary-grid">


<?php foreach (
    $wardSummary
    as
    $ward
): ?>


<?php

$total =
    (int)(
        $ward[
            'TOTAL_BED'
        ]
        ?? 0
    );


$occupied =
    (int)(
        $ward[
            'OCCUPIED'
        ]
        ?? 0
    );


$available =
    (int)(
        $ward[
            'AVAILABLE_BEDS'
        ]
        ?? 0
    );


$percentage =
    $total > 0
        ?
        round(
            (
                $occupied
                /
                $total
            )
            *
            100
        )
        :
        0;


$capacityClass =
    '';


$alertClass =
    'capacity-ok';


$alertText =
    'Available';


if (
    $available <= 1
    &&
    $total > 0
) {

    $capacityClass =
        'critical';

    $alertClass =
        'capacity-critical';

    $alertText =
        $available === 0
            ?
            'Full'
            :
            'Low Capacity';

}
elseif (
    $available <= 2
    &&
    $total > 0
) {

    $capacityClass =
        'warning';

    $alertClass =
        'capacity-warning';

    $alertText =
        'Limited Beds';
}


if (
    $percentage >= 80
) {

    $progressClass =
        'occupancy-high';

}
elseif (
    $percentage >= 60
) {

    $progressClass =
        'occupancy-medium';

}
else {

    $progressClass =
        'occupancy-low';
}

?>


<div
    class="
        ward-summary-card
        <?= h(
            $capacityClass
        ) ?>
    "
>


<div class="ward-name">

<?= h(
    $ward[
        'WARD_NAME'
    ]
) ?>

</div>


<div class="ward-status-row">


<div>


<div class="ward-total-label">

Total Beds

</div>


<div class="ward-total-number">

<?= $total ?>

</div>


</div>


<div class="ward-mini-stat">


<div class="ward-total-label">

Available

</div>


<div
    class="
        ward-mini-value
        <?=
        $available <= 1
            ?
            'text-critical'
            :
            (
                $available <= 2
                    ?
                    'text-warning-custom'
                    :
                    'text-available'
            )
        ?>
    "
>

<?= $available ?>

</div>


</div>


</div>



<div class="occupancy-progress">


<div
    class="
        occupancy-bar
        <?= $progressClass ?>
    "
    style="
        width:
        <?= $percentage ?>%;
    "
></div>


</div>



<div class="ward-footer">


<span class="occupancy-text">

<?= $occupied ?>

occupied

&nbsp;•&nbsp;

<?= $percentage ?>%

</span>


<span
    class="
        capacity-alert
        <?= $alertClass ?>
    "
>

<?= $alertText ?>

</span>


</div>



<?php if (
    $available <= 2
    &&
    $total > 0
): ?>


<button
    type="button"
    class="btn btn-outline-primary btn-sm w-100 mt-3"
    onclick='openAddBedModal(
        <?= json_encode(
            (string)$ward[
                'WARD_ID'
            ]
        ) ?>,
        <?= json_encode(
            $ward[
                'WARD_NAME'
            ]
        ) ?>
    )'
>

<i class="bi bi-plus-lg me-1"></i>

Add Bed

</button>


<?php endif; ?>


</div>


<?php endforeach; ?>


</div>



<!-- =====================================================
     WARD FILTER
===================================================== -->

<div class="filter-box">


<div class="filter-title">

Filter Ward

</div>


<form method="GET">


<div class="row g-2">


<div class="col-lg-4">


<select
    name="ward"
    class="form-select"
    onchange="this.form.submit()"
>


<option value="All">

All Wards

</option>


<?php foreach (
    $wards
    as
    $ward
): ?>


<option
    value="<?= h(
        $ward[
            'WARD_ID'
        ]
    ) ?>"
    <?=
    (
        (string)$ward_id
        ===
        (string)$ward[
            'WARD_ID'
        ]
    )
        ?
        'selected'
        :
        ''
    ?>
>

<?= h(
    $ward[
        'WARD_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


</div>


</div>


</form>


</div>



<!-- =====================================================
     BED LAYOUT
===================================================== -->

<div class="content-card">


<div class="card-heading">


<div class="card-heading-left">


<div class="card-icon icon-layout">

<i class="bi bi-grid-3x3-gap"></i>

</div>


<div>

<h5 class="card-title-clean">

<?= h(
    $selectedWardName
) ?>

</h5>


<div class="card-subtitle">

<?= count(
    $result
) ?>

bed(s) shown

</div>


</div>


</div>


</div>



<div class="bed-grid">


<?php if (
    empty(
        $result
    )
): ?>


<div class="empty-state">


<i class="bi bi-hospital"></i>


No beds found for this ward.


</div>


<?php endif; ?>



<?php foreach (
    $result
    as
    $row
): ?>


<?php

$isAvailable =
    strtolower(
        trim(
            $row[
                'STATUS'
            ]
        )
    )
    ===
    'available';


$cardClass =
    $isAvailable
        ?
        'available-card'
        :
        'occupied-card';

?>


<div
    class="
        bed-card
        <?= $cardClass ?>
    "
>


<!-- =================================================
     HEADER
================================================= -->

<div class="bed-card-header">


<div class="d-flex align-items-center gap-2">


<div class="bed-icon">

<i class="bi bi-hospital"></i>

</div>


<div>


<div class="bed-number">

<?= h(
    $row[
        'BED_NUMBER'
    ]
) ?>

</div>


<div class="bed-ward">

<?= h(
    $row[
        'WARD_NAME'
    ]
) ?>

</div>


</div>


</div>



<?php if ($isAvailable): ?>


<span class="status-pill status-available">

<i class="bi bi-circle-fill" style="font-size:6px;"></i>

Available

</span>


<?php else: ?>


<span class="status-pill status-occupied">

<i class="bi bi-circle-fill" style="font-size:6px;"></i>

Occupied

</span>


<?php endif; ?>


</div>



<!-- =================================================
     AVAILABLE
================================================= -->

<?php if ($isAvailable): ?>


<div class="empty-bed">


<i class="bi bi-person-x"></i>


No patient assigned


</div>


<!-- =================================================
     OCCUPIED
================================================= -->

<?php else: ?>


<div class="patient-box">


<div class="patient-name">

<?= h(
    $row[
        'NAME'
    ]
    ??
    'Unknown Patient'
) ?>

</div>


<div class="patient-meta">


<span>

<i class="bi bi-calendar3"></i>

Age:
<?= h(
    $row[
        'AGE'
    ]
    ??
    'N/A'
) ?>

</span>


<span>

<i class="bi bi-person"></i>

<?= h(
    $row[
        'GENDER'
    ]
    ??
    'N/A'
) ?>

</span>


</div>


</div>



<!-- =================================================
     TRANSFER
================================================= -->

<div class="transfer-box">


<form method="POST">


<input
    type="hidden"
    name="admission_id"
    value="<?= h(
        $row[
            'ADMISSION_ID'
        ]
        ?? ''
    ) ?>"
>


<input
    type="hidden"
    name="current_ward"
    value="<?= h(
        $ward_id
    ) ?>"
>


<label class="transfer-label">

Transfer Patient

</label>


<div class="transfer-row">


<select
    name="new_bed"
    class="form-select"
    required
>


<option value="">

Select available bed

</option>


<?php foreach (
    $availableBeds
    as
    $availableBed
): ?>


<option
    value="<?= h(
        $availableBed[
            'BED_ID'
        ]
    ) ?>"
>

<?= h(
    $availableBed[
        'BED_NUMBER'
    ]
) ?>

•
<?= h(
    $availableBed[
        'WARD_NAME'
    ]
) ?>

</option>


<?php endforeach; ?>


</select>


<button
    type="submit"
    name="transfer"
    class="btn-transfer transferBtn"
    data-patient="<?= h(
        $row[
            'NAME'
        ]
        ?? ''
    ) ?>"
>

<i class="bi bi-arrow-left-right"></i>

</button>


</div>


</form>


</div>


<?php endif; ?>



<!-- =================================================
     FOOTER
================================================= -->

<div class="bed-footer">


<div class="history-text">

Admission History:

<strong>

<?= (int)(
    $row[
        'TOTAL_HISTORY'
    ]
    ?? 0
) ?>

</strong>

</div>



<form
    method="POST"
    class="delete-bed-form"
>


<input
    type="hidden"
    name="delete_bed"
    value="<?= h(
        $row[
            'BED_ID'
        ]
    ) ?>"
>


<input
    type="hidden"
    name="current_ward"
    value="<?= h(
        $ward_id
    ) ?>"
>


<button
    type="submit"
    class="btn-delete-bed"
    <?=
    !$isAvailable
        ?
        'disabled'
        :
        ''
    ?>
    title="<?=
    !$isAvailable
        ?
        'Occupied beds cannot be deleted'
        :
        'Delete bed'
    ?>"
>

<i class="bi bi-trash"></i>

<?=
$isAvailable
    ?
    'Delete'
    :
    'Occupied'
?>

</button>


</form>


</div>


</div>


<?php endforeach; ?>


</div>


</div>


</div>


</div>



<!-- =====================================================
     ADD BED MODAL
===================================================== -->

<div
    class="modal fade"
    id="addBedModal"
    tabindex="-1"
    aria-hidden="true"
>


<div
    class="
        modal-dialog
        modal-dialog-centered
    "
>


<div class="modal-content">


<div class="modal-header">


<div>


<h5 class="modal-title">

Add New Bed

</h5>


<div class="card-subtitle">

Add additional bed capacity to this ward.

</div>


</div>


<button
    type="button"
    class="btn-close"
    data-bs-dismiss="modal"
></button>


</div>


<div class="modal-body">


<form method="POST">


<input
    type="hidden"
    name="current_ward"
    value="<?= h(
        $ward_id
    ) ?>"
>


<input
    type="hidden"
    name="ward_id"
    id="modalWardId"
>


<div class="mb-3">


<label class="form-label">

Ward

</label>


<input
    type="text"
    id="modalWardName"
    class="form-control"
    readonly
>


</div>



<div class="mb-3">


<label class="form-label">

Bed Number

</label>


<input
    type="text"
    name="bed_number"
    class="form-control"
    placeholder="Example: B105"
    required
>


</div>



<button
    type="submit"
    name="add_bed"
    class="btn btn-add btn-main w-100"
>

<i class="bi bi-plus-circle me-1"></i>

Add Bed

</button>


</form>


</div>


</div>


</div>


</div>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   ADD BED MODAL
========================================================= */

function openAddBedModal(
    wardId,
    wardName
)
{

    document
        .getElementById(
            'modalWardId'
        )
        .value =
        wardId;


    document
        .getElementById(
            'modalWardName'
        )
        .value =
        wardName;


    const modal =
        new bootstrap.Modal(
            document.getElementById(
                'addBedModal'
            )
        );


    modal.show();

}


/* =========================================================
   READY
========================================================= */

document.addEventListener(
    'DOMContentLoaded',
    function()
    {

        /* =================================================
           DELETE CONFIRM
        ================================================= */

        document
            .querySelectorAll(
                '.delete-bed-form'
            )
            .forEach(
                function(form)
                {

                    form.addEventListener(
                        'submit',
                        function(event)
                        {

                            event.preventDefault();


                            Swal.fire({

                                icon:
                                    'warning',

                                title:
                                    'Delete Bed?',

                                text:
                                    'This bed will be permanently removed.',

                                showCancelButton:
                                    true,

                                confirmButtonText:
                                    'Yes, Delete',

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

                                        form.submit();

                                    }

                                }
                            );

                        }
                    );

                }
            );


        /* =================================================
           TRANSFER CONFIRM
        ================================================= */

        document
            .querySelectorAll(
                '.transferBtn'
            )
            .forEach(
                function(button)
                {

                    button.addEventListener(
                        'click',
                        function(event)
                        {

                            const form =
                                this.closest(
                                    'form'
                                );


                            const select =
                                form.querySelector(
                                    'select[name="new_bed"]'
                                );


                            if (
                                !select.value
                            ) {

                                return;
                            }


                            event.preventDefault();


                            const patientName =
                                this.dataset.patient
                                ||
                                'this patient';


                            Swal.fire({

                                icon:
                                    'question',

                                title:
                                    'Transfer Patient?',

                                html:
                                    'Move <strong>'
                                    +
                                    escapeHtml(
                                        patientName
                                    )
                                    +
                                    '</strong> to the selected bed?',

                                showCancelButton:
                                    true,

                                confirmButtonText:
                                    'Yes, Transfer',

                                cancelButtonText:
                                    'Cancel',

                                confirmButtonColor:
                                    '#2563eb',

                                cancelButtonColor:
                                    '#64748b'

                            })
                            .then(
                                function(result)
                                {

                                    if (
                                        result.isConfirmed
                                    ) {

                                        /*
                                         Add hidden transfer field because
                                         requestSubmit/button.click is avoided
                                         after Swal confirmation.
                                        */

                                        const input =
                                            document.createElement(
                                                'input'
                                            );


                                        input.type =
                                            'hidden';


                                        input.name =
                                            'transfer';


                                        input.value =
                                            '1';


                                        form.appendChild(
                                            input
                                        );


                                        form.submit();

                                    }

                                }
                            );

                        }
                    );

                }
            );


        /* =================================================
           REDIRECT MESSAGE
        ================================================= */

        <?php if (
            $message !== ''
        ): ?>


        Swal.fire({

            icon:
                <?= json_encode(
                    in_array(
                        $messageType,
                        [
                            'success',
                            'warning',
                            'error',
                            'info'
                        ],
                        true
                    )
                        ?
                        $messageType
                        :
                        'info'
                ) ?>,

            title:
                <?= json_encode(
                    $messageType ===
                    'success'
                        ?
                        'Success'
                        :
                        (
                            $messageType ===
                            'warning'
                                ?
                                'Warning'
                                :
                                'Error'
                        )
                ) ?>,

            text:
                <?= json_encode(
                    $message
                ) ?>,

            confirmButtonColor:
                '#2563eb'

        });


        <?php endif; ?>

    }
);


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(text)
{

    const div =
        document.createElement(
            'div'
        );


    div.textContent =
        text
        ??
        '';


    return div.innerHTML;

}

</script>


</body>

</html>