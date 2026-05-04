<?php
session_start();
$conn = new mysqli("localhost","root","","tunicare");

$email = $_SESSION['email'];
$type = $_POST['type'];

if($type == "personal"){
    $stmt = $conn->prepare("
        UPDATE patients 
        SET fname=?, lname=?, age=?, kg=?, sex=? 
        WHERE email=?
    ");
    $stmt->bind_param("ssisss",
        $_POST['fname'],
        $_POST['lname'],
        $_POST['age'],
        $_POST['kg'],
        $_POST['sex'],
        $email
    );
}

if($type == "contact"){
    $stmt = $conn->prepare("
        UPDATE patients 
        SET email=?, phone=? 
        WHERE email=?
    ");
    $stmt->bind_param("sss",
        $_POST['email'],
        $_POST['phone'],
        $email
    );
}


if($type == "history"){

    $history = isset($_POST['history']) ? implode(",", $_POST['history']) : "";

    $stmt = $conn->prepare("UPDATE patients SET history=? WHERE email=?");
    $stmt->bind_param("ss", $history, $email);
    $stmt->execute();
}

if($type == "allergies"){

    $allergies = isset($_POST['allergies']) ? implode(",", $_POST['allergies']) : "";

    $stmt = $conn->prepare("UPDATE patients SET allergies=? WHERE email=?");
    $stmt->bind_param("ss", $allergies, $email);
    $stmt->execute();
}


if($type == "doctors"){

    $conn->query("DELETE FROM patient_doctors WHERE patient_email='$email'");

    if(!empty($_POST['doctors'])){
        foreach($_POST['doctors'] as $doc_id){
            $stmt = $conn->prepare("
                INSERT INTO patient_doctors (patient_email, doctor_id)
                VALUES (?, ?)
            ");
            $stmt->bind_param("si", $email, $doc_id);
            $stmt->execute();
        }
    }

}
if(isset($stmt)){
    $stmt->execute();
}

header("Location: profilp.php");
?>