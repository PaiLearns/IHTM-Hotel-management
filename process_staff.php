<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_staff_message($type, $message, $old = array())
{
    $_SESSION['staff_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
    $_SESSION['staff_form_old'] = $old;
    header('Location: staff.php');
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required to create staff accounts.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: staff.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_staff_message('error', 'Your request could not be verified. Reload the page and try again.');
}

$fullName = trim(isset($_POST['full_name']) ? (string) $_POST['full_name'] : '');
$username = trim(isset($_POST['username']) ? (string) $_POST['username'] : '');
$role = isset($_POST['role']) ? (string) $_POST['role'] : '';
$password = isset($_POST['password']) ? (string) $_POST['password'] : '';
$passwordConfirm = isset($_POST['password_confirm']) ? (string) $_POST['password_confirm'] : '';
$old = array(
    'full_name' => $fullName,
    'username' => $username,
    'role' => $role,
);

if ($fullName === '' || strlen($fullName) > 120) {
    redirect_with_staff_message('error', 'Enter a full name of 120 characters or fewer.', $old);
}
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    redirect_with_staff_message('error', 'Use a username of 3–50 letters, numbers, periods, underscores, or hyphens.', $old);
}
if (!in_array($role, array('admin', 'receptionist'), true)) {
    redirect_with_staff_message('error', 'Select a valid staff role.', $old);
}
if (strlen($password) < 12 || strlen($password) > 72) {
    redirect_with_staff_message('error', 'The password must be between 12 and 72 bytes.', $old);
}
if (!hash_equals($password, $passwordConfirm)) {
    redirect_with_staff_message('error', 'The password and confirmation do not match.', $old);
}

try {
    $statement = $pdo->prepare(
        'INSERT INTO users (username, password_hash, full_name, role, status)
         VALUES (:username, :password_hash, :full_name, :role, :status)'
    );
    $statement->execute(array(
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'role' => $role,
        'status' => 'active',
    ));
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000') {
        redirect_with_staff_message('error', 'That username is already in use. Choose another username.', $old);
    }
    error_log('IHTM staff account creation failed: ' . $exception->getMessage());
    redirect_with_staff_message('error', 'The staff account could not be created. Please try again.', $old);
}

redirect_with_staff_message('success', 'Staff account for ' . $fullName . ' was created.');
