<?php
session_start();

include("../config/config.php");
require('../fpdf/fpdf.php');

if(!isset($_GET['id'])){
    die("Invalid Admission ID");
}

$admission_id = $_GET['id'];

/* ===================================
   PATIENT INFORMATION
=================================== */

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
    ':id'=>$admission_id
]);

$patient = $stmt->fetch(PDO::FETCH_ASSOC);

$admissionDate = strtotime($patient['ADMISSION_DATE']);

if(!empty($patient['DISCHARGE_DATE']))
{
    $dischargeDate = strtotime($patient['DISCHARGE_DATE']);
}
else
{
    $dischargeDate = time();
}

$totalDays =
ceil(
($dischargeDate - $admissionDate)
/
(60*60*24)
);

/* ===================================
   DIAGNOSIS
=================================== */

$diagStmt = $conn->prepare("

SELECT
DIAGNOSIS_DETAILS,
DATE_RECORDED

FROM SYARMIMI.DIAGNOSIS

WHERE ADMISSION_ID = :id

ORDER BY DATE_RECORDED DESC

");

$diagStmt->execute([
    ':id'=>$admission_id
]);

$diagnosisList =
$diagStmt->fetchAll(PDO::FETCH_ASSOC);

/* ===================================
   MEDICATION
=================================== */

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
    ':id'=>$admission_id
]);

$medicationList =
$medStmt->fetchAll(PDO::FETCH_ASSOC);

/* ===================================
   PDF
=================================== */

$pdf = new FPDF();
$pdf->AddPage();

/* ===================================
   HEADER
=================================== */

$pdf->SetFillColor(25,118,210);

$pdf->Rect(0,0,210,40,'F');

$pdf->SetTextColor(255,255,255);

$pdf->SetFont('Helvetica','B',22);

$pdf->Cell(0,12,'ZB-CARE HOSPITAL',0,1,'C');

$pdf->SetFont('Helvetica','',12);

$pdf->Cell(0,0,'Patient Medical Summary Report',0,1,'C');

$pdf->Ln(10);

$pdf->SetTextColor(255,255,255);
$pdf->SetFont('Helvetica','',10);

$pdf->Cell(
0,
10,
'Report ID : ZBC-'.$admission_id,
0,
1,
'R'
);

$pdf->Ln(5);

$pdf->SetTextColor(0,0,0);

/* reset color */

$pdf->SetTextColor(0,0,0);

/* ===================================
   PATIENT INFORMATION
=================================== */

$pdf->SetFont('Helvetica','B',15);

$pdf->Cell(
0,
10,
'I. Patient Information',
0,
1
);

$pdf->Ln(2);

$pdf->SetFont('Helvetica','',10);

$pdf->Cell(60,10,'Patient Name',1);
$pdf->Cell(120,10,$patient['NAME'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Age',1);
$pdf->Cell(120,10,$patient['AGE'].' years old',1);
$pdf->Ln();

$pdf->Cell(60,10,'Gender',1);
$pdf->Cell(120,10,$patient['GENDER'],1);
$pdf->Ln();

$pdf->Cell(60,10,'IC Number',1);
$pdf->Cell(120,10,$patient['IC_NUMBER'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Phone Number',1);
$pdf->Cell(120,10,$patient['PHONE'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Ward',1);
$pdf->Cell(120,10,$patient['WARD_NAME'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Bed Number',1);
$pdf->Cell(120,10,$patient['BED_NUMBER'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Admission Date',1);
$pdf->Cell(120,10,$patient['ADMISSION_DATE'],1);
$pdf->Ln();

$pdf->Cell(60,10,'Discharge Date',1);

$pdf->Cell(
120,
10,
empty($patient['DISCHARGE_DATE'])
?
'Still Admitted'
:
$patient['DISCHARGE_DATE'],
1
);

$pdf->Ln();

$pdf->Cell(60,10,'Length of Stay',1);

$pdf->Cell(
120,
10,
$totalDays.' Day(s)',
1
);

$pdf->Ln(15);

/* ===================================
   HOSPITAL COURSE SUMMARY
=================================== */

$pdf->SetFont('Helvetica','B',15);

$pdf->Cell(
0,
10,
'II. Summary of Hospital Course',
0,
1
);

$pdf->SetFont('Helvetica','',10);

$latestDiagnosis = '';

if(!empty($diagnosisList))
{
    $latestDiagnosis =
    $diagnosisList[0]['DIAGNOSIS_DETAILS'];
}

$medNames = [];

foreach($medicationList as $m)
{
    $medNames[] =
    $m['MEDICATION_NAME'].
    ' ('.
    $m['DOSAGE'].
    ')';
}

$medicationSummary =
empty($medNames)
?
'No medication prescribed'
:
implode(', ', $medNames);

$summary =

'Patient was admitted on '.
$patient['ADMISSION_DATE'].

' and received treatment in the '.
$patient['WARD_NAME'].

' ward. Primary diagnosis recorded was '.
$latestDiagnosis.

'. During hospitalization, the patient was prescribed '.
$medicationSummary.

'. The patient remained hospitalized for '.
$totalDays.

' day(s) and was discharged in stable condition.';

$pdf->MultiCell(
0,
7,
$summary
);

$pdf->Ln(10);

/* ===================================
   DIAGNOSIS TABLE
=================================== */

$pdf->SetFont('Helvetica','B',14);
$pdf->Cell(0,10,'Diagnosis History',0,1);

$pdf->SetFillColor(52,73,94);
$pdf->SetTextColor(255,255,255);

$pdf->SetFont('Helvetica','B',10);

$pdf->Cell(45,10,'Date',1,0,'C',true);
$pdf->Cell(145,10,'Diagnosis',1,1,'C',true);

$pdf->SetTextColor(0,0,0);

$pdf->SetFont('Helvetica','',10);

foreach($diagnosisList as $d){

    $pdf->Cell(
        45,
        10,
        $d['DATE_RECORDED'],
        1
    );

    $pdf->Cell(
        145,
        10,
        substr($d['DIAGNOSIS_DETAILS'],0,70),
        1
    );

    $pdf->Ln();
}

$pdf->Ln(8);


/* ===================================
   FOOTER
=================================== */

$pdf->SetFont('Helvetica','I',10);

$pdf->Ln(10);

$pdf->SetFont('Helvetica','I',10);

$pdf->Cell(
0,
8,
'Prepared By: ZB-CARE Hospital Management System',
0,
1,
'R'
);

$pdf->Cell(
0,
8,
'Report ID: ZBC-'.$admission_id,
0,
1,
'R'
);

$pdf->Cell(
0,
8,
'Generated: '.date('d M Y h:i A'),
0,
1,
'R'
);

/* ===================================
   OUTPUT
=================================== */

$pdf->Output(
    'I',
    'Patient_Report_'.$admission_id.'.pdf'
);

?>