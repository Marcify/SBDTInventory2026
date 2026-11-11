<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');           // never leak to JSON clients
ini_set('log_errors', '1');

require_once __DIR__ . '/../config/database.php';   // gives us $pdo
require_once __DIR__ . '/includes/response.php';
require_once __DIR__ . '/includes/router.php';
require_once __DIR__ . '/includes/auth.php';

// Session config — same cookie as web pages, hardened
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

// JSON-only: reject non-JSON Content-Type on mutating requests
$method = $_SERVER['REQUEST_METHOD'];
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') === false) {
        json_error('Content-Type must be application/json', 415);
    }
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if ($raw !== '' && $body === null && json_last_error() !== JSON_ERROR_NONE) {
        json_error('Malformed JSON body', 400);
    }
    $GLOBALS['request_body'] = is_array($body) ? $body : [];
} else {
    $GLOBALS['request_body'] = [];
}

// Convert warnings/notices into exceptions so nothing half-completes silently
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return false;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Catch-all: any uncaught throwable -> 500 JSON, logged server-side
set_exception_handler(function (Throwable $e) {
    error_log('[API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if ($e instanceof PDOException) {
        json_error('Database error', 500);
    }
    json_error('Internal server error', 500);
});

function body(string $key, mixed $default = null): mixed {
    return $GLOBALS['request_body'][$key] ?? $default;
}