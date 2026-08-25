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

$dateObj = DateTime::createFromFormat('Y-m-d', $reportDate);

if (
    !$dateObj ||
    $dateObj->format('Y-m-d') !== $reportDate
) {
    die("Invalid Date");
}


/* =========================================================
   FETCH DATA
   IMPORTANT:
   Only use columns that exist in your database.
========================================================= */

$sql = "

SELECT

    ma.ADMIN_TIME,

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
    ON ma.MEDORDER_ID = mo.MEDORDER_ID

JOIN SYARMIMI.ADMISSION a
    ON mo.ADMISSION_ID = a.ADMISSION_ID

JOIN SYARMIMI.PATIENT p
    ON a.PATIENT_ID = p.PATIENT_ID

JOIN SYARMIMI.MEDICATION m
    ON mo.MEDICATION_ID = m.MEDICATION_ID

LEFT JOIN SYARMIMI.BED b
    ON a.BED_ID = b.BED_ID

LEFT JOIN SYARMIMI.WARD w
    ON b.WARD_ID = w.WARD_ID

LEFT JOIN SYARMIMI.HOSPITAL_STAFF hs
    ON ma.ACCOUNT_ID = hs.ACCOUNT_ID

WHERE TRUNC(ma.ADMIN_TIME)
      =
      TO_DATE(:report_date,'YYYY-MM-DD')

ORDER BY ma.ADMIN_TIME ASC

";


$stmt = $conn->prepare($sql);

$stmt->execute([
    ':report_date' => $reportDate
]);

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PDF
========================================================= */

$pdf = new FPDF(
    'P',
    'mm',
    'A4'
);

$pdf->AddPage();

$pdf->SetAutoPageBreak(
    true,
    18
);


/* =========================================================
   PAGE SETTINGS
========================================================= */

$pageWidth = 210;

$margin = 15;

$contentWidth = 180;


/* =========================================================
   HEADER
========================================================= */

/*
    Main header
*/

$pdf->SetFillColor(
    146,
    64,
    14
);

$pdf->Rect(
    0,
    0,
    $pageWidth,
    42,
    'F'
);


/*
    Hospital name
*/

$pdf->SetTextColor(
    255,
    255,
    255
);

$pdf->SetFont(
    'Helvetica',
    'B',
    21
);

$pdf->SetXY(
    $margin,
    9
);

$pdf->Cell(
    $contentWidth,
    9,
    'ZB-CARE HOSPITAL',
    0,
    1,
    'C'
);


/*
    Report title
*/

$pdf->SetFont(
    'Helvetica',
    '',
    11
);

$pdf->SetXY(
    $margin,
    19
);

$pdf->Cell(
    $contentWidth,
    7,
    'Medication Administration Report',
    0,
    1,
    'C'
);


/*
    Date
*/

$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->SetXY(
    $margin,
    29
);

$pdf->Cell(
    $contentWidth,
    6,
    'Report Date: ' .
    $dateObj->format('d M Y'),
    0,
    1,
    'C'
);


/*
    Reset text
*/

$pdf->SetTextColor(
    0,
    0,
    0
);

$pdf->SetY(50);


/* =========================================================
   REPORT INFORMATION BOX
========================================================= */

$pdf->SetFillColor(
    248,
    250,
    252
);

$pdf->SetDrawColor(
    220,
    224,
    230
);

$pdf->Rect(
    $margin,
    $pdf->GetY(),
    $contentWidth,
    25,
    'FD'
);


/*
    Left label
*/

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->SetTextColor(
    80,
    80,
    80
);

$pdf->SetXY(
    $margin + 6,
    $pdf->GetY() + 5
);

$pdf->Cell(
    45,
    6,
    'REPORT TYPE',
    0,
    0
);


/*
    Left value
*/

$pdf->SetFont(
    'Helvetica',
    '',
    10
);

$pdf->SetTextColor(
    30,
    30,
    30
);

$pdf->SetXY(
    $margin + 6,
    $pdf->GetY() + 11
);

$pdf->Cell(
    65,
    6,
    'Medication Administration',
    0,
    0
);


/*
    Right label
*/

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->SetTextColor(
    80,
    80,
    80
);

$pdf->SetXY(
    $margin + 100,
    $pdf->GetY() - 6
);

$pdf->Cell(
    55,
    6,
    'TOTAL RECORDS',
    0,
    1
);


/*
    Right value
*/

$pdf->SetFont(
    'Helvetica',
    'B',
    16
);

$pdf->SetTextColor(
    146,
    64,
    14
);

$pdf->SetXY(
    $margin + 100,
    $pdf->GetY()
);

$pdf->Cell(
    55,
    8,
    count($rows),
    0,
    1
);


/*
    Reset
*/

$pdf->SetTextColor(
    0,
    0,
    0
);

$pdf->SetY(
    $pdf->GetY() + 10
);


/* =========================================================
   SECTION TITLE
========================================================= */

$pdf->SetFont(
    'Helvetica',
    'B',
    14
);

$pdf->SetTextColor(
    45,
    45,
    45
);

$pdf->Cell(
    0,
    8,
    'Medication Administration Details',
    0,
    1
);


/*
    Small line under title
*/

$pdf->SetDrawColor(
    146,
    64,
    14
);

$pdf->SetLineWidth(
    0.8
);

$pdf->Line(
    $margin,
    $pdf->GetY(),
    $margin + 45,
    $pdf->GetY()
);

$pdf->Ln(5);


