<?php
require 'cookies.php';

// Countdown falls gelockt
$locked = !empty($_SESSION['lock_until']) && time() < $_SESSION['lock_until'];
$remaining = $locked ? ($_SESSION['lock_until'] - time()) : 0;

// Flash-Message einmalig anzeigen
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$flashType = (is_array($flash) && ($flash['type'] ?? '') === 'error') ? 'error' : 'info';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Login</title>
  <link rel="stylesheet" href="style.css">

  <style>
    .alert { padding:10px; margin:10px 0; border-radius:6px; }
    .alert.error { background:#ffd6d6; color:#8a0000; }
    .muted { color:#666; font-size:0.9rem; }
  </style>
</head>
<body>
  <h1>Login</h1>

  <?php if (is_array($flash)): ?>
    <div class="alert <?= $flashType ?>">
      <?= e($flash['text'] ?? '') ?>
    </div>
  <?php endif; ?>

  <?php if ($locked): ?>
    <p class="alert error">Zu viele Loginversuche. Bitte warten: <span id="count" data-remaining="<?= (int)$remaining ?>"><?= (int)$remaining ?></span> Sekunden</p>
    <p class="muted">Die Seite aktualisiert sich automatisch, sobald die Sperre vorbei ist.</p>
    <script src="login.js"></script>
  <?php else: ?>
    <form action="/db_queries/loginUser.php" method="post" autocomplete="on">
      <?= csrf_field() ?>
      <label>Username<br>
        <input type="text" name="username" required maxlength="100" autocomplete="username">
      </label><br><br>

      <label>Passwort<br>
        <input type="password" name="password" required autocomplete="current-password">
      </label><br><br>

      <button type="submit">Login</button>
    </form>
    <p><a href="index.php">Zurück</a></p>
  <?php endif; ?>

</body>
</html>
