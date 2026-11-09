<?php
require '../cookies.php';
require 'db.php';

const MAX_ATTEMPTS   = 5;
const LOCK_SECONDS   = 120;

function login_fail(string $text): void {
    $_SESSION['flash'] = ['type' => 'error', 'text' => $text];
    header('Location: ../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

if (!csrf_valid()) {
    login_fail('Ungültige Anfrage. Bitte erneut versuchen.');
}

$username = trim((string)($_POST['username'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if ($username === '' || $password === '') {
    login_fail('Bitte Benutzername und Passwort eingeben.');
}

try {
    // Fehlversuche werden serverseitig pro Benutzername UND pro IP gezählt.
    // (Vorher in der Session -> durch Löschen des Cookies trivial umgehbar.)
    $conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (username), INDEX (ip), INDEX (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $cnt = $conn->prepare('SELECT COUNT(*) AS c, TIMESTAMPDIFF(SECOND, MAX(attempted_at), NOW()) AS since_last
                           FROM login_attempts
                           WHERE (username = :u OR ip = :ip)
                             AND attempted_at > (NOW() - INTERVAL ' . LOCK_SECONDS . ' SECOND)');
    $cnt->execute([':u' => $username, ':ip' => $ip]);
    $state = $cnt->fetch(PDO::FETCH_ASSOC);

    if ((int)$state['c'] >= MAX_ATTEMPTS) {
        $_SESSION['lock_until'] = time() + max(1, LOCK_SECONDS - (int)$state['since_last']);
        login_fail('Zu viele Loginversuche. Bitte ' . LOCK_SECONDS . ' Sekunden warten.');
    }

    $stmt = $conn->prepare('SELECT user_id, username, `password`, email FROM `user` WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Auch bei unbekanntem User einen Hash prüfen -> kein Timing-Unterschied (User Enumeration)
    $hash = $row['password'] ?? password_hash('dummy-password-for-timing', PASSWORD_DEFAULT);
    $ok   = password_verify($password, $hash) && $row;

    if ($ok) {
        $del = $conn->prepare('DELETE FROM login_attempts WHERE username = :u OR ip = :ip');
        $del->execute([':u' => $username, ':ip' => $ip]);

        if (password_needs_rehash($row['password'], PASSWORD_DEFAULT)) {
            $up = $conn->prepare('UPDATE `user` SET `password` = :p WHERE user_id = :id');
            $up->execute([':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $row['user_id']]);
        }

        session_regenerate_id(true);
        unset($_SESSION['lock_until'], $_SESSION['flash'], $_SESSION['loginattempt']);
        $_SESSION['user_id']   = (int)$row['user_id'];
        $_SESSION['username']  = $row['username'];
        $_SESSION['email']     = $row['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['csrf']      = bin2hex(random_bytes(32));

        $_SESSION['__created_at']  = time();
        $_SESSION['__last_active'] = time();
        $_SESSION['__last_regen']  = time();

        header('Location: ../index.php');
        exit;
    }

    $ins = $conn->prepare('INSERT INTO login_attempts (username, ip) VALUES (:u, :ip)');
    $ins->execute([':u' => mb_substr($username, 0, 100), ':ip' => $ip]);

    $remainingAttempts = max(0, MAX_ATTEMPTS - ((int)$state['c'] + 1));
    if ($remainingAttempts === 0) {
        $_SESSION['lock_until'] = time() + LOCK_SECONDS;
        login_fail('Zu viele Loginversuche. Bitte ' . LOCK_SECONDS . ' Sekunden warten.');
    }

    login_fail("Benutzername oder Passwort ist falsch. Verbleibende Versuche: {$remainingAttempts}");

} catch (PDOException $ex) {
    error_log('Login error: ' . $ex->getMessage());
    login_fail('Interner Fehler. Bitte später erneut versuchen.');
} finally {
    $conn = null;
}
