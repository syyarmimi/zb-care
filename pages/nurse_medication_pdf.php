<?php

session_start();

include("../config/config.php");
require('../fpdf/fpdf.php');


/* =========================================================
   ROLE
========================================================= */

if (
    !isset($_SESSION['role']) ||
    $_SESSION['role'] != 'nurse'
) {
    die("Access Denied");
}


/* =========================================================
   DATE
========================================================= */

$reportDate = $_GET['date'] ?? date('Y-m-d');


$dateObj = DateTime::createFromFormat(
    'Y-m-d',
    $reportDate
);


if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $reportDate
) {
    die("Invalid Date");
}


/* =========================================================
   FETCH MEDICATION ADMINISTRATION
========================================================= */

/*
    IMPORTANT:

    Correct MEDICATION_ADMIN columns:

    MEDDELIVERY_ID
    DELIVERY_TIME
    STATUS
    ACCOUNT_ID
    MEDORDER_ID
*/

$sql = "

SELECT

    ma.MEDDELIVERY_ID,

    ma.DELIVERY_TIME,

    ma.STATUS,

    p.NAME AS PATIENT_NAME,

    a.ADMISSION_ID,

    w.WARD_NAME,

    b.BED_NUMBER,

    m.MEDICATION_NAME,

    mo.DOSAGE,

    mo.FREQUENCY,

    hs.USERNAME AS GIVEN_BY

FROM SYARMIMI.MEDICATION_ADMIN ma


JOIN SYARMIMI.MEDICATION_ORDER mo

    ON ma.MEDORDER_ID =
       mo.MEDORDER_ID


JOIN SYARMIMI.ADMISSION a

    ON mo.ADMISSION_ID =
       a.ADMISSION_ID


JOIN SYARMIMI.PATIENT p

    ON a.PATIENT_ID =
       p.PATIENT_ID


JOIN SYARMIMI.MEDICATION m

    ON mo.MEDICATION_ID =
       m.MEDICATION_ID


LEFT JOIN SYARMIMI.BED b

    ON a.BED_ID =
       b.BED_ID


LEFT JOIN SYARMIMI.WARD w

    ON b.WARD_ID =
       w.WARD_ID


LEFT JOIN SYARMIMI.HOSPITAL_STAFF hs

    ON ma.ACCOUNT_ID =
       hs.ACCOUNT_ID


WHERE TRUNC(ma.DELIVERY_TIME)
=
TO_DATE(:report_date,'YYYY-MM-DD')


ORDER BY
    ma.DELIVERY_TIME ASC

";


$stmt = $conn->prepare($sql);


$stmt->execute([
    ':report_date' => $reportDate
]);


$rows =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   SUMMARY
========================================================= */

$totalMedication =
    count($rows);


$patients = [];


$wards = [];


foreach($rows as $row){

    if(!empty($row['PATIENT_NAME'])){

        $patients[
            $row['PATIENT_NAME']
        ] = true;

    }


    if(!empty($row['WARD_NAME'])){

        $wards[
            $row['WARD_NAME']
        ] = true;

    }

}


$totalPatients =
    count($patients);


$totalWards =
    count($wards);


/* =========================================================
   PDF
========================================================= */

$pdf = new FPDF(
    'P',
    'mm',
    'A4'
);


$pdf->SetMargins(
    15,
    15,
    15
);


$pdf->SetAutoPageBreak(
    true,
    18
);


$pdf->AddPage();


/* =========================================================
   HEADER
========================================================= */

$pdf->SetFillColor(
    146,
    64,
    14
);


$pdf->Rect(
    0,
    0,
    210,
    42,
    'F'
);


$pdf->SetTextColor(
    255,
    255,
    255
);


$pdf->SetFont(
    'Helvetica',
    'B',
    22
);


$pdf->Cell(
    0,
    10,
    'ZB-CARE HOSPITAL',
    0,
    1,
    'C'
);


$pdf->SetFont(
    'Helvetica',
    '',
    12
);


$pdf->Cell(
    0,
    7,
    'Medication Administration Report',
    0,
    1,
    'C'
);


$pdf->SetFont(
    'Helvetica',
    '',
    10
);


$pdf->Cell(
    0,
    7,
    'Report Date: ' .
    $dateObj->format('d M Y'),
    0,
    1,
    'C'
);


$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->Ln(13);


/* =========================================================
   REPORT INFORMATION
========================================================= */

$pdf->SetFont(
    'Helvetica',
    'B',
    14
);


$pdf->SetTextColor(
    146,
    64,
    14
);


$pdf->Cell(
    0,
    9,
    'I. Report Information',
    0,
    1
);


$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->SetFont(
    'Helvetica',
    '',
    10
);


$pdf->SetFillColor(
    248,
    250,
    252
);


$pdf->Cell(
    60,
    9,
    'Report Date',
    1,
    0,
    'L',
    true
);


$pdf->Cell(
    120,
    9,
    $dateObj->format('d M Y'),
    1,
    1
);


$pdf->Cell(
    60,
    9,
    'Medication Given',
    1,
    0,
    'L',
    true
);


$pdf->Cell(
    120,
    9,
    $totalMedication . ' medication(s)',
    1,
    1
);


$pdf->Cell(
    60,
    9,
    'Patients Served',
    1,
    0,
    'L',
    true
);


$pdf->Cell(
    120,
    9,
    $totalPatients . ' patient(s)',
    1,
    1
);


$pdf->Cell(
    60,
    9,
    'Wards Involved',
    1,
    0,
    'L',
    true
);


$pdf->Cell(
    120,
    9,
    $totalWards . ' ward(s)',
    1,
    1
);


$pdf->Ln(10);


/* =========================================================
   SUMMARY
========================================================= */

