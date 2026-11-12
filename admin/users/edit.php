<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$userId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($userId === false) {
    header('Location: index.php');
    exit();
}

global $pdo;

$stmt = $pdo->prepare('SELECT id, name, username, role, is_active FROM users WHERE id = ?');
$stmt->execute([$userId]);
$editUser = $stmt->fetch();

if (!$editUser) {
    header('Location: index.php');
    exit();
}

$isSelf = ($editUser['id'] == $_SESSION['user_id']);
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '') {
        $error = 'Name is required.';
    } elseif (mb_strlen($name) > 120) {
        $error = 'Name must not exceed 120 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $error = 'Username must be 3-50 characters using letters, numbers, dots, dashes, or underscores.';
    } elseif ($isSelf && $role !== 'admin') {
        $error = 'You cannot change your own role.';
    } elseif ($password !== '') {
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (strlen($password) > 72) {
            $error = 'Password must not exceed 72 characters.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Password and confirmation do not match.';
        }
    } else {
        // Duplicate username check (excluding this user).
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $userId]);

        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        } else {
            if ($password !== '') {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, password = ? WHERE id = ?');
                $stmt->execute([$name, $username, $role, password_hash($password, PASSWORD_DEFAULT), $userId]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ? WHERE id = ?');
                $stmt->execute([$name, $username, $role, $userId]);
            }

            header('Location: index.php');
            exit();
        }
    }
}

$page_title = 'Edit User';
$active_nav = 'users';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar">
    <h1 class="page-title">Edit User</h1>
    <a href="index.php" class="cancel-btn">&larr; Back to Users</a>
</div>

<div class="edit-panel">
    <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Name</label>
            <input type="text" id="name" name="name" maxlength="120"
                   value="<?= htmlspecialchars($editUser['name']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="username">Username</label>
            <input type="text" id="username" name="username" maxlength="50"
                   value="<?= htmlspecialchars($editUser['username']) ?>" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="role">Role</label>
            <select id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                <option value="dancer" <?= $editUser['role'] === 'dancer' ? 'selected' : '' ?>>Dancer</option>
                <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="admin">
                <p class="welcome">You cannot change your own role.</p>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label class="form-label" for="password">New Password <span class="welcome">(leave blank to keep current)</span></label>
            <input type="password" id="password" name="password" minlength="8" maxlength="72"
                   placeholder="••••••••">
        </div>
        <div class="form-group">
            <label class="form-label" for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" maxlength="72"
                   placeholder="••••••••">
        </div>
        <div class="form-group">
            <button type="submit" class="save-btn">Save Changes</button>
            <a href="index.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
