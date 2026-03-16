<?php
require '../cookies.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../signup.php');
    exit;
}

// POST-Daten
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$password_confirm = $_POST['password_confirm'] ?? '';
$email = trim($_POST['email'] ?? '');

// Validierungen
$errors = [];

if ($username === '') {
    $errors[] = 'Username ist erforderlich.';
}

if ($password === '') {
    $errors[] = 'Passwort ist erforderlich.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
} elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)) {
    $errors[] = 'Passwort muss Groß- und Kleinbuchstaben enthalten.';
}

if ($password !== $password_confirm) {
    $errors[] = 'Passwörter stimmen nicht überein.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-Mail ist ungültig.';
}

if ($errors) {
    foreach ($errors as $err) {
        echo '<p style="color:red;">' . htmlspecialchars($err) . '</p>';
    }
    echo '<p><a href="../signup.php">Zurück</a></p>';
    exit;
}

// Passwort hashen
$pwdHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $stmt = $conn->prepare("INSERT INTO `user` (username, `password`, email) VALUES (:u, :p, :e)");
    $stmt->execute([
        ':u' => $username,
        ':p' => $pwdHash,
        ':e' => $email
    ]);

    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    $_SESSION['logged_in'] = true;

    header('Location: ../index.php');
    exit;

} catch (PDOException $ex) {
    $msg = $ex->getMessage();
    if ($ex->getCode() === '23000' || strpos($msg, '1062') !== false) {
        echo '<p style="color:red;">Fehler: Benutzername oder E-Mail existiert bereits.</p>';
    } else {
        echo '<p style="color:red;">Datenbankfehler: ' . htmlspecialchars($msg) . '</p>';
    }
    echo '<p><a href="../signup.php">Zurück</a></p>';
} finally {
    $conn = null;
}
