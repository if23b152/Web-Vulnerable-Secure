<?php
$host = getenv('DB_HOST') ?: 'db';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'websec';
$user = getenv('DB_USER') ?: 'websec_user';
$pass = getenv('DB_PASS') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
  $conn = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
  echo "Connection failed: " . $e->getMessage();
  exit;
}
