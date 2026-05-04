<?php

if(session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$conn  = new mysqli("localhost","root","","tunicare");
$email = $_SESSION['email'] ?? "";

if(!$email){ echo json_encode(['confirmed'=>0]); exit; }

$stmt = $conn->prepare("
    SELECT COUNT(*) as c
    FROM appointments
    WHERE patient_email = ?
    AND status = 'confirmed'
    AND patient_notified = 0
    AND date >= CURDATE()
");
$stmt->bind_param("s", $email);
$stmt->execute();
$count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);

echo json_encode(['confirmed' => $count]);