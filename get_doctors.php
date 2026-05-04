<?php
$conn = new mysqli("localhost", "root", "", "tunicare");

$data = json_decode(file_get_contents("php://input"), true);

$specialties = $data['specialties'] ?? [];

if (empty($specialties)) {
    echo json_encode([]);
    exit();
}

$specialties = array_map([$conn, 'real_escape_string'], $specialties);

$placeholders = "'" . implode("','", $specialties) . "'";

$sql = "SELECT id, fname, lname, speciality 
        FROM doctors 
        WHERE speciality IN ($placeholders)";

$result = $conn->query($sql);

$output = [];

while ($row = $result->fetch_assoc()) {
    $output[] = [
        "id" => $row["id"],
        "name" => $row["fname"] . " " . $row["lname"],
        "specialty" => $row["speciality"]
    ];
}

echo json_encode($output);
?>