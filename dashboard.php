<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Dashboard';
$today = date('Y-m-d');

try {
    $roomCounts = array(
        'total' => (int) $pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn(),
        'available' => (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn(),
        'occupied' => (int) $pdo->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn(),
    );

    $todayStatement = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN check_in = :check_in_date THEN 1 ELSE 0 END) AS check_ins,
            SUM(CASE WHEN check_out = :check_out_date THEN 1 ELSE 0 END) AS check_outs
         FROM reservations
         WHERE status NOT IN ('cancelled', 'checked_out')"
    );
    $todayStatement->execute(array(
        'check_in_date' => $today,
        'check_out_date' => $today,
    ));
    $todayCounts = $todayStatement->fetch();

    $activityStatement = $pdo->prepare(
        "SELECT
            res.id,
            g.first_name,
            g.last_name,
            r.room_number,
            res.check_in,
            res.check_out,
            res.status
         FROM reservations res
         INNER JOIN guests g ON g.id = res.guest_id
         INNER JOIN rooms r ON r.id = res.room_id
         WHERE res.status <> 'cancelled'
         ORDER BY res.created_at DESC, res.id DESC
         LIMIT 8"
    );
    $activityStatement->execute();
    $recentReservations = $activityStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM dashboard query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The dashboard could not load. Please check the database configuration and try again.');
}

$statusClasses = array(
    'pending' => 'bg-amber-100 text-amber-800',
    'confirmed' => 'bg-blue-100 text-blue-800',
    'checked_in' => 'bg-emerald-100 text-emerald-800',
    'checked_out' => 'bg-slate-100 text-slate-700',
);

require __DIR__ . '/includes/header.php';
?>
<section class="mb-7">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Front desk overview</p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Welcome, <?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $_SESSION['username'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="mt-2 text-slate-600">Today’s room and guest activity at a glance.</p>
</section>

<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5" aria-label="Hotel summary">
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Total rooms</p>
        <p class="mt-3 text-3xl font-bold text-ihtm-navy"><?php echo $roomCounts['total']; ?></p>
    </article>
    <article class="rounded-xl border border-amber-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2"><p class="text-sm font-medium text-slate-500">Available rooms</p><span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Available</span></div>
        <p class="mt-3 text-3xl font-bold text-amber-700"><?php echo $roomCounts['available']; ?></p>
    </article>
    <article class="rounded-xl border border-blue-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2"><p class="text-sm font-medium text-slate-500">Occupied rooms</p><span class="rounded-full bg-ihtm-navy px-2.5 py-1 text-xs font-semibold text-white">Occupied</span></div>
        <p class="mt-3 text-3xl font-bold text-ihtm-navy"><?php echo $roomCounts['occupied']; ?></p>
    </article>
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Today's check-ins</p>
        <p class="mt-3 text-3xl font-bold text-ihtm-navy"><?php echo (int) $todayCounts['check_ins']; ?></p>
    </article>
    <article class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <p class="text-sm font-medium text-slate-500">Today's check-outs</p>
        <p class="mt-3 text-3xl font-bold text-ihtm-navy"><?php echo (int) $todayCounts['check_outs']; ?></p>
    </article>
</section>

<section class="mt-7">
    <h2 class="mb-3 text-lg font-bold text-ihtm-navy">Quick actions</h2>
    <div class="flex flex-wrap gap-3">
        <a href="new_reservation.php" class="rounded-lg bg-amber-400 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-300">New Reservation</a>
        <a href="reservations.php#reservations" class="rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ihtm-blue">Check-in Guest</a>
        <a href="rooms.php" class="rounded-lg border border-ihtm-navy bg-white px-4 py-2.5 text-sm font-semibold text-ihtm-navy transition hover:bg-slate-50">View Rooms</a>
    </div>
</section>

<section id="todays-bookings" class="mt-8 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-1 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-lg font-bold text-ihtm-navy">Recent activity &amp; bookings</h2>
            <p class="text-sm text-slate-500">Latest reservations, including today’s arrivals and departures.</p>
        </div>
        <span class="text-sm text-slate-500"><?php echo count($recentReservations); ?> recent</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-semibold">Guest</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Room</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Check-in</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Check-out</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Invoice</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$recentReservations): ?>
                    <tr><td colspan="6" class="px-5 py-8 text-center text-slate-500">No reservations to display yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentReservations as $reservation): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap px-5 py-3.5 font-medium text-slate-800"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars($reservation['room_number'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['check_in'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars(date('M j, Y', strtotime($reservation['check_out'])), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <?php
                                $statusClass = isset($statusClasses[$reservation['status']]) ? $statusClasses[$reservation['status']] : 'bg-slate-100 text-slate-700';
                                $statusLabel = $reservation['status'] === 'confirmed' || $reservation['status'] === 'pending' ? 'Booked' : ucwords(str_replace('_', ' ', $reservation['status']));
                                ?>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <?php if ($reservation['status'] === 'checked_out'): ?>
                                    <a href="invoice.php?id=<?php echo (int) $reservation['id']; ?>" class="font-semibold text-amber-700 underline decoration-amber-400 underline-offset-2 hover:text-ihtm-navy">View Invoice</a>
                                <?php else: ?>
                                    <span class="text-slate-300">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
