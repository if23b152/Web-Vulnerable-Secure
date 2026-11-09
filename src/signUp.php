<?php require 'cookies.php'; ?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Sign Up</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
<form action="./db_queries/signUpUser.php" method="post">
  <?= csrf_field() ?>
  <label>
    Username:<br>
    <input type="text" name="username" required minlength="3" maxlength="32"
           pattern="[A-Za-z0-9_.\-]{3,32}"
           title="3-32 Zeichen: Buchstaben, Zahlen, _ . -">
  </label><br><br>

  <label>
    Passwort:<br>
    <input type="password" name="password" required minlength="8" maxlength="72"
           pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]).{8,72}"
           title="Mindestens 8 Zeichen, mit Groß-, Kleinbuchstaben und einer Zahl"
           autocomplete="new-password">
  </label><br><br>

  <label>
    Passwort bestätigen:<br>
    <input type="password" name="password_confirm" required autocomplete="new-password">
  </label><br><br>

  <label>
    E-Mail:<br>
    <input type="email" name="email" required maxlength="255">
    <small>Format: name@domain.tld (z. B. max.mustermann@example.com)</small>
  </label><br><br>

  <button type="submit">Sign Up</button>
</form>

<a href="index.php">Zurück</a>
</body>
</html>
