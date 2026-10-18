<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_reservation_message($type, $message)
{
    $_SESSION['reservation_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
    header('Location: reservations.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservations.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_reservation_message('error', 'Your request could not be verified. Please try again.');
}

$reservationId = isset($_POST['reservation_id']) ? filter_var($_POST['reservation_id'], FILTER_VALIDATE_INT) : false;
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
if ($reservationId === false || $reservationId < 1 || !in_array($action, array('checkin', 'checkout', 'cancel'), true)) {
    redirect_with_reservation_message('error', 'Select a valid reservation action.');
}

try {
    $pdo->beginTransaction();

    $reservationStatement = $pdo->prepare(
        'SELECT id, room_id, status FROM reservations WHERE id = :reservation_id FOR UPDATE'
    );
    $reservationStatement->execute(array('reservation_id' => $reservationId));
    $reservation = $reservationStatement->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        redirect_with_reservation_message('error', 'That reservation could not be found.');
    }

    $roomStatement = $pdo->prepare('SELECT id, status FROM rooms WHERE id = :room_id FOR UPDATE');
    $roomStatement->execute(array('room_id' => $reservation['room_id']));
    $room = $roomStatement->fetch();
    if (!$room) {
        throw new RuntimeException('Reservation references a room that does not exist.');
    }

    if ($action === 'checkin') {
        if (!in_array($reservation['status'], array('pending', 'confirmed'), true)) {
            $pdo->rollBack();
            redirect_with_reservation_message('error', 'Only a booked reservation can be checked in.');
        }
        if ($room['status'] !== 'available') {
            $pdo->rollBack();
            redirect_with_reservation_message('error', 'The assigned room is not available for check-in.');
        }

        $updateReservation = $pdo->prepare("UPDATE reservations SET status = 'checked_in' WHERE id = :reservation_id");
        $updateReservation->execute(array('reservation_id' => $reservationId));
        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :room_id");
        $updateRoom->execute(array('room_id' => $reservation['room_id']));
        $message = 'Guest checked in. The room is now marked occupied.';
    } elseif ($action === 'checkout') {
        if ($reservation['status'] !== 'checked_in') {
            $pdo->rollBack();
            redirect_with_reservation_message('error', 'Only a checked-in reservation can be checked out.');
        }

        $updateReservation = $pdo->prepare("UPDATE reservations SET status = 'checked_out' WHERE id = :reservation_id");
        $updateReservation->execute(array('reservation_id' => $reservationId));
        $updateRoom = $pdo->prepare("UPDATE rooms SET status = 'maintenance' WHERE id = :room_id");
        $updateRoom->execute(array('room_id' => $reservation['room_id']));
        $message = 'Guest checked out. The room is now marked available.';
    } else {
        if (!in_array($reservation['status'], array('pending', 'confirmed'), true)) {
            $pdo->rollBack();
            redirect_with_reservation_message('error', 'Only a booked reservation can be cancelled.');
        }

        $updateReservation = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = :reservation_id");
        $updateReservation->execute(array('reservation_id' => $reservationId));
        $message = 'Reservation cancelled.';
    }

    $pdo->commit();
    if ($action === 'checkout' || $action === 'checkin') {
        header('Location: invoice.php?reservation_id=' . $reservationId);
        exit;
    }
    redirect_with_reservation_message('success', $message);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM reservation status update failed: ' . $exception->getMessage());
    redirect_with_reservation_message('error', 'The reservation status could not be updated. Please try again.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM reservation status update failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The reservation could not be updated. Please contact an administrator.');
}
