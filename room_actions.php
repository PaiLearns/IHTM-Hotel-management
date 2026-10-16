<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_room_message($type, $message)
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
    redirect_with_room_message('error', 'Your request could not be verified. Please try again.');
}

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$allowedActions = array('add', 'edit', 'delete');
if (!in_array($action, $allowedActions, true)) {
    redirect_with_room_message('error', 'Select a valid room action.');
}

$roomTypes = array('single', 'double', 'twin', 'deluxe', 'suite', 'family');
$roomStatuses = array('available', 'occupied', 'maintenance');

if ($action === 'delete') {
    $roomId = isset($_POST['room_id']) ? filter_var($_POST['room_id'], FILTER_VALIDATE_INT) : false;
    if ($roomId === false || $roomId < 1) {
        redirect_with_room_message('error', 'Select a valid room to delete.');
    }

    try {
        $statement = $pdo->prepare('DELETE FROM rooms WHERE id = :room_id');
        $statement->execute(array('room_id' => $roomId));
        if ($statement->rowCount() === 0) {
            redirect_with_room_message('error', 'That room could not be found.');
        }
        redirect_with_room_message('success', 'Room deleted.');
    } catch (PDOException $exception) {
        error_log('IHTM room deletion failed: ' . $exception->getMessage());
        if ($exception->getCode() === '23000') {
            redirect_with_room_message('error', 'This room has reservation history and cannot be deleted.');
        }
        redirect_with_room_message('error', 'The room could not be deleted. Please try again.');
    }
}

$roomNumber = trim(isset($_POST['room_number']) ? (string) $_POST['room_number'] : '');
$roomType = isset($_POST['room_type']) ? (string) $_POST['room_type'] : '';
$capacity = isset($_POST['capacity']) ? filter_var($_POST['capacity'], FILTER_VALIDATE_INT) : false;
$rateInput = isset($_POST['nightly_rate']) ? trim((string) $_POST['nightly_rate']) : '';
$status = isset($_POST['status']) ? (string) $_POST['status'] : '';
$roomId = $action === 'edit' && isset($_POST['room_id'])
    ? filter_var($_POST['room_id'], FILTER_VALIDATE_INT)
    : false;

if ($roomNumber === '' || strlen($roomNumber) > 20) {
    redirect_with_room_message('error', 'Room number is required and must be 20 characters or fewer.');
}
if (!in_array($roomType, $roomTypes, true)) {
    redirect_with_room_message('error', 'Select a valid room type.');
}
if ($capacity === false || $capacity < 1 || $capacity > 2147483647) {
    redirect_with_room_message('error', 'Room capacity must be a positive whole number.');
}
if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $rateInput) || (float) $rateInput > 99999999.99) {
    redirect_with_room_message('error', 'Enter a valid nightly rate between 0 and 99,999,999.99.');
}
if (!in_array($status, $roomStatuses, true)) {
    redirect_with_room_message('error', 'Select a valid room status.');
}
if ($action === 'edit' && ($roomId === false || $roomId < 1)) {
    redirect_with_room_message('error', 'Select a valid room to update.');
}

try {
    if ($action === 'add') {
        $statement = $pdo->prepare(
            'INSERT INTO rooms (room_number, room_type, capacity, nightly_rate, status)
             VALUES (:room_number, :room_type, :capacity, :nightly_rate, :status)'
        );
        $statement->execute(array(
            'room_number' => $roomNumber,
            'room_type' => $roomType,
            'capacity' => $capacity,
            'nightly_rate' => $rateInput,
            'status' => $status,
        ));
        redirect_with_room_message('success', 'Room ' . $roomNumber . ' added.');
    }

    $statement = $pdo->prepare(
        'UPDATE rooms
         SET room_number = :room_number,
             room_type = :room_type,
             capacity = :capacity,
             nightly_rate = :nightly_rate,
             status = :status
         WHERE id = :room_id'
    );
    $statement->execute(array(
        'room_number' => $roomNumber,
        'room_type' => $roomType,
        'capacity' => $capacity,
        'nightly_rate' => $rateInput,
        'status' => $status,
        'room_id' => $roomId,
    ));

    if ($statement->rowCount() === 0) {
        $checkStatement = $pdo->prepare('SELECT id FROM rooms WHERE id = :room_id');
        $checkStatement->execute(array('room_id' => $roomId));
        if (!$checkStatement->fetch()) {
            redirect_with_room_message('error', 'That room could not be found.');
        }
    }
    redirect_with_room_message('success', 'Room ' . $roomNumber . ' updated.');
} catch (PDOException $exception) {
    error_log('IHTM room action failed: ' . $exception->getMessage());
    if ($exception->getCode() === '23000') {
        redirect_with_room_message('error', 'That room number is already in use.');
    }
    redirect_with_room_message('error', 'The room could not be saved. Please try again.');
}
