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

<div class="toolbar">
    <h1 class="page-title">Browse Items</h1>
    <form method="GET" class="search-form">
        <input type="text" name="q" class="search-input"
               placeholder="Search item or category&hellip;"
               value="<?= htmlspecialchars($q) ?>">
        <button type="submit" class="search-btn">Search</button>
        <?php if ($q !== ''): ?>
            <a href="index.php" class="cancel-btn">Clear</a>
        <?php endif; ?>
    </form>
    <a href="../borrowings/create.php" class="borrow-btn">Go to Borrow Form</a>
</div>

<div class="content-box">
    <div class="table-wrapper">
        <table>
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
