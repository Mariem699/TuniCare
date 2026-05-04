<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? "";
if(empty($email)){
    exit("no session");
}

$today = date("Y-m-d");


$stmt = $conn->prepare("SELECT water_today FROM patients WHERE email=?");
$stmt->bind_param("s",$email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$current = (int)$user['water_today'];
$new = $current + 1;


$stmt = $conn->prepare("
UPDATE patients 
SET water_today=? 
WHERE email=?
");
$stmt->bind_param("is",$new,$email);
$stmt->execute();


$stmt = $conn->prepare("
SELECT water FROM health_data WHERE email=? AND date=?
");
$stmt->bind_param("ss",$email,$today);
$stmt->execute();
$res = $stmt->get_result();

if($row = $res->fetch_assoc()){

    $stmt = $conn->prepare("
        UPDATE health_data 
        SET water = water + 1 
        WHERE email=? AND date=?
    ");
    $stmt->bind_param("ss",$email,$today);
    $stmt->execute();

} else {

    $stmt = $conn->prepare("
        INSERT INTO health_data(email,date,water)
        VALUES(?,?,1)
    ");
    $stmt->bind_param("ss",$email,$today);
    $stmt->execute();
}

echo "ok";
?>