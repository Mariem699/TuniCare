<?php
session_start();
$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? "";
$list = $_POST['remove'] ?? [];

if(empty($email) || empty($list)){
    header("Location: profilp.php");
    exit();
}

foreach($list as $doc){
    $stmt = $conn->prepare("
    DELETE FROM patient_doctors
    WHERE patient_email=? AND doctor_email=?
    ");
    $stmt->bind_param("ss",$email,$doc);
    $stmt->execute();
}

header("Location: profilp.php");
exit();
?>