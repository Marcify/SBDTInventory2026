<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_csrf();

$user = current_user();

// Where to send the user back after the attempt (same host only).
$fallback = $user['role'] === 'admin'
    ? '/SBDTInventory/admin/dashboard.php'
    : '/SBDTInventory/dancer/dashboard.php';

function pw_redirect(string $fallback, array $params): void {
    $target = $fallback;

    // Redirect back to the page the user came from, but only
    // within the same host to avoid open redirects.
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $parts = parse_url($_SERVER['HTTP_REFERER']);

        if (!empty($parts['host']) && $parts['host'] === ($_SERVER['HTTP_HOST'] ?? '')) {
            $target = $parts['path'];
            parse_str($parts['query'] ?? '', $query);
            unset($query['pw'], $query['pw_error']);
            $target .= '?' . http_build_query(array_merge($query, $params));
            header('Location: ' . $target);
            return;
        }
    }

    header('Location: ' . $target . '?' . http_build_query($params));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $fallback);
    exit();
}

$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
$stmt->execute([$user['id']]);
$row = $stmt->fetch();

$error = null;

if (!$row || !password_verify($currentPassword, $row['password'])) {
    $error = 'Current password is incorrect.';
} elseif (strlen($newPassword) < 8) {
    $error = 'New password must be at least 8 characters.';
} elseif (strlen($newPassword) > 72) {
    $error = 'New password must not exceed 72 characters.';
} elseif ($newPassword !== $confirmPassword) {
    $error = 'New password and confirmation do not match.';
} elseif ($currentPassword === $newPassword) {
    $error = 'New password must be different from the current password.';
}

if ($error !== null) {
    pw_redirect($fallback, ['pw_error' => $error]);
    exit();
}

$stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
$stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $user['id']]);

pw_redirect($fallback, ['pw' => 'changed']);
exit();
