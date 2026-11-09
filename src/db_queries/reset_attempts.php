<?php
// Dieser Endpoint erlaubte es jedem, die Login-Sperre ohne Authentifizierung
// zurückzusetzen (Brute-Force-Schutz umgehbar). Er ist daher deaktiviert.
require '../cookies.php';

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'disabled']);
