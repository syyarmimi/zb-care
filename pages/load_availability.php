<?php
ob_start(); // Pastikan tak ada output lain keluar sebelum JSON
session_start();
include("../config/config.php");

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'doctor') {
    echo json_encode([]);
    exit();
}

$username = $_SESSION['user'] ?? '';

try {
    // Ambil Doctor ID
    $stmtDoctor = $conn->prepare("SELECT ACCOUNT_ID FROM SYARMIMI.HOSPITAL_STAFF WHERE LOWER(USERNAME) = LOWER(:username)");
    $stmtDoctor->execute([':username' => $username]);
    $doctor = $stmtDoctor->fetch(PDO::FETCH_ASSOC);
    $doctorId = $doctor['ACCOUNT_ID'] ?? 0;

    if ($doctorId === 0) {
        echo json_encode([]);
        exit();
    }

    // Ambil data availability
    $sql = "SELECT TO_CHAR(AVAILABLE_DATE, 'YYYY-MM-DD') AS ADATE, STATUS 
            FROM SYARMIMI.DOCTOR_AVAILABILITY 
            WHERE ACCOUNT_ID = :doctor";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':doctor' => $doctorId]);

    $events = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['STATUS'] === 'Available') {
            $events[] = [
                'title'           => "🟢 Available\n08:00 AM - 05:00 PM",
                'start'           => $row['ADATE'],
                'backgroundColor' => '#22c55e',
                'borderColor'     => '#16a34a',
                'allDay'          => true
            ];
        } else {
            $events[] = [
                'title'           => "🔴 Unavailable",
                'start'           => $row['ADATE'],
                'backgroundColor' => '#ef4444',
                'borderColor'     => '#dc2626',
                'allDay'          => true
            ];
        }
    }

    ob_clean(); // Buang sebarang whitespace yang tak sengaja
    echo json_encode($events);

} catch (PDOException $e) {
    echo json_encode([]);
}
exit();