<?php
/* get_unread.php – retourne UNIQUEMENT du JSON */
if(session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","tunicare");
$email = $_SESSION['email'] ?? "";

$unread = 0;
if($email){
    $stmt = $conn->prepare("SELECT COUNT(*) as c FROM messages WHERE receiver_email=? AND is_read=0");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $unread = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
}

echo json_encode(['messages' => $unread]);