<?php
require '../cookies.php';
require 'db.php';

function signup_errors(array $errors): void {
    foreach ($errors as $err) {
        echo '<p style="color:red;">' . e($err) . '</p>';
    }
    echo '<p><a href="../signUp.php">Zurück</a></p>';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../signUp.php');
    exit;
}

if (!csrf_valid()) {
    signup_errors(['Ungültige Anfrage (CSRF). Bitte Formular neu laden.']);
}

// POST-Daten
$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$password_confirm = (string)($_POST['password_confirm'] ?? '');
$email = trim((string)($_POST['email'] ?? ''));

// Validierungen
$errors = [];

if ($username === '') {
    $errors[] = 'Username ist erforderlich.';
} elseif (!preg_match('/^[A-Za-z0-9_.-]{3,32}$/', $username)) {
    $errors[] = 'Username: 3-32 Zeichen, nur Buchstaben, Zahlen, "_", "." und "-".';
}

if ($password === '') {
    $errors[] = 'Passwort ist erforderlich.';
} elseif (strlen($password) < 8) {
    $errors[] = 'Passwort muss mindestens 8 Zeichen lang sein.';
} elseif (strlen($password) > 72) {
    $errors[] = 'Passwort darf maximal 72 Zeichen lang sein.';
} elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors[] = 'Passwort muss Groß-, Kleinbuchstaben und eine Zahl enthalten.';
}

if (!hash_equals($password, $password_confirm)) {
    $errors[] = 'Passwörter stimmen nicht überein.';
}

if ($email === '' || strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'E-Mail ist ungültig.';
}

if ($errors) {
    signup_errors($errors);
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

    // Neue Session-ID nach Anmeldung (Session Fixation verhindern)
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$conn->lastInsertId();
    $_SESSION['username']  = $username;
    $_SESSION['email']     = $email;
    $_SESSION['logged_in'] = true;
    $_SESSION['csrf']      = bin2hex(random_bytes(32));
    $_SESSION['__last_regen'] = time();

    header('Location: ../index.php');
    exit;

} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        signup_errors(['Benutzername oder E-Mail existiert bereits.']);
    }
    error_log('Signup error: ' . $ex->getMessage());
    signup_errors(['Interner Fehler. Bitte später erneut versuchen.']);
} finally {
    $conn = null;
}
