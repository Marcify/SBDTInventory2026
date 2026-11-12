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

<h1 class="page-title">Dancer Dashboard</h1>
<p class="welcome">Welcome, <?= htmlspecialchars($user['name'] ?? $user['username']) ?>! Browse what's available, then submit a borrow request.</p>

<div class="summary-container">
    <div class="summary-card">
        <div class="card-heading">
            <h6>Browse</h6>
        </div>
        <p class="welcome">See all items with available stock.</p>
        <a href="items/index.php" class="edit-btn">Browse Available Items</a>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Borrow</h6>
        </div>
        <p class="welcome">Request one or more items in a single borrowing.</p>
        <a href="borrowings/create.php" class="borrow-btn">Borrow an Item</a>
    </div>
</div>

<div class="content-box">
    <div class="section-header">
        <h3 class="section-title">My Borrowed Items</h3>
        <a href="borrowings/index.php" class="filter-link">View all &rarr;</a>
    </div>

    <div class="table-wrapper">
        <table>
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
                                    <a href="borrowings/view.php?id=<?= $b['id'] ?>" class="edit-btn">View Items</a>
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
