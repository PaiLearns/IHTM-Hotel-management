<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

$requestedId = isset($_GET['reservation_id']) ? $_GET['reservation_id'] : (isset($_GET['id']) ? $_GET['id'] : null);
$reservationId = $requestedId !== null ? filter_var($requestedId, FILTER_VALIDATE_INT) : false;

if ($reservationId === false || $reservationId < 1) {
    http_response_code(400);
    exit('A valid reservation ID is required to view this invoice.');
}

try {
    $statement = $pdo->prepare(
        'SELECT
            res.id,
            res.check_in,
            res.check_out,
            res.status,
            g.first_name,
            g.last_name,
            g.email,
            g.phone,
            r.room_number,
            r.room_type,
            r.nightly_rate
         FROM reservations res
         INNER JOIN guests g ON g.id = res.guest_id
         INNER JOIN rooms r ON r.id = res.room_id
         WHERE res.id = :reservation_id
         LIMIT 1'
    );
    $statement->execute(array('reservation_id' => $reservationId));
    $invoice = $statement->fetch();
} catch (PDOException $exception) {
    error_log('IHTM invoice lookup failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('The invoice could not be loaded. Please try again later.');
}

if (!$invoice) {
    http_response_code(404);
    exit('The requested reservation invoice could not be found.');
}

$checkIn = new DateTimeImmutable($invoice['check_in']);
$checkOut = new DateTimeImmutable($invoice['check_out']);
$totalNights = max(0, (int) $checkIn->diff($checkOut)->format('%r%a'));
$nightlyRate = (float) $invoice['nightly_rate'];
$roomTotal = $nightlyRate * $totalNights;
$taxesAndFees = 0.00;
$amountDue = $roomTotal + $taxesAndFees;
$pageTitle = 'Invoice #' . str_pad((string) $invoice['id'], 6, '0', STR_PAD_LEFT);
$invoiceStatusLabels = array(
    'pending' => 'Booked',
    'confirmed' => 'Booked',
    'checked_in' => 'Checked-In',
    'checked_out' => 'Checked-Out',
    'cancelled' => 'Cancelled',
);
$invoiceStatus = isset($invoiceStatusLabels[$invoice['status']])
    ? $invoiceStatusLabels[$invoice['status']]
    : ucwords(str_replace('_', ' ', $invoice['status']));

require __DIR__ . '/includes/header.php';
?>
<style>
    @media print {
        @page {
            margin: 16mm;
            size: auto;
        }
        html, body {
            background: #fff !important;
            color: #111827 !important;
            min-height: auto !important;
        }
        body > header,
        body > footer,
        .no-print {
            display: none !important;
        }
        body > main {
            display: block !important;
            max-width: none !important;
            min-height: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .invoice-sheet {
            border: 0 !important;
            box-shadow: none !important;
            margin: 0 !important;
            max-width: none !important;
            padding: 0 !important;
        }
        .invoice-table thead {
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }
        a {
            color: inherit !important;
            text-decoration: none !important;
        }
    }
</style>

<div class="no-print mb-5 flex flex-wrap items-center justify-between gap-3">
    <a href="reservations.php" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-ihtm-navy transition hover:bg-slate-50">&larr; Back to reservations</a>
    <button type="button" onclick="window.print()" class="rounded-lg bg-amber-400 px-5 py-2.5 text-sm font-semibold text-slate-950 shadow-sm transition hover:bg-amber-300">Print invoice</button>
</div>

<article class="invoice-sheet mx-auto max-w-4xl rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
    <header class="flex flex-col gap-5 border-b-2 border-ihtm-navy pb-6 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <img src="assets/images/ihtm.png" alt="IHTM logo" class="h-16 w-16 rounded-lg object-contain">
            <div>
                <p class="text-xl font-extrabold tracking-wide text-ihtm-navy">IHTM</p>
                <p class="max-w-sm text-sm font-medium text-slate-600">Institute of Hospitality and Tourism Management</p>
            </div>
        </div>
        <div class="sm:text-right">
            <h1 class="text-3xl font-bold uppercase tracking-wide text-ihtm-navy">Invoice</h1>
            <p class="mt-1 font-semibold text-slate-700">#<?php echo str_pad((string) $invoice['id'], 6, '0', STR_PAD_LEFT); ?></p>
            <p class="mt-1 text-sm text-slate-500">Issued <?php echo htmlspecialchars(date('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </header>

    <section class="grid gap-6 border-b border-slate-200 py-6 sm:grid-cols-2">
        <div>
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Billed to</h2>
            <p class="mt-2 text-lg font-bold text-slate-900"><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name'], ENT_QUOTES, 'UTF-8'); ?></p>
            <?php if (!empty($invoice['email'])): ?><p class="mt-1 text-sm text-slate-600"><?php echo htmlspecialchars($invoice['email'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($invoice['phone'])): ?><p class="mt-1 text-sm text-slate-600"><?php echo htmlspecialchars($invoice['phone'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (empty($invoice['email']) && empty($invoice['phone'])): ?><p class="mt-1 text-sm text-slate-500">No contact details on file</p><?php endif; ?>
        </div>
        <div class="sm:text-right">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Stay details</h2>
            <p class="mt-2 text-sm text-slate-700"><span class="font-semibold">Room:</span> <?php echo htmlspecialchars($invoice['room_number'] . ' · ' . ucfirst($invoice['room_type']), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mt-1 text-sm text-slate-700"><span class="font-semibold">Check-in:</span> <?php echo htmlspecialchars($checkIn->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mt-1 text-sm text-slate-700"><span class="font-semibold">Check-out:</span> <?php echo htmlspecialchars($checkOut->format('F j, Y'), ENT_QUOTES, 'UTF-8'); ?></p>
            <p class="mt-1 text-sm text-slate-700"><span class="font-semibold">Total nights:</span> <?php echo $totalNights; ?></p>
            <p class="mt-1 text-sm text-slate-700"><span class="font-semibold">Status:</span> <?php echo htmlspecialchars($invoiceStatus, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </section>

    <section class="py-6">
        <h2 class="mb-4 text-lg font-bold text-ihtm-navy">Cost breakdown</h2>
        <div class="overflow-x-auto">
            <table class="invoice-table min-w-full text-left text-sm">
                <thead class="bg-ihtm-navy text-white">
                    <tr>
                        <th scope="col" class="rounded-l-md px-4 py-3 font-semibold">Description</th>
                        <th scope="col" class="px-4 py-3 text-right font-semibold">Calculation</th>
                        <th scope="col" class="rounded-r-md px-4 py-3 text-right font-semibold">Amount (USD)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr>
                        <td class="px-4 py-4 text-slate-700">Room rate per night</td>
                        <td class="px-4 py-4 text-right text-slate-600"><?php echo $totalNights; ?> night<?php echo $totalNights === 1 ? '' : 's'; ?> &times; $<?php echo number_format($nightlyRate, 2); ?></td>
                        <td class="px-4 py-4 text-right font-medium text-slate-800">$<?php echo number_format($roomTotal, 2); ?></td>
                    </tr>
                    <tr>
                        <td class="px-4 py-4 text-slate-700">Taxes and fees</td>
                        <td class="px-4 py-4 text-right text-slate-600">No taxes or fees configured</td>
                        <td class="px-4 py-4 text-right font-medium text-slate-800">$<?php echo number_format($taxesAndFees, 2); ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-ihtm-navy">
                        <th scope="row" colspan="2" class="px-4 py-4 text-right text-base font-bold text-ihtm-navy">Total amount due</th>
                        <td class="px-4 py-4 text-right text-xl font-extrabold text-ihtm-navy">$<?php echo number_format($amountDue, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <footer class="border-t border-slate-200 pt-5 text-center text-sm text-slate-500">
        <p class="mb-2 font-medium text-slate-600">This invoice is a stay estimate. Payment status is not tracked by this system.</p>
        Thank you for staying with IHTM - Institute of Hospitality and Tourism Management.
    </footer>
</article>
<?php require __DIR__ . '/includes/footer.php'; ?>
