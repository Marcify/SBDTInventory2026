<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$page_title = 'Manage Categories';
$active_nav = 'categories';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar">
    <h1 class="page-title">Manage Categories</h1>
    <a href="create.php" class="add-btn">+ Add New Category</a>
</div>

<div class="content-box">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="2" class="empty">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
