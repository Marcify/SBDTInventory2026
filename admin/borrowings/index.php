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

[$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 10);

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

<div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="page-title h3 mb-1">Borrowings</h1>
        <p class="welcome mb-0"><?= (int)$activeCount ?> active borrowing<?= (int)$activeCount === 1 ? '' : 's' ?></p>
    </div>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="card-body border-bottom table-search">
        <form method="GET" class="filter-form row g-2 align-items-center">
            <div class="col-12 col-md-3">
                <label class="visually-hidden" for="status-filter">Status</label>
                <select id="status-filter" name="status" class="form-select filter-select">
                    <option value="">All statuses</option>
                    <option value="borrowed" <?= $statusFilter === 'borrowed' ? 'selected' : '' ?>>Borrowed</option>
                    <option value="partial" <?= $statusFilter === 'partial' ? 'selected' : '' ?>>Partial</option>
                    <option value="returned" <?= $statusFilter === 'returned' ? 'selected' : '' ?>>Returned</option>
                </select>
            </div>
            <div class="col-12 col-md">
                <label class="visually-hidden" for="borrowing-search">Search borrowings</label>
                <input type="text" id="borrowing-search" name="q" class="form-control filter-input"
                       placeholder="Search dancer, reason, or reference&hellip;"
                       value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-6 col-md-auto">
                <button type="submit" class="btn btn-maroon search-btn w-100">Filter</button>
            </div>
            <?php if ($statusFilter !== '' || $search !== ''): ?>
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
                                    <a href="view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-outline-maroon edit-btn">
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

    <div class="card-body">
        <?php render_pagination($page, $perPage, $totalRows, $totalPages); ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
