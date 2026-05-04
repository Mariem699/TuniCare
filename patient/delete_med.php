<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

if(empty($_SESSION['email'])){
    header("Location: login.html");
    exit();
}

$id = $_POST['id'] ?? 0;

$stmt = $conn->prepare("
UPDATE medications 
SET is_active = 0 
WHERE id=? AND email=?
");

$stmt->bind_param("is",$id,$_SESSION['email']);
$stmt->execute();

header("Location: medication_profile.php");
exit();
?>