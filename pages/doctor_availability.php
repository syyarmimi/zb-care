<?php
session_start();
include("../config/config.php");

// 1. SECURITY CHECK
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    header("Location: ../auth/login.php");
    exit();
}

$username = $_SESSION['user'] ?? '';

// 2. GET DOCTOR ID
$stmtDoctor = $conn->prepare("
    SELECT ACCOUNT_ID 
    FROM SYARMIMI.HOSPITAL_STAFF 
    WHERE LOWER(USERNAME) = LOWER(:username)
");
$stmtDoctor->execute([':username' => $username]);
$doctor = $stmtDoctor->fetch(PDO::FETCH_ASSOC);
$doctorId = $doctor['ACCOUNT_ID'] ?? 0;

$selectedDate = $_GET['date'] ?? '';

$appointmentsOnDate = [];
$slotList = [];

if(!empty($selectedDate))
{
    $stmt = $conn->prepare("
        SELECT
    PATIENT_NAME,
    APPOINTMENT_TIME,
    STATUS
FROM SYARMIMI.APPOINTMENT
WHERE ACCOUNT_ID = :doctor
AND STATUS = 'Approved'
AND APPOINTMENT_DATE = :date
ORDER BY APPOINTMENT_TIME
    ");

    $stmt->execute([
        ':doctor' => $doctorId,
        ':date'   => $selectedDate
    ]);

    $appointmentsOnDate =
        $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if(!empty($selectedDate))
{
    $slotStmt = $conn->prepare("
    SELECT
        DS.SLOT_TIME,
        DS.STATUS,
        A.PATIENT_NAME
    FROM SYARMIMI.DOCTOR_SLOT DS

    LEFT JOIN SYARMIMI.APPOINTMENT A
    ON DS.APPOINTMENT_ID = A.APPOINTMENT_ID

    WHERE DS.ACCOUNT_ID = :doctor
    AND DS.SLOT_DATE = TO_DATE(:date,'YYYY-MM-DD')

    ORDER BY DS.SLOT_TIME
    ");

    $slotStmt->execute([
        ':doctor' => $doctorId,
        ':date' => $selectedDate
    ]);

    $slotList = $slotStmt->fetchAll(PDO::FETCH_ASSOC);
}

/* =========================================
    SAVE FROM CALENDAR AJAX (POST REQUEST)
========================================= */
if (isset($_POST['save'])) {

    $date = substr($_POST['date'], 0, 10);
    $status = $_POST['status'];

    try {

        $conn->beginTransaction();

        // Check availability
        $checkSql = "
            SELECT COUNT(*)
            FROM SYARMIMI.DOCTOR_AVAILABILITY
            WHERE ACCOUNT_ID = $doctorId
            AND AVAILABLE_DATE = TO_DATE('$date','YYYY-MM-DD')
        ";

        $exists = $conn->query($checkSql)->fetchColumn();

        if ($exists > 0) {

            $updateSql = "
                UPDATE SYARMIMI.DOCTOR_AVAILABILITY
                SET STATUS = '$status'
                WHERE ACCOUNT_ID = $doctorId
                AND AVAILABLE_DATE = TO_DATE('$date','YYYY-MM-DD')
            ";

            $conn->exec($updateSql);

        } else {

            $insertSql = "
                INSERT INTO SYARMIMI.DOCTOR_AVAILABILITY
                (
                    AVAILABILITY_ID,
                    ACCOUNT_ID,
                    AVAILABLE_DATE,
                    STATUS,
                    START_TIME,
                    END_TIME
                )
                VALUES
                (
                    SYARMIMI.DOCTOR_AVAIL_SEQ.NEXTVAL,
                    $doctorId,
                    TO_DATE('$date','YYYY-MM-DD'),
                    '$status',
                    '08:00',
                    '17:00'
                )
            ";

            $conn->exec($insertSql);
        }

        // SLOTS
        if ($status == 'Available') {

          for ($hour = 8; $hour <= 16; $hour++) {

            $slotStatus = 'Available';

            if($hour == 13)
            {
            $slotStatus = 'Lunch Break';
            }

            $slotTime = sprintf('%02d:00', $hour);

            $checkSlotSql = "
            SELECT COUNT(*)
            FROM SYARMIMI.DOCTOR_SLOT
            WHERE ACCOUNT_ID = $doctorId
            AND SLOT_DATE = TO_DATE('$date','YYYY-MM-DD')
            AND SLOT_TIME = '$slotTime'
            ";

                $slotExists = $conn->query($checkSlotSql)->fetchColumn();

                if ($slotExists == 0) {

                    $insertSlotSql = "
                        INSERT INTO SYARMIMI.DOCTOR_SLOT
                        (
                            SLOT_ID,
                            ACCOUNT_ID,
                            SLOT_DATE,
                            SLOT_TIME,
                            MAX_PATIENT,
                            CURRENT_PATIENT,
                            STATUS
                        )
                        VALUES
                        (
                            SYARMIMI.DOCTOR_SLOT_SEQ.NEXTVAL,
                            $doctorId,
                            TO_DATE('$date','YYYY-MM-DD'),
                            '$slotTime',
                            1,
                            0,
                            '$slotStatus'
                        )
                    ";

                    $conn->exec($insertSlotSql);

                } else {

                    $updateSlotSql = "
                        UPDATE SYARMIMI.DOCTOR_SLOT
                        SET STATUS = '$slotStatus'
                        WHERE ACCOUNT_ID = $doctorId
                        AND SLOT_DATE = TO_DATE('$date','YYYY-MM-DD')
                        AND SLOT_TIME = '$slotTime'
                    ";

                    $conn->exec($updateSlotSql);
                }
            }

        } else {

            $disableSql = "
                UPDATE SYARMIMI.DOCTOR_SLOT
                SET STATUS = 'Unavailable'
                WHERE ACCOUNT_ID = $doctorId
                AND SLOT_DATE = TO_DATE('$date','YYYY-MM-DD')
            ";

            $conn->exec($disableSql);
        }

        $conn->commit();

        echo "success";
        exit();

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        echo "Database Error: " . $e->getMessage();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Doctor Availability Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background:#f4f6f9; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .page-title { font-size:32px; font-weight:700; color: #333; }
        .calendar-box { background:white; border-radius:15px; padding:25px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
        .legend { margin-bottom:20px; }
        .legend span { display:inline-block; padding:5px 15px; border-radius:20px; color:white; margin-right:10px; font-size: 14px; }
        .available { background:#22c55e; }
        .unavailable { background:#ef4444; }
        #calendar { max-width: 100%; margin: 0 auto; min-height: 500px; }
        .fc-daygrid-day { cursor: pointer; }
        .sidebar { width: 250px !important; min-width: 250px !important;}
    </style>
</head>
<body>

<div class="d-flex">
    <?php include("../includes/sidebar_doctor.php"); ?>

    <div class="flex-grow-1 p-4">
        <h2 class="page-title mb-4">📅 Doctor Availability Calendar</h2>

        <div class="legend">
            <span class="available">🟢 Available</span>
            <span class="unavailable">🔴 Unavailable</span>
        </div>

        <div class="calendar-box">
            <div id="calendar"></div>
        </div>
        <?php if(!empty($selectedDate)): ?>

<div class="calendar-box mt-4">

<h4>
📋 Appointments on <?= $selectedDate ?>
</h4>

<?php if(count($appointmentsOnDate) > 0): ?>

<table class="table table-bordered">

<tr>
    <th>Time</th>
    <th>Patient</th>
    <th>Status</th>
</tr>

<?php foreach($appointmentsOnDate as $row): ?>

<tr>
    <td><?= $row['APPOINTMENT_TIME'] ?></td>
    <td><?= $row['PATIENT_NAME'] ?></td>
    <td><?= $row['STATUS'] ?></td>
</tr>

<?php endforeach; ?>

</table>

<?php else: ?>

<div class="alert alert-info">
No appointments booked on this date.
</div>

<?php endif; ?>

</div>

<?php endif; ?>

<?php if(!empty($selectedDate)): ?>

<div class="calendar-box mt-4">

<h4>
🕒 Doctor Time Slots
</h4>

<table class="table table-bordered">

<tr>
<th>Slot</th>
<th>Status</th>
<th>Patient</th>
</tr>

<?php foreach($slotList as $slot): ?>

<tr>

<td>

<?php

$start = substr($slot['SLOT_TIME'],0,5);

$end = date(
'H:i',
strtotime($slot['SLOT_TIME'].' +1 hour')
);

echo $start . " - " . $end;

?>

</td>

<td>

<?php

if($slot['STATUS'] == 'Booked')
{
    echo "<span class='badge bg-danger'>Booked</span>";
}
elseif($slot['STATUS'] == 'Lunch Break')
{
    echo "<span class='badge bg-warning text-dark'>Lunch Break</span>";
}
elseif($slot['STATUS'] == 'Unavailable')
{
    echo "<span class='badge bg-secondary'>Unavailable</span>";
}
else
{
    echo "<span class='badge bg-success'>Available</span>";
}

?>

</td>

<td>

<?= $slot['PATIENT_NAME'] ?? '-' ?>

</td>

</tr>

<?php endforeach; ?>

</table>

</div>

<?php endif; ?>
        
    </div>
</div>

<div class="modal fade" id="availabilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Set Availability</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="availabilityDate">
                <div class="mb-3">
                    <label class="form-label">Selected Date</label>
                    <input type="text" id="displayDate" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select id="availabilityStatus" class="form-select">
                        <option value="Available">🟢 Available</option>
                        <option value="Unavailable">🔴 Unavailable</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="saveAvailability" class="btn btn-success">Save Availability</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth'
        },
        height: 'auto',
        

events: 'load_availability.php',

dateClick: function(info)
{
    document.getElementById('availabilityDate').value =
    info.dateStr;

    document.getElementById('displayDate').value =
    info.dateStr;

    var modal = new bootstrap.Modal(
        document.getElementById('availabilityModal')
    );

    modal.show();
},

eventClick: function(info)
{
    let clickedDate =
    info.event.startStr;

    window.location =
    'doctor_availability.php?date='
    + clickedDate;
}
    });
    calendar.render();

    document.getElementById('saveAvailability').addEventListener('click', function() {
        let date = document.getElementById('availabilityDate').value;
        let status = document.getElementById('availabilityStatus').value;
        let btn = this;

        btn.disabled = true;
        btn.innerText = "Saving...";

        fetch('doctor_availability.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'date=' + encodeURIComponent(date) + '&status=' + encodeURIComponent(status) + '&save=1'
        })
        .then(response => response.text())
        .then(data => {
            if (data.trim() === "success") {
                bootstrap.Modal.getInstance(document.getElementById('availabilityModal')).hide();
                calendar.refetchEvents();
                Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Availability Updated',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
        });
            } else {
                Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data,
                confirmButtonColor: '#dc3545'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'warning',
                title: 'Network Error',
                text: error,
                confirmButtonColor: '#f59e0b'
        });
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerText = "Save Availability";
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>