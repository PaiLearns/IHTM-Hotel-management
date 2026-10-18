<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'Rooms';
$allowedFilters = array('all', 'available', 'occupied', 'dirty', 'maintenance');
$filter = isset($_GET['status']) && in_array($_GET['status'], $allowedFilters, true)
    ? $_GET['status']
    : 'all';
$roomTypes = array(
    'single' => 'Single',
    'double' => 'Double',
    'twin' => 'Twin',
    'deluxe' => 'Deluxe',
    'suite' => 'Suite',
    'family' => 'Family',
);
$roomStatuses = array(
    'available' => 'Available',
    'occupied' => 'Occupied',
    'dirty' => 'Dirty',
    'maintenance' => 'Maintenance',
);
$roomStatusClasses = array(
    'available' => 'border-emerald-200 bg-emerald-50',
    'occupied' => 'border-blue-200 bg-blue-50',
    'dirty' => 'border-amber-200 bg-amber-50',
    'maintenance' => 'border-slate-300 bg-slate-100',
);
$roomBadgeClasses = array(
    'available' => 'bg-emerald-100 text-emerald-800',
    'occupied' => 'bg-ihtm-navy text-white',
    'dirty' => 'bg-amber-100 text-amber-800',
    'maintenance' => 'bg-slate-200 text-slate-700',
);

try {
    if ($filter === 'all') {
        $statement = $pdo->query('SELECT id, room_number, room_type, capacity, nightly_rate, status FROM rooms ORDER BY room_number');
    } else {
        $statement = $pdo->prepare('SELECT id, room_number, room_type, capacity, nightly_rate, status FROM rooms WHERE status = :status ORDER BY room_number');
        $statement->execute(array('status' => $filter));
    }
    $rooms = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM room listing failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The room list could not load. Please check the database configuration and try again.');
}

$flash = isset($_SESSION['room_status_flash']) ? $_SESSION['room_status_flash'] : null;
unset($_SESSION['room_status_flash']);

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Front desk workspace</p>
        <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Room management</h1>
        <p class="mt-2 text-slate-600">Manage room details, rates, capacity, and operational status. Mark rooms available after maintenance is complete.</p>
    </div>
    <p class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200"><?php echo count($rooms); ?> room<?php echo count($rooms) === 1 ? '' : 's'; ?></p>
</section>

<?php if ($flash): ?>
    <div role="status" class="mb-5 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<details class="mb-7 rounded-xl border border-slate-200 bg-white shadow-sm" open>
    <summary class="cursor-pointer list-none px-5 py-4 text-lg font-bold text-ihtm-navy marker:hidden">
        <span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-400 text-sm text-slate-950">+</span>
        Add New Room
    </summary>
    <form action="room_actions.php" method="post" class="border-t border-slate-200 px-5 py-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="add">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="new-room-number" class="mb-1.5 block text-sm font-semibold text-slate-700">Room number</label>
                <input id="new-room-number" name="room_number" type="text" required maxlength="20" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="new-room-type" class="mb-1.5 block text-sm font-semibold text-slate-700">Room type</label>
                <select id="new-room-type" name="room_type" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    <?php foreach ($roomTypes as $value => $label): ?><option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="new-room-capacity" class="mb-1.5 block text-sm font-semibold text-slate-700">Max heads</label>
                <input id="new-room-capacity" name="capacity" type="number" required min="1" max="2147483647" value="1" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="new-room-rate" class="mb-1.5 block text-sm font-semibold text-slate-700">Price per night</label>
                <input id="new-room-rate" name="nightly_rate" type="number" required min="0" max="99999999.99" step="0.01" value="0.00" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="new-room-status" class="mb-1.5 block text-sm font-semibold text-slate-700">Initial status</label>
                <select id="new-room-status" name="status" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    <?php foreach ($roomStatuses as $value => $label): ?><option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <button type="submit" class="mt-4 rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ihtm-blue">Add Room</button>
    </form>
</details>

