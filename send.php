<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");

$me   = $_SESSION['email'] ?? "";
$msg  = trim($_POST['msg'] ?? "");
$conv = (int)($_POST['conv'] ?? 0);

if(!$me || !$msg || !$conv) exit;

/* get receiver */
$stmt = $conn->prepare("SELECT user1,user2 FROM conversations WHERE id=?");
$stmt->bind_param("i",$conv);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();

$receiver = ($c['user1'] === $me) ? $c['user2'] : $c['user1'];

/* insert message */
$stmt = $conn->prepare("
INSERT INTO messages (conversation_id, sender_email, receiver_email, message, created_at, is_read)
VALUES (?, ?, ?, ?, NOW(), 0)
");
$stmt->bind_param("isss", $conv, $me, $receiver, $msg);
$stmt->execute();

/* 🔥 UPDATE conversation cache (IMPORTANT) */
$stmt = $conn->prepare("
UPDATE conversations 
SET last_message=?, last_time=NOW()
WHERE id=?
");
$stmt->bind_param("si", $msg, $conv);
$stmt->execute();

echo "ok";