<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

$totalRows = (int)$pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed', 'partial')"
)->fetchColumn();
[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);

// Fetch active borrowings with the dancer's name (paginated)
$sql = "SELECT borrowings.*, users.name AS user_name
        FROM borrowings
        JOIN users ON borrowings.user_id = users.id
        WHERE borrowings.status IN ('borrowed', 'partial')
        ORDER BY borrowings.borrowed_at DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$borrowings = $stmt->fetchAll();

$page_title = 'Borrowings & Returns';
$active_nav = 'borrowings';

require __DIR__ . '/../../includes/layout_start.php';
?>

<h1 class="page-title">Active Borrowings</h1>

<div class="content-box">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference Code</th>
                    <th>Dancer</th>
                    <th>Reason</th>
                    <th>Borrowed At</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($borrowings)): ?>
                    <tr>
                        <td colspan="6" class="empty">No active borrowings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($borrowings as $b): ?>
                        <tr>
                            <td class="quantity"><?= htmlspecialchars($b['reference_code']) ?></td>
                            <td><?= htmlspecialchars($b['user_name']) ?></td>
                            <td><?= htmlspecialchars($b['reason']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($b['borrowed_at'])) ?></td>
                            <td>
                                <span class="badge <?= $b['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed' ?>">
                                    <?= ucfirst($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="view.php?id=<?= $b['id'] ?>" class="edit-btn">View / Return</a>
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
