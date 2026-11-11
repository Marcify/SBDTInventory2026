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
 * Force login. Redirect to login.php if not logged in.
 * Used on top of pages that require authentication.
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: /SBDTInventory/login.php');
        exit();
    }
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