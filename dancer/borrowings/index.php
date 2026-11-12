<?php
require_once __DIR__ . '/../../includes/auth.php';
require_dancer();
require_once __DIR__ . '/../../includes/pagination.php';

$user = current_user();

// This dancer's borrowings only: active first, returned at the bottom.
$sql = "SELECT borrowings.*
        FROM borrowings
        WHERE borrowings.user_id = ?
        ORDER BY
            CASE WHEN borrowings.status = 'returned' THEN 1 ELSE 0 END ASC,
            borrowings.borrowed_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user['id']]);
$all_borrowings = $stmt->fetchAll();

$totalRows = count($all_borrowings);
[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);
$borrowings = array_slice($all_borrowings, $offset, $perPage);

$page_title = 'My Borrowings';
$active_nav = 'my-borrowings';

require __DIR__ . '/../../includes/layout_start.php';
?>

<h1 class="page-title">My Borrowings</h1>
<p class="welcome">Everything you have borrowed, with its return status. Returned borrowings are kept for your records.</p>

<div class="content-box">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Reason</th>
                    <th>Borrowed At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr>
                        <td colspan="5" class="empty">You haven't borrowed anything yet. <a href="create.php">Borrow your first item</a>.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($borrowings as $b): $isReturned = $b['status'] === 'returned'; ?>
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
                                    <a href="view.php?id=<?= $b['id'] ?>" class="edit-btn">View</a>
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
