<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

function redirect_with_reservation_error($message, $old = array())
{
    $_SESSION['reservation_form_flash'] = array(
        'message' => $message,
        'old' => $old,
    );
    header('Location: new_reservation.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: new_reservation.php');
    exit;
}

$submittedToken = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
$sessionToken = isset($_SESSION['csrf_token']) ? (string) $_SESSION['csrf_token'] : '';
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    redirect_with_reservation_error('Your request could not be verified. Please reload the form and try again.');
}

$firstName = trim(isset($_POST['first_name']) ? (string) $_POST['first_name'] : '');
$lastName = trim(isset($_POST['last_name']) ? (string) $_POST['last_name'] : '');
$email = trim(isset($_POST['email']) ? (string) $_POST['email'] : '');
$phone = trim(isset($_POST['phone']) ? (string) $_POST['phone'] : '');
$roomId = isset($_POST['room_id']) ? filter_var($_POST['room_id'], FILTER_VALIDATE_INT) : false;
$checkIn = isset($_POST['check_in']) ? (string) $_POST['check_in'] : '';
$checkOut = isset($_POST['check_out']) ? (string) $_POST['check_out'] : '';
$old = array(
    'first_name' => $firstName,
    'last_name' => $lastName,
    'email' => $email,
    'phone' => $phone,
    'room_id' => isset($_POST['room_id']) ? (string) $_POST['room_id'] : '',
    'check_in' => $checkIn,
    'check_out' => $checkOut,
);

$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
$today = date('Y-m-d');
if ($firstName === '' || strlen($firstName) > 80 || $lastName === '' || strlen($lastName) > 80) {
    redirect_with_reservation_error('Enter the guest’s first and last name.', $old);
}
if ($email === '' && $phone === '') {
    redirect_with_reservation_error('Provide an email address or phone number for the guest.', $old);
}
if ($email !== '' && (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    redirect_with_reservation_error('Enter a valid email address.', $old);
}
if (strlen($phone) > 30) {
    redirect_with_reservation_error('The phone number is too long.', $old);
}
if ($roomId === false || $roomId < 1) {
    redirect_with_reservation_error('Select a valid room.', $old);
}
if (!preg_match($datePattern, $checkIn) || !preg_match($datePattern, $checkOut)) {
    redirect_with_reservation_error('Enter valid check-in and check-out dates.', $old);
}
$checkInDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn);
$checkOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut);
if (!$checkInDate || $checkInDate->format('Y-m-d') !== $checkIn || !$checkOutDate || $checkOutDate->format('Y-m-d') !== $checkOut) {
    redirect_with_reservation_error('Enter valid check-in and check-out dates.', $old);
}
if ($checkIn < $today || $checkOut <= $checkIn) {
    redirect_with_reservation_error('Check-in cannot be in the past and check-out must be after check-in.', $old);
}

try {
    $pdo->beginTransaction();

    $roomStatement = $pdo->prepare("SELECT id, status FROM rooms WHERE id = :room_id FOR UPDATE");
    $roomStatement->execute(array('room_id' => $roomId));
    $room = $roomStatement->fetch();

    if (!$room || $room['status'] !== 'available') {
        $pdo->rollBack();
        redirect_with_reservation_error('That room is no longer available. Choose another room.', $old);
    }

    $overlapStatement = $pdo->prepare(
        "SELECT id
         FROM reservations
         WHERE room_id = :room_id
           AND status IN ('pending', 'confirmed', 'checked_in')
           AND check_in < :check_out
           AND check_out > :check_in
         LIMIT 1"
    );
    $overlapStatement->execute(array(
        'room_id' => $roomId,
        'check_in' => $checkIn,
        'check_out' => $checkOut,
    ));
    if ($overlapStatement->fetch()) {
        $pdo->rollBack();
        redirect_with_reservation_error('This room already has a reservation that overlaps those dates.', $old);
    }

    $guestConditions = array();
    $guestParameters = array();
    if ($email !== '') {
        $guestConditions[] = 'email = :email';
        $guestParameters['email'] = $email;
    }
    if ($phone !== '') {
        $guestConditions[] = 'phone = :phone';
        $guestParameters['phone'] = $phone;
    }

    $guestStatement = $pdo->prepare(
        'SELECT id FROM guests WHERE ' . implode(' OR ', $guestConditions) . ' LIMIT 2 FOR UPDATE'
    );
    $guestStatement->execute($guestParameters);
    $matchingGuests = $guestStatement->fetchAll();

    if (count($matchingGuests) > 1) {
        $pdo->rollBack();
        redirect_with_reservation_error('The email and phone number match different guest records. Verify the contact details and try again.', $old);
    }

    if ($matchingGuests) {
        $guestId = (int) $matchingGuests[0]['id'];
    } else {
        $guestInsert = $pdo->prepare(
            'INSERT INTO guests (first_name, last_name, email, phone)
             VALUES (:first_name, :last_name, :email, :phone)'
        );
        $guestInsert->execute(array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
        ));
        $guestId = (int) $pdo->lastInsertId();
    }

    $reservationInsert = $pdo->prepare(
        'INSERT INTO reservations (guest_id, room_id, user_id, check_in, check_out, status)
         VALUES (:guest_id, :room_id, :user_id, :check_in, :check_out, :status)'
    );
    $reservationInsert->execute(array(
        'guest_id' => $guestId,
        'room_id' => $roomId,
        'user_id' => (int) $_SESSION['user_id'],
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'status' => 'confirmed',
    ));
    $reservationId = (int) $pdo->lastInsertId();

    $pdo->commit();
    header('Location: invoice.php?reservation_id=' . $reservationId);
    exit;
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM reservation creation failed: ' . $exception->getMessage());
    redirect_with_reservation_error('The reservation could not be saved. Please try again.', $old);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('IHTM reservation creation failed: ' . $exception->getMessage());
    redirect_with_reservation_error('The reservation could not be saved. Please try again.', $old);
}
