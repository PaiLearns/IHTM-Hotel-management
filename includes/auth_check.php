<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/session.php';
start_secure_session();

if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    header('Location: index.php');
    exit;
}
