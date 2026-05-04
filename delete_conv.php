<?php
session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error){
    echo json_encode(['success'=>false,'error'=>'DB error']);
    exit;
}

$me   = $_SESSION['email'] ?? "";
$conv = (int)($_POST['conv'] ?? 0);

if(!$me || !$conv){
    echo json_encode(['success'=>false,'error'=>'Missing data']);
    exit;
}

/* Verify this conversation belongs to $me */
$stmt = $conn->prepare("SELECT user1, user2 FROM conversations WHERE id=?");
$stmt->bind_param("i", $conv);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if(!$row){
    echo json_encode(['success'=>false,'error'=>'Conversation not found']);
    exit;
}

/* Soft-delete only for this user (like Messenger) */
if($row['user1'] === $me){
    $upd = $conn->prepare("UPDATE conversations SET deleted_by_user1=1 WHERE id=?");
} elseif($row['user2'] === $me){
    $upd = $conn->prepare("UPDATE conversations SET deleted_by_user2=1 WHERE id=?");
} else {
    echo json_encode(['success'=>false,'error'=>'Not authorized']);
    exit;
}

/* Mark messages as deleted for this user using the deleted_for JSON column */
$msgs = $conn->prepare("SELECT id, deleted_for FROM messages WHERE conversation_id=?");
$msgs->bind_param("i", $conv);
$msgs->execute();
$msgRows = $msgs->get_result();

while($msgRow = $msgRows->fetch_assoc()){
    $deletedFor = json_decode($msgRow['deleted_for'] ?? '[]', true) ?: [];
    if(!in_array($me, $deletedFor)){
        $deletedFor[] = $me;
    }
    $json = json_encode($deletedFor);
    $upMsg = $conn->prepare("UPDATE messages SET deleted_for=? WHERE id=?");
    $upMsg->bind_param("si", $json, $msgRow['id']);
    $upMsg->execute();
}

$upd->bind_param("i", $conv);
$upd->execute();

echo json_encode(['success'=>true]);