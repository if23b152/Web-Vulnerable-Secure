<?php
require 'cookies.php';

if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$old   = $_SESSION['old']   ?? [];  unset($_SESSION['old']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>E-Mail verfassen</title>
    <link rel="stylesheet" href="style.css">

  <style>
    body { font-family: system-ui, Arial, sans-serif; max-width: 720px; margin: 40px auto; }
    label { display:block; margin: 12px 0 6px; }
    input[type=text], textarea { width: 100%; padding: 8px; }
    textarea { min-height: 160px; }
    .alert { padding:10px; border-radius:6px; margin: 10px 0; background:#eef; }
    .btn { margin-top: 12px; padding: 10px 14px; cursor:pointer; }
  </style>
</head>
<body>
  <h1>E-Mail verfassen</h1>

  <?php if ($flash): ?>
    <div class="alert"><?= htmlspecialchars($flash) ?></div>
  <?php endif; ?>

  <form action="send_email.php" method="post" enctype="multipart/form-data" novalidate>
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">

  <label>Empfänger (Username)</label>
  <input type="text" name="to" required value="<?= htmlspecialchars($old['to'] ?? '') ?>">

  <label>Betreff</label>
  <input type="text" name="subject" maxlength="255" required value="<?= htmlspecialchars($old['subject'] ?? '') ?>">

  <label>Nachricht</label>
  <textarea name="body" required><?= htmlspecialchars($old['body'] ?? '') ?></textarea>

  <label>Anhänge (max. 3, je max. 10 MB) — Erlaubt: .pdf, .png, .jpg, .jpeg, .txt</label>
  <input type="file" name="files[]" multiple accept=".pdf,image/png,image/jpeg,text/plain">

  <button class="btn" type="submit">Senden</button>
  <a href="index.php">Zurück</a>
</form>

  <h3>List aller möglichen Empfänger:</h3>
  <?php
    include './db_queries/allUsers.php';
  ?>
</body>
</html>
