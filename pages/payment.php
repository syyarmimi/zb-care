<?php

session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

include("../config/config.php");


/* =========================================================
   HELPERS
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function normalizePhone($value)
{
    return preg_replace(
        '/\D+/',
        '',
        (string)$value
    );
}

function formatMoney($amount)
{
    return number_format(
        (float)$amount,
        2
    );
}

function generateReferenceNo(int $paymentId): string
{
    return
        'ZBC' .
        date('ymd') .
        str_pad(
            (string)$paymentId,
            5,
            '0',
            STR_PAD_LEFT
        );
}


/* =========================================================
   BASIC STATE
========================================================= */

$errorMessage = '';
$successMessage = '';

$verifiedPatient = null;
$bills = [];
$selectedBill = null;
$selectedBillItems = [];
$selectedPayment = null;


/* =========================================================
   RESTORE VERIFIED PATIENT FROM SESSION
========================================================= */

$verifiedPatientId =
    (int)(
        $_SESSION['payment_patient_id']
        ?? 0
    );

if ($verifiedPatientId > 0) {

    try {

        $restoreStmt =
            $conn->prepare("
                SELECT
                    PATIENT_ID,
                    NAME,
                    IC_NUMBER,
                    PHONE
                FROM SYARMIMI.PATIENT
                WHERE PATIENT_ID = ?
            ");

        $restoreStmt->execute([
            $verifiedPatientId
        ]);

        $verifiedPatient =
            $restoreStmt->fetch(
                PDO::FETCH_ASSOC
            );

        if (!$verifiedPatient) {

            unset(
                $_SESSION['payment_patient_id']
            );

            $verifiedPatientId = 0;
        }

    }
    catch (Throwable $e) {

        $verifiedPatient = null;
        $verifiedPatientId = 0;

        unset(
            $_SESSION['payment_patient_id']
        );
    }
}


/* =========================================================
   LOG OUT / CLEAR PAYMENT SEARCH
========================================================= */

if (
    isset($_GET['clear'])
    &&
    $_GET['clear'] === '1'
) {

    unset(
        $_SESSION['payment_patient_id']
    );

    header(
        "Location: payment.php"
    );

    exit();
}


/* =========================================================
   FIND PATIENT BY IC + PHONE
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['find_bill'])
) {

    $icNumber =
        trim(
            $_POST['ic_number']
            ?? ''
        );

    $phoneInput =
        normalizePhone(
            $_POST['phone']
            ?? ''
        );


    if ($icNumber === '' || $phoneInput === '') {

        $errorMessage =
            "Please enter both IC number and phone number.";

    }
    else {

        try {

            /*
             Normalize IC and phone inside Oracle so these all match:

             900101-01-1234  = 900101011234
             013-746-2851    = 0137462851

             Only digits are compared.
            */
            $patientStmt =
                $conn->prepare("
                    SELECT
                        PATIENT_ID,
                        NAME,
                        IC_NUMBER,
                        PHONE

                    FROM SYARMIMI.PATIENT

                    WHERE
                        REGEXP_REPLACE(
                            NVL(IC_NUMBER, ''),
                            '[^0-9]',
                            ''
                        )
                        =
                        REGEXP_REPLACE(
                            NVL(?, ''),
                            '[^0-9]',
                            ''
                        )
                ");

            $patientStmt->execute([
                $icNumber
            ]);

            $candidatePatients =
                $patientStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );

            $matchedPatient = null;
            $patientWithoutPhone = false;

            foreach (
                $candidatePatients
                as
                $candidate
            ) {

                $storedPhone =
                    normalizePhone(
                        $candidate['PHONE']
                        ?? ''
                    );

                if ($storedPhone === '') {
                    $patientWithoutPhone = true;
                    continue;
                }

                if ($storedPhone === $phoneInput) {

                    $matchedPatient = $candidate;
                    break;
                }
            }


            if (!$matchedPatient) {

                if (
                    $patientWithoutPhone
                    &&
                    count($candidatePatients) === 1
                ) {
                    $errorMessage =
                        "This patient record does not have a registered phone number. Please contact the hospital to update the patient details before using online payment.";
                }
                else {
                    $errorMessage =
                        "The IC number and phone number do not match our patient record.";
                }

                unset(
                    $_SESSION['payment_patient_id']
                );

                $verifiedPatient = null;
                $verifiedPatientId = 0;

            }
            else {

                $verifiedPatient =
                    $matchedPatient;

                $verifiedPatientId =
                    (int)$matchedPatient[
                        'PATIENT_ID'
                    ];

                $_SESSION['payment_patient_id'] =
                    $verifiedPatientId;
            }

        }
        catch (Throwable $e) {

            $errorMessage =
                "Unable to search for billing records. " .
                $e->getMessage();
        }
    }
}


