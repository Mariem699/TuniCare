<?php
session_start();


if(!isset($_SESSION['email']) || empty($_SESSION['email'])){
    header("Location: ../login.html");
    exit();
}

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error){
    die("DB error");
}

$email = $_SESSION['email'];
$now = date("H:i");

$stmt = $conn->prepare("
SELECT name 
FROM medications
WHERE email=?
AND (
    DATE_FORMAT(time_take,'%H:%i') = ?
    OR DATE_FORMAT(time2,'%H:%i') = ?
    OR DATE_FORMAT(time3,'%H:%i') = ?
)
LIMIT 1
");

$stmt->bind_param("ssss", $email, $now, $now, $now);
$stmt->execute();

$res = $stmt->get_result();

if($row = $res->fetch_assoc()){
    echo $row['name'];
}
?>