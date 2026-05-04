<?php
session_start();
$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? "";
$doctor = $_POST['doctor_email'] ?? "";

if(empty($email) || empty($doctor)){
    header("Location: profilp.php");
    exit();
}

$stmt = $conn->prepare("
INSERT INTO patient_doctors(patient_email, doctor_email)
VALUES(?,?)
");
$stmt->bind_param("ss",$email,$doctor);
$stmt->execute();

header("Location: profilp.php");
exit();
?>