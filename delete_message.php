<?php

if(session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error){
    echo json_encode(['success'=>false,'error'=>'DB error']);
    exit;
}

$me    = $_SESSION['email'] ?? "";
$msgId = (int)($_POST['msg_id'] ?? 0);

if(!$me || !$msgId){
    echo json_encode(['success'=>false,'error'=>'Missing data']);
    exit;
}

/* Verify this message was received by $me */
$stmt = $conn->prepare("SELECT id, deleted_for FROM messages WHERE id=? AND receiver_email=?");
$stmt->bind_param("is", $msgId, $me);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if(!$row){
    echo json_encode(['success'=>false,'error'=>'Message not found or not authorized']);
    exit;
}

/* Add $me to deleted_for JSON array */
$deletedFor = json_decode($row['deleted_for'] ?? '[]', true) ?: [];
if(!in_array($me, $deletedFor)){
    $deletedFor[] = $me;
}
$json = json_encode($deletedFor);

$upd = $conn->prepare("UPDATE messages SET deleted_for=? WHERE id=?");
$upd->bind_param("si", $json, $msgId);
$upd->execute();

echo json_encode(['success'=>true]);