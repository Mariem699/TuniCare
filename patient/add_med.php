<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'];

$name = $_POST['name'];
$dosage = $_POST['dosage'];

$t1 = $_POST['time1'];
$t2 = $_POST['time2'] ?? null;
$t3 = $_POST['time3'] ?? null;

$start = $_POST['start_date'];
$duration = $_POST['duration_days'];

$stmt = $conn->prepare("
INSERT INTO medications
(email,name,dosage,time_take,time2,time3,start_date,duration_days)
VALUES(?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
    "sssssssi",
    $email,$name,$dosage,$t1,$t2,$t3,$start,$duration
);

$stmt->execute();

header("Location: medication_profile.php");
?>