/* =========================================================
   PROCESS PAYMENT
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['confirm_payment'])
) {

    if ($verifiedPatientId <= 0) {

        $errorMessage =
            "Please verify the patient details first.";

    }
    else {

        $billId =
            (int)(
                $_POST['bill_id']
                ?? 0
            );

        $paymentMethod =
            trim(
                $_POST['payment_method']
                ?? ''
            );


        $allowedMethods = [
            'Online Banking',
            'Credit / Debit Card'
        ];


        if ($billId <= 0) {

            $errorMessage =
                "Invalid bill selected.";

        }
        elseif (
            !in_array(
                $paymentMethod,
                $allowedMethods,
                true
            )
        ) {

            $errorMessage =
                "Please select a valid payment method.";

        }
        else {

            try {

                $conn->beginTransaction();


                /* =========================================
                   LOCK BILL AND VERIFY OWNERSHIP
                ========================================= */

                $billStmt =
                    $conn->prepare("
                        SELECT
                            BILL_ID,
                            PATIENT_ID,
                            TOTAL_AMOUNT,
                            STATUS

                        FROM SYARMIMI.BILL

                        WHERE
                            BILL_ID = ?
                            AND PATIENT_ID = ?

                        FOR UPDATE
                    ");

                $billStmt->execute([
                    $billId,
                    $verifiedPatientId
                ]);

                $billForPayment =
                    $billStmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$billForPayment) {

                    throw new Exception(
                        "The selected bill could not be found."
                    );
                }


                if (
                    strtoupper(
                        trim(
                            (string)$billForPayment[
                                'STATUS'
                            ]
                        )
                    )
                    !==
                    'UNPAID'
                ) {

                    throw new Exception(
                        "This bill is no longer unpaid."
                    );
                }


                $amount =
                    (float)$billForPayment[
                        'TOTAL_AMOUNT'
                    ];


                if ($amount < 0) {

                    throw new Exception(
                        "Invalid bill amount."
                    );
                }


                /* =========================================
                   PAYMENT ID
                ========================================= */

                $paymentId =
                    (int)$conn
                        ->query("
                            SELECT
                                SYARMIMI.PAYMENT_SEQ.NEXTVAL
                            FROM DUAL
                        ")
                        ->fetchColumn();


                $referenceNo =
                    generateReferenceNo(
                        $paymentId
                    );


                /* =========================================
                   INSERT PAYMENT
                ========================================= */

                $insertPayment =
                    $conn->prepare("
                        INSERT INTO SYARMIMI.PAYMENT
                        (
                            PAYMENT_ID,
                            BILL_ID,
                            PAYMENT_METHOD,
                            AMOUNT,
                            PAYMENT_DATE,
                            PAYMENT_STATUS,
                            REFERENCE_NO
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            SYSDATE,
                            'Paid',
                            ?
                        )
                    ");

                $insertPayment->execute([
                    $paymentId,
                    $billId,
                    $paymentMethod,
                    $amount,
                    $referenceNo
                ]);


                /* =========================================
                   UPDATE BILL
                ========================================= */

                $updateBill =
                    $conn->prepare("
                        UPDATE SYARMIMI.BILL

                        SET
                            STATUS = 'Paid'

                        WHERE
                            BILL_ID = ?
                            AND PATIENT_ID = ?
                            AND UPPER(TRIM(STATUS)) = 'UNPAID'
                    ");

                $updateBill->execute([
                    $billId,
                    $verifiedPatientId
                ]);


                /* =========================================
                   VERIFY BILL STATUS
                ========================================= */

                $verifyStmt =
                    $conn->prepare("
                        SELECT STATUS

                        FROM SYARMIMI.BILL

                        WHERE
                            BILL_ID = ?
                            AND PATIENT_ID = ?
                    ");

                $verifyStmt->execute([
                    $billId,
                    $verifiedPatientId
                ]);

                $newBillStatus =
                    strtoupper(
                        trim(
                            (string)$verifyStmt
                                ->fetchColumn()
                        )
                    );


                if ($newBillStatus !== 'PAID') {

                    throw new Exception(
                        "Unable to verify payment completion."
                    );
                }


                $conn->commit();


                $_SESSION['payment_success_message'] =
                    "Payment completed successfully.";

                header(
                    "Location: payment.php?receipt=" .
                    urlencode(
                        (string)$paymentId
                    )
                );

                exit();

            }
            catch (Throwable $e) {

                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }

                $errorMessage =
                    $e->getMessage();
            }
        }
    }
}


/* =========================================================
   LOAD RECEIPT
========================================================= */

$receiptPaymentId =
    (int)(
        $_GET['receipt']
        ?? 0
    );

