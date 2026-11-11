<?php
declare(strict_types=1);

function api_current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'       => (int)$_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? null,
        'role'     => $_SESSION['role'] ?? null,
    ];
}

function api_require_login(): array {
    $u = api_current_user();
    if (!$u) json_error('Authentication required', 401);
    return $u;
}

function api_require_role(string $role): array {
    $u = api_require_login();
    if ($u['role'] !== $role) json_error('Forbidden', 403);
    return $u;
}

function api_require_admin(): array  { return api_require_role('admin'); }
function api_require_dancer(): array { return api_require_role('dancer'); }

/** CSRF check for mutating requests. Token issued at login. */
function api_require_csrf(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) return;

    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored = $_SESSION['api_csrf'] ?? '';
    if ($stored === '' || !hash_equals($stored, $sent)) {
        json_error('Invalid or missing CSRF token', 403);
    }
}

function api_issue_csrf(): string {
    if (empty($_SESSION['api_csrf'])) {
        $_SESSION['api_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['api_csrf'];
}