<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

if(empty($_SESSION['email'])){
    header("Location: login.html");
    exit();
}

$email = $_SESSION['email'];

$id = $_POST['id'];
$name = $_POST['name'];
$dosage = $_POST['dosage'];

$t1 = !empty($_POST['time1']) ? $_POST['time1'] : null;
$t2 = !empty($_POST['time2']) ? $_POST['time2'] : null;
$t3 = !empty($_POST['time3']) ? $_POST['time3'] : null;

$start = $_POST['start_date'];
$duration = $_POST['duration_days'];

/*  UPDATE  */ 
$stmt = $conn->prepare("
UPDATE medications
SET name=?, dosage=?, time_take=?, time2=?, time3=?, start_date=?, duration_days=?
WHERE id=? AND email=?
");

$stmt->bind_param(
    "ssssssiss",
    $name,$dosage,$t1,$t2,$t3,$start,$duration,$id,$email
);

$stmt->execute();

header("Location: medication_profile.php");
exit();
?>