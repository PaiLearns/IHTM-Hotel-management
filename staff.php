<?php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Administrator access is required to manage staff accounts.');
}

try {
    $statement = $pdo->query(
        'SELECT id, username, full_name, role, status, created_at
         FROM users
         ORDER BY full_name, username'
    );
    $staffMembers = $statement->fetchAll();
} catch (PDOException $exception) {
    error_log('IHTM staff list query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Staff accounts could not be loaded. Please try again later.');
}

$flash = isset($_SESSION['staff_flash']) ? $_SESSION['staff_flash'] : null;
unset($_SESSION['staff_flash']);
$old = isset($_SESSION['staff_form_old']) ? $_SESSION['staff_form_old'] : array();
unset($_SESSION['staff_form_old']);
$pageTitle = 'Staff Management';

require __DIR__ . '/includes/header.php';
?>
<section class="mb-6">
    <p class="text-sm font-semibold uppercase tracking-wider text-amber-700">Administration</p>
    <h1 class="mt-1 text-3xl font-bold tracking-tight text-ihtm-navy">Staff Management</h1>
    <p class="mt-2 text-slate-600">Create accounts for reception staff and administrators.</p>
</section>

<?php if ($flash): ?>
    <div role="status" class="mb-5 rounded-lg border px-4 py-3 text-sm font-medium <?php echo $flash['type'] === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-800'; ?>">
        <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<section class="mb-7 rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-bold text-ihtm-navy">Add staff account</h2>
        <p class="mt-1 text-sm text-slate-500">Passwords must be at least 12 characters. Share passwords with staff securely.</p>
    </div>
    <form action="process_staff.php" method="post" class="px-5 py-5">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="staff-full-name" class="mb-1.5 block text-sm font-semibold text-slate-700">Full name</label>
                <input id="staff-full-name" name="full_name" type="text" required maxlength="120" autocomplete="name" value="<?php echo htmlspecialchars(isset($old['full_name']) ? $old['full_name'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="staff-username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username</label>
                <input id="staff-username" name="username" type="text" required minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" autocomplete="username" value="<?php echo htmlspecialchars(isset($old['username']) ? $old['username'] : '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                <p class="mt-1 text-xs text-slate-500">3–50 letters, numbers, periods, underscores, or hyphens.</p>
            </div>
            <div>
                <label for="staff-role" class="mb-1.5 block text-sm font-semibold text-slate-700">Role</label>
                <select id="staff-role" name="role" required class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    <option value="receptionist" <?php echo !isset($old['role']) || $old['role'] === 'receptionist' ? 'selected' : ''; ?>>Receptionist</option>
                    <option value="admin" <?php echo isset($old['role']) && $old['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
            </div>
            <div>
                <label for="staff-password" class="mb-1.5 block text-sm font-semibold text-slate-700">Initial password</label>
                <input id="staff-password" name="password" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
            <div>
                <label for="staff-password-confirm" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm password</label>
                <input id="staff-password-confirm" name="password_confirm" type="password" required minlength="12" maxlength="72" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3.5 py-2.5 outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
            </div>
        </div>
        <button type="submit" class="mt-5 rounded-lg bg-ihtm-navy px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-ihtm-blue">Create Staff Account</button>
    </form>
</section>

<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-4">
        <h2 class="text-lg font-bold text-ihtm-navy">Staff accounts</h2>
        <p class="mt-1 text-sm text-slate-500"><?php echo count($staffMembers); ?> account<?php echo count($staffMembers) === 1 ? '' : 's'; ?></p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-semibold">Name</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Username</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Role</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Status</th>
                    <th scope="col" class="px-5 py-3 font-semibold">Added</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (!$staffMembers): ?>
                    <tr><td colspan="5" class="px-5 py-8 text-center text-slate-500">No staff accounts found.</td></tr>
                <?php else: ?>
                    <?php foreach ($staffMembers as $staff): ?>
                        <tr>
                            <td class="px-5 py-3.5 font-medium text-slate-800"><?php echo htmlspecialchars($staff['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars($staff['username'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars(ucfirst($staff['role']), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="px-5 py-3.5"><span class="rounded-full px-2.5 py-1 text-xs font-semibold <?php echo $staff['status'] === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'; ?>"><?php echo htmlspecialchars(ucfirst($staff['status']), ENT_QUOTES, 'UTF-8'); ?></span></td>
                            <td class="whitespace-nowrap px-5 py-3.5 text-slate-600"><?php echo htmlspecialchars(date('M j, Y', strtotime($staff['created_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
