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

<div class="mb-4">
    <h1 class="page-title h3 mb-1">Admin Dashboard</h1>
    <p class="welcome mb-0">Welcome back, <strong><?= htmlspecialchars($user['name'] ?? $user['username']) ?></strong>! Here's where your inventory stands today.</p>
</div>

<div class="row g-3 mb-4 summary-container summary-cols-3">
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-box-seam" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Active Items</div>
                    <div class="stat-value"><?= (int)$stats['item_count'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-stack" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Total Physical Stock</div>
                    <div class="stat-value"><?= (int)$stats['total_stock'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Available to Borrow</div>
                    <div class="stat-value"><?= (int)$stats['available_stock'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-arrow-left-right" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Borrowed Units</div>
                    <div class="stat-value"><?= (int)$stats['borrowed_units'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-bandaid" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Damaged</div>
                    <div class="stat-value"><?= (int)$stats['damaged_units'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-xl-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="stat-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></span>
                <div>
                    <div class="small text-uppercase fw-semibold text-muted">Lost</div>
                    <div class="stat-value"><?= (int)$stats['lost_units'] ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="card-header section-header bg-white">
        <h2 class="section-title h5 mb-0">Recent Borrowings</h2>
        <a href="borrowings/index.php" class="btn btn-sm btn-outline-maroon filter-link">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>

    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
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
                                    <a href="borrowings/view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-maroon edit-btn">
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
