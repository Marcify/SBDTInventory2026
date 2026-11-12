<?php

session_start();
require_once __DIR__ . '/../config/database.php';

/**
 * Is a user logged in?
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Get the logged-in user's data
 */
function current_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

/**
 * Force login. Redirect to index.php if not logged in.
 * Revalidates the account against the database on every
 * protected request, so deactivation and role changes
 * take effect immediately.
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /SBDTInventory/index.php');
        exit();
    }

    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT id, username, role
         FROM users
         WHERE id = ? AND is_active = TRUE'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // Account deleted or deactivated: kill the session.
        session_unset();
        session_destroy();
        header('Location: /SBDTInventory/index.php');
        exit();
    }

    // Keep the session in sync with the database (e.g., after a role change).
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
}

/**
 * Force admin role. Dancers get bounced to their dashboard.
 * Used on top of pages that require admin role.
 */
function require_admin() {
    require_login();

    if ($_SESSION['role'] !== 'admin') {
        header('Location: /SBDTInventory/dancer/dashboard.php');
        exit();
    }
}

/**
 * Force dancer role. Admins get bounced to their dashboard.
 * Used on top of pages that require dancer role.
 */
function require_dancer() {
    require_login();

    if ($_SESSION['role'] !== 'dancer') {
        header('Location: /SBDTInventory/admin/dashboard.php');
        exit();
    }
}

/**
 * Get (or create) the CSRF token for this session.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Hidden input to include in every form.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token())
        . '">';
}

/**
 * Reject POST requests without a valid CSRF token.
 * Call immediately after require_login()/require_admin()/require_dancer().
 */
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(403);
        exit('Invalid or missing CSRF token. Go back and reload the page.');
    }
}
