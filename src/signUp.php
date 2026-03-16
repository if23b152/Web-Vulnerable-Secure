<link rel="stylesheet" href="style.css">
<form action="./db_queries/signUpUser.php" method="post">
  <label>
    Username:<br>
    <input type="text" name="username" required>
  </label><br><br>

  <label>
    Passwort:<br>
    <input type="password" name="password" required minlength="8"
           pattern="(?=.*[a-z])(?=.*[A-Z]).{8,}"
           title="Mindestens 8 Zeichen, mit Groß- und Kleinbuchstaben">
  </label><br><br>

  <label>
    Passwort bestätigen:<br>
    <input type="password" name="password_confirm" required>
  </label><br><br>

  <label>
    E-Mail:<br>
    <input type="email" name="email" required>
    <small>Format: name@domain.tld (z. B. max.mustermann@example.com)</small>
  </label><br><br>

  <button type="submit">Sign Up</button>
</form>

<a href="index.php">Zurück</a>
