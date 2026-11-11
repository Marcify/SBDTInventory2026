<?php
declare(strict_types=1);

route('POST', '/auth/login', function () {
    $username = trim((string)body('username', ''));
    $password = (string)body('password', '');

    if ($username === '' || $password === '') {
        json_validation(['credentials' => 'Username and password required']);
    }

    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_error('Invalid username or password', 401);
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