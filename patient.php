<?php
session_start();

$conn = new mysqli("localhost", "root", "", "tunicare");

if ($conn->connect_error) {
    die("Connection failed");
}

if (
    empty($_POST['p_fname']) ||
    empty($_POST['p_lname']) ||
    empty($_POST['p_age']) ||
    empty($_POST['sex']) ||
    empty($_POST['p_email']) ||
    empty($_POST['p_phone']) ||
    empty($_POST['p_pass'])
) {
    die("❌ All required fields must be filled");
}

$fname = $_POST['p_fname'];
$lname = $_POST['p_lname'];
$age   = (int)$_POST['p_age'];
$kg = !empty($_POST['p_kg']) ? (int)$_POST['p_kg'] : 0;

$sex   = $_POST['sex'];
$email = $_POST['p_email'];
$phone = $_POST['p_phone'];

$history   = isset($_POST['history']) ? implode(",", $_POST['history']) : "";
$allergies = isset($_POST['allergies']) ? implode(",", $_POST['allergies']) : "";

$doctor_id = !empty($_POST['p_doctor']) ? (int)$_POST['p_doctor'] : 0;

$password = password_hash($_POST['p_pass'], PASSWORD_DEFAULT);

/* 1️⃣ INSERT patient */
$stmt = $conn->prepare("
INSERT INTO patients 
(fname, lname, age, kg, sex, email, phone, history, allergies, doctor_id, password)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
"ssissssssss",
$fname,
$lname,
$age,
$kg,
$sex,
$email,
$phone,
$history,
$allergies,
$doctor_id,
$password
);

if ($stmt->execute()) {

   
    if ($doctor_id) {

        $getDoc = $conn->prepare("SELECT email FROM doctors WHERE id=?");
        $getDoc->bind_param("i", $doctor_id);
        $getDoc->execute();
        $doc = $getDoc->get_result()->fetch_assoc();

        if ($doc) {

            
            $rel = $conn->prepare("
                INSERT INTO patient_doctors (patient_email, doctor_email)
                VALUES (?, ?)
            ");

            $rel->bind_param("ss", $email, $doc['email']);
            $rel->execute();
        }
    }

    $_SESSION['email'] = $email;
    header("Location: patient/homepatient.php");
    exit();

} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>