<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

// Search filter: matches item name or category name.
$q = trim((string)($_GET['q'] ?? ''));
$where = '';
$searchParams = [];

if ($q !== '') {
    $where = 'WHERE items.name LIKE ? OR categories.name LIKE ?';
    $searchParams = ['%' . $q . '%', '%' . $q . '%'];
}

$countStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM items
     LEFT JOIN categories ON items.category_id = categories.id
     $where"
);
$countStmt->execute($searchParams);
$totalRows = (int)$countStmt->fetchColumn();

[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 10);

// Fetch all items with their category names (paginated, filtered)
$sql = "SELECT items.*, categories.name AS category_name 
        FROM items 
        LEFT JOIN categories ON items.category_id = categories.id 
        $where
        ORDER BY items.name ASC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
foreach ($searchParams as $i => $param) {
    $stmt->bindValue($i + 1, $param, PDO::PARAM_STR);
}
$stmt->bindValue(count($searchParams) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($searchParams) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll();

$page_title = 'Manage Items';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Manage Items</h1>
    <a href="create.php" class="btn btn-maroon add-btn"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add New Item</a>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="card-body border-bottom table-search">
        <form method="GET" class="search-form row g-2 align-items-center">
            <div class="col-12 col-md">
                <label class="visually-hidden" for="item-search">Search items</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="text" id="item-search" name="q" class="form-control search-input"
                           placeholder="Search item or category&hellip;"
                           value="<?= htmlspecialchars($q) ?>">
                </div>
            </div>
            <div class="col-6 col-md-auto">
                <button type="submit" class="btn btn-maroon search-btn w-100">Search</button>
            </div>
            <?php if ($q !== ''): ?>
                <div class="col-6 col-md-auto">
                    <a href="index.php" class="btn btn-outline-maroon cancel-btn w-100">Clear</a>
                </div>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
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
                        <td colspan="10" class="empty">
                            <?= $q !== '' ? 'No items match &quot;' . htmlspecialchars($q) . '&quot;.' : 'No items found. Add one!' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= $item['id'] ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= htmlspecialchars($item['category_name'] ?? 'None') ?></td>
                            <td class="quantity"><?= $item['total_stock'] ?></td>
                            <td class="quantity"><?= $item['available_stock'] ?></td>
                            <td class="borrowed-stock"><?= $item['borrowed_stock'] ?></td>
                            <td class="damaged-stock"><?= $item['damaged_stock'] ?></td>
                            <td class="lost-stock"><?= $item['lost_stock'] ?></td>
                            <td>
                                <span class="<?= $item['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                    <?= $item['is_active'] ? 'Active' : 'Archived' ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="edit.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-maroon edit-btn">Edit</a>
                                    <?php if ((int)$item['damaged_stock'] > 0): ?>
                                        <a href="repair.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-gold edit-btn">Repair (<?= $item['damaged_stock'] ?>)</a>
                                    <?php endif; ?>
                                    <form action="toggle_status.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="current_status" value="<?= $item['is_active'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $item['is_active'] ? 'btn-outline-maroon remove-btn' : 'btn-gold save-btn' ?>">
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

    <div class="card-body">
        <?php render_pagination($page, $perPage, $totalRows, $totalPages); ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>