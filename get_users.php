<?php
session_start();
$conn = new mysqli("localhost","root","","tunicare");

$me = $_SESSION['email'];
$role = $_SESSION['role'];

if ($role == "doctor") {

    $sql = "
    SELECT patient_email AS email, MAX(created_at) as last_msg
    FROM messages
    WHERE doctor_email='$me'
    GROUP BY patient_email
    ORDER BY last_msg DESC
    ";

} else {

    $sql = "
    SELECT doctor_email AS email, MAX(created_at) as last_msg
    FROM messages
    WHERE patient_email='$me'
    GROUP BY doctor_email
    ORDER BY last_msg DESC
    ";
}

$res = $conn->query($sql);

$data = [];

while($row = $res->fetch_assoc()){
    $data[] = $row;
}

echo json_encode($data);