$pdf->SetFont(
    'Helvetica',
    'B',
    14
);


$pdf->SetTextColor(
    146,
    64,
    14
);


$pdf->Cell(
    0,
    9,
    'II. Administration Summary',
    0,
    1
);


$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->SetFont(
    'Helvetica',
    '',
    10
);


if($totalMedication > 0){

    $summary =

        'A total of ' .
        $totalMedication .
        ' medication administration record(s) ' .
        'were recorded on ' .
        $dateObj->format('d M Y') .
        '. The medication was administered to ' .
        $totalPatients .
        ' patient(s) across ' .
        $totalWards .
        ' ward(s).';


}
else{

    $summary =

        'No medication administration records ' .
        'were recorded on ' .
        $dateObj->format('d M Y') .
        '.';

}


$pdf->MultiCell(
    0,
    7,
    $summary
);


$pdf->Ln(10);


/* =========================================================
   MEDICATION TABLE
========================================================= */

$pdf->SetFont(
    'Helvetica',
    'B',
    14
);


$pdf->SetTextColor(
    146,
    64,
    14
);


$pdf->Cell(
    0,
    9,
    'III. Medication Administration Details',
    0,
    1
);


$pdf->SetTextColor(
    0,
    0,
    0
);


/*
    TABLE WIDTH = 180mm

    No       10
    Patient  38
    Ward     22
    Bed      15
    Medicine 35
    Dosage   22
    Time     20
    Given By 18

    Total = 180
*/


$pdf->SetFillColor(
    55,
    65,
    81
);


$pdf->SetTextColor(
    255,
    255,
    255
);


$pdf->SetFont(
    'Helvetica',
    'B',
    8
);


$pdf->Cell(
    10,
    9,
    'No.',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    38,
    9,
    'Patient',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    22,
    9,
    'Ward',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    15,
    9,
    'Bed',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    35,
    9,
    'Medication',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    22,
    9,
    'Dosage',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    20,
    9,
    'Time',
    1,
    0,
    'C',
    true
);


$pdf->Cell(
    18,
    9,
    'Given By',
    1,
    1,
    'C',
    true
);


/* =========================================================
   TABLE DATA
========================================================= */

$pdf->SetTextColor(
    0,
    0,
    0
);


$pdf->SetFont(
    'Helvetica',
    '',
    7.5
);


$no = 1;


foreach($rows as $row){

    /*
       Format time
    */

    $deliveryTime = '-';


    if(!empty($row['DELIVERY_TIME'])){

        $timestamp =
            strtotime(
                $row['DELIVERY_TIME']
            );


        if($timestamp !== false){

            $deliveryTime =
                date(
                    'h:i A',
                    $timestamp
                );

        }

    }


    /*
       Patient
    */

    $patientName =
        $row['PATIENT_NAME'] ?? '-';


    $patientName =
        substr(
            $patientName,
            0,
            23
        );


    /*
       Ward
    */

    $wardName =
        $row['WARD_NAME'] ?? '-';


    $wardName =
        substr(
            $wardName,
            0,
            13
        );


    /*
       Medication
    */

    $medication =
        $row['MEDICATION_NAME'] ?? '-';


    $medication =
        substr(
            $medication,
            0,
            20
        );


    /*
       Dosage
    */

    $dosage =
        $row['DOSAGE'] ?? '-';


    $dosage =
        substr(
            $dosage,
            0,
            13
        );


    /*
       Given By
    */

    $givenBy =
        $row['GIVEN_BY'] ?? '-';


    $givenBy =
        substr(
            $givenBy,
            0,
            11
        );


    $pdf->Cell(
        10,
        8,
        $no,
        1,
        0,
        'C'
    );


    $pdf->Cell(
        38,
        8,
        $patientName,
        1
    );


    $pdf->Cell(
        22,
        8,
        $wardName,
        1
    );


    $pdf->Cell(
        15,
        8,
        $row['BED_NUMBER'] ?? '-',
        1,
        0,
        'C'
    );


    $pdf->Cell(
        35,
        8,
        $medication,
        1
    );


    $pdf->Cell(
        22,
        8,
        $dosage,
        1
    );


    $pdf->Cell(
        20,
        8,
        $deliveryTime,
        1,
        0,
        'C'
    );


    $pdf->Cell(
        18,
        8,
        $givenBy,
        1,
        1
    );


    $no++;

}


/* =========================================================
   NO RECORD
========================================================= */

if($totalMedication == 0){

    $pdf->SetFont(
        'Helvetica',
        'I',
        9
    );


    $pdf->Cell(
        180,
        12,
        'No medication administration records found for this date.',
        1,
        1,
        'C'
    );

}


/* =========================================================
   FOOTER
========================================================= */

$pdf->Ln(12);


$pdf->SetDrawColor(
    200,
    200,
    200
);


$pdf->Line(
    15,
    $pdf->GetY(),
    195,
    $pdf->GetY()
);


$pdf->Ln(4);


$pdf->SetFont(
    'Helvetica',
    'I',
    8
);


$pdf->SetTextColor(
    100,
    100,
    100
);


$pdf->Cell(
    0,
    6,
    'Prepared By: ZB-CARE Hospital Management System',
    0,
    1,
    'R'
);


$pdf->Cell(
    0,
    6,
    'Report Date: ' .
    $dateObj->format('d M Y'),
    0,
    1,
    'R'
);


$pdf->Cell(
    0,
    6,
    'Generated: ' .
    date('d M Y h:i A'),
    0,
    1,
    'R'
);


$pdf->SetTextColor(
    0,
    0,
    0
);


/* =========================================================
   OUTPUT
========================================================= */

$pdf->Output(
    'I',
    'Nurse_Medication_Report_' .
    $reportDate .
    '.pdf'
);

?>