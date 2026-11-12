<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

// Category list with item usage counts
$stmt = $pdo->query(
    "SELECT c.*, COUNT(i.id) AS item_count
     FROM categories c
     LEFT JOIN items i ON i.category_id = c.id
     GROUP BY c.id
     ORDER BY c.name ASC"
);
$categories = $stmt->fetchAll();

// Flash messages passed through the query string by edit/delete handlers.
$success = isset($_GET['saved']) ? 'Category renamed successfully.' : '';
$error = '';
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$page_title = 'Manage Categories';
$active_nav = 'categories';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar">
    <h1 class="page-title">Manage Categories</h1>
    <a href="create.php" class="add-btn">+ Add New Category</a>
</div>

<?php if ($success): ?>
    <p class="message success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="message error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>

<div class="content-box">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Items</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($categories)): ?>
                    <tr>
                        <td colspan="4" class="empty">No categories found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= (int)$cat['item_count'] ?></td>
                            <td>
                                <div class="row-actions">
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="edit-btn">Edit</a>
                                    <?php if ((int)$cat['item_count'] === 0): ?>
                                        <form action="delete.php" method="POST"
                                              onsubmit="return confirm('Delete category &quot;<?= htmlspecialchars($cat['name']) ?>&quot;?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="remove-btn">Delete</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge badge-borrowed" title="Categories in use cannot be deleted">In use</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
