<?php
require 'cookies.php'; 

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']);
}

session_destroy();

unset($_SESSION['flash'], $_SESSION['old']);

header('Location: /login.php?loggedout=1');
exit;
