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

    // Validate all fields first (password checks are a sub-branch,
    // not part of the update chain).
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
    }

    if ($error === null) {
        // Duplicate username check (excluding this user).
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $stmt->execute([$username, $userId]);

        if ($stmt->fetch()) {
            $error = 'That username is already taken.';
        }
    }

    if ($error === null) {
        if ($password !== '') {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ?, password = ? WHERE id = ?');
            $stmt->execute([$name, $username, $role, password_hash($password, PASSWORD_DEFAULT), $userId]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET name = ?, username = ?, role = ? WHERE id = ?');
            $stmt->execute([$name, $username, $role, $userId]);
        }

        header('Location: index.php?saved=1');
        exit();
    }
}

$page_title = 'Edit User';
$active_nav = 'users';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Edit User</h1>
    <a href="index.php" class="btn btn-outline-maroon cancel-btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Users</a>
</div>

<div class="card edit-panel border-0 shadow-sm">
    <div class="card-body p-4">
        <?php if ($error): ?>
            <div class="alert alert-danger message error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-12 col-md-6 mb-3 form-group">
                    <label class="form-label" for="name">Name</label>
                    <input type="text" class="form-control" id="name" name="name" maxlength="120"
                           value="<?= htmlspecialchars($editUser['name']) ?>" required>
                </div>
                <div class="col-12 col-md-6 mb-3 form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" maxlength="50"
                           value="<?= htmlspecialchars($editUser['username']) ?>" required>
                </div>
            </div>
            <div class="mb-3 form-group">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role" <?= $isSelf ? 'disabled' : '' ?>>
                    <option value="dancer" <?= $editUser['role'] === 'dancer' ? 'selected' : '' ?>>Dancer</option>
                    <option value="admin" <?= $editUser['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
                <?php if ($isSelf): ?>
                    <input type="hidden" name="role" value="admin">
                    <p class="form-note welcome">You cannot change your own role.</p>
                <?php endif; ?>
            </div>
            <div class="mb-3 form-group">
                <label class="form-label" for="password">New Password <span class="fw-normal text-muted">(leave blank to keep current)</span></label>
                <input type="password" class="form-control" id="password" name="password" minlength="8" maxlength="72"
                       autocomplete="new-password" placeholder="••••••••">
            </div>
            <div class="mb-3 form-group">
                <label class="form-label" for="confirm_password">Confirm New Password</label>
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" maxlength="72"
                       autocomplete="new-password" placeholder="••••••••">
            </div>
            <div class="d-flex flex-wrap gap-2 form-group">
                <button type="submit" class="btn btn-maroon save-btn">Save Changes</button>
                <a href="index.php" class="btn btn-outline-maroon cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
