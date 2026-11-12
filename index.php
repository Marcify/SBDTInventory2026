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
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verify password using password_verify()
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['is_active']) {
                $error = "This account has been deactivated. Contact an administrator.";
            } else {
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
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#841818">
    <title>Sign In - SBDT Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/styles.css">
</head>
<body class="login-page">

<div class="card login-card">
    <div class="login-accent" aria-hidden="true"></div>
    <div class="card-body p-4 p-sm-5">
        <img class="logo" src="./assets/images/sbdt-logo.png" alt="SBDT Logo">

        <h1 class="system-name">Inventory Management System</h1>
        <p class="description">Sign in to your account</p>

        <?php if ($error): ?>
            <div class="alert alert-danger text-start message error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="post" class="text-start">
            <div class="mb-3 form-group">
                <label class="form-label" for="username">Username</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
                    <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
                </div>
            </div>

            <div class="mb-4 form-group">
                <label class="form-label" for="password">Password</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
                    <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-maroon w-100">Login</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelector('form')?.addEventListener('submit', function (event) {
        if (event.defaultPrevented) return;
        var btn = this.querySelector('button[type="submit"]');
        if (!btn) return;
        btn.classList.add('btn-loading');
        btn.setAttribute('aria-busy', 'true');
    });
</script>
</body>
</html>
