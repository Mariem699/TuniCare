<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost", "root", "", "tunicare");
if($conn->connect_error){
    echo json_encode(["success" => false, "error" => "DB connection failed"]);
    exit;
}

$email = $_SESSION['email'] ?? "";
if(!$email){
    echo json_encode(["success" => false, "error" => "Not authenticated"]);
    exit;
}

$ok  = true;
$now = date("Y-m-d H:i:s");

/*  1. Messages: soft-delete + mark as read  */ 
$stmt1 = $conn->prepare("
    UPDATE messages
    SET
        is_read = 1,
        deleted_for = JSON_ARRAY_APPEND(
            IFNULL(deleted_for, JSON_ARRAY()),
            '$',
            ?
        )
    WHERE receiver_email = ?
    AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL)
");
$stmt1->bind_param("sss", $email, $email, $email);
$ok = $ok && $stmt1->execute();

/*  2. Appointments: soft-delete PAST appointments (date+time already passed)  */ 
$colCheck2 = $conn->query("SHOW COLUMNS FROM appointments LIKE 'deleted_for'");
if($colCheck2 && $colCheck2->num_rows > 0){
    $stmt3 = $conn->prepare("
        UPDATE appointments
        SET deleted_for = JSON_ARRAY_APPEND(
            IFNULL(deleted_for, JSON_ARRAY()),
            '$',
            ?
        )
        WHERE patient_email = ?
        AND status != 'cancelled'
        AND CONCAT(date, ' ', time) < ?
        AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL)
    ");
    $stmt3->bind_param("ssss", $email, $email, $now, $email);
    $ok = $ok && $stmt3->execute();
}

echo json_encode(["success" => $ok]);