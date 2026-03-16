<?php
require 'db.php';

$stmt = $conn->prepare("SELECT username FROM user");
$stmt->execute();

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($rows) {
    foreach ($rows as $row) {
        echo "username: " . htmlspecialchars($row['username']) . "<br>";
    }
} else {
    echo "0 results";
}
