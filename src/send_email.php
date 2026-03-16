<?php
require 'cookies.php';
require __DIR__ . '/db_queries/db.php';

$UPLOAD_DIR  = __DIR__ . '/uploads';
$MAX_FILES   = 3;
$MAX_BYTES   = 10 * 1024 * 1024;       
$ALLOWED_MIME = ['application/pdf','image/png','image/jpeg','text/plain'];

function fail_redirect(string $msg, string $back = 'compose.php', array $old = []): void {
    $_SESSION['flash'] = $msg;
    if ($old) $_SESSION['old'] = $old;
    header('Location: ' . $back);
    exit;
}

if (!empty($_SESSION['csrf'])) {
    $csrf = $_POST['csrf'] ?? '';
    if (!$csrf || !hash_equals($_SESSION['csrf'], $csrf)) {
        fail_redirect('Ungültiger CSRF-Token.');
    }
}

$senderId = $_SESSION['user_id'] ?? null;
if (!$senderId) {
    fail_redirect('Bitte zuerst einloggen.');
}

$toUsername = trim($_POST['to'] ?? '');
$subject    = trim($_POST['subject'] ?? '');
$body       = trim($_POST['body'] ?? '');

if ($toUsername === '' || $subject === '' || $body === '') {
    fail_redirect('Bitte alle Pflichtfelder ausfüllen.', 'index.php', [
        'to' => $toUsername, 'subject' => $subject, 'body' => $body
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
    fail_redirect('DB-Fehler (Empfänger): ' . htmlspecialchars($e->getMessage()));
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
        $mime = $finfo->file($tmp) ?: 'application/octet-stream';
        if (!in_array($mime, $ALLOWED_MIME, true)) {
            fail_redirect("Dateityp nicht erlaubt: „{$orig}“ ({$mime}).",
                'compose.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
        }

        $filesOk[] = [
            'tmp'  => $tmp,
            'orig' => basename($orig),
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
    $conn->rollBack();
    foreach ($storedPaths as $p) { @unlink($p); }
    fail_redirect('Fehler beim Senden: ' . htmlspecialchars($e->getMessage()),
        'index.php', ['to'=>$toUsername,'subject'=>$subject,'body'=>$body]);
}
