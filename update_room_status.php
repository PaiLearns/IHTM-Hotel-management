<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_to_rooms_with_message($type, $message)
{
    $_SESSION['room_status_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
    header('Location: rooms.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: rooms.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_to_rooms_with_message('error', 'Your request could not be verified. Please try again.');
}

$roomId = isset($_POST['room_id']) ? filter_var($_POST['room_id'], FILTER_VALIDATE_INT) : false;
$status = isset($_POST['status']) ? (string) $_POST['status'] : '';
$allowedStatuses = array('available', 'occupied', 'dirty', 'maintenance');

if ($roomId === false || $roomId < 1 || !in_array($status, $allowedStatuses, true)) {
    redirect_to_rooms_with_message('error', 'Select a valid room and status.');
}

try {
    $statement = $pdo->prepare('UPDATE rooms SET status = :status WHERE id = :room_id');
    $statement->execute(array(
        'status' => $status,
        'room_id' => $roomId,
    ));

    if ($statement->rowCount() === 0) {
        $checkStatement = $pdo->prepare('SELECT id FROM rooms WHERE id = :room_id');
        $checkStatement->execute(array('room_id' => $roomId));
        if (!$checkStatement->fetch()) {
            redirect_to_rooms_with_message('error', 'That room could not be found.');
        }
    }

    redirect_to_rooms_with_message('success', 'Room status updated to ' . ucfirst($status) . '.');
} catch (PDOException $exception) {
    error_log('IHTM room status update failed: ' . $exception->getMessage());
    redirect_to_rooms_with_message('error', 'The room status could not be updated. Please try again.');
}
