<?php
session_start();

include("../config/config.php");
require('../fpdf/fpdf.php');


/* =========================================================
   VALIDATE ADMISSION ID
========================================================= */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid Admission ID");
}

$admission_id = intval($_GET['id']);


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function formatDate($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp !== false) {
        return date('d M Y', $timestamp);
    }

    return $date;
}


function formatDateTime($date)
{
    if (empty($date)) {
        return '-';
    }

    $timestamp = strtotime($date);

    if ($timestamp !== false) {
        return date('d M Y, h:i A', $timestamp);
    }

    return $date;
}


/* =========================================================
   CUSTOM PDF CLASS
========================================================= */

class ZBCarePDF extends FPDF
{
    function Header()
    {
        /* TOP HEADER */

        $this->SetFillColor(31, 78, 121);
        $this->Rect(0, 0, 210, 35, 'F');

        /* Hospital Name */

        $this->SetTextColor(255, 255, 255);

        $this->SetFont('Helvetica', 'B', 20);

        $this->SetXY(15, 7);

        $this->Cell(
            0,
            8,
            'ZB-CARE HOSPITAL',
            0,
            1,
            'L'
        );

        /* Subtitle */

        $this->SetFont('Helvetica', '', 10);

        $this->SetXY(15, 17);

        $this->Cell(
            0,
            6,
            'Specialist Hospital Management System',
            0,
            1,
            'L'
        );

        /* Report title */

        $this->SetFont('Helvetica', 'B', 11);

        $this->SetXY(15, 25);

        $this->Cell(
            0,
            5,
            'PATIENT MEDICAL SUMMARY REPORT',
            0,
            1,
            'L'
        );

        /* Reset */

        $this->SetTextColor(30, 30, 30);

        $this->SetY(45);
    }


    function Footer()
    {
        $this->SetY(-20);

        /* Footer line */

        $this->SetDrawColor(210, 210, 210);

        $this->Line(
            15,
            $this->GetY(),
            195,
            $this->GetY()
        );

        $this->Ln(4);

        $this->SetFont(
            'Helvetica',
            '',
            8
        );

        $this->SetTextColor(
            110,
            110,
            110
        );

        $this->Cell(
            0,
            5,
            'ZB-CARE Hospital Management System',
            0,
            0,
            'L'
        );

        $this->Cell(
            0,
            5,
            'Page ' . $this->PageNo(),
            0,
            1,
            'R'
        );
    }


    /* =====================================================
       SECTION TITLE
    ===================================================== */

    function SectionTitle($number, $title)
    {
        $this->Ln(3);

        $this->SetFillColor(
            31,
            78,
            121
        );

        $this->SetTextColor(
            255,
            255,
            255
        );

        $this->SetFont(
            'Helvetica',
            'B',
            11
        );

        $this->Cell(
            12,
            8,
            $number,
            0,
            0,
            'C',
            true
        );

        $this->Cell(
            0,
            8,
            $title,
            0,
            1,
            'L',
            true
        );

        $this->SetTextColor(
            30,
            30,
            30
        );

        $this->Ln(4);
    }


    /* =====================================================
       LABEL + VALUE
    ===================================================== */

    function InfoRow(
        $label1,
        $value1,
        $label2,
        $value2
    ) {

        $labelWidth = 35;
        $valueWidth = 55;

        $this->SetFont(
            'Helvetica',
            'B',
            9
        );

        $this->SetFillColor(
            242,
            245,
            248
        );

        $this->SetDrawColor(
            220,
            225,
            230
        );

        $this->Cell(
            $labelWidth,
            8,
            $label1,
            1,
            0,
            'L',
            true
        );

        $this->SetFont(
            'Helvetica',
            '',
            9
        );

        $this->Cell(
            $valueWidth,
            8,
            $value1,
            1,
            0,
            'L'
        );


        $this->SetFont(
            'Helvetica',
            'B',
            9
        );

        $this->SetFillColor(
            242,
            245,
            248
        );

        $this->Cell(
            $labelWidth,
            8,
            $label2,
            1,
            0,
            'L',
            true
        );


        $this->SetFont(
            'Helvetica',
            '',
            9
        );

        $this->Cell(
            $valueWidth,
            8,
            $value2,
            1,
            1,
            'L'
        );
    }


    /* =====================================================
       TABLE HEADER
    ===================================================== */

