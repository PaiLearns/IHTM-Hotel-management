<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Reservations';
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$statusLabels = array(
    'pending' => 'Booked',
    'confirmed' => 'Booked',
    'checked_in' => 'Checked-In',
    'checked_out' => 'Checked-Out',
    'cancelled' => 'Cancelled',
);
$statusClasses = array(
    'pending' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-amber-100 text-amber-800',
    'checked_in' => 'bg-blue-100 text-blue-800',
    'checked_out' => 'bg-slate-100 text-slate-700',
    'cancelled' => 'bg-red-100 text-red-800',
);

try {
    $sql = "SELECT
                res.id,
                g.first_name,
                g.last_name,
                r.room_number,
                res.check_in,
                res.check_out,
                res.status,
                DATEDIFF(res.check_out, res.check_in) * r.nightly_rate AS total_price
            FROM reservations res
            INNER JOIN guests g ON g.id = res.guest_id
            INNER JOIN rooms r ON r.id = res.room_id";
    $parameters = array();

    if ($search !== '') {
        $sql .= " WHERE CONCAT(g.first_name, ' ', g.last_name) LIKE :guest_search
                   OR r.room_number LIKE :room_search";
        $parameters['guest_search'] = '%' . $search . '%';
        $parameters['room_search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY res.created_at DESC, res.id DESC LIMIT 100';
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $reservations = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM reservation listing failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Reservations could not be loaded. Please check the database configuration and try again.');
}

$flash = isset($_SESSION['reservation_flash']) ? $_SESSION['reservation_flash'] : null;
unset($_SESSION['reservation_flash']);

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Front desk workspace</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Reservations</h1>
        <p class="mt-2 text-slate-600">Search bookings, manage guest arrivals, and complete check-outs.</p>
    </div>
    <a href="new_reservation.php" class="inline-flex items-center justify-center rounded-lg bg-amber-400 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-300">+ New Reservation</a>
</section>

<?php if ($flash): ?>
    <div role="status" class="mb-5 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<section id="reservations" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-lg font-bold text-ihtm-navy">Booking list</h2>
            <p class="text-sm text-slate-500">Showing up to 100 most recent reservations.</p>
        </div>
        <form action="reservations.php" method="get" class="flex w-full gap-2 lg:max-w-md">
            <label for="reservation-search" class="sr-only">Search guest name or room number</label>
            <input id="reservation-search" type="search" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search guest name or room number"
                   class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3.5 py-2.5 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            <button type="submit" class="rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ihtm-blue">Search</button>
            <?php if ($search !== ''): ?><a href="reservations.php" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">Clear</a><?php endif; ?>
        </form>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-semibold">Guest</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Room</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Check-in</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Check-out</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Total price</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$reservations): ?>
                    <tr><td colspan="7" class="px-5 py-10 text-center text-slate-500"><?php echo $search !== '' ? 'No reservations matched your search.' : 'No reservations have been created yet.'; ?></td></tr>
                <?php else: ?>
                    <?php foreach ($reservations as $reservation): ?>
                        <?php
                        $reservationStatus = $reservation['status'];
                        $statusLabel = isset($statusLabels[$reservationStatus]) ? $statusLabels[$reservationStatus] : ucwords(str_replace('_', ' ', $reservationStatus));
                        $statusClass = isset($statusClasses[$reservationStatus]) ? $statusClasses[$reservationStatus] : 'bg-slate-100 text-slate-700';
                        $isBooked = in_array($reservationStatus, array('pending', 'confirmed'), true);
                        ?>
                        <tr class="align-top hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-4 font-medium text-slate-800"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?php echo htmlspecialchars($reservation['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['check_in'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-4 text-slate-600"><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['check_out'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-4 font-semibold text-slate-700">$<?php echo number_format((float) $reservation['total_price'], 2); ?></td>
                            <td class="whitespace-nowrap px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td class="min-w-52 px-5 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <?php if ($isBooked): ?>
                                        <form action="process_checkin_checkout.php" method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['id']; ?>">
                                            <input type="hidden" name="action" value="checkin">
                                            <button type="submit" class="rounded-md bg-ihtm-navy px-3 py-2 text-xs font-semibold text-white transition hover:bg-ihtm-blue">Check-In</button>
                                        </form>
                                        <form action="process_checkin_checkout.php" method="post" onsubmit="return confirm('Cancel this booking?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-50">Cancel</button>
                                        </form>
                                    <?php elseif ($reservationStatus === 'checked_in'): ?>
                                        <form action="process_checkin_checkout.php" method="post">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['id']; ?>">
                                            <input type="hidden" name="action" value="checkout">
                                            <button type="submit" class="rounded-md bg-amber-400 px-3 py-2 text-xs font-semibold text-slate-950 transition hover:bg-amber-300">Check-Out</button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="invoice.php?reservation_id=<?php echo (int) $reservation['id']; ?>" class="rounded-md border border-amber-300 px-3 py-2 text-xs font-semibold text-amber-800 transition hover:bg-amber-50"><?php echo $reservationStatus === 'checked_out' ? 'View Bill' : 'Invoice'; ?></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
