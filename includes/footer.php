</main>
<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-5 text-center text-sm text-slate-500 sm:px-6 lg:px-8">
        &copy; <?php echo date('Y');                 if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
            http_response_code(404);
            exit;
        }

        ?> IHTM - Institute of Hospitality and Tourism Management
    </div>
</footer>
</body>
</html>
