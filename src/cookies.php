<?php
if (session_status() === PHP_SESSION_NONE) {

    $lifetime = 3600; 

    session_set_cookie_params([
        'lifetime' => $lifetime,        // 1 Stunde
        'path'     => '/',
        'domain'   => '',              
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    }

    if (time() - $_SESSION['last_activity'] > $lifetime) {
        session_unset();       
        session_destroy();    
        header('Location: login.php?session=expired');
        exit;
    }

    $_SESSION['last_activity'] = time();
}
?>
