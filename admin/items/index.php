<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();
[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);

// Fetch all items with their category names (paginated)
$sql = "SELECT items.*, categories.name AS category_name 
        FROM items 
        LEFT JOIN categories ON items.category_id = categories.id 
        ORDER BY items.name ASC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$page_title = 'Manage Items';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar">
    <h1 class="page-title">Manage Items</h1>
    <a href="create.php" class="add-btn">+ Add New Item</a>
</div>

<div class="content-box">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Total</th>
                    <th>Available</th>
                    <th>Borrowed</th>
                    <th>Damaged</th>
                    <th>Lost</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="10" class="empty">No items found. Add one!</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $item['id'] ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name'] ?? 'None') ?></td>
                            <td class="quantity"><?= $item['total_stock'] ?></td>
                            <td class="quantity"><?= $item['available_stock'] ?></td>
                            <td><?= $item['borrowed_stock'] ?></td>
                            <td><?= $item['damaged_stock'] ?></td>
                            <td><?= $item['lost_stock'] ?></td>
                            <td>
                                <span class="<?= $item['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $item['is_active'] ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="edit.php?id=<?= $item['id'] ?>" class="edit-btn">Edit</a>
                                    <form action="toggle_status.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $item['is_active'] ?>">
                                        <button type="submit" class="<?= $item['is_active'] ? 'remove-btn' : 'save-btn' ?>">
                                            <?= $item['is_active'] ? 'Archive' : 'Unarchive' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php render_pagination($page, $perPage, $totalRows, $totalPages); ?>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>