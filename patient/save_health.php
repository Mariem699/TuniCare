<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'] ?? "";
if(empty($email)){
    die("not logged in");
}

$date = date("Y-m-d");


$weight = $_POST['weight'] ?? 0;
$glucose = $_POST['glucose'] ?? 0;
$tension = $_POST['tension'] ?? "";


$stmt = $conn->prepare("SELECT id FROM health_data WHERE email=? AND date=?");
$stmt->bind_param("ss",$email,$date);
$stmt->execute();
$res = $stmt->get_result();

if($res->num_rows > 0){

    $stmt = $conn->prepare("
        UPDATE health_data 
        SET weight=?, glucose=?, tension=? 
        WHERE email=? AND date=?
    ");

    $stmt->bind_param("ddsss",
        $weight,
        $glucose,
        $tension,
        $email,
        $date
    );

    $stmt->execute();

} else {

   
    $stmt = $conn->prepare("
        INSERT INTO health_data(email,date,weight,glucose,tension,water)
        VALUES(?,?,?,?,?,0)
    ");

    $stmt->bind_param("ssdds",
        $email,
        $date,
        $weight,
        $glucose,
        $tension
    );

    $stmt->execute();
}

echo "saved";
exit;