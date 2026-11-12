<?php
require_once __DIR__ . '/../../includes/auth.php';
require_dancer();

// Search filter: matches item name or category name.
$q = trim((string)($_GET['q'] ?? ''));
$searchClause = '';
$searchParams = [];

if ($q !== '') {
    $searchClause = 'AND (items.name LIKE ? OR categories.name LIKE ?)';
    $searchParams = ['%' . $q . '%', '%' . $q . '%'];
}

// Only show items that are active and have available stock (optionally filtered)
$stmt = $pdo->prepare("
    SELECT items.*, categories.name AS category_name 
    FROM items 
    JOIN categories ON items.category_id = categories.id 
    WHERE items.is_active = TRUE AND items.available_stock > 0
    $searchClause
    ORDER BY categories.name ASC, items.name ASC
");
$stmt->execute($searchParams);
$items = $stmt->fetchAll();

$page_title = 'Browse Items';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Browse Items</h1>
    <a href="../borrowings/create.php" class="btn btn-maroon borrow-btn">Go to Borrow Form</a>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="card-body border-bottom table-search">
        <form method="GET" class="search-form row g-2 align-items-center">
            <div class="col-12 col-md">
                <label class="visually-hidden" for="browse-search">Search items</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <input type="text" id="browse-search" name="q" class="form-control search-input"
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
                    <th>Category</th>
                    <th>Item Name</th>
                    <th>Available Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr>
                        <td colspan="3" class="empty">
                            <?= $q !== '' ? 'No available items match &quot;' . htmlspecialchars($q) . '&quot;.' : 'No items available for borrowing right now.' ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['category_name']) ?></td>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td class="quantity"><?= $item['available_stock'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
