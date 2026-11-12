<?php
declare(strict_types=1);

// List all users.
route('GET', '/users', function () {
    api_require_admin();

    global $pdo;

    $stmt = $pdo->query(
        'SELECT id, name, username, role, is_active, created_at
         FROM users
         ORDER BY role ASC, username ASC'
    );

    $users = array_map(
        static function (array $user): array {
            $user['id'] = (int)$user['id'];
            $user['is_active'] = (bool)$user['is_active'];

            return $user;
        },
        $stmt->fetchAll()
    );

    json_success(['users' => $users]);
});

// Create a user.
route('POST', '/users', function () {
    api_require_admin();
    api_require_csrf();

    $data = request_body();

    $unknownFields = array_diff(
        array_keys($data),
        ['name', 'username', 'password', 'role']
    );

    if ($unknownFields) {
        json_validation([
            'fields' => 'Unsupported fields: ' . implode(', ', $unknownFields),
        ]);
    }

    $name = $data['name'] ?? null;
    $username = $data['username'] ?? null;
    $password = $data['password'] ?? null;
    $role = $data['role'] ?? 'dancer';

    $errors = [];

    if (!is_string($name) || trim($name) === '') {
        $errors['name'] = 'Name is required and must be a string';
    } else {
        $name = trim($name);
        $length = preg_match_all('/./us', $name);

        if ($length === false || $length > 120) {
            $errors['name'] = 'Name must not exceed 120 characters';
        }
    }

    if (!is_string($username) || trim($username) === '') {
        $errors['username'] = 'Username is required and must be a string';
    } else {
        $username = trim($username);

        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $errors['username'] =
                'Username must be 3-50 characters using letters, numbers, dots, dashes, or underscores';
        }
    }

    if (!is_string($password) || strlen($password) < 8) {
        $errors['password'] = 'Password must be a string of at least 8 characters';
    } elseif (strlen($password) > 72) {
        // password_hash() with bcrypt uses only the first 72 bytes.
        $errors['password'] = 'Password must not exceed 72 characters';
    }

    if (
        !is_string($role) ||
        !in_array($role, ['admin', 'dancer'], true)
    ) {
        $errors['role'] = 'Role must be admin or dancer';
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, username, password, role)
             VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $name,
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
        ]);
    } catch (PDOException $e) {
        if ((int)($e->errorInfo[1] ?? 0) === 1062) {
            json_error('That username is already taken', 409);
        }

        throw $e;
    }

    json_success([
        'user' => [
            'id' => (int)$pdo->lastInsertId(),
            'name' => $name,
            'username' => $username,
            'role' => $role,
            'is_active' => true,
        ],
    ], 201);
});

// Update a user: name, username, role, password, and/or is_active.
route('PATCH', '/users/{id}', function ($id) {
    $currentUser = api_require_admin();
    api_require_csrf();

    $userId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($userId === false) {
        json_validation([
            'id' => 'User ID must be a positive integer',
        ]);
    }

    $data = request_body();

    $unknownFields = array_diff(
        array_keys($data),
        ['name', 'username', 'password', 'role', 'is_active']
    );

    if ($unknownFields) {
        json_validation([
            'fields' => 'Unsupported fields: ' . implode(', ', $unknownFields),
        ]);
    }

    if (!$data) {
        json_validation([
            'fields' => 'Provide at least one field to update',
        ]);
    }

    $errors = [];
    $sets = [];
    $params = [];

    // --- name (optional) ---
    if (array_key_exists('name', $data)) {
        $name = $data['name'];
        if (!is_string($name) || trim($name) === '') {
            $errors['name'] = 'Name is required and must be a string';
        } else {
            $name = trim($name);
            $length = preg_match_all('/./us', $name);
            if ($length === false || $length > 120) {
                $errors['name'] = 'Name must not exceed 120 characters';
            }
        }
        $sets['name'] = $name;
    }

    // --- username (optional) ---
    if (array_key_exists('username', $data)) {
        $username = $data['username'];
        if (!is_string($username) || trim($username) === '') {
            $errors['username'] = 'Username is required and must be a string';
        } else {
            $username = trim($username);
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
                $errors['username'] =
                    'Username must be 3-50 characters using letters, numbers, dots, dashes, or underscores';
            }
        }
        $sets['username'] = $username;
    }

    // --- password (optional) ---
    if (array_key_exists('password', $data)) {
        $password = $data['password'];
        if (!is_string($password) || strlen($password) < 8) {
            $errors['password'] = 'Password must be a string of at least 8 characters';
        } elseif (strlen($password) > 72) {
            $errors['password'] = 'Password must not exceed 72 characters';
        }
        $sets['__password'] = $password;
    }

    // --- role (optional) ---
    if (array_key_exists('role', $data)) {
        $role = $data['role'];
        if (
            !is_string($role) ||
            !in_array($role, ['admin', 'dancer'], true)
        ) {
            $errors['role'] = 'Role must be admin or dancer';
        } elseif ($userId === $currentUser['id'] && $role !== 'admin') {
            // Prevent an admin from demoting themselves out of the system.
            json_error('You cannot change your own role', 409);
        }
        $sets['role'] = $role;
    }

    // --- is_active (optional, self-lockout guard) ---
    if (array_key_exists('is_active', $data)) {
        if (!is_bool($data['is_active'])) {
            $errors['is_active'] = 'Active status must be true or false';
        } elseif ($userId === $currentUser['id'] && $data['is_active'] === false) {
            json_error('You cannot deactivate your own account', 409);
        }
        $sets['is_active'] = $data['is_active'] ? 1 : 0;
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        // Lock the row so concurrent edits cannot collide.
        $stmt = $pdo->prepare(
            'SELECT id FROM users WHERE id = ? FOR UPDATE'
        );
        $stmt->execute([$userId]);

        if (!$stmt->fetch()) {
            $pdo->rollBack();
            json_error('User not found', 404);
        }

        $sqlParts = [];
        foreach ($sets as $field => $value) {
            if ($field === '__password') {
                $sqlParts[] = 'password = ?';
                $params[] = password_hash($value, PASSWORD_DEFAULT);
            } else {
                $sqlParts[] = $field . ' = ?';
                $params[] = $value;
            }
        }

        // Duplicate username check happens inside the transaction.
        $params[] = $userId;

        $stmt = $pdo->prepare(
            'UPDATE users SET ' . implode(', ', $sqlParts) . ' WHERE id = ?'
        );

        try {
            $stmt->execute($params);
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $pdo->rollBack();
                json_error('That username is already taken', 409);
            }

            throw $e;
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $stmt = $pdo->prepare(
        'SELECT id, name, username, role, is_active, created_at
         FROM users
         WHERE id = ?'
    );

    $stmt->execute([$userId]);

    $user = $stmt->fetch();

    if ($user) {
        $user['id'] = (int)$user['id'];
        $user['is_active'] = (bool)$user['is_active'];
    }

    json_success(['user' => $user]);
});

