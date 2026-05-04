<?php
session_start();

$conn = new mysqli("localhost","root","","tunicare");
if($conn->connect_error) exit;

$me   = $_SESSION['email'] ?? "";
$conv = isset($_GET['conv']) ? (int)$_GET['conv'] : 0;
if(!$me || !$conv) exit;

/* Verify conversation belongs to $me */
$stmt = $conn->prepare("SELECT user1, user2 FROM conversations WHERE id=?");
$stmt->bind_param("i",$conv);
$stmt->execute();
$c = $stmt->get_result()->fetch_assoc();
if(!$c) exit;

/* Mark messages as read */
$upd = $conn->prepare("UPDATE messages SET is_read=1 WHERE conversation_id=? AND receiver_email=? AND is_read=0");
$upd->bind_param("is",$conv,$me);
$upd->execute();

/* Load messages */
$stmt = $conn->prepare("
SELECT sender_email, message, created_at 
FROM messages 
WHERE conversation_id=?
AND (
    (sender_email=? AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL))
    OR
    (receiver_email=? AND (deleted_for IS NULL OR JSON_SEARCH(deleted_for, 'one', ?) IS NULL))
)
ORDER BY created_at ASC
");
$stmt->bind_param("issss", $conv, $me, $me, $me, $me);
$stmt->execute();
$res = $stmt->get_result();

$lastDate = null;
while($m = $res->fetch_assoc()):
    $isMe    = ($m['sender_email'] === $me);
    $class   = $isMe ? "me" : "other";
    $time    = date("H:i", strtotime($m['created_at']));
    $msgDate = date("Y-m-d", strtotime($m['created_at']));

    /* Date separator */
    if($msgDate !== $lastDate){
        $lastDate = $msgDate;
        $label = ($msgDate === date("Y-m-d")) ? "Today" : date("d M Y", strtotime($msgDate));
        echo '<div class="date-sep"><span>'.htmlspecialchars($label).'</span></div>';
    }
?>
<div class="msg-wrap <?= $class ?>">
    <div class="msg <?= $class ?>">
        <?= htmlspecialchars($m['message']) ?>
        <span class="msg-time"><?= $time ?></span>
    </div>
</div>
<?php endwhile; ?>