if (
    $receiptPaymentId > 0
    &&
    $verifiedPatientId > 0
) {

    try {

        $receiptStmt =
            $conn->prepare("
                SELECT
                    PYM.PAYMENT_ID,
                    PYM.BILL_ID,
                    PYM.PAYMENT_METHOD,
                    PYM.AMOUNT,
                    PYM.PAYMENT_STATUS,
                    PYM.REFERENCE_NO,

                    TO_CHAR(
                        PYM.PAYMENT_DATE,
                        'DD-MON-YYYY HH24:MI'
                    ) AS PAYMENT_DATE_DISPLAY,

                    CASE
                        WHEN B.ADMISSION_ID IS NOT NULL
                            THEN 'ADMISSION'
                        WHEN B.CONSULTATION_ID IS NOT NULL
                            THEN 'WALKIN'
                        WHEN B.APPOINTMENT_ID IS NOT NULL
                            THEN 'APPOINTMENT'
                        ELSE 'GENERAL'
                    END AS BILL_TYPE,

                    B.TOTAL_AMOUNT,
                    B.STATUS AS BILL_STATUS,

                    TO_CHAR(
                        B.BILL_DATE,
                        'DD-MON-YYYY'
                    ) AS BILL_DATE_DISPLAY,

                    PT.PATIENT_ID,
                    PT.NAME,
                    PT.IC_NUMBER,
                    PT.PHONE

                FROM SYARMIMI.PAYMENT PYM

                JOIN SYARMIMI.BILL B
                    ON PYM.BILL_ID =
                       B.BILL_ID

                JOIN SYARMIMI.PATIENT PT
                    ON B.PATIENT_ID =
                       PT.PATIENT_ID

                WHERE
                    PYM.PAYMENT_ID = ?
                    AND PT.PATIENT_ID = ?
            ");

        $receiptStmt->execute([
            $receiptPaymentId,
            $verifiedPatientId
        ]);

        $selectedPayment =
            $receiptStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($selectedPayment) {

            $selectedBill =
                $selectedPayment;

            $itemStmt =
                $conn->prepare("
                    SELECT
                        BILL_ITEM_ID,
                        ITEM_TYPE,
                        DESCRIPTION,
                        QUANTITY,
                        UNIT_PRICE,
                        SUBTOTAL

                    FROM SYARMIMI.BILL_ITEM

                    WHERE BILL_ID = ?

                    ORDER BY BILL_ITEM_ID
                ");

            $itemStmt->execute([
                $selectedPayment[
                    'BILL_ID'
                ]
            ]);

            $selectedBillItems =
                $itemStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    }
    catch (Throwable $e) {

        $errorMessage =
            "Unable to load the receipt. " .
            $e->getMessage();
    }
}


/* =========================================================
   LOAD SELECTED BILL DETAILS
========================================================= */

$viewBillId =
    (int)(
        $_GET['bill']
        ?? 0
    );

if (
    !$selectedPayment
    &&
    $viewBillId > 0
    &&
    $verifiedPatientId > 0
) {

    try {

        $viewStmt =
            $conn->prepare("
                SELECT
                    B.BILL_ID,

                    CASE
                        WHEN B.ADMISSION_ID IS NOT NULL
                            THEN 'ADMISSION'
                        WHEN B.CONSULTATION_ID IS NOT NULL
                            THEN 'WALKIN'
                        WHEN B.APPOINTMENT_ID IS NOT NULL
                            THEN 'APPOINTMENT'
                        ELSE 'GENERAL'
                    END AS BILL_TYPE,

                    B.TOTAL_AMOUNT,
                    B.STATUS,
                    B.APPOINTMENT_ID,
                    B.CONSULTATION_ID,
                    B.ADMISSION_ID,

                    TO_CHAR(
                        B.BILL_DATE,
                        'DD-MON-YYYY'
                    ) AS BILL_DATE_DISPLAY,

                    P.NAME,
                    P.IC_NUMBER,
                    P.PHONE

                FROM SYARMIMI.BILL B

                JOIN SYARMIMI.PATIENT P
                    ON B.PATIENT_ID =
                       P.PATIENT_ID

                WHERE
                    B.BILL_ID = ?
                    AND B.PATIENT_ID = ?
            ");

        $viewStmt->execute([
            $viewBillId,
            $verifiedPatientId
        ]);

        $selectedBill =
            $viewStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if ($selectedBill) {

            $itemStmt =
                $conn->prepare("
                    SELECT
                        BILL_ITEM_ID,
                        ITEM_TYPE,
                        DESCRIPTION,
                        QUANTITY,
                        UNIT_PRICE,
                        SUBTOTAL

                    FROM SYARMIMI.BILL_ITEM

                    WHERE BILL_ID = ?

                    ORDER BY BILL_ITEM_ID
                ");

            $itemStmt->execute([
                $viewBillId
            ]);

            $selectedBillItems =
                $itemStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );
        }

    }
    catch (Throwable $e) {

        $errorMessage =
            "Unable to load the selected bill. " .
            $e->getMessage();
    }
}


/* =========================================================
   LOAD ALL PATIENT BILLS
========================================================= */

if ($verifiedPatientId > 0) {

    try {

        $billListStmt =
            $conn->prepare("
                SELECT
                    B.BILL_ID,

                    CASE
                        WHEN B.ADMISSION_ID IS NOT NULL
                            THEN 'ADMISSION'
                        WHEN B.CONSULTATION_ID IS NOT NULL
                            THEN 'WALKIN'
                        WHEN B.APPOINTMENT_ID IS NOT NULL
                            THEN 'APPOINTMENT'
                        ELSE 'GENERAL'
                    END AS BILL_TYPE,

                    B.TOTAL_AMOUNT,
                    B.STATUS,

                    TO_CHAR(
                        B.BILL_DATE,
                        'DD-MON-YYYY'
                    ) AS BILL_DATE_DISPLAY,

                    B.APPOINTMENT_ID,
                    B.CONSULTATION_ID,
                    B.ADMISSION_ID,

                    (
                        SELECT
                            MAX(PY.PAYMENT_ID)

                        FROM SYARMIMI.PAYMENT PY

                        WHERE
                            PY.BILL_ID =
                            B.BILL_ID

                            AND UPPER(
                                TRIM(
                                    PY.PAYMENT_STATUS
                                )
                            )
                            =
                            'PAID'
                    ) AS PAYMENT_ID

                FROM SYARMIMI.BILL B

                WHERE
                    B.PATIENT_ID = ?

                ORDER BY
                    CASE
                        WHEN UPPER(TRIM(B.STATUS)) = 'UNPAID'
                        THEN 0
                        ELSE 1
                    END,
                    B.BILL_DATE DESC,
                    B.BILL_ID DESC
            ");

        $billListStmt->execute([
            $verifiedPatientId
        ]);

        $bills =
            $billListStmt->fetchAll(
                PDO::FETCH_ASSOC
            );

    }
    catch (Throwable $e) {

        if ($errorMessage === '') {

            $errorMessage =
                "Unable to load billing records. " .
                $e->getMessage();
        }
    }
}


/* =========================================================
   SUCCESS MESSAGE
========================================================= */

if (
    isset(
        $_SESSION['payment_success_message']
    )
) {

    $successMessage =
        (string)$_SESSION[
            'payment_success_message'
        ];

    unset(
        $_SESSION['payment_success_message']
    );
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
ZB-CARE | Payment
</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
    rel="stylesheet"
>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    background:#f4f7fb;
    color:#172033;
    font-family:'Segoe UI',Arial,sans-serif;
}

.topbar{
    position:sticky;
    top:0;
    z-index:50;
    border-bottom:1px solid rgba(226,232,240,.9);
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(14px);
}

.topbar-inner{
    width:min(1180px,calc(100% - 32px));
    min-height:72px;
    margin:0 auto;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
}

.brand{
    display:flex;
    align-items:center;
    gap:11px;
    color:#0f172a;
    text-decoration:none;
    font-weight:850;
    letter-spacing:-.4px;
}

.brand-icon{
    width:39px;
    height:39px;
    display:grid;
    place-items:center;
    border-radius:11px;
    background:#2563eb;
    color:#fff;
    box-shadow:0 8px 20px rgba(37,99,235,.18);
}

.home-link{
    display:inline-flex;
    align-items:center;
    gap:7px;
    color:#475569;
    text-decoration:none;
    font-size:13px;
    font-weight:650;
}

.home-link:hover{
    color:#2563eb;
}

.page-shell{
    width:min(1120px,calc(100% - 32px));
    margin:42px auto 70px;
}

.hero{
    margin-bottom:25px;
    text-align:center;
}

.hero-badge{
    display:inline-flex;
    align-items:center;
    gap:7px;
    margin-bottom:12px;
    padding:7px 11px;
    border:1px solid #dbeafe;
    border-radius:999px;
    background:#eff6ff;
    color:#2563eb;
    font-size:11px;
    font-weight:750;
}

.hero h1{
    margin:0;
    color:#0f172a;
    font-size:34px;
    font-weight:850;
    letter-spacing:-.9px;
}

.hero p{
    max-width:620px;
    margin:9px auto 0;
    color:#64748b;
    font-size:14px;
    line-height:1.65;
}

.card-box{
    margin-bottom:18px;
    padding:24px;
    border:1px solid #e3e9f0;
    border-radius:18px;
    background:#fff;
    box-shadow:0 10px 30px rgba(15,23,42,.045);
}

.search-card{
    width:min(690px,100%);
    margin:0 auto 22px;
}

.section-title{
    display:flex;
    align-items:center;
    gap:10px;
    margin-bottom:17px;
    color:#0f172a;
    font-size:17px;
    font-weight:800;
}

.section-icon{
    width:34px;
    height:34px;
    display:grid;
    place-items:center;
    border-radius:10px;
    background:#eff6ff;
    color:#2563eb;
}

.form-label{
    margin-bottom:6px;
    color:#475569;
    font-size:12px;
    font-weight:700;
}

.form-control,
.form-select{
    min-height:46px;
    border:1px solid #dce3eb;
    border-radius:10px;
    font-size:13px;
}

.form-control:focus,
.form-select:focus{
    border-color:#60a5fa;
    box-shadow:0 0 0 4px rgba(37,99,235,.08);
}

.btn-main{
    min-height:46px;
    border:0;
    border-radius:10px;
    background:#2563eb;
    color:#fff;
    font-weight:750;
}

.btn-main:hover{
    background:#1d4ed8;
    color:#fff;
}

.patient-banner{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:18px;
    margin-bottom:20px;
    padding:18px 20px;
    border:1px solid #dbeafe;
    border-radius:15px;
    background:#eff6ff;
}

.patient-name{
    color:#0f172a;
    font-size:18px;
    font-weight:800;
}

.patient-meta{
    margin-top:4px;
    color:#64748b;
    font-size:12px;
}

.change-btn{
    white-space:nowrap;
    color:#2563eb;
    text-decoration:none;
    font-size:12px;
    font-weight:750;
}

.bill-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:15px;
}

.bill-card{
    position:relative;
    padding:19px;
    border:1px solid #e5eaf0;
    border-radius:14px;
    background:#fff;
    transition:.2s ease;
}

.bill-card:hover{
    transform:translateY(-2px);
    box-shadow:0 10px 26px rgba(15,23,42,.06);
}

.bill-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    gap:12px;
}

.bill-id{
    color:#0f172a;
    font-size:15px;
    font-weight:800;
}

.bill-type{
    margin-top:4px;
    color:#64748b;
    font-size:11px;
}

.status{
    display:inline-flex;
    align-items:center;
    padding:5px 8px;
    border-radius:999px;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
}

.status-unpaid{
    background:#fff7ed;
    color:#c2410c;
}

.status-paid{
    background:#f0fdf4;
    color:#15803d;
}

.bill-amount{
    margin-top:19px;
    color:#0f172a;
    font-size:26px;
    font-weight:850;
    letter-spacing:-.6px;
}

.bill-date{
    margin-top:3px;
    color:#94a3b8;
    font-size:10px;
}

.bill-actions{
    display:flex;
    gap:8px;
    margin-top:17px;
}

.bill-actions a{
    flex:1;
}

.btn-view,
.btn-pay,
.btn-receipt{
    border-radius:9px;
    font-size:11px;
    font-weight:750;
}

.btn-pay{
    background:#2563eb;
    color:#fff;
}

.btn-pay:hover{
    background:#1d4ed8;
    color:#fff;
}

.btn-receipt{
    border:1px solid #bbf7d0;
    background:#f0fdf4;
    color:#15803d;
}

.empty-state{
    padding:34px 18px;
    text-align:center;
    color:#64748b;
}

.empty-icon{
    width:54px;
    height:54px;
    margin:0 auto 11px;
    display:grid;
    place-items:center;
    border-radius:50%;
    background:#f1f5f9;
    color:#64748b;
    font-size:22px;
}

.bill-detail-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    margin-bottom:18px;
}

.detail-title{
    color:#0f172a;
    font-size:22px;
    font-weight:850;
}

.detail-subtitle{
    margin-top:5px;
    color:#64748b;
    font-size:12px;
}

.amount-big{
    color:#0f172a;
    text-align:right;
    font-size:28px;
    font-weight:850;
}

.amount-label{
    color:#94a3b8;
    text-align:right;
    font-size:10px;
    text-transform:uppercase;
}

.items{
    overflow:hidden;
    border:1px solid #e7ebf0;
    border-radius:12px;
}

.item-row{
    display:grid;
    grid-template-columns:minmax(0,1fr) 85px 100px 110px;
    gap:10px;
    align-items:center;
    padding:13px 15px;
    border-bottom:1px solid #eef2f6;
    font-size:12px;
}

.item-row:last-child{
    border-bottom:0;
}

.item-head{
    background:#f8fafc;
    color:#64748b;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
}

.item-desc{
    color:#334155;
    font-weight:650;
}

.item-type{
    margin-top:2px;
    color:#94a3b8;
    font-size:9px;
    text-transform:uppercase;
}

.text-end{
    text-align:right;
}

.total-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-top:14px;
    padding:15px 17px;
    border-radius:11px;
    background:#f8fafc;
}

.total-row strong{
    color:#0f172a;
    font-size:19px;
}

.payment-panel{
    margin-top:20px;
    padding:18px;
    border:1px solid #dbeafe;
    border-radius:12px;
    background:#f8fbff;
}

.payment-options{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    margin-top:11px;
}

.payment-option input{
    display:none;
}

.payment-option label{
    display:flex;
    align-items:center;
    gap:10px;
    height:100%;
    padding:13px;
    border:1px solid #dce3eb;
    border-radius:10px;
    background:#fff;
    cursor:pointer;
    color:#475569;
    font-size:12px;
    font-weight:650;
}

.payment-option input:checked + label{
    border-color:#2563eb;
    background:#eff6ff;
    color:#1d4ed8;
    box-shadow:0 0 0 2px rgba(37,99,235,.06);
}

.pay-button{
    width:100%;
    min-height:48px;
    margin-top:14px;
    border:0;
    border-radius:10px;
    background:#2563eb;
    color:#fff;
    font-weight:800;
}

.receipt-card{
    width:min(760px,100%);
    margin:0 auto;
}

.receipt-success{
    text-align:center;
    padding-bottom:20px;
    border-bottom:1px dashed #cbd5e1;
}

.receipt-icon{
    width:60px;
    height:60px;
    display:grid;
    place-items:center;
    margin:0 auto 11px;
    border-radius:50%;
    background:#dcfce7;
    color:#16a34a;
    font-size:27px;
}

.receipt-reference{
    margin-top:5px;
    color:#64748b;
    font-size:11px;
}

.receipt-info{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:10px;
    margin:18px 0;
}

.receipt-info-box{
    padding:12px 14px;
    border-radius:10px;
    background:#f8fafc;
}

.receipt-info-label{
    color:#94a3b8;
    font-size:9px;
    font-weight:750;
    text-transform:uppercase;
}

.receipt-info-value{
    margin-top:4px;
    color:#334155;
    font-size:12px;
    font-weight:700;
}

.receipt-actions{
    display:flex;
    justify-content:center;
    gap:9px;
    margin-top:20px;
}

.alert{
    border-radius:11px;
    font-size:12px;
}

.demo-note{
    margin-top:13px;
    color:#94a3b8;
    font-size:10px;
    line-height:1.5;
}

@media(max-width:760px){

    .bill-grid{
        grid-template-columns:1fr;
    }

    .item-row{
        grid-template-columns:minmax(0,1fr) 70px 95px;
    }

    .item-row > :nth-child(3){
        display:none;
    }

    .payment-options{
        grid-template-columns:1fr;
    }

    .bill-detail-head{
        flex-direction:column;
    }

    .amount-big,
    .amount-label{
        text-align:left;
    }

    .receipt-info{
        grid-template-columns:1fr;
    }
}

@media(max-width:540px){

    .page-shell{
        width:min(100% - 20px,1120px);
        margin-top:26px;
    }

    .topbar-inner{
        width:min(100% - 20px,1180px);
    }

    .hero h1{
        font-size:28px;
    }

    .card-box{
        padding:18px;
    }

    .patient-banner{
        align-items:flex-start;
        flex-direction:column;
    }
}


/* =========================================================
   PREMIUM RECEIPT STYLE
========================================================= */

.receipt-card{
    width:min(820px,100%);
    margin:0 auto;
    padding:0;
    overflow:hidden;
    border:1px solid #dfe7ef;
    border-radius:20px;
    background:#fff;
    box-shadow:0 18px 50px rgba(15,23,42,.08);
}

.receipt-topband{
    height:8px;
    background:linear-gradient(90deg,#2563eb,#0ea5e9);
}

.receipt-body{
    padding:34px 36px 32px;
}

.receipt-brand{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:16px;
    margin-bottom:28px;
}

.receipt-brand-left{
    display:flex;
    align-items:center;
    gap:12px;
}

.receipt-brand-logo{
    width:46px;
    height:46px;
    display:grid;
    place-items:center;
    border-radius:13px;
    background:#eff6ff;
    color:#2563eb;
    font-size:22px;
}

.receipt-brand-name{
    color:#0f172a;
    font-size:18px;
    font-weight:850;
    letter-spacing:-.3px;
}

.receipt-brand-sub{
    margin-top:2px;
    color:#94a3b8;
    font-size:10px;
    text-transform:uppercase;
    letter-spacing:.12em;
}

.receipt-badge-paid{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:8px 11px;
    border-radius:999px;
    background:#ecfdf5;
    color:#15803d;
    font-size:10px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.06em;
}

.receipt-success{
    margin-bottom:26px;
    padding:24px 20px;
    border:1px solid #dcfce7;
    border-radius:16px;
    background:linear-gradient(180deg,#f0fdf4,#ffffff);
    text-align:center;
}

.receipt-icon{
    width:62px;
    height:62px;
    display:grid;
    place-items:center;
    margin:0 auto 12px;
    border-radius:50%;
    background:#16a34a;
    color:#fff;
    font-size:29px;
    box-shadow:0 10px 24px rgba(22,163,74,.18);
}

.receipt-success .detail-title{
    font-size:24px;
}

.receipt-reference{
    margin-top:7px;
    color:#64748b;
    font-size:11px;
}

.receipt-reference strong{
    color:#0f172a;
    letter-spacing:.04em;
}

.receipt-info{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:12px;
    margin:0 0 24px;
}

.receipt-info-box{
    padding:14px 15px;
    border:1px solid #e8edf3;
    border-radius:12px;
    background:#fbfcfe;
}

.receipt-info-label{
    color:#94a3b8;
    font-size:9px;
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.08em;
}

.receipt-info-value{
    margin-top:5px;
    color:#1e293b;
    font-size:12px;
    font-weight:750;
}

.receipt-items-title{
    margin:0 0 10px;
    color:#0f172a;
    font-size:13px;
    font-weight:800;
}

.receipt-card .items{
    border:1px solid #e7edf3;
    border-radius:14px;
    background:#fff;
}

.receipt-card .item-row{
    grid-template-columns:minmax(0,1fr) 70px 105px 110px;
    padding:14px 16px;
}

.receipt-card .item-head{
    background:#f8fafc;
}

.receipt-card .total-row{
    margin-top:16px;
    padding:17px 18px;
    border:1px solid #dbeafe;
    border-radius:13px;
    background:#eff6ff;
}

.receipt-card .total-row span{
    color:#475569;
    font-size:12px;
    font-weight:700;
}

.receipt-card .total-row strong{
    color:#1d4ed8;
    font-size:23px;
    font-weight:900;
}

.receipt-footer-note{
    display:flex;
    align-items:flex-start;
    gap:8px;
    margin-top:18px;
    padding-top:16px;
    border-top:1px dashed #cbd5e1;
    color:#94a3b8;
    font-size:10px;
    line-height:1.55;
}

.receipt-actions{
    display:flex;
    justify-content:center;
    gap:9px;
    margin-top:22px;
}

.receipt-actions .btn{
    min-width:140px;
    min-height:42px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
}

.receipt-stamp{
    margin-top:4px;
    color:#64748b;
    font-size:10px;
}

@media print{

    @page{
        size:A4;
        margin:14mm;
    }

    .topbar,
    .hero,
    .receipt-actions,
    .patient-banner,
    .alert{
        display:none !important;
    }

    html,
    body{
        background:#fff !important;
        -webkit-print-color-adjust:exact;
        print-color-adjust:exact;
    }

    .page-shell{
        width:100%;
        margin:0;
    }

    .receipt-card{
        width:100%;
        max-width:none;
        margin:0;
        border:1px solid #dbe3ec;
        border-radius:16px;
        box-shadow:none;
        page-break-inside:avoid;
    }

    .receipt-body{
        padding:24px 26px 22px;
    }

    .receipt-actions{
        display:none !important;
    }

    .receipt-success{
        background:#f8fffb !important;
    }

    .receipt-card .total-row{
        background:#eff6ff !important;
    }

    .item-row,
    .receipt-info-box,
    .receipt-success,
    .total-row{
        break-inside:avoid;
    }
}

</style>

</head>

<body>


<header class="topbar">

<div class="topbar-inner">

<a
    href="../index.php"
    class="brand"
>
<span class="brand-icon">
<i class="bi bi-heart-pulse-fill"></i>
</span>

ZB-CARE
</a>


<a
    href="../index.php"
    class="home-link"
>
<i class="bi bi-arrow-left"></i>
Back to Home
</a>

</div>

</header>


<main class="page-shell">


<div class="hero">

<div class="hero-badge">
<i class="bi bi-shield-check"></i>
Secure Patient Billing
</div>

<h1>
Hospital Payment
</h1>

<p>
Verify your patient details to view outstanding bills,
review the itemised charges and make a payment.
</p>

</div>


<?php if ($errorMessage !== ''): ?>

<div class="alert alert-danger">
<i class="bi bi-exclamation-circle me-2"></i>
<?= h($errorMessage) ?>
</div>

<?php endif; ?>


<?php if ($successMessage !== ''): ?>

<div class="alert alert-success">
<i class="bi bi-check-circle me-2"></i>
<?= h($successMessage) ?>
</div>

<?php endif; ?>


<?php if (!$verifiedPatient): ?>


<!-- =====================================================
     SEARCH
===================================================== -->

<div class="card-box search-card">

<div class="section-title">

<span class="section-icon">
<i class="bi bi-search"></i>
</span>

Find Your Bill

</div>

<form method="POST">

<div class="row g-3">

<div class="col-md-6">

<label class="form-label">
IC Number
</label>

<input
    type="text"
    name="ic_number"
    class="form-control"
    placeholder="e.g. 900101-01-1234"
    value="<?= h($_POST['ic_number'] ?? '') ?>"
    required
>

</div>


<div class="col-md-6">

<label class="form-label">
Phone Number
</label>

<input
    type="text"
    name="phone"
    class="form-control"
    placeholder="e.g. 013-746-2851"
    value="<?= h($_POST['phone'] ?? '') ?>"
    required
>

</div>

</div>


<button
    type="submit"
    name="find_bill"
    value="1"
    class="btn btn-main w-100 mt-3"
>
<i class="bi bi-search me-1"></i>
Find My Bill
</button>

<div class="demo-note">
For privacy, both details must match the patient record before billing information is displayed.
Hyphens and spaces are accepted, for example
<strong>900101-01-1234</strong> or <strong>900101011234</strong>,
and <strong>013-746-2851</strong> or <strong>0137462851</strong>.
</div>

</form>

</div>


<?php else: ?>


<!-- =====================================================
     VERIFIED PATIENT
===================================================== -->

<div class="patient-banner">

<div>

<div class="patient-name">
<?= h($verifiedPatient['NAME']) ?>
</div>

<div class="patient-meta">
IC:
<?= h($verifiedPatient['IC_NUMBER']) ?>

&nbsp;•&nbsp;

Phone:
<?= h($verifiedPatient['PHONE']) ?>
</div>

</div>

<a
    href="payment.php?clear=1"
    class="change-btn"
>
<i class="bi bi-arrow-repeat me-1"></i>
Search Another Patient
</a>

</div>


<?php if ($selectedPayment): ?>


<!-- =====================================================
     RECEIPT
===================================================== -->

<div class="receipt-card">

<div class="receipt-topband"></div>

<div class="receipt-body">

<div class="receipt-brand">

<div class="receipt-brand-left">

<div class="receipt-brand-logo">
<i class="bi bi-heart-pulse-fill"></i>
</div>

<div>
<div class="receipt-brand-name">ZB-CARE Specialist Hospital</div>
<div class="receipt-brand-sub">Official Payment Receipt</div>
</div>

</div>

<div class="receipt-badge-paid">
<i class="bi bi-shield-check"></i>
Paid
</div>

</div>


<div class="receipt-success">

<div class="receipt-icon">
<i class="bi bi-check-lg"></i>
</div>

<div class="detail-title">
Payment Successful
</div>

<div class="receipt-reference">
Payment Reference:
<strong>
<?= h($selectedPayment['REFERENCE_NO']) ?>
</strong>
</div>

<div class="receipt-stamp">
Thank you. Your payment has been recorded successfully.
</div>

</div>


<div class="receipt-info">

<div class="receipt-info-box">

<div class="receipt-info-label">
Patient
</div>

<div class="receipt-info-value">
<?= h($selectedPayment['NAME']) ?>
</div>

</div>


<div class="receipt-info-box">

<div class="receipt-info-label">
IC Number
</div>

<div class="receipt-info-value">
<?= h($selectedPayment['IC_NUMBER']) ?>
</div>

</div>


<div class="receipt-info-box">

<div class="receipt-info-label">
Bill
</div>

<div class="receipt-info-value">
#<?= (int)$selectedPayment['BILL_ID'] ?>
—
<?= h(ucfirst(strtolower($selectedPayment['BILL_TYPE']))) ?>
</div>

</div>


<div class="receipt-info-box">

<div class="receipt-info-label">
Payment Date & Time
</div>

<div class="receipt-info-value">
<?= h($selectedPayment['PAYMENT_DATE_DISPLAY']) ?>
</div>

</div>


<div class="receipt-info-box">

<div class="receipt-info-label">
Payment Method
</div>

<div class="receipt-info-value">
<?= h($selectedPayment['PAYMENT_METHOD']) ?>
</div>

</div>


<div class="receipt-info-box">

<div class="receipt-info-label">
Payment Status
</div>

<div class="receipt-info-value">
<?= h($selectedPayment['PAYMENT_STATUS']) ?>
</div>

</div>

</div>


<div class="receipt-items-title">
Payment Breakdown
</div>


<div class="items">

<div class="item-row item-head">
<div>Description</div>
<div class="text-end">Qty</div>
<div class="text-end">Unit Price</div>
<div class="text-end">Subtotal</div>
</div>

<?php foreach ($selectedBillItems as $item): ?>

<div class="item-row">

<div>

<div class="item-desc">
<?= h($item['DESCRIPTION']) ?>
</div>

<div class="item-type">
<?= h($item['ITEM_TYPE']) ?>
</div>

</div>

<div class="text-end">
<?= h($item['QUANTITY']) ?>
</div>

<div class="text-end">
RM <?= formatMoney($item['UNIT_PRICE']) ?>
</div>

<div class="text-end">
<strong>
RM <?= formatMoney($item['SUBTOTAL']) ?>
</strong>
</div>

</div>

<?php endforeach; ?>

</div>


<div class="total-row">

<span>
Total Amount Paid
</span>

<strong>
RM <?= formatMoney($selectedPayment['AMOUNT']) ?>
</strong>

</div>


<div class="receipt-footer-note">

<i class="bi bi-info-circle"></i>

<div>
This receipt was generated electronically by ZB-CARE Specialist Hospital System.
Please keep the payment reference for future reference.
</div>

</div>


<div class="receipt-actions">

<a
    href="payment.php"
    class="btn btn-outline-secondary"
>
<i class="bi bi-arrow-left me-1"></i>
Back to Bills
</a>

<button
    type="button"
    class="btn btn-primary"
    onclick="window.print()"
>
<i class="bi bi-printer me-1"></i>
Print Receipt
</button>

</div>

</div>

</div>


<?php elseif ($selectedBill): ?>


<!-- =====================================================
     SELECTED BILL
===================================================== -->

<div class="card-box">

<div class="bill-detail-head">

<div>

<div class="detail-title">
Bill #<?= (int)$selectedBill['BILL_ID'] ?>
</div>

<div class="detail-subtitle">
<?= h(ucfirst(strtolower($selectedBill['BILL_TYPE']))) ?>
&nbsp;•&nbsp;
<?= h($selectedBill['BILL_DATE_DISPLAY']) ?>
</div>

</div>


<div>

<div class="amount-label">
Total Amount
</div>

<div class="amount-big">
RM <?= formatMoney($selectedBill['TOTAL_AMOUNT']) ?>
</div>

</div>

</div>


<div class="items">

<div class="item-row item-head">
<div>Description</div>
<div class="text-end">Qty</div>
<div class="text-end">Unit Price</div>
<div class="text-end">Subtotal</div>
</div>


<?php if (!empty($selectedBillItems)): ?>

<?php foreach ($selectedBillItems as $item): ?>

<div class="item-row">

<div>

<div class="item-desc">
<?= h($item['DESCRIPTION']) ?>
</div>

<div class="item-type">
<?= h($item['ITEM_TYPE']) ?>
</div>

</div>

<div class="text-end">
<?= h($item['QUANTITY']) ?>
</div>

<div class="text-end">
RM <?= formatMoney($item['UNIT_PRICE']) ?>
</div>

<div class="text-end">
<strong>
RM <?= formatMoney($item['SUBTOTAL']) ?>
</strong>
</div>

</div>

<?php endforeach; ?>

<?php else: ?>

<div class="empty-state">
No itemised charges were found for this bill.
</div>

<?php endif; ?>

</div>


<div class="total-row">

<span>
Total
</span>

<strong>
RM <?= formatMoney($selectedBill['TOTAL_AMOUNT']) ?>
</strong>

</div>


<?php if (
    strtoupper(trim($selectedBill['STATUS']))
    ===
    'UNPAID'
): ?>

<div class="payment-panel">

<div class="section-title mb-1">

<span class="section-icon">
<i class="bi bi-credit-card"></i>
</span>

Payment Method

</div>

<div class="demo-note mt-0">
Select a payment method to complete this academic demonstration payment.
No real bank or card transaction will be performed.
</div>


<form
    method="POST"
    id="paymentForm"
>

<input
    type="hidden"
    name="bill_id"
    value="<?= (int)$selectedBill['BILL_ID'] ?>"
>

<input
    type="hidden"
    name="confirm_payment"
    value="1"
>


<div class="payment-options">

<div class="payment-option">

<input
    type="radio"
    name="payment_method"
    id="online_banking"
    value="Online Banking"
    required
>

<label for="online_banking">
<i class="bi bi-bank fs-5"></i>
Online Banking
</label>

</div>


<div class="payment-option">

<input
    type="radio"
    name="payment_method"
    id="card_payment"
    value="Credit / Debit Card"
    required
>

<label for="card_payment">
<i class="bi bi-credit-card-2-front fs-5"></i>
Credit / Debit Card
</label>

</div>

</div>


<button
    type="submit"
    class="pay-button"
>
<i class="bi bi-lock-fill me-1"></i>
Pay RM <?= formatMoney($selectedBill['TOTAL_AMOUNT']) ?>
</button>

</form>

</div>

<?php endif; ?>


<div class="mt-3">

<a
    href="payment.php"
    class="btn btn-outline-secondary btn-sm"
>
<i class="bi bi-arrow-left me-1"></i>
Back to Bills
</a>

</div>

</div>


<?php else: ?>


<!-- =====================================================
     BILL LIST
===================================================== -->

<div class="card-box">

<div class="section-title">

<span class="section-icon">
<i class="bi bi-receipt"></i>
</span>

Your Bills

</div>


<?php if (!empty($bills)): ?>

<div class="bill-grid">


<?php foreach ($bills as $bill): ?>

<?php
$isUnpaid =
    strtoupper(
        trim(
            (string)$bill['STATUS']
        )
    )
    ===
    'UNPAID';
?>

<div class="bill-card">

<div class="bill-top">

<div>

<div class="bill-id">
Bill #<?= (int)$bill['BILL_ID'] ?>
</div>

<div class="bill-type">
<?= h(ucfirst(strtolower($bill['BILL_TYPE']))) ?>
</div>

</div>


<span class="status <?= $isUnpaid
    ? 'status-unpaid'
    : 'status-paid'
?>">
<?= h($bill['STATUS']) ?>
</span>

</div>


<div class="bill-amount">
RM <?= formatMoney($bill['TOTAL_AMOUNT']) ?>
</div>

<div class="bill-date">
Issued:
<?= h($bill['BILL_DATE_DISPLAY']) ?>
</div>


<div class="bill-actions">

<a
    href="payment.php?bill=<?= (int)$bill['BILL_ID'] ?>"
    class="btn btn-outline-secondary btn-view"
>
<i class="bi bi-eye me-1"></i>
View
</a>


<?php if ($isUnpaid): ?>

<a
    href="payment.php?bill=<?= (int)$bill['BILL_ID'] ?>"
    class="btn btn-pay"
>
<i class="bi bi-credit-card me-1"></i>
Pay Now
</a>

<?php elseif (!empty($bill['PAYMENT_ID'])): ?>

<a
    href="payment.php?receipt=<?= (int)$bill['PAYMENT_ID'] ?>"
    class="btn btn-receipt"
>
<i class="bi bi-receipt-cutoff me-1"></i>
Receipt
</a>

<?php endif; ?>

</div>

</div>

<?php endforeach; ?>


</div>

<?php else: ?>

<div class="empty-state">

<div class="empty-icon">
<i class="bi bi-receipt"></i>
</div>

<strong>
No billing records found.
</strong>

<div class="mt-1">
There are currently no bills available for this patient.
</div>

</div>

<?php endif; ?>

</div>


<?php endif; ?>


<?php endif; ?>


</main>


<script>

/* =========================================================
   PAYMENT CONFIRMATION
========================================================= */

const paymentForm =
    document.getElementById(
        'paymentForm'
    );

if (paymentForm) {

    paymentForm.addEventListener(
        'submit',
        function(event)
        {

            event.preventDefault();

            const selectedMethod =
                document.querySelector(
                    'input[name="payment_method"]:checked'
                );

            if (!selectedMethod) {

                Swal.fire({
                    icon:'warning',
                    title:'Select Payment Method',
                    text:'Please select a payment method before continuing.',
                    confirmButtonColor:'#2563eb'
                });

                return;
            }


            Swal.fire({

                icon:'question',

                title:'Confirm Payment?',

                html:
                    'Payment method: <strong>' +
                    selectedMethod.value +
                    '</strong><br><br>' +
                    'This payment is simulated for academic demonstration purposes.',

                showCancelButton:true,

                reverseButtons:true,

                confirmButtonText:'Yes, Pay Now',

                cancelButtonText:'Cancel',

                confirmButtonColor:'#2563eb',

                cancelButtonColor:'#64748b'

            })
            .then(
                function(result)
                {

                    if (result.isConfirmed) {

                        Swal.fire({

                            title:'Processing Payment',

                            text:'Please wait while the payment is being recorded...',

                            allowOutsideClick:false,

                            allowEscapeKey:false,

                            didOpen:function()
                            {
                                Swal.showLoading();
                            }

                        });

                        paymentForm.submit();
                    }
                }
            );
        }
    );
}

</script>

</body>
</html>
