<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Inventory stats: one aggregate query per metric, summed across active items.
$statsStmt = $pdo->query(
    "SELECT
        COUNT(*) AS item_count,
        COALESCE(SUM(total_stock), 0) AS total_stock,
        COALESCE(SUM(available_stock), 0) AS available_stock,
        COALESCE(SUM(borrowed_stock), 0) AS borrowed_units,
        COALESCE(SUM(damaged_stock), 0) AS damaged_units,
        COALESCE(SUM(lost_stock), 0) AS lost_units
     FROM items
     WHERE is_active = TRUE"
);
$stats = $statsStmt->fetch();

$activeBorrowings = (int)$pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed', 'partial')"
)->fetchColumn();

// The five latest borrowings, newest first.
$recentStmt = $pdo->query(
    "SELECT borrowings.*, users.name AS user_name
     FROM borrowings
     JOIN users ON users.id = borrowings.user_id
     ORDER BY borrowings.borrowed_at DESC
     LIMIT 5"
);
$recent_borrowings = $recentStmt->fetchAll();

$page_title = 'Admin Dashboard';
$active_nav = 'dashboard';

require __DIR__ . '/../includes/layout_start.php';
?>

<h1 class="page-title">Admin Dashboard</h1>
<p class="welcome">Welcome back, <strong><?= htmlspecialchars($user['name'] ?? $user['username']) ?></strong>! Here's where your inventory stands today.</p>

<div class="summary-container summary-cols-3">
    <div class="summary-card">
        <div class="card-heading">
            <h6>Active Items</h6>
        </div>
        <h3><?= (int)$stats['item_count'] ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Total Physical Stock</h6>
        </div>
        <h3><?= (int)$stats['total_stock'] ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Available to Borrow</h6>
        </div>
        <h3><?= (int)$stats['available_stock'] ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Borrowed Units</h6>
        </div>
        <h3><?= (int)$stats['borrowed_units'] ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Damaged</h6>
        </div>
        <h3><?= (int)$stats['damaged_units'] ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Lost</h6>
        </div>
        <h3><?= (int)$stats['lost_units'] ?></h3>
    </div>
</div>

<div class="content-box">
    <div class="section-header">
        <h3 class="section-title">Recent Borrowings</h3>
        <a href="borrowings/index.php" class="filter-link">View all &rarr;</a>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Dancer</th>
                    <th>Borrowed At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_borrowings)): ?>
                    <tr>
                        <td colspan="5" class="empty">No borrowings yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_borrowings as $b): $isReturned = $b['status'] === 'returned'; ?>
                        <tr class="<?= $isReturned ? 'row-returned' : '' ?>">
                            <td class="quantity"><?= htmlspecialchars($b['reference_code']) ?></td>
                            <td><?= htmlspecialchars($b['user_name']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($b['borrowed_at'])) ?></td>
                            <td>
                                <span class="badge <?= $isReturned ? 'badge-returned' : ($b['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed') ?>">
                                    <?= ucfirst($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="borrowings/view.php?id=<?= $b['id'] ?>" class="edit-btn">
                                        <?= $isReturned ? 'View' : 'View / Return' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>
