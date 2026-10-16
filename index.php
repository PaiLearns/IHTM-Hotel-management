<?php

require_once __DIR__ . '/includes/session.php';
start_secure_session();

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$loginError = isset($_SESSION['login_error']) ? $_SESSION['login_error'] : '';
unset($_SESSION['login_error']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in | IHTM - Institute of Hospitality and Tourism Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ihtm: {
                            navy: '#102A43',
                            blue: '#163B5C',
                            gold: '#F4B942'
                        }
                    }
                }
            }
        };
    </script>
</head>
<body class="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-100 via-white to-blue-100 px-4 py-10 text-slate-800">
    <main class="w-full max-w-md">
        <section class="overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200">
            <div class="bg-ihtm-navy px-8 py-8 text-center text-white">
                <img src="assets/images/ihtm.png" alt="IHTM logo" class="mx-auto mb-4 h-20 w-20 rounded-xl bg-white object-contain p-2">
                <p class="text-2xl font-bold tracking-wide">IHTM</p>
                <h1 class="mt-1 text-sm font-medium leading-6 text-slate-200">Institute of Hospitality and Tourism Management</h1>
            </div>
            <div class="px-8 py-8">
                <h2 class="text-xl font-bold text-ihtm-navy">Receptionist sign in</h2>
                <p class="mt-1 text-sm text-slate-500">Enter your credentials to access the hotel management system.</p>

                <?php if ($loginError !== ''): ?>
                    <div role="alert" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        <?php echo htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <form action="login_process.php" method="post" class="mt-6 space-y-5">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div>
                        <label for="username" class="mb-1.5 block text-sm font-semibold text-slate-700">Username</label>
                        <input id="username" name="username" type="text" autocomplete="username" required maxlength="50" autofocus
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-3 outline-none transition placeholder:text-slate-400 focus:border-ihtm-gold focus:ring-2 focus:ring-amber-200">
                    </div>
                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="w-full rounded-lg border border-slate-300 px-3.5 py-3 outline-none transition placeholder:text-slate-400 focus:border-ihtm-gold focus:ring-2 focus:ring-amber-200">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-ihtm-navy px-4 py-3 font-semibold text-white shadow-sm transition hover:bg-ihtm-blue focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2">
                        Sign in
                    </button>
                </form>
            </div>
        </section>
        <p class="mt-5 text-center text-sm text-slate-500">Secure front desk access</p>
    </main>
</body>
</html>
