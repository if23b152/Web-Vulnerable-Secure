<?php
require 'cookies.php';
require __DIR__ . '/db_queries/db.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
if (!$userId || empty($_SESSION['logged_in'])) {
  http_response_code(401); exit('Unauthorized');
}

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

// Gleiche Antwort für "existiert nicht" und "gehört dir nicht" -> keine ID-Enumeration
if (!$row || ($userId !== (int)$row['sender_id'] && $userId !== (int)$row['recipient_id'])) {
  http_response_code(404); exit('Not found');
}

$file = find_upload($row['stored_name']);
if ($file === null) { http_response_code(404); exit('Not found'); }

$allowed = ['application/pdf','image/png','image/jpeg','text/plain'];
$mime = in_array($row['mime_type'], $allowed, true) ? $row['mime_type'] : 'application/octet-stream';

// Nur Bilder/PDF inline anzeigen, alles andere immer als Download
if ($disp === 'inline' && !in_array($mime, ['application/pdf','image/png','image/jpeg'], true)) {
  $disp = 'attachment';
}

$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $row['orig_name']);

header('X-Content-Type-Options: nosniff');
// PDF-Viewer der Browser funktionieren nicht im sandbox-Modus
header($mime === 'application/pdf'
  ? "Content-Security-Policy: default-src 'none'; object-src 'self'; frame-ancestors 'self'"
  : "Content-Security-Policy: default-src 'none'; img-src 'self'; sandbox");
header('Cache-Control: private, no-store');
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: ' . $disp . '; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($row['orig_name']));

readfile($file);
exit;