<section class="mb-6 flex flex-wrap gap-2" aria-label="Filter rooms by status">
    <?php foreach ($allowedFilters as $statusFilter): ?>
        <?php $filterLabel = $statusFilter === 'all' ? 'All rooms' : ucfirst($statusFilter); ?>
        <a href="rooms.php?status=<?php echo urlencode($statusFilter); ?>"
           class="rounded-full px-4 py-2 text-sm font-semibold transition <?php echo $filter === $statusFilter ? 'bg-amber-400 text-slate-950 shadow-sm' : 'border border-slate-300 bg-white text-slate-600 hover:border-amber-400 hover:text-ihtm-navy'; ?>"
           <?php echo $filter === $statusFilter ? 'aria-current="page"' : ''; ?>>
            <?php echo htmlspecialchars($filterLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
    <?php endforeach; ?>
</section>

<?php if (!$rooms): ?>
    <section class="rounded-xl border border-slate-200 bg-white p-10 text-center shadow-sm">
        <h2 class="text-lg font-semibold text-ihtm-navy">No rooms found</h2>
        <p class="mt-2 text-sm text-slate-500">There are no rooms in this status category.</p>
    </section>
<?php else: ?>
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" aria-label="Rooms">
        <?php foreach ($rooms as $room): ?>
            <?php
            $roomStatus = $room['status'];
            $cardClass = isset($roomStatusClasses[$roomStatus]) ? $roomStatusClasses[$roomStatus] : 'border-slate-200 bg-white';
            $badgeClass = isset($roomBadgeClasses[$roomStatus]) ? $roomBadgeClasses[$roomStatus] : 'bg-slate-100 text-slate-700';
            ?>
            <article class="overflow-hidden rounded-xl border bg-white shadow-sm <?php echo $cardClass; ?>">
                <div class="flex items-start justify-between gap-3 p-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500"><?php echo htmlspecialchars(isset($roomTypes[$room['room_type']]) ? $roomTypes[$room['room_type']] : ucfirst($room['room_type']), ENT_QUOTES, 'UTF-8'); ?> room</p>
                        <h2 class="mt-1 text-2xl font-bold text-ihtm-navy">Room <?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(isset($roomStatuses[$roomStatus]) ? $roomStatuses[$roomStatus] : ucfirst($roomStatus), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="space-y-1 border-t border-black/5 px-5 py-3 text-sm text-slate-600">
                    <p>Capacity: <?php echo (int) $room['capacity']; ?> guest<?php echo (int) $room['capacity'] === 1 ? '' : 's'; ?></p>
                    <p>Rate: $<?php echo number_format((float) $room['nightly_rate'], 2); ?> / night</p>
                </div>
                <details class="border-t border-black/5 bg-white/80">
                    <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-ihtm-navy hover:bg-slate-50">Edit Room</summary>
                    <form action="room_actions.php" method="post" class="space-y-3 px-5 pb-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="room_id" value="<?php echo (int) $room['id']; ?>">
                        <div>
                            <label for="room-number-<?php echo (int) $room['id']; ?>" class="mb-1 block text-xs font-semibold text-slate-600">Room number</label>
                            <input id="room-number-<?php echo (int) $room['id']; ?>" name="room_number" type="text" required maxlength="20" value="<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="room-type-<?php echo (int) $room['id']; ?>" class="mb-1 block text-xs font-semibold text-slate-600">Room type</label>
                                <select id="room-type-<?php echo (int) $room['id']; ?>" name="room_type" required class="w-full rounded-md border border-slate-300 bg-white px-2 py-2 text-sm">
                                    <?php foreach ($roomTypes as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $room['room_type'] === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label for="room-capacity-<?php echo (int) $room['id']; ?>" class="mb-1 block text-xs font-semibold text-slate-600">Max heads</label>
                                <input id="room-capacity-<?php echo (int) $room['id']; ?>" name="capacity" type="number" required min="1" max="2147483647" value="<?php echo (int) $room['capacity']; ?>" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                            </div>
                            <div>
                                <label for="room-rate-<?php echo (int) $room['id']; ?>" class="mb-1 block text-xs font-semibold text-slate-600">Rate / night</label>
                                <input id="room-rate-<?php echo (int) $room['id']; ?>" name="nightly_rate" type="number" required min="0" max="99999999.99" step="0.01" value="<?php echo htmlspecialchars((string) $room['nightly_rate'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-md border border-slate-300 px-2 py-2 text-sm">
                            </div>
                            <div>
                                <label for="room-status-<?php echo (int) $room['id']; ?>" class="mb-1 block text-xs font-semibold text-slate-600">Status</label>
                                <select id="room-status-<?php echo (int) $room['id']; ?>" name="status" required class="w-full rounded-md border border-slate-300 bg-white px-2 py-2 text-sm">
                                    <?php foreach ($roomStatuses as $value => $label): ?><option value="<?php echo $value; ?>" <?php echo $roomStatus === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="w-full rounded-md bg-ihtm-navy px-3 py-2 text-sm font-semibold text-white hover:bg-ihtm-blue">Save Room Changes</button>
                    </form>
                </details>
                <form action="room_actions.php" method="post" class="border-t border-black/5 bg-white px-5 py-3" onsubmit="return confirm('Permanently delete this room? Rooms with reservation history cannot be deleted.');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="room_id" value="<?php echo (int) $room['id']; ?>">
                    <button type="submit" class="text-sm font-semibold text-red-700 underline decoration-red-200 underline-offset-2 hover:text-red-900">Delete room</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
