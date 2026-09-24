<?php

$appEnv = $_ENV['APP_ENV'] ?? 'local';

$isProduction = ($appEnv === 'production');

if (session_status() === PHP_SESSION_NONE) {

    session_name(
        $_ENV['SESSION_NAME'] ?? 'tablebuzz_session'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isProduction,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

define(
    'APP_NAME',
    $_ENV['APP_NAME'] ?? 'TableBuzz'
);

define(
    'APP_ENV',
    $appEnv
);

define(
    'APP_URL',
    rtrim(
        $_ENV['APP_URL'] ?? 'http://localhost/tablebuzz-v2',
        '/'
    )
);

define(
    'IS_PRODUCTION',
    $isProduction
);