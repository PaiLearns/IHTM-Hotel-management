<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_transfer_message($reservationId, $type, $message)
{
    $_SESSION['transfer_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
    header('Location: transfer_room.php?reservation_id=' . (int) $reservationId);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reservations.php');
    exit;
}

$reservationId = isset($_POST['reservation_id']) ? filter_var($_POST['reservation_id'], FILTER_VALIDATE_INT) : false;
if ($reservationId === false || $reservationId < 1) {
    header('Location: reservations.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_transfer_message($reservationId, 'error', 'Your request could not be verified. Please reload the page and try again.');
}

$newRoomId = isset($_POST['new_room_id']) ? filter_var($_POST['new_room_id'], FILTER_VALIDATE_INT) : false;
$reason = trim(isset($_POST['reason']) ? (string) $_POST['reason'] : '');
if ($newRoomId === false || $newRoomId < 1 || !preg_match('/^.{1,500}$/us', $reason)) {
    redirect_with_transfer_message($reservationId, 'error', 'Select an available room and provide a reason of 500 characters or fewer.');
}

try {
    $pdo->beginTransaction();

    $reservationStatement = $pdo->prepare(
        'SELECT id, room_id, check_in, check_out, status
         FROM reservations
         WHERE id = :reservation_id
         FOR UPDATE'
    );
    $reservationStatement->execute(array('reservation_id' => $reservationId));
    $reservation = $reservationStatement->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'That reservation could not be found.');
    }
    if (!in_array($reservation['status'], array('confirmed', 'checked_in'), true)) {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'Only confirmed or checked-in reservations can be transferred.');
    }

    $oldRoomId = (int) $reservation['room_id'];
    if ($newRoomId === $oldRoomId) {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'Choose a different room from the current room.');
    }

    $roomIds = array($oldRoomId, $newRoomId);
    sort($roomIds, SORT_NUMERIC);
    $roomParameters = array();
    $roomPlaceholders = array();
    foreach ($roomIds as $index => $roomId) {
        $placeholder = 'room_id_' . $index;
        $roomPlaceholders[] = ':' . $placeholder;
        $roomParameters[$placeholder] = $roomId;
    }
    $roomStatement = $pdo->prepare(
        'SELECT id, room_number, status
         FROM rooms
         WHERE id IN (' . implode(', ', $roomPlaceholders) . ')
         ORDER BY id
         FOR UPDATE'
    );
    $roomStatement->execute($roomParameters);
    $lockedRooms = array();
    foreach ($roomStatement->fetchAll() as $room) {
        $lockedRooms[(int) $room['id']] = $room;
    }

    if (!isset($lockedRooms[$oldRoomId]) || !isset($lockedRooms[$newRoomId])) {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'The current or destination room could not be found.');
    }
    if ($lockedRooms[$newRoomId]['status'] !== 'available') {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'That room is no longer available. Choose another room.');
    }
    if ($reservation['status'] === 'checked_in' && $lockedRooms[$oldRoomId]['status'] !== 'occupied') {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'The current room is not marked occupied, so the transfer was not completed.');
    }
    if ($reservation['status'] === 'confirmed' && $lockedRooms[$oldRoomId]['status'] === 'occupied') {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'The current room is occupied, so the transfer was not completed.');
    }

    $overlapStatement = $pdo->prepare(
        "SELECT id
         FROM reservations
         WHERE room_id = :room_id
           AND id <> :reservation_id
           AND status IN ('pending', 'confirmed', 'checked_in')
           AND check_in < :check_out
           AND check_out > :check_in
         LIMIT 1
         FOR UPDATE"
    );
    $overlapStatement->execute(array(
        'room_id' => $newRoomId,
        'reservation_id' => $reservationId,
        'check_out' => $reservation['check_out'],
        'check_in' => $reservation['check_in'],
    ));
    if ($overlapStatement->fetch()) {
        $pdo->rollBack();
        redirect_with_transfer_message($reservationId, 'error', 'That room has another reservation overlapping these dates.');
    }

    $updateReservation = $pdo->prepare('UPDATE reservations SET room_id = :room_id WHERE id = :reservation_id');
    $updateReservation->execute(array(
        'room_id' => $newRoomId,
        'reservation_id' => $reservationId,
    ));

    if ($reservation['status'] === 'checked_in') {
        $updateRooms = $pdo->prepare(
            "UPDATE rooms
             SET status = CASE id
                 WHEN :old_room_id THEN 'dirty'
                 WHEN :new_room_id THEN 'occupied'
             END
             WHERE id IN (:old_room_filter, :new_room_filter)"
        );
        $updateRooms->execute(array(
            'old_room_id' => $oldRoomId,
            'new_room_id' => $newRoomId,
            'old_room_filter' => $oldRoomId,
            'new_room_filter' => $newRoomId,
        ));
    }

    $transferInsert = $pdo->prepare(
        'INSERT INTO room_transfers
            (reservation_id, from_room_id, from_room_number, to_room_id, to_room_number, changed_by, reason)
         VALUES
            (:reservation_id, :from_room_id, :from_room_number, :to_room_id, :to_room_number, :changed_by, :reason)'
    );
    $transferInsert->execute(array(
        'reservation_id' => $reservationId,
        'from_room_id' => $oldRoomId,
        'from_room_number' => $lockedRooms[$oldRoomId]['room_number'],
        'to_room_id' => $newRoomId,
        'to_room_number' => $lockedRooms[$newRoomId]['room_number'],
        'changed_by' => (int) $_SESSION['user_id'],
        'reason' => $reason,
    ));

    $pdo->commit();
    redirect_with_transfer_message(
        $reservationId,
        'success',
        'Reservation transferred from room ' . $lockedRooms[$oldRoomId]['room_number'] . ' to room ' . $lockedRooms[$newRoomId]['room_number'] . '. The destination room rate now applies to the full stay.'
    );
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM room transfer failed: ' . $exception->getMessage());
    redirect_with_transfer_message($reservationId, 'error', 'The room transfer could not be saved. Please try again.');
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM room transfer failed: ' . $exception->getMessage());
    redirect_with_transfer_message($reservationId, 'error', 'The room transfer could not be saved. Please try again.');
}
