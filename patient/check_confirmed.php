<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? null;

if(!$email){
    echo json_encode(["new_confirmed" => false]);
    exit();
}

$stmt = $conn->prepare("
    SELECT id
    FROM appointments
    WHERE patient_email=? 
    AND status='confirmed'
    AND patient_notified=0
    LIMIT 1
");

$stmt->bind_param("s",$email);
$stmt->execute();
$res = $stmt->get_result();

$hasNew = false;

if($row = $res->fetch_assoc()){
    $hasNew = true;

    $upd = $conn->prepare("
        UPDATE appointments
        SET patient_notified=1
        WHERE id=?
    ");
    $upd->bind_param("i",$row['id']);
    $upd->execute();
}

echo json_encode([
    "new_confirmed" => $hasNew
]);