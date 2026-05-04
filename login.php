<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$email = $_POST['email'] ?? "";
$password = $_POST['password'] ?? "";
$role = $_POST['role'] ?? "";


if ($role != "doctor" && $role != "patient") {
    die("Invalid role");
}


if ($role == "doctor") {
    $stmt = $conn->prepare("SELECT * FROM doctors WHERE email = ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM patients WHERE email = ?");
}

$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        
        $_SESSION['email'] = $user['email'];

        if ($role == "doctor") {
            header("Location: doctor/homedoctor.php");
        } else {
            header("Location: patient/homepatient.php");
        }
        exit();

    } else {
        echo "❌ Wrong password";
    }

} else {
    echo "❌ Email not found! Create an account.";
}

$stmt->close();
$conn->close();
?>