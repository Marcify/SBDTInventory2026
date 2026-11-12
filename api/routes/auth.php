<?php
declare(strict_types=1);

route('POST', '/auth/login', function () {
    $username = body('username', '');
    $password = body('password', '');

    if (!is_string($username) || !is_string($password)) {
        json_validation([
            'credentials' => 'Username and password must be strings',
        ]);
    }

    $username = trim($username);

    if ($username === '' || $password === '') {
        json_validation(['credentials' => 'Username and password required']);
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_error('Invalid username or password', 401);
    }

    if (!(bool)$user['is_active']) {
        json_error('This account has been deactivated. Contact an administrator.', 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = (int)$user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['role'];
    unset($_SESSION['api_csrf']);          // rotate on login

    json_success([
        'user' => [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
        ],
        'csrf_token' => api_issue_csrf(),
    ]);
});

route('POST', '/auth/logout', function () {
    api_require_csrf();
    $_SESSION = [];
    session_destroy();
    json_success(['message' => 'Logged out']);
});

route('GET', '/me', function () {
    $u = api_require_login();
    json_success(['user' => $u, 'csrf_token' => api_issue_csrf()]);
});

route('POST', '/auth/change-password', function () {
    api_require_login();
    api_require_csrf();

    $currentPassword = body('current_password');
    $newPassword = body('new_password');

    $errors = [];

    if (!is_string($currentPassword) || $currentPassword === '') {
        $errors['current_password'] = 'Current password is required';
    }

    if (!is_string($newPassword) || strlen($newPassword) < 8) {
        $errors['new_password'] =
            'New password must be a string of at least 8 characters';
    } elseif (strlen($newPassword) > 72) {
        $errors['new_password'] =
            'New password must not exceed 72 characters';
    }

    if ($errors) {
        json_validation($errors);
    }

    $user = api_current_user();

    global $pdo;

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPassword, $row['password'])) {
        json_validation([
            'current_password' => 'Current password is incorrect',
        ]);
    }

    $stmt = $pdo->prepare(
        'UPDATE users SET password = ? WHERE id = ?'
    );

    $stmt->execute([
        password_hash($newPassword, PASSWORD_DEFAULT),
        $user['id'],
    ]);

    json_success(['message' => 'Password updated']);
});