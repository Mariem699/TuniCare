<?php
/* get_conv_unread.php
 * Retourne un objet JSON { "peer_email": unread_count, ... }
 * pour mettre à jour les badges bleus par conversation dans chat.php
 */
if(session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

$conn = new mysqli("localhost","root","","tunicare");
$me   = $_SESSION['email'] ?? "";

if(!$me){ echo '{}'; exit; }

$meEsc = mysqli_real_escape_string($conn, $me);

/* Récupère toutes les conversations visibles + le nombre de messages non lus par peer */
$result = $conn->query("
    SELECT
        CASE WHEN c.user1 = '$meEsc' THEN c.user2 ELSE c.user1 END AS peer,
        COUNT(m.id) AS unread
    FROM conversations c
    LEFT JOIN messages m
        ON m.conversation_id = c.id
        AND m.receiver_email = '$meEsc'
        AND m.is_read = 0
    WHERE
        (c.user1 = '$meEsc' AND c.deleted_by_user1 = 0)
        OR
        (c.user2 = '$meEsc' AND c.deleted_by_user2 = 0)
    GROUP BY peer
");

$data = [];
if($result){
    while($row = $result->fetch_assoc()){
        if($row['peer']) $data[$row['peer']] = (int)$row['unread'];
    }
}

echo json_encode($data);