<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");
if ($conn->connect_error) {
    die("DB error");
}

$emailSession = $_SESSION['email'] ?? "";

if(empty($emailSession)){
    header("Location: login.html");
    exit();
}

$type = $_POST['type'] ?? "";

if($type === "name"){
    $fname = $_POST['fname'] ?? "";
    $lname = $_POST['lname'] ?? "";

    $stmt = $conn->prepare("UPDATE doctors SET fname=?, lname=? WHERE email=?");
    $stmt->bind_param("sss", $fname, $lname, $emailSession);
    $stmt->execute();
}

if($type === "contact"){
    $email = $_POST['email'] ?? "";
    $phone = $_POST['phone'] ?? "";

    $stmt = $conn->prepare("UPDATE doctors SET email=?, phone=? WHERE email=?");
    $stmt->bind_param("sss", $email, $phone, $emailSession);
    $stmt->execute();

    
    $_SESSION['email'] = $email;
}

if($type === "speciality"){
    $speciality = isset($_POST['d_speciality'])
        ? implode(",", array_map('trim', $_POST['d_speciality']))
        : "";
    if(!empty($speciality)){
        $stmt = $conn->prepare("UPDATE doctors SET speciality=? WHERE email=?");
        $stmt->bind_param("ss", $speciality, $emailSession);
        $stmt->execute();
    }
}

header("Location: profild.php");
exit();
?>