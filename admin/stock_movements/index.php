<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

$totalRows = (int)$pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 50);

// Fetch all stock movements, joining with items and users to get names (paginated)
$sql = "SELECT stock_movements.*, items.name AS item_name, users.username, users.name AS user_name 
        FROM stock_movements 
        JOIN items ON stock_movements.item_id = items.id 
        JOIN users ON stock_movements.user_id = users.id 
        ORDER BY stock_movements.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$movements = $stmt->fetchAll();

$page_title = 'Stock History';
$active_nav = 'stock';

require __DIR__ . '/../../includes/layout_start.php';
?>

<h1 class="page-title h3">Stock Movements History</h1>

<div class="card content-box border-0 shadow-sm">
    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Date &amp; Time</th>
                    <th>Item</th>
                    <th>Type</th>
                    <th>Quantity</th>
                    <th>Performed By</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movements)): ?>
                    <tr>
                        <td colspan="6" class="empty">No stock movements recorded yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($movements as $m): ?>
                        <tr>
                            <td><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></td>
                            <td><?= htmlspecialchars($m['item_name']) ?></td>
                            <td>
                                <span class="badge <?= $m['type'] === 'IN' ? 'badge-available' : ($m['type'] === 'OUT' ? 'badge-unavailable' : 'badge-borrowed') ?>">
                                    <?= $m['type'] ?>
                                </span>
                            </td>
                            <td class="quantity"><?= $m['quantity'] ?></td>
                            <td><?= htmlspecialchars($m['user_name']) ?></td>
                            <td><?= htmlspecialchars($m['reason']) ?></td>
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
