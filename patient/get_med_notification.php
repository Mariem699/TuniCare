<?php
session_start();
header("Content-Type: application/json");

$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? "";
if(!$email){
    echo json_encode([]);
    exit;
}

$now = date("H:i:s");

$stmt = $conn->prepare("
    SELECT id, name, dosage, time_take
    FROM medications
    WHERE email=?
    AND is_active=1
    AND notified=0
    AND TIME(time_take) <= TIME(?)
    ORDER BY time_take ASC
    LIMIT 1
");

$stmt->bind_param("ss", $email, $now);
$stmt->execute();

$res = $stmt->get_result();
$med = $res->fetch_assoc();

if($med){
    // mark as shown
    $u = $conn->prepare("UPDATE medications SET notified=1 WHERE id=?");
    $u->bind_param("i", $med['id']);
    $u->execute();
}

echo json_encode($med ?? []);