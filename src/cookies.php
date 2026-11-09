<?php
// Zentrale Session- und Sicherheitskonfiguration (wird von jeder Seite eingebunden)

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

if (session_status() === PHP_SESSION_NONE) {

    $lifetime = 3600;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_set_cookie_params([
        'lifetime' => $lifetime,        // 1 Stunde
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    session_start();

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    if (time() - $_SESSION['last_activity'] > $lifetime) {
        session_unset();
        session_destroy();
        header('Location: /login.php?session=expired');
        exit;
    }

    $_SESSION['last_activity'] = time();

    // Session-ID regelmäßig erneuern (Session Fixation / Hijacking erschweren)
    if (!empty($_SESSION['logged_in'])) {
        if (!isset($_SESSION['__last_regen']) || time() - $_SESSION['__last_regen'] > 900) {
            session_regenerate_id(true);
            $_SESSION['__last_regen'] = time();
        }
    }
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8') . '">';
    }

    function csrf_valid(): bool {
        $token = $_POST['csrf'] ?? '';
        return is_string($token) && $token !== '' && !empty($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    // Upload-Verzeichnis: bevorzugt außerhalb des Webroots (APP_UPLOAD_DIR), sonst src/uploads (per .htaccess gesperrt)
    function upload_dir(): string {
        $env = rtrim((string)getenv('APP_UPLOAD_DIR'), '/');
        if ($env !== '' && is_dir($env) && is_writable($env)) {
            return $env;
        }
        return __DIR__ . '/uploads';
    }

    function find_upload(string $stored): ?string {
        if (!preg_match('/^[a-f0-9]{32}-[a-f0-9]{16}$/', $stored)) {
            return null; // nur von uns generierte Namen -> kein Path Traversal
        }
        $env = rtrim((string)getenv('APP_UPLOAD_DIR'), '/');
        foreach (array_filter([$env, __DIR__ . '/uploads']) as $dir) {
            $p = $dir . DIRECTORY_SEPARATOR . $stored;
            if (is_file($p)) {
                return $p;
            }
        }
        return null;
    }

    function require_login(): void {
        if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
            header('Location: /login.php');
            exit;
        }
    }
}
