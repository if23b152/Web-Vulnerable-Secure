<?php
require 'cookies.php';
require './db_queries/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (is_array($flash)) {
    $flash = $flash['text'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TryToHackMe</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>Hey! Try to hack this website!</h1>
  <h2>What do you want to do?</h2>

  <?php if ($flash): ?>
    <div class="alert"><?= e($flash) ?></div>
  <?php endif; ?>

  <?php
    if(!empty($_SESSION['logged_in'])):
  ?>
  <p>Username: <?= e($_SESSION['username'] ?? '') ?></p>
  <br>
  <p>E-mail: <?= e($_SESSION['email'] ?? '') ?></p>
  <form action="logout.php" method="post">
    <?= csrf_field() ?>
    <button type="submit">Log out</button>
  </form>

  <?php
    else:
  ?>

  <h3>Please sign up/log in to view your emails</h3>
    <form action="signUp.php">
  <button type="submit">Sign Up</button>
  </form>
  <br><br>
  <form action="login.php">
  <button type="submit">Log in</button>
  </form>

  <?php
    endif;
  ?>
  <?php
    if(!empty($_SESSION['logged_in'])):
  ?>
    <a href="compose.php">Klicke hier um Emails zu verfassen!</a>
  <?php endif;?>

  <?php
      include 'emails.php';
  ?>


</body>
</html>
