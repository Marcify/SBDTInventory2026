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

<div class="toolbar">
    <h1 class="page-title">Borrowing: <?= htmlspecialchars($borrowing['reference_code']) ?></h1>
    <a href="index.php" class="cancel-btn">&larr; Back to My Borrowings</a>
</div>

<div class="summary-container">
    <div class="summary-card">
        <div class="card-heading">
            <h6>Reason</h6>
        </div>
        <h6 class="summary-value"><?= htmlspecialchars($borrowing['reason']) ?></h6>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Borrowed</h6>
        </div>
        <h6 class="summary-value summary-value--soft"><?= date('M d, Y h:i A', strtotime($borrowing['borrowed_at'])) ?></h6>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Status</h6>
        </div>
        <?php $isReturned = $borrowing['status'] === 'returned'; ?>
        <span class="badge <?= $isReturned ? 'badge-returned' : ($borrowing['status'] === 'partial' ? 'badge-partial' : 'badge-borrowed') ?>">
            <?= ucfirst($borrowing['status']) ?>
        </span>
    </div>
</div>

<div class="content-box">
    <div class="table-wrapper">
        <table>
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
                            <div class="progress">
                                <div class="progress-track">
                                    <div class="progress-fill <?= $pct >= 100 ? 'progress-done' : '' ?>" style="width: <?= $pct ?>%;"></div>
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
