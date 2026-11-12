<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_once __DIR__ . '/../../includes/pagination.php';

// Filters: status (allowlist) and free-text search over dancer name,
// reason, and reference code.
$statusFilter = $_GET['status'] ?? '';
if (!in_array($statusFilter, ['borrowed', 'partial', 'returned'], true)) {
    $statusFilter = '';
}

$search = trim($_GET['q'] ?? '');

$conditions = [];
$params = [];

if ($statusFilter !== '') {
    $conditions[] = 'borrowings.status = ?';
    $params[] = $statusFilter;
}

if ($search !== '') {
    $conditions[] = '(users.name LIKE ? OR borrowings.reason LIKE ? OR borrowings.reference_code LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$whereSql = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

$countStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM borrowings JOIN users ON borrowings.user_id = users.id $whereSql"
);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();

[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);

// Active first (newest first), returned at the bottom.
$sql = "SELECT borrowings.*, users.name AS user_name
        FROM borrowings
        JOIN users ON borrowings.user_id = users.id
        $whereSql
        ORDER BY
            CASE WHEN borrowings.status = 'returned' THEN 1 ELSE 0 END ASC,
            borrowings.borrowed_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$borrowings = $stmt->fetchAll();

$activeCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed', 'partial')"
)->fetchColumn();

$page_title = 'Borrowings & Returns';
$active_nav = 'borrowings';

require __DIR__ . '/../../includes/layout_start.php';
?>

<h1 class="page-title">Borrowings</h1>

<div class="content-box">
    <div class="table-search">
        <form method="GET" class="filter-form">
            <select name="status" class="filter-select">
                <option value="">All statuses</option>
                <option value="borrowed" <?= $statusFilter === 'borrowed' ? 'selected' : '' ?>>Borrowed</option>
                <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Returned</option>
            </select>
            <input type="text" name="q" class="filter-input"
                   placeholder="Search dancer, reason, or reference&hellip;"
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="search-btn">Filter</button>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
                <a href="index.php" class="cancel-btn">Clear</a>
            <?php endif; ?>
        </form>
    </div>

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
                        <td colspan="6" class="empty">No borrowings found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($borrowings as $b): $isReturned = $b['status'] === 'returned'; ?>
                        <tr class="<?= $isReturned ? 'row-returned' : '' ?>">
                            <td class="quantity"><?= htmlspecialchars($b['reference_code']) ?></td>
                            <td><?= htmlspecialchars($b['user_name']) ?></td>
                            <td><?= htmlspecialchars($b['reason']) ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($b['borrowed_at'])) ?></td>
                            <td>
                                <span class="badge <?= $isReturned ? 'badge-returned' : ($b['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed') ?>">
                                    <?= ucfirst($b['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a href="view.php?id=<?= $b['id'] ?>" class="edit-btn">
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

    <?php render_pagination($page, $perPage, $totalRows, $totalPages); ?>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
