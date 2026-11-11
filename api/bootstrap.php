<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/response.php';

// Log internal errors rather than exposing them to API clients.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    json_error('Internal server error', 500);
});

session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/router.php';
require_once __DIR__ . '/includes/validate.php';
require_once __DIR__ . '/includes/auth.php';
