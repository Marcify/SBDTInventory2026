<?php
require_once __DIR__ . '/../../includes/auth.php';
require_dancer();

$user = current_user();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

// Ownership enforced in the query: dancers can only see their own borrowings.
$stmt = $pdo->prepare(
    "SELECT borrowings.*
     FROM borrowings
     WHERE borrowings.id = ? AND borrowings.user_id = ?"
);
$stmt->execute([$id, $user['id']]);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    die("Borrowing not found.");
}

// Item lines with return history.
$stmt = $pdo->prepare(
    "SELECT
        bd.id,
        bd.item_id,
        i.name AS item_name,
        bd.quantity_borrowed,
        bd.quantity_returned
     FROM borrowing_details bd
     JOIN items i ON i.id = bd.item_id
     WHERE bd.borrowing_id = ?
     ORDER BY bd.id ASC"
);
$stmt->execute([$id]);
$details = $stmt->fetchAll();

// All return records for this borrowing, grouped by detail.
$stmt = $pdo->prepare(
    "SELECT
        r.borrowing_detail_id AS detail_id,
        r.condition_enum AS condition_value,
        r.quantity,
        r.returned_at
     FROM item_returns r
     JOIN borrowing_details bd ON bd.id = r.borrowing_detail_id
     WHERE bd.borrowing_id = ?
     ORDER BY r.returned_at ASC, r.id ASC"
);
$stmt->execute([$id]);

$returnsByDetail = [];
foreach ($stmt->fetchAll() as $return) {
    $returnsByDetail[(int)$return['detail_id']][] = $return;
}

$page_title = 'Borrowing ' . $borrowing['reference_code'];
$active_nav = 'my-borrowings';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Borrowing: <?= htmlspecialchars($borrowing['reference_code']) ?></h1>
    <a href="index.php" class="btn btn-outline-maroon cancel-btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to My Borrowings</a>
</div>

<div class="row g-3 mb-4 summary-container">
    <div class="col-12 col-md-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="small text-uppercase fw-semibold text-muted mb-2">Reason</div>
                <h2 class="summary-value summary-value--soft h6"><?= htmlspecialchars($borrowing['reason']) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="small text-uppercase fw-semibold text-muted mb-2">Borrowed</div>
                <h2 class="summary-value summary-value--soft h6"><?= date('M d, Y h:i A', strtotime($borrowing['borrowed_at'])) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card summary-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="small text-uppercase fw-semibold text-muted mb-2">Status</div>
                <?php $isReturned = $borrowing['status'] === 'returned'; ?>
                <span class="badge <?= $isReturned ? 'badge-returned' : ($borrowing['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed') ?>">
                    <?= ucfirst($borrowing['status']) ?>
                </span>
            </div>
        </div>
    </div>
</div>

<div class="card content-box border-0 shadow-sm">
    <div class="table-responsive table-wrapper">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Borrowed</th>
                    <th>Returned</th>
                    <th>Progress</th>
                    <th>Return History</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($details as $d):
                    $remaining = (int)$d['quantity_borrowed'] - (int)$d['quantity_returned'];
                    $pct = (int)$d['quantity_borrowed'] > 0
                        ? (int)round((int)$d['quantity_returned'] / (int)$d['quantity_borrowed'] * 100)
                        : 0;
                ?>
                    <tr>
                        <td><?= htmlspecialchars($d['item_name']) ?></td>
                        <td class="quantity"><?= $d['quantity_borrowed'] ?></td>
                        <td><?= $d['quantity_returned'] ?></td>
                        <td>
                            <div class="return-progress">
                                <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : 'bg-gold' ?>" style="width: <?= $pct ?>%;"></div>
                                </div>
                                <div class="progress-label">
                                    <?= $pct ?>% &middot; <?= $remaining ?> still out
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php $history = $returnsByDetail[(int)$d['id']] ?? []; ?>
                            <?php if (empty($history)): ?>
                                <span class="summary-value--soft">No returns yet</span>
                            <?php else: ?>
                                <div class="return-history">
                                    <?php foreach ($history as $r): ?>
                                        <div class="item-line">
                                            <span class="badge <?= $r['condition_value'] === 'good' ? 'badge-returned' : ($r['condition_value'] === 'damaged' ? 'badge-partial' : 'badge-unavailable') ?>">
                                                <?= ucfirst($r['condition_value']) ?>
                                            </span>
                                            <span><?= $r['quantity'] ?></span>
                                            <span class="return-date"><?= date('M d, h:i A', strtotime($r['returned_at'])) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
