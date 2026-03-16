<?php
require 'cookies.php';

// Countdown falls gelockt
$locked = !empty($_SESSION['lock_until']) && time() < $_SESSION['lock_until'];
$remaining = $locked ? ($_SESSION['lock_until'] - time()) : 0;

// Flash-Message einmalig anzeigen
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
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

  <?php if ($flash): ?>
    <div class="alert <?= htmlspecialchars($flash['type']) ?>">
      <?= htmlspecialchars($flash['text']) ?>
    </div>
  <?php endif; ?>

  <?php if ($locked): ?>
    <p class="alert error">Zu viele Loginversuche. Bitte warten: <span id="count"><?= (int)$remaining ?></span> Sekunden</p>
    <p class="muted">Die Seite aktualisiert sich automatisch, sobald die Sperre vorbei ist.</p>

<script>
  let remaining = <?= (int)$remaining ?>;
  const el = document.getElementById('count');
  const intv = setInterval(() => {
    remaining = Math.max(0, remaining - 1);
    el.textContent = remaining;
    if (remaining <= 0) {
      clearInterval(intv);
      location.reload();
    }
  }, 1000);

  document.getElementById('resetBtn').addEventListener('click', async (e) => {
    e.preventDefault();
    try {
      const res = await fetch('./db_queries/reset_attempts.php', {
        method: 'POST',
        headers: { 'Cache-Control': 'no-cache' },
      });
      const json = await res.json();
      if (json.ok) {
        clearInterval(intv);
        location.reload();
      } else {
        alert('Reset fehlgeschlagen.');
      }
    } catch (err) {
      alert('Error');
    }
  });
</script>

  <?php 
    else: ?>
    <form action="/db_queries/loginUser.php" method="post">
      <label>Username<br>
        <input type="text" name="username" required>
      </label><br><br>

      <label>Passwort<br>
        <input type="password" name="password" required>
      </label><br><br>

      <button type="submit">Login</button>
    </form>
    <p><a href="index.php">Zurück</a></p>
  <?php endif; ?>

</body>
</html>
