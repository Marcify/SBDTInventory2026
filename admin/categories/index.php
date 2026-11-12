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

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Manage Categories</h1>
    <a href="create.php" class="btn btn-maroon add-btn"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add New Category</a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show message success" role="alert">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show message error" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card content-box border-0 shadow-sm">
    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
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
                                    <a href="edit.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-maroon edit-btn">Edit</a>
                                    <?php if ((int)$cat['item_count'] === 0): ?>
                                        <form action="delete.php" method="POST"
                                              onsubmit="return confirm('Delete category &quot;<?= htmlspecialchars($cat['name']) ?>&quot;?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-maroon remove-btn">Delete</button>
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
