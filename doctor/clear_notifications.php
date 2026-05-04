<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) die("DB error");

$email = $_SESSION['email'] ?? "";
if(empty($email)) exit;



$stmt1 = $conn->prepare("
UPDATE messages
SET deleted_for = JSON_ARRAY_APPEND(
    IFNULL(deleted_for, JSON_ARRAY()),
    '$',
    ?
)
WHERE receiver_email = ?
AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for,'one', ?) IS NULL)
");
$stmt1->bind_param("sss", $email, $email, $email);
$stmt1->execute();


$stmt2 = $conn->prepare("
UPDATE appointments
SET deleted_for = JSON_ARRAY_APPEND(
    IFNULL(deleted_for, JSON_ARRAY()),
    '$',
    ?
)
WHERE doctor_email = ?
AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for,'one', ?) IS NULL)
");
$stmt2->bind_param("sss", $email, $email, $email);
$stmt2->execute();


$stmt3 = $conn->prepare("
UPDATE doctor_schedule
SET deleted_for = JSON_ARRAY_APPEND(
    IFNULL(deleted_for, JSON_ARRAY()),
    '$',
    ?
)
WHERE doctor_email = ?
AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for,'one', ?) IS NULL)
");
$stmt3->bind_param("sss", $email, $email, $email);
$stmt3->execute();

echo json_encode(["success"=>true]);