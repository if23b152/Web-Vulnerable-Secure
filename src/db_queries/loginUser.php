<?php
session_start();
require 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    $_SESSION['flash'] = ['type' => 'error', 'text' => 'Bitte Benutzername und Passwort eingeben.'];
    header('Location: ../login.php');
    exit;
}

$_SESSION['loginattempt'] = $_SESSION['loginattempt'] ?? 0;

if (!empty($_SESSION['lock_until']) && time() < $_SESSION['lock_until']) {
    $remaining = $_SESSION['lock_until'] - time();
    $_SESSION['flash'] = ['type'=>'error', 'text'=>"Zu viele Loginversuche. Bitte noch {$remaining} Sekunden warten."];
    header('Location: ../login.php');
    exit;
}

try {
    $stmt = $conn->prepare('SELECT user_id, username, `password`, email FROM `user` WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && password_verify($password, $row['password'])) {
session_regenerate_id(true);     
$_SESSION['user_id']   = $row['user_id'];
$_SESSION['username']  = $row['username'];
$_SESSION['email']     = $row['email'];
$_SESSION['logged_in'] = true;

$_SESSION['__created_at']  = time();
$_SESSION['__last_active'] = time();
$_SESSION['__last_regen']  = time();

header('Location: ../index.php');
exit;


        $_SESSION['loginattempt'] = 0;
        unset($_SESSION['lock_until'], $_SESSION['flash']);

        header('Location: ../index.php');
        exit;
    }

    $_SESSION['loginattempt']++;
    $remainingAttempts = max(0, 5 - $_SESSION['loginattempt']);

    if ($_SESSION['loginattempt'] > 5) {
        $_SESSION['lock_until'] = time() + 120;
        $_SESSION['flash'] = [
            'type' => 'error',
            'text' => 'Zu viele Loginversuche. Bitte 120 Sekunden warten.'
        ];
        $_SESSION['loginattempt'] = 0;
    } else {
        $_SESSION['flash'] = [
            'type' => 'error',
            'text' => "Benutzername oder Passwort ist falsch. Verbleibende Versuche: {$remainingAttempts}"
        ];
    }

    header('Location: ../login.php');
    exit;

} catch (PDOException $ex) {
    $_SESSION['flash'] = ['type'=>'error', 'text'=>'Datenbankfehler: '.htmlspecialchars($ex->getMessage())];
    header('Location: ../login.php');
    exit;
} finally {
    $conn = null;
}
