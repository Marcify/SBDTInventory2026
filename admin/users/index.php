<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$stmt = $pdo->query("SELECT * FROM users ORDER BY role DESC, username ASC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <style>
        body { font-family: sans-serif; margin: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f4f4f4; }
        .btn { text-decoration: none; padding: 5px 10px; background: #333; color: white; border-radius: 3px; font-size: 14px; border: none; cursor: pointer; }
        .btn-danger { background: #c00; }
        .btn-success { background: #28a745; }
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 12px; font-weight: bold; color: white; }
        .bg-admin { background: #333; }
        .bg-dancer { background: #007bff; }
        .bg-inactive { background: #999; }
    </style>
</head>
<body>
    <h1>Manage Users</h1>
    <a href="/SBDTInventory/admin/dashboard.php">Back to Dashboard</a> | 
    <a href="create.php" class="btn">+ Add New User</a>

    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['name']) ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <span class="badge <?= $u['role'] === 'admin' ? 'bg-admin' : 'bg-dancer' ?>">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($u['is_active']): ?>
                            <span style="color: green; font-weight: bold;">Active</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">Deactivated</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Prevent admin from deactivating themselves -->
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                            <form action="toggle_status.php" method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $u['is_active'] ?>">
                                <button type="submit" class="btn <?= $u['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                                    <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <em>(You)</em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>