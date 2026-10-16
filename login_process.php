<?php

require_once __DIR__ . '/includes/session.php';
start_secure_session();

function redirect_with_login_error($message)
{
    $_SESSION['login_error'] = $message;
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_login_error('Your sign-in session expired. Please try again.');
}

$username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';

if ($username === '' || $password === '' || strlen($username) > 50) {
    redirect_with_login_error('Enter a valid username and password.');
}

try {
    require __DIR__ . '/includes/db.php';
    $statement = $pdo->prepare(
        'SELECT id, username, password_hash, full_name, role
         FROM users
         WHERE username = :username AND status = :status
         LIMIT 1'
    );
    $statement->execute(array('username' => $username, 'status' => 'active'));
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        redirect_with_login_error('The username or password was not recognized.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_error']);

    header('Location: dashboard.php');
    exit;
} catch (Throwable $exception) {
    error_log('IHTM sign-in failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'The sign-in service is temporarily unavailable. Please try again later.';
}
