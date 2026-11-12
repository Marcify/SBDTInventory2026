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

<div class="card edit-panel border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="section-title h4">Add New User</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger message error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="row">
                <div class="col-12 col-md-6 mb-3 form-group">
                    <label class="form-label" for="name">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>

                <div class="col-12 col-md-6 mb-3 form-group">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
            </div>

            <div class="mb-3 form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" required>
            </div>

            <div class="mb-3 form-group">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="dancer">Dancer</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div class="d-flex flex-wrap gap-2 form-group-spaced">
                <button type="submit" class="btn btn-maroon save-btn">Create User</button>
                <a href="index.php" class="btn btn-outline-maroon cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
