<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);

$stmt = $pdo->prepare("SELECT * FROM users ORDER BY role DESC, username ASC LIMIT ? OFFSET ?");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll();

$page_title = 'Manage Users';
$active_nav = 'users';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar">
    <h1 class="page-title">Manage Users</h1>
    <a href="create.php" class="add-btn">+ Add New User</a>
</div>

<div class="content-box">
    <div class="table-wrapper">
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
                            <span class="badge <?= $u['role'] === 'admin' ? 'badge-unavailable' : 'badge-borrowed' ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $u['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Deactivated' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form action="toggle_status.php" method="POST">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="current_status" value="<?= $u['is_active'] ?>">
                                    <button type="submit" class="<?= $u['is_active'] ? 'remove-btn' : 'save-btn' ?>">
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
    </div>

    <?php render_pagination($page, $perPage, $totalRows, $totalPages); ?>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
