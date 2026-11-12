<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'dancer';

    if ($name === '' || $username === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Hash the password before saving
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $username, $hashed_password, $role]);

            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            // Catch duplicate username error
            $error = 'That username is already taken.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Add User</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="password"],
        select {
            padding: 8px;
            width: 300px;
        }

        button {
            padding: 10px 15px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 3px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>
    <h1>Add New User</h1>
    <a href="index.php">&larr; Back to Users</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required>
        </div>

        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required>
        </div>

        <div class="form-group">
            <label>Role</label>
            <select name="role" required>
                <option value="dancer">Dancer</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <button type="submit">Create User</button>
    </form>
</body>

</html>