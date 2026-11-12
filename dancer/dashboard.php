<?php
require_once __DIR__ . '/../includes/auth.php';
require_dancer();

$user = current_user();

// Most relevant borrowings for this dancer: active first, then newest.
$stmt = $pdo->prepare(
    "SELECT borrowings.*
     FROM borrowings
     WHERE borrowings.user_id = ?
     ORDER BY
         CASE WHEN borrowings.status = 'returned' THEN 1 ELSE 0 END ASC,
         borrowings.borrowed_at DESC
     LIMIT 5"
);
$stmt->execute([$user['id']]);
$recent_borrowings = $stmt->fetchAll();

$page_title = 'Dancer Dashboard';
$active_nav = 'dashboard';

require __DIR__ . '/../includes/layout_start.php';
?>

<div class="mb-4">
    <h1 class="page-title h3 mb-1">Dancer Dashboard</h1>
    <p class="welcome mb-0">Welcome, <?= htmlspecialchars($user['name'] ?? $user['username']) ?>! Browse what's available, then submit a borrow request.</p>
</div>

<div class="row g-3 mb-4 summary-container">
    <div class="col-12 col-md-6">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="stat-icon"><i class="bi bi-search" aria-hidden="true"></i></span>
                    <h2 class="h6 mb-0 text-maroon">Browse</h2>
                </div>
                <p class="welcome">See all items with available stock.</p>
                <a href="items/index.php" class="btn btn-outline-maroon edit-btn mt-auto align-self-start">Browse Available Items</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="stat-icon"><i class="bi bi-bag-plus" aria-hidden="true"></i></span>
                    <h2 class="h6 mb-0 text-maroon">Borrow</h2>
                </div>
                <p class="welcome">Request one or more items in a single borrowing.</p>
                <a href="borrowings/create.php" class="btn btn-maroon borrow-btn mt-auto align-self-start">Borrow an Item</a>
            </div>
        </div>
    </div>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="card-header section-header bg-white">
        <h2 class="section-title h5 mb-0">My Borrowed Items</h2>
        <a href="borrowings/index.php" class="btn btn-sm btn-outline-maroon filter-link">View all <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>

    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Reason</th>
                    <th>Borrowed At</th>
                    <th>Status</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_borrowings)): ?>
                    <tr>
                        <td colspan="5" class="empty">You haven't borrowed anything yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recent_borrowings as $b): $isReturned = $b['status'] === 'returned'; ?>
                        <tr class="<?= $isReturned ? 'row-returned' : '' ?>">
                            <td class="quantity"><?= htmlspecialchars($b['reference_code']) ?></td>
                            <td><?= htmlspecialchars($b['reason']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($b['borrowed_at'])) ?></td>
                            <td>
                                <span class="badge <?= $isReturned ? 'badge-returned' : ($b['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed') ?>">
                                    <?= ucfirst($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="borrowings/view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-maroon edit-btn">View Items</a>
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
