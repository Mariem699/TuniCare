<?php
$conn = new mysqli("localhost", "root", "", "tunicare");


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fname = $_POST['d_fname'] ?? "";
    $lname = $_POST['d_lname'] ?? "";
    $email = $_POST['d_email'] ?? "";
    $phone = $_POST['d_phone'] ?? "";

   
    $speciality = isset($_POST['d_speciality']) 
        ? implode(",", $_POST['d_speciality']) 
        : "";

    
    if (!isset($_POST['d_pass']) || empty($_POST['d_pass'])) {
        die("Password required");
    }

    $password = password_hash($_POST['d_pass'], PASSWORD_DEFAULT);

    
    $stmt = $conn->prepare("INSERT INTO doctors 
    (fname, lname, email, phone, speciality, password)
    VALUES (?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssss",
        $fname,
        $lname,
        $email,
        $phone,
        $speciality,
        $password
    );

    if ($stmt->execute()) {
        header("Location: doctor/homedoctor.php?email=".$email);
    exit();
    } else {
        echo "Erreur: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>