<?php

require_once __DIR__ . '/includes/session.php';
start_secure_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';

if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(400);
    echo 'The logout request could not be verified. Please return to the application and try again.';
    exit;
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $cookieParameters = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookieParameters['path'], $cookieParameters['domain'], $cookieParameters['secure'], $cookieParameters['httponly']);
}

session_destroy();
header('Location: index.php');
exit;
