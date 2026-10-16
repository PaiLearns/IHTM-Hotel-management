<?php

ini_set('display_errors', '0');

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    http_response_code(404);
    exit;
}

function start_secure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    $isHttps = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params(array(
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ));

    if (!session_start()) {
        throw new RuntimeException('Unable to start the session.');
    }
}
