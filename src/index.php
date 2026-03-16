<?php
require 'cookies.php';
  require './db_queries/db.php';
  $username = isset($_POST['username']) ? trim($_POST['username']) : '';
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $email    = isset($_POST['email']) ? trim($_POST['email']) : null;
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

  <?php
    if(!empty($_SESSION['logged_in'])):
  ?>
  <p>Username: <?php echo $_SESSION['username'];?></p>
  <br>
  <p>E-mail: <?php echo $_SESSION['email'];?></p>
  <form action="logout.php" method="post">
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
