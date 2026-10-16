<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$pageTitle = 'New Reservation';

try {
    $roomStatement = $pdo->query(
        "SELECT id, room_number, room_type, capacity, nightly_rate
         FROM rooms
         WHERE status = 'available'
         ORDER BY room_number"
    );
    $availableRooms = $roomStatement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM available room lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Available rooms could not be loaded. Please try again.');
}

$flash = isset($_SESSION['reservation_form_flash']) ? $_SESSION['reservation_form_flash'] : null;
unset($_SESSION['reservation_form_flash']);
$old = $flash && isset($flash['old']) ? $flash['old'] : array();

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Front desk workspace</p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">New reservation</h1>
    <p class="mt-2 text-slate-600">Add guest details, select an available room, and confirm the stay dates.</p>
</section>

<?php if ($flash): ?>
    <div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<form action="process_reservation.php" method="post" class="space-y-6">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-bold text-ihtm-navy">Guest details</h2>
        <p class="mt-1 text-sm text-slate-500">Guests with a matching email address or phone number will be linked to their existing record.</p>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div>
                <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-700">First name</label>
                <input id="first_name" name="first_name" type="text" required maxlength="80" autocomplete="given-name" value="<?php echo htmlspecialchars(isset($old['first_name']) ? $old['first_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-700">Last name</label>
                <input id="last_name" name="last_name" type="text" required maxlength="80" autocomplete="family-name" value="<?php echo htmlspecialchars(isset($old['last_name']) ? $old['last_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Email address</label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?php echo htmlspecialchars(isset($old['email']) ? $old['email'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700">Phone number</label>
                <input id="phone" name="phone" type="tel" maxlength="30" autocomplete="tel" value="<?php echo htmlspecialchars(isset($old['phone']) ? $old['phone'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                <p class="mt-1 text-xs text-slate-500">Provide an email address or phone number so the guest can be identified.</p>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <h2 class="text-lg font-bold text-ihtm-navy">Booking details</h2>
        <div class="mt-5 grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="room_id" class="mb-1.5 block text-sm font-semibold text-slate-700">Available room</label>
                <select id="room_id" name="room_id" required <?php echo !$availableRooms ? 'disabled' : ''; ?> class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    <option value="">Select a room</option>
                    <?php foreach ($availableRooms as $room): ?>
                        <option value="<?php echo (int) $room['id']; ?>" data-rate="<?php echo htmlspecialchars((string) $room['nightly_rate'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo isset($old['room_id']) && (string) $old['room_id'] === (string) $room['id'] ? 'selected' : ''; ?>>
                            Room <?php echo htmlspecialchars($room['room_number'], ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(ucfirst($room['room_type']), ENT_QUOTES, 'UTF-8'); ?> · $<?php echo number_format((float) $room['nightly_rate'], 2); ?>/night · capacity <?php echo (int) $room['capacity']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$availableRooms): ?><p class="mt-2 text-sm font-medium text-red-700">No rooms are currently available to book.</p><?php endif; ?>
            </div>
            <div>
                <label for="check_in" class="mb-1.5 block text-sm font-semibold text-slate-700">Check-in date</label>
                <input id="check_in" name="check_in" type="date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars(isset($old['check_in']) ? $old['check_in'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="check_out" class="mb-1.5 block text-sm font-semibold text-slate-700">Check-out date</label>
                <input id="check_out" name="check_out" type="date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" value="<?php echo htmlspecialchars(isset($old['check_out']) ? $old['check_out'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
        </div>
        <div class="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Estimated total: <strong id="estimated-total" class="text-ihtm-navy">Select a room and dates</strong>
            <span class="ml-1 text-xs text-slate-500">Calculated from nightly rate × nights; the server validates availability and dates when submitted.</span>
        </div>
    </section>
    <div class="flex flex-wrap gap-3">
        <button type="submit" <?php echo !$availableRooms ? 'disabled' : ''; ?> class="rounded-lg bg-ihtm-navy px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-ihtm-blue disabled:cursor-not-allowed disabled:opacity-50">Create reservation</button>
        <a href="reservations.php" class="rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
    </div>
</form>

<script>
    (function () {
        var roomSelect = document.getElementById('room_id');
        var checkIn = document.getElementById('check_in');
        var checkOut = document.getElementById('check_out');
        var total = document.getElementById('estimated-total');

        function updateEstimate() {
            var selectedRoom = roomSelect.options[roomSelect.selectedIndex];
            var start = new Date(checkIn.value + 'T00:00:00');
            var end = new Date(checkOut.value + 'T00:00:00');
            var nights = (end - start) / 86400000;
            var rate = selectedRoom ? parseFloat(selectedRoom.getAttribute('data-rate')) : NaN;

            if (!selectedRoom || !selectedRoom.value || !Number.isFinite(rate) || !Number.isInteger(nights) || nights < 1) {
                total.textContent = 'Select a room and valid dates';
                return;
            }

            total.textContent = '$' + (rate * nights).toFixed(2) + ' (' + nights + ' night' + (nights === 1 ? '' : 's') + ')';
        }

        roomSelect.addEventListener('change', updateEstimate);
        checkIn.addEventListener('change', function () {
            if (checkIn.value) {
                var minimumCheckout = new Date(checkIn.value + 'T00:00:00');
                minimumCheckout.setDate(minimumCheckout.getDate() + 1);
                var minValue = minimumCheckout.toISOString().slice(0, 10);
                checkOut.min = minValue;
                if (checkOut.value && checkOut.value < minValue) checkOut.value = minValue;
            }
            updateEstimate();
        });
        checkOut.addEventListener('change', updateEstimate);
        updateEstimate();
    }());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
