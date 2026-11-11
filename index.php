<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: /SBDTInventory/admin/dashboard.php');
    } else {
        header('Location: /SBDTInventory/dancer/dashboard.php');
    }
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate username/password, query users using PDO prepared statement
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verify password using password_verify()
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID and store user_id, username, role
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header('Location: /SBDTInventory/admin/dashboard.php');
            } else {
                header('Location: /SBDTInventory/dancer/dashboard.php');
            }

            exit();
        }

        $error = 'Invalid username or password.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body class="login-page">

    <div class="login-card">
        <img class="logo" src="./assets/images/sbdt-logo.png" alt="SBDT Logo">

        <h1 class="system-name">Inventory Management System</h1>
        <p class="description">Sign in to your account</p>

        <?php if ($error): ?>
            <p class="message error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label class="form-label" for="username">Username:</label>
                <input type="text" id="username" name="username" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>

            <button type="submit">Login</button>
        </form>
    </div>
    
</body>
</html>