<?php
require './cookies.php';

require __DIR__ . '/db.php';  

if (empty($_SESSION['user_id'])) {
    $_SESSION['emails'] = [];
    return;
}

$userId = (int)$_SESSION['user_id'];

$sql = "
    SELECT e.email_id,
           u.username AS sender_name,
           e.subject,
           e.body,
           e.sent_at
    FROM emails e
    JOIN user u ON e.sender_id = u.user_id
    WHERE e.recipient_id = :uid
    ORDER BY e.sent_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute([':uid' => $userId]); 
$_SESSION['emails'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