/* =========================================================
   TABLE
========================================================= */

/*
    Table width = 180 mm

    Patient       36
    Ward          24
    Bed           15
    Medication    35
    Dosage        22
    Frequency     28
    Time          20

    Total = 180
*/

$pdf->SetFont(
    'Helvetica',
    'B',
    8
);

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

$pdf->SetDrawColor(
    55,
    65,
    81
);


/* HEADER */

$pdf->Cell(
    36,
    9,
    'Patient',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    24,
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
    28,
    9,
    'Frequency',
    1,
    0,
    'C',
    true
);

$pdf->Cell(
    20,
    9,
    'Given',
    1,
    1,
    'C',
    true
);


/* =========================================================
   TABLE DATA
========================================================= */

$pdf->SetTextColor(
    30,
    30,
    30
);

$pdf->SetFont(
    'Helvetica',
    '',
    7.5
);

$pdf->SetDrawColor(
    215,
    219,
    225
);


if (count($rows) > 0) {

    foreach ($rows as $index => $row) {

        /*
            Alternate row background
        */

        if ($index % 2 == 0) {

            $pdf->SetFillColor(
                248,
                250,
                252
            );

        } else {

            $pdf->SetFillColor(
                255,
                255,
                255
            );
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
                22
            );


        $pdf->Cell(
            36,
            9,
            $patientName,
            1,
            0,
            'L',
            true
        );


        /*
            Ward
        */

        $ward =
            $row['WARD_NAME'] ?? '-';

        $ward =
            substr(
                $ward,
                0,
                14
            );


        $pdf->Cell(
            24,
            9,
            $ward,
            1,
            0,
            'L',
            true
        );


        /*
            Bed
        */

        $bed =
            $row['BED_NUMBER'] ?? '-';


        $pdf->Cell(
            15,
            9,
            $bed,
            1,
            0,
            'C',
            true
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
                21
            );


        $pdf->Cell(
            35,
            9,
            $medication,
            1,
            0,
            'L',
            true
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


        $pdf->Cell(
            22,
            9,
            $dosage,
            1,
            0,
            'C',
            true
        );


        /*
            Frequency
        */

        $frequency =
            $row['FREQUENCY'] ?? '-';

        $frequency =
            substr(
                $frequency,
                0,
                17
            );


        $pdf->Cell(
            28,
            9,
            $frequency,
            1,
            0,
            'C',
            true
        );


        /*
            Administration time

            IMPORTANT:
            This uses ADMIN_TIME only.
        */

        $givenTime = '-';

        if (!empty($row['ADMIN_TIME'])) {

            $timestamp =
                strtotime(
                    $row['ADMIN_TIME']
                );

            if ($timestamp !== false) {

                $givenTime =
                    date(
                        'h:i A',
                        $timestamp
                    );
            }
        }


        $pdf->Cell(
            20,
            9,
            $givenTime,
            1,
            1,
            'C',
            true
        );
    }

} else {

    /*
        No records
    */

    $pdf->SetFillColor(
        248,
        250,
        252
    );

    $pdf->SetFont(
        'Helvetica',
        'I',
        9
    );

    $pdf->Cell(
        180,
        15,
        'No medication was recorded as given on this date.',
        1,
        1,
        'C',
        true
    );
}


/* =========================================================
   SUMMARY FOOTER BOX
========================================================= */

$pdf->Ln(10);


/*
    Summary title
*/

$pdf->SetFont(
    'Helvetica',
    'B',
    12
);

$pdf->SetTextColor(
    45,
    45,
    45
);

$pdf->Cell(
    0,
    8,
    'Report Summary',
    0,
    1
);


/*
    Summary box
*/

$pdf->SetFillColor(
    250,
    247,
    244
);

$pdf->SetDrawColor(
    225,
    215,
    205
);

$pdf->Rect(
    $margin,
    $pdf->GetY(),
    $contentWidth,
    25,
    'FD'
);


/*
    Summary content
*/

$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->SetTextColor(
    55,
    55,
    55
);

$pdf->SetXY(
    $margin + 6,
    $pdf->GetY() + 5
);

$pdf->Cell(
    50,
    6,
    'Report Date:',
    0,
    0
);

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->Cell(
    50,
    6,
    $dateObj->format('d M Y'),
    0,
    0
);


$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->Cell(
    35,
    6,
    'Total Medication:',
    0,
    0
);

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->Cell(
    30,
    6,
    count($rows),
    0,
    1
);


/*
    Second summary line
*/

$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->SetXY(
    $margin + 6,
    $pdf->GetY() + 3
);

$pdf->Cell(
    50,
    6,
    'Prepared For:',
    0,
    0
);

$pdf->SetFont(
    'Helvetica',
    'B',
    9
);

$pdf->Cell(
    50,
    6,
    'Nursing Department',
    0,
    0
);


/*
    Reset
*/

$pdf->SetTextColor(
    0,
    0,
    0
);


/* =========================================================
   FOOTER
========================================================= */

$pdf->SetY(
    -25
);

$pdf->SetDrawColor(
    220,
    220,
    220
);

$pdf->Line(
    $margin,
    $pdf->GetY(),
    $margin + $contentWidth,
    $pdf->GetY()
);

$pdf->Ln(3);

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
    5,
    'Generated by ZB-CARE Hospital Management System',
    0,
    1,
    'L'
);

$pdf->Cell(
    0,
    5,
    'Generated: ' .
    date('d M Y h:i A'),
    0,
    1,
    'L'
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