    function TableHeader($columns)
    {
        $this->SetFillColor(
            31,
            78,
            121
        );

        $this->SetTextColor(
            255,
            255,
            255
        );

        $this->SetFont(
            'Helvetica',
            'B',
            9
        );

        foreach ($columns as $column) {

            $this->Cell(
                $column['width'],
                8,
                $column['title'],
                1,
                0,
                $column['align'],
                true
            );
        }

        $this->Ln();

        $this->SetTextColor(
            30,
            30,
            30
        );

        $this->SetFont(
            'Helvetica',
            '',
            9
        );
    }
}


/* =========================================================
   PATIENT INFORMATION
========================================================= */

$stmt = $conn->prepare("

SELECT
    P.PATIENT_ID,
    P.NAME,
    P.AGE,
    P.GENDER,
    P.IC_NUMBER,
    P.PHONE,
    P.ADDRESS,

    A.ADMISSION_DATE,
    A.DISCHARGE_DATE,

    W.WARD_NAME,
    B.BED_NUMBER

FROM SYARMIMI.ADMISSION A

JOIN SYARMIMI.PATIENT P
    ON A.PATIENT_ID = P.PATIENT_ID

JOIN SYARMIMI.BED B
    ON A.BED_ID = B.BED_ID

JOIN SYARMIMI.WARD W
    ON B.WARD_ID = W.WARD_ID

WHERE A.ADMISSION_ID = :id

");

$stmt->execute([
    ':id' => $admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$patient) {
    die("Patient record not found.");
}


/* =========================================================
   CALCULATE LENGTH OF STAY
========================================================= */

$admissionTimestamp =
    strtotime($patient['ADMISSION_DATE']);

if (!empty($patient['DISCHARGE_DATE'])) {

    $dischargeTimestamp =
        strtotime($patient['DISCHARGE_DATE']);

} else {

    $dischargeTimestamp = time();
}


if ($admissionTimestamp !== false) {

    $totalDays =
        ceil(
            (
                $dischargeTimestamp -
                $admissionTimestamp
            )
            /
            (60 * 60 * 24)
        );

    if ($totalDays < 1) {
        $totalDays = 1;
    }

} else {

    $totalDays = '-';
}


/* =========================================================
   DIAGNOSIS
========================================================= */

$diagStmt = $conn->prepare("

SELECT
    DIAGNOSIS_DETAILS,
    DATE_RECORDED

FROM SYARMIMI.DIAGNOSIS

WHERE ADMISSION_ID = :id

ORDER BY DATE_RECORDED DESC

");

$diagStmt->execute([
    ':id' => $admission_id
]);

$diagnosisList =
    $diagStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   MEDICATION
========================================================= */

$medStmt = $conn->prepare("

SELECT
    M.MEDICATION_NAME,
    MO.DOSAGE,
    MO.FREQUENCY

FROM SYARMIMI.MEDICATION_ORDER MO

JOIN SYARMIMI.MEDICATION M
    ON MO.MEDICATION_ID = M.MEDICATION_ID

WHERE MO.ADMISSION_ID = :id

ORDER BY MO.MEDORDER_ID DESC

");

$medStmt->execute([
    ':id' => $admission_id
]);

$medicationList =
    $medStmt->fetchAll(PDO::FETCH_ASSOC);


/* =========================================================
   PREPARE SUMMARY
========================================================= */

$latestDiagnosis = 'No diagnosis recorded';

if (!empty($diagnosisList)) {

    $latestDiagnosis =
        $diagnosisList[0]['DIAGNOSIS_DETAILS'];
}


$medicationNames = [];

foreach ($medicationList as $med) {

    $medicationNames[] =
        $med['MEDICATION_NAME'];
}


if (!empty($medicationNames)) {

    $medicationSummary =
        implode(
            ', ',
            $medicationNames
        );

} else {

    $medicationSummary =
        'No medication prescribed';
}


/* =========================================================
   CREATE PDF
========================================================= */

$pdf = new ZBCarePDF(
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
    25
);

$pdf->AddPage();


/* =========================================================
   REPORT META
========================================================= */

$pdf->SetFont(
    'Helvetica',
    '',
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
    'Report ID: ZBC-' . $admission_id,
    0,
    0,
    'L'
);

$pdf->Cell(
    0,
    5,
    'Generated: ' . date('d M Y, h:i A'),
    0,
    1,
    'R'
);

$pdf->SetTextColor(
    30,
    30,
    30
);

$pdf->Ln(4);


/* =========================================================
   PATIENT INFORMATION
========================================================= */

$pdf->SectionTitle(
    '01',
    'PATIENT INFORMATION'
);


/* Patient name highlight */

$pdf->SetFillColor(
    235,
    243,
    250
);

$pdf->SetDrawColor(
    190,
    210,
    225
);

$pdf->SetFont(
    'Helvetica',
    'B',
    13
);

$pdf->Cell(
    0,
    10,
    strtoupper($patient['NAME']),
    1,
    1,
    'L',
    true
);

$pdf->Ln(3);


/* Information table */

$pdf->InfoRow(
    'Patient ID',
    $patient['PATIENT_ID'],
    'IC Number',
    $patient['IC_NUMBER']
);

$pdf->InfoRow(
    'Age',
    $patient['AGE'] . ' years',
    'Gender',
    $patient['GENDER']
);

$pdf->InfoRow(
    'Phone',
    $patient['PHONE'],
    'Ward',
    $patient['WARD_NAME']
);

$pdf->InfoRow(
    'Bed',
    $patient['BED_NUMBER'],
    'Length of Stay',
    $totalDays . ' day(s)'
);

$pdf->InfoRow(
    'Admission',
    formatDate($patient['ADMISSION_DATE']),
    'Discharge',
    empty($patient['DISCHARGE_DATE'])
        ? 'Still Admitted'
        : formatDate($patient['DISCHARGE_DATE'])
);


/* Address */

if (!empty($patient['ADDRESS'])) {

    $pdf->SetFont(
        'Helvetica',
        'B',
        9
    );

    $pdf->SetFillColor(
        242,
        245,
        248
    );

    $pdf->Cell(
        35,
        8,
        'Address',
        1,
        0,
        'L',
        true
    );

    $pdf->SetFont(
        'Helvetica',
        '',
        9
    );

    $pdf->MultiCell(
        145,
        8,
        $patient['ADDRESS'],
        1,
        'L'
    );
}


/* =========================================================
   HOSPITAL COURSE SUMMARY
========================================================= */

$pdf->SectionTitle(
    '02',
    'HOSPITAL COURSE SUMMARY'
);


/* Summary box */

$pdf->SetFillColor(
    248,
    250,
    252
);

$pdf->SetDrawColor(
    210,
    215,
    220
);

$pdf->SetFont(
    'Helvetica',
    '',
    9.5
);


$summaryText =
    'The patient was admitted on ' .
    formatDate($patient['ADMISSION_DATE']) .
    ' and was placed in the ' .
    $patient['WARD_NAME'] .
    ' ward, Bed ' .
    $patient['BED_NUMBER'] .
    '. ' .

    'The latest recorded diagnosis was: ' .
    $latestDiagnosis .
    '. ' .

    'During the hospital stay, the patient was prescribed ' .
    $medicationSummary .
    '. ' .

    'The total length of stay was ' .
    $totalDays .
    ' day(s).';


$pdf->MultiCell(
    0,
    6.5,
    $summaryText,
    1,
    'L',
    true
);

$pdf->Ln(5);


/* =========================================================
   DIAGNOSIS HISTORY
========================================================= */

$pdf->SectionTitle(
    '03',
    'DIAGNOSIS HISTORY'
);


if (empty($diagnosisList)) {

    $pdf->SetFont(
        'Helvetica',
        'I',
        9
    );

    $pdf->SetTextColor(
        100,
        100,
        100
    );

    $pdf->Cell(
        0,
        8,
        'No diagnosis records found.',
        0,
        1
    );

    $pdf->SetTextColor(
        30,
        30,
        30
    );

} else {

    $pdf->TableHeader([
        [
            'title' => 'Date',
            'width' => 42,
            'align' => 'C'
        ],
        [
            'title' => 'Diagnosis',
            'width' => 138,
            'align' => 'L'
        ]
    ]);


    foreach ($diagnosisList as $d) {

        $diagnosis =
            $d['DIAGNOSIS_DETAILS'];


        /* Calculate required height */

        $lineCount =
            max(
                1,
                ceil(
                    $pdf->GetStringWidth(
                        $diagnosis
                    ) / 125
                )
            );

        $rowHeight =
            max(
                8,
                min(
                    20,
                    $lineCount * 5
                )
            );


        /* Check page */

        if (
            $pdf->GetY() +
            $rowHeight >
            270
        ) {

            $pdf->AddPage();

            $pdf->SectionTitle(
                '03',
                'DIAGNOSIS HISTORY (CONTINUED)'
            );

            $pdf->TableHeader([
                [
                    'title' => 'Date',
                    'width' => 42,
                    'align' => 'C'
                ],
                [
                    'title' => 'Diagnosis',
                    'width' => 138,
                    'align' => 'L'
                ]
            ]);
        }


        $startY =
            $pdf->GetY();


        /* Date */

        $pdf->SetFont(
            'Helvetica',
            '',
            9
        );

        $pdf->Cell(
            42,
            $rowHeight,
            formatDate(
                $d['DATE_RECORDED']
            ),
            1,
            0,
            'C'
        );


        /* Diagnosis */

        $pdf->SetXY(
            57,
            $startY
        );

        $pdf->MultiCell(
            138,
            5,
            $diagnosis,
            1,
            'L'
        );


        /* Ensure row alignment */

        $currentY =
            $pdf->GetY();

        if ($currentY < $startY + $rowHeight) {

            $pdf->SetY(
                $startY + $rowHeight
            );
        }
    }
}


$pdf->Ln(6);


/* =========================================================
   MEDICATION HISTORY
========================================================= */

$pdf->SectionTitle(
    '04',
    'MEDICATION HISTORY'
);


if (empty($medicationList)) {

    $pdf->SetFont(
        'Helvetica',
        'I',
        9
    );

    $pdf->SetTextColor(
        100,
        100,
        100
    );

    $pdf->Cell(
        0,
        8,
        'No medication records found.',
        0,
        1
    );

    $pdf->SetTextColor(
        30,
        30,
        30
    );

} else {

    $pdf->TableHeader([
        [
            'title' => 'Medication',
            'width' => 70,
            'align' => 'L'
        ],
        [
            'title' => 'Dosage',
            'width' => 45,
            'align' => 'L'
        ],
        [
            'title' => 'Frequency',
            'width' => 65,
            'align' => 'L'
        ]
    ]);


    foreach ($medicationList as $med) {

        $medication =
            $med['MEDICATION_NAME'];

        $dosage =
            $med['DOSAGE'];

        $frequency =
            $med['FREQUENCY'];


        $pdf->SetFont(
            'Helvetica',
            '',
            9
        );


        /* Medication */

        $pdf->Cell(
            70,
            8,
            $medication,
            1,
            0,
            'L'
        );


        /* Dosage */

        $pdf->Cell(
            45,
            8,
            $dosage,
            1,
            0,
            'L'
        );


        /* Frequency */

        $pdf->Cell(
            65,
            8,
            $frequency,
            1,
            1,
            'L'
        );
    }
}


$pdf->Ln(8);


/* =========================================================
   ADMISSION STATUS
========================================================= */

$pdf->SectionTitle(
    '05',
    'ADMISSION STATUS'
);


if (!empty($patient['DISCHARGE_DATE'])) {

    $status =
        'DISCHARGED';

    $statusText =
        'The patient was discharged on ' .
        formatDate(
            $patient['DISCHARGE_DATE']
        ) .
        '.';

} else {

    $status =
        'CURRENTLY ADMITTED';

    $statusText =
        'The patient is currently admitted and remains under hospital care.';
}


/* Status box */

$pdf->SetFillColor(
    235,
    247,
    240
);

$pdf->SetDrawColor(
    180,
    220,
    195
);

$pdf->SetFont(
    'Helvetica',
    'B',
    11
);

$pdf->Cell(
    0,
    9,
    $status,
    1,
    1,
    'C',
    true
);


$pdf->SetFont(
    'Helvetica',
    '',
    9
);

$pdf->MultiCell(
    0,
    6,
    $statusText,
    1,
    'C',
    true
);

$pdf->Ln(10);


/* =========================================================
   REPORT SIGN-OFF
========================================================= */

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

$pdf->Ln(6);

$pdf->SetFont(
    'Helvetica',
    '',
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
    'This report is generated electronically by the ZB-CARE Hospital Management System.',
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    5,
    'Report ID: ZBC-' . $admission_id,
    0,
    1,
    'C'
);

$pdf->Cell(
    0,
    5,
    'Generated on ' . date('d M Y, h:i A'),
    0,
    1,
    'C'
);


/* =========================================================
   OUTPUT PDF
========================================================= */

$pdf->Output(
    'I',
    'ZBCARE_Patient_Report_' . $admission_id . '.pdf'
);

?>