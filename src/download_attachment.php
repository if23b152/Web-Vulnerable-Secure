<?php
require 'cookies.php';
require __DIR__ . '/db_queries/db.php';

$attId = (int)($_GET['att'] ?? 0);
$disp  = ($_GET['disp'] ?? 'attachment') === 'inline' ? 'inline' : 'attachment';

$sql = "SELECT a.orig_name, a.stored_name, a.mime_type, a.size_bytes,
               e.sender_id, e.recipient_id
        FROM email_attachments a
        JOIN emails e ON e.email_id = a.email_id
        WHERE a.attachment_id = :id";
$stmt = $conn->prepare($sql);
$stmt->execute([':id'=>$attId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Not found'); }

$userId = $_SESSION['user_id'] ?? 0;
if ($userId != $row['sender_id'] && $userId != $row['recipient_id']) {
  http_response_code(403); exit('Forbidden');
}

$UPLOAD_DIR = __DIR__ . '/uploads';
$file = $UPLOAD_DIR . DIRECTORY_SEPARATOR . $row['stored_name'];
if (!is_file($file)) { http_response_code(404); exit('Missing file'); }

header('X-Content-Type-Options: nosniff');
header('Content-Type: ' . $row['mime_type']);
header('Content-Length: ' . $row['size_bytes']);
header('Content-Disposition: '.$disp.'; filename="'.rawurlencode($row['orig_name']).'"');

readfile($file);
exit;
