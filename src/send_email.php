<?php
require 'cookies.php';
require __DIR__ . '/db_queries/db.php';

// Uploads möglichst außerhalb des Webroots ablegen (APP_UPLOAD_DIR aus docker-compose)
$UPLOAD_DIR  = upload_dir();
$MAX_FILES   = 3;
$MAX_BYTES   = 10 * 1024 * 1024;       
$ALLOWED_MIME = ['application/pdf','image/png','image/jpeg','text/plain'];
$ALLOWED_EXT  = [
    'application/pdf' => ['pdf'],
    'image/png'       => ['png'],
    'image/jpeg'      => ['jpg', 'jpeg'],
    'text/plain'      => ['txt'],
];

function fail_redirect(string $msg, string $back = 'compose.php', array $old = []): void {
    $_SESSION['flash'] = $msg;
    if ($old) $_SESSION['old'] = $old;
    header('Location: ' . $back);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: compose.php');
    exit;
}

$senderId = (int)($_SESSION['user_id'] ?? 0);
if (!$senderId || empty($_SESSION['logged_in'])) {
    fail_redirect('Bitte zuerst einloggen.', 'login.php');
}

// CSRF-Token wird IMMER geprüft (vorher nur, wenn bereits ein Token in der Session lag)
if (!csrf_valid()) {
    fail_redirect('Ungültiger CSRF-Token.');
}

$toUsername = trim((string)($_POST['to'] ?? ''));
$subject    = trim((string)($_POST['subject'] ?? ''));
$body       = trim((string)($_POST['body'] ?? ''));

if ($toUsername === '' || $subject === '' || $body === '') {
    fail_redirect('Bitte alle Pflichtfelder ausfüllen.', 'compose.php', [
        'to' => $toUsername, 'subject' => $subject, 'body' => $body
    ]);
}

if (mb_strlen($subject) > 255 || mb_strlen($body) > 20000) {
    fail_redirect('Betreff (max. 255) oder Nachricht (max. 20000 Zeichen) zu lang.', 'compose.php', [
        'to' => $toUsername, 'subject' => mb_substr($subject, 0, 255), 'body' => mb_substr($body, 0, 20000)
    ]);
}

try {
    $st = $conn->prepare('SELECT user_id FROM `user` WHERE username = :u LIMIT 1');
    $st->execute([':u' => $toUsername]);
    $rec = $st->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        fail_redirect('Empfänger nicht gefunden.', 'compose.php', [
            'to' => $toUsername, 'subject' => $subject, 'body' => $body
        ]);
    }
    $recipientId = (int)$rec['user_id'];
} catch (PDOException $e) {
    error_log('send_email recipient lookup: ' . $e->getMessage());
    fail_redirect('Interner Fehler. Bitte später erneut versuchen.');
}

if (!is_dir($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0700, true) && !is_dir($UPLOAD_DIR)) {
        fail_redirect('Upload-Verzeichnis konnte nicht erstellt werden.');
    }
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$filesOk = [];

if (!empty($_FILES['files']['name']) && is_array($_FILES['files']['name'])) {
    $nonEmpty = array_filter($_FILES['files']['name'], fn($n) => $n !== '');
    if (count($nonEmpty) > $MAX_FILES) {
        fail_redirect('Zu viele Dateien. Maximal ' . $MAX_FILES . '.',
            'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
    }

    $n = count($_FILES['files']['name']);
    for ($i = 0; $i < $n; $i++) {
        $err = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;

        $orig = $_FILES['files']['name'][$i] ?? ('file_'.$i);
        $tmp  = $_FILES['files']['tmp_name'][$i] ?? '';
        $size = (int)($_FILES['files']['size'][$i] ?? 0);

        if ($err !== UPLOAD_ERR_OK) {
            fail_redirect("Upload-Fehler bei „{$orig}“ (Code {$err}).",
                'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }
        if ($size > $MAX_BYTES) {
            fail_redirect("Datei zu groß: „{$orig}“ (max 10 MB).",
                'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }
        if (!is_uploaded_file($tmp)) {
            fail_redirect('Ungültiger Upload.', 'compose.php',
                ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }
        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, $ALLOWED_MIME, true)) {
            fail_redirect("Dateityp nicht erlaubt: „{$orig}“ ({$mime}).",
                'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }

        // Dateiendung muss zum erkannten MIME-Typ passen (z. B. keine .php mit PNG-Header)
        $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if (!in_array($ext, $ALLOWED_EXT[$mime] ?? [], true)) {
            fail_redirect("Dateiendung passt nicht zum Inhalt: „{$orig}“.",
                'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }

        // Originalnamen säubern (Steuerzeichen, Pfadanteile, Quotes entfernen)
        $cleanName = preg_replace('/[\x00-\x1F\x7F"\\\\\/]/u', '_', basename($orig));
        $cleanName = mb_substr($cleanName ?: ('file_'.$i.'.'.$ext), 0, 200);

        $filesOk[] = [
            'tmp'  => $tmp,
            'orig' => $cleanName,
            'size' => $size,
            'mime' => $mime,
        ];
    }
}

$storedPaths = [];
try {
    $conn->beginTransaction();

    $insMail = $conn->prepare('INSERT INTO emails
        (sender_id, recipient_id, subject, body, sent_at, status)
        VALUES (:s, :r, :sub, :body, NOW(), :status)');
    $insMail->execute([
        ':s' => $senderId,
        ':r' => $recipientId,
        ':sub' => $subject,
        ':body' => $body,
        ':status' => 'sent'
    ]);
    $emailId = (int)$conn->lastInsertId();

    foreach ($filesOk as $f) {
        $sha    = hash_file('sha256', $f['tmp']);
        $stored = bin2hex(random_bytes(16)) . '-' . substr($sha, 0, 16);
        $dest   = $UPLOAD_DIR . DIRECTORY_SEPARATOR . $stored;

        if (!move_uploaded_file($f['tmp'], $dest)) {
            throw new RuntimeException('move_uploaded_file fehlgeschlagen bei „'.$f['orig'].'“');
        }
        @chmod($dest, 0600);
        $storedPaths[] = $dest;

        $insAtt = $conn->prepare('INSERT INTO email_attachments
            (email_id, owner_id, orig_name, stored_name, mime_type, size_bytes, sha256)
            VALUES (:e,:o,:orig,:stored,:mime,:size,:sha)');
        $insAtt->execute([
            ':e' => $emailId,
            ':o' => $senderId,
            ':orig' => $f['orig'],
            ':stored' => $stored,
            ':mime' => $f['mime'],
            ':size' => $f['size'],
            ':sha' => $sha
        ]);
    }

    $conn->commit();
    $_SESSION['flash'] = 'E-Mail erfolgreich gesendet' . (count($filesOk) ? ' (inkl. Anhänge).' : '.');
    unset($_SESSION['old']);
    header('Location: index.php');
    exit;

} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    foreach ($storedPaths as $p) { @unlink($p); }
    error_log('send_email failed: ' . $e->getMessage());
    fail_redirect('Fehler beim Senden. Bitte erneut versuchen.',
        'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
}
