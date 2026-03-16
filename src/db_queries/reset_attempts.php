<?php
require './cookies.php';

unset($_SESSION['loginattempt'], $_SESSION['lock_until']);

$_SESSION['loginattempt'] = 0;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true]);
