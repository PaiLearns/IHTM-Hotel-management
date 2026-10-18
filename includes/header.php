<?php

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/session.php';
    start_secure_session();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$currentUsername = isset($_SESSION['username']) ? $_SESSION['username'] : 'Receptionist';
$currentRole = isset($_SESSION['role']) ? ucfirst($_SESSION['role']) : 'Receptionist';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

function nav_link_class($page, $currentPage)
{
    $base = 'rounded-md px-3 py-2 text-sm font-semibold transition-colors';
    $activePage = in_array($currentPage, array('new_reservation.php', 'invoice.php'), true)
        ? 'reservations.php'
        : ($currentPage === 'process_staff.php' ? 'staff.php' : $currentPage);
    return $page === $activePage
        ? $base . ' bg-amber-400 text-slate-950'
        : $base . ' text-white hover:bg-amber-400 hover:text-slate-950';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . ' | ' : ''; ?>IHTM - Institute of Hospitality and Tourism Management</title>
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
<body class="min-h-screen bg-slate-100 text-slate-800 antialiased">
<header class="bg-ihtm-navy text-white shadow-lg">
    <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
        <a href="dashboard.php" class="flex items-center gap-3" aria-label="IHTM dashboard">
            <img src="assets/images/ihtm.png" alt="IHTM logo" class="h-12 w-12 rounded-lg bg-white object-contain p-1">
            <span>
                <span class="block text-xl font-bold tracking-wide">IHTM</span>
                <span class="block text-xs text-slate-200 sm:text-sm">Institute of Hospitality and Tourism Management</span>
            </span>
        </a>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between lg:justify-end">
            <nav aria-label="Main navigation" class="flex flex-wrap items-center gap-1">
                <a href="dashboard.php" class="<?php echo nav_link_class('dashboard.php', $currentPage); ?>">Dashboard</a>
                <a href="rooms.php" class="<?php echo nav_link_class('rooms.php', $currentPage); ?>">Rooms</a>
                <a href="reservations.php" class="<?php echo nav_link_class('reservations.php', $currentPage); ?>">Reservations</a>
                <a href="inventory.php" class="<?php echo nav_link_class('inventory.php', $currentPage); ?>">Inventory</a>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="staff.php" class="<?php echo nav_link_class('staff.php', $currentPage); ?>">Staff</a>
                <?php endif; ?>
            </nav>
            <div class="flex items-center justify-between gap-3 border-t border-white/20 pt-3 sm:border-l sm:border-t-0 sm:pl-4 sm:pt-0">
                <span class="text-sm">
                    <span class="block font-semibold"><?php echo htmlspecialchars($currentUsername, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="block text-xs text-slate-300"><?php echo htmlspecialchars($currentRole, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <form action="logout.php" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="rounded-md border border-amber-300 px-3 py-2 text-sm font-semibold text-amber-200 transition hover:bg-amber-400 hover:text-slate-950">Logout</button>
                </form>
            </div>
        </div>
    </div>
</header>
<main class="mx-auto min-h-[calc(100vh-10rem)] max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
