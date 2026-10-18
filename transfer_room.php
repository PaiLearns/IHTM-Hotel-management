<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$reservationId = isset($_GET['reservation_id'])
    ? filter_var($_GET['reservation_id'], FILTER_VALIDATE_INT)
    : false;
if ($reservationId === false || $reservationId < 1) {
    http_response_code(400);
    exit('A valid reservation ID is required to transfer a room.');
}

try {
    $reservationStatement = $pdo->prepare(
        'SELECT
            res.id,
            res.room_id,
            res.check_in,
            res.check_out,
            res.status,
            g.first_name,
            g.last_name,
            r.room_number,
            r.nightly_rate
         FROM reservations res
         INNER JOIN guests g ON g.id = res.guest_id
         INNER JOIN rooms r ON r.id = res.room_id
         WHERE res.id = :reservation_id
         LIMIT 1'
    );
    $reservationStatement->execute(array('reservation_id' => $reservationId));
    $reservation = $reservationStatement->fetch();

    if (!$reservation) {
        http_response_code(404);
        exit('The requested reservation could not be found.');
    }

    $canTransfer = in_array($reservation['status'], array('confirmed', 'checked_in'), true);
    $availableRooms = array();
    if ($canTransfer) {
        $roomStatement = $pdo->prepare(
            "SELECT r.id, r.room_number, r.room_type, r.nightly_rate
             FROM rooms r
             WHERE r.status = 'available'
               AND r.id <> :current_room_id
               AND NOT EXISTS (
                   SELECT 1
                   FROM reservations other_res
                   WHERE other_res.room_id = r.id
                     AND other_res.id <> :reservation_id
                     AND other_res.status IN ('pending', 'confirmed', 'checked_in')
                     AND other_res.check_in < :requested_check_out
                     AND other_res.check_out > :requested_check_in
               )
             ORDER BY r.room_number"
        );
        $roomStatement->execute(array(
            'current_room_id' => $reservation['room_id'],
            'reservation_id' => $reservationId,
            'requested_check_out' => $reservation['check_out'],
            'requested_check_in' => $reservation['check_in'],
        ));
        $availableRooms = $roomStatement->fetchAll();
    }

    $historyStatement = $pdo->prepare(
        'SELECT
            transfers.from_room_number,
            transfers.to_room_number,
            transfers.reason,
            transfers.transferred_at,
            users.full_name AS changed_by_name
         FROM room_transfers transfers
         INNER JOIN users ON users.id = transfers.changed_by
         WHERE transfers.reservation_id = :reservation_id
         ORDER BY transfers.transferred_at DESC, transfers.id DESC'
    );
    $historyStatement->execute(array('reservation_id' => $reservationId));
    $transferHistory = $historyStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM room transfer page failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Room transfer details could not be loaded. Please try again later.');
}

$flash = isset($_SESSION['transfer_flash']) ? $_SESSION['transfer_flash'] : null;
unset($_SESSION['transfer_flash']);
$pageTitle = 'Transfer Room';
$statusLabels = array(
    'confirmed' => 'Confirmed',
    'checked_in' => 'Checked-in',
);

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6">
    <a href="reservations.php" class="text-sm font-semibold text-ihtm-blue hover:underline">&larr; Back to reservations</a>
    <p class="mt-4 text-sm font-semibold uppercase tracking-wider text-amber-700">Front desk workspace</p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Transfer Room</h1>
    <p class="mt-2 text-slate-600">Move this reservation to another room. The destination room's current nightly rate applies to the full stay.</p>
</section>

<?php if ($flash): ?>
    <div role="status" class="mb-5 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<section class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <h2 class="text-lg font-bold text-ihtm-navy">Reservation #<?php echo (int) $reservation['id']; ?></h2>
    <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="font-semibold text-slate-500">Guest</dt><dd class="mt-1 text-slate-800"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
        <div><dt class="font-semibold text-slate-500">Current room</dt><dd class="mt-1 text-slate-800"><?php echo htmlspecialchars($reservation['room_number'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
        <div><dt class="font-semibold text-slate-500">Stay</dt><dd class="mt-1 text-slate-800"><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['check_in'])) . ' – ' . date('M j, Y', strtotime($reservation['check_out'])), ENT_QUOTES, 'UTF-8'); ?></dd></div>
        <div><dt class="font-semibold text-slate-500">Status</dt><dd class="mt-1 text-slate-800"><?php echo htmlspecialchars(isset($statusLabels[$reservation['status']]) ? $statusLabels[$reservation['status']] : ucfirst($reservation['status']), ENT_QUOTES, 'UTF-8'); ?></dd></div>
    </dl>
</section>

<?php if (!$canTransfer): ?>
    <div role="status" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Only confirmed or checked-in reservations can be transferred.</div>
<?php elseif (!$availableRooms): ?>
    <div role="status" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">There are no available rooms for these stay dates.</div>
<?php else: ?>
    <form action="process_room_transfer.php" method="post" class="mb-6 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['id']; ?>">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="new-room-id" class="mb-1.5 block text-sm font-semibold text-slate-700">Available destination room</label>
                <select id="new-room-id" name="new_room_id" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    <?php foreach ($availableRooms as $room): ?>
                        <option value="<?php echo (int) $room['id']; ?>">
                            <?php echo htmlspecialchars('Room ' . $room['room_number'] . ' · ' . ucfirst($room['room_type']) . ' · $' . number_format((float) $room['nightly_rate'], 2) . '/night', ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="transfer-reason" class="mb-1.5 block text-sm font-semibold text-slate-700">Reason for transfer</label>
                <input id="transfer-reason" name="reason" type="text" required maxlength="500" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100" placeholder="For example, guest request or room issue">
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500"><?php echo $reservation['status'] === 'checked_in' ? 'The old room will be marked dirty and the new room occupied.' : 'The new room will be assigned to the reservation.'; ?></p>
        <button type="submit" class="mt-4 rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ihtm-blue" onclick="return confirm('Transfer this reservation to the selected room?');">Transfer Reservation</button>
    </form>
<?php endif; ?>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-bold text-ihtm-navy">Transfer history</h2>
    </div>
    <?php if (!$transferHistory): ?>
        <p class="px-5 py-6 text-sm text-slate-500">No room transfers have been recorded for this reservation.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr><th scope="col" class="px-5 py-3 font-semibold">When</th><th scope="col" class="px-5 py-3 font-semibold">From</th><th scope="col" class="px-5 py-3 font-semibold">To</th><th scope="col" class="px-5 py-3 font-semibold">Staff</th><th scope="col" class="px-5 py-3 font-semibold">Reason</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($transferHistory as $transfer): ?>
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-600"><?php echo htmlspecialchars(date('M j, Y g:i a', strtotime($transfer['transferred_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3 text-slate-700"><?php echo htmlspecialchars($transfer['from_room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3 text-slate-700"><?php echo htmlspecialchars($transfer['to_room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3 text-slate-600"><?php echo htmlspecialchars($transfer['changed_by_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3 text-slate-600"><?php echo htmlspecialchars($transfer['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
