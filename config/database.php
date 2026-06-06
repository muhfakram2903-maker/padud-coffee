<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'padud-coffee');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset('utf8mb4');
        if ($conn->connect_error) {
            http_response_code(500);
            die(json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->connect_error]));
        }
    }
    return $conn;
}
