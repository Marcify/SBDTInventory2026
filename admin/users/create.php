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

$page_title = 'Add User';
$active_nav = 'users';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="edit-panel">
    <h3 class="section-title">Add New User</h3>

    <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Full Name</label>
            <input type="text" id="name" name="name" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="role">Role</label>
            <select id="role" name="role" required>
                <option value="dancer">Dancer</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div class="form-group-spaced">
            <button type="submit" class="save-btn">Create User</button>
            <a href="index.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
