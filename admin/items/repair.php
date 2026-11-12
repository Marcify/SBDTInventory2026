<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

$itemId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

if ($itemId === false) {
    header('Location: index.php');
    exit();
}

global $pdo;

$stmt = $pdo->prepare('SELECT id, name, damaged_stock, available_stock FROM items WHERE id = ?');
$stmt->execute([$itemId]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: index.php');
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $quantity = filter_var($_POST['quantity'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $reason = trim($_POST['reason'] ?? '');
    $damaged = (int)$item['damaged_stock'];

    if ($quantity === false || $quantity < 1) {
        $error = 'Quantity must be a positive number.';
    } elseif ($quantity > $damaged) {
        $error = 'Cannot repair more than the ' . $damaged . ' damaged unit(s).';
    } elseif ($reason === '') {
        $error = 'Reason is required.';
    } elseif (mb_strlen($reason) > 255) {
        $error = 'Reason must not exceed 255 characters.';
    } else {
        // Same discipline as the API: lock the row and re-check inside the transaction.
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'SELECT id, total_stock, available_stock, borrowed_stock, damaged_stock, lost_stock
                 FROM items WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$itemId]);
            $locked = $stmt->fetch();

            if (!$locked) {
                throw new RuntimeException('Item not found.');
            }

            $balances = [
                'total_stock'     => (int)$locked['total_stock'],
                'available_stock' => (int)$locked['available_stock'],
                'borrowed_stock'  => (int)$locked['borrowed_stock'],
                'damaged_stock'   => (int)$locked['damaged_stock'],
                'lost_stock'      => (int)$locked['lost_stock'],
            ];

            if (min($balances) < 0 || $balances['total_stock'] !== array_sum($balances) - $balances['total_stock']) {
                throw new RuntimeException('Inventory balances are inconsistent. Reconcile this item first.');
            }

            if ($quantity > $balances['damaged_stock']) {
                throw new RuntimeException('Cannot repair more than the damaged stock.');
            }

            $stmt = $pdo->prepare(
                'UPDATE items
                 SET total_stock = ?, available_stock = ?, damaged_stock = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $balances['total_stock'],
                $balances['available_stock'] + $quantity,
                $balances['damaged_stock'] - $quantity,
                $itemId,
            ]);

            $stmt = $pdo->prepare(
                'INSERT INTO stock_movements (item_id, user_id, type, quantity, reason)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$itemId, $_SESSION['user_id'], 'IN', $quantity, $reason]);

            $pdo->commit();

            header('Location: index.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

$page_title = 'Repair Damaged Stock';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="toolbar mb-4">
    <h1 class="page-title h3 mb-0">Repair Damaged Stock</h1>
    <a href="index.php" class="btn btn-outline-maroon cancel-btn"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to Items</a>
</div>

<div class="card edit-panel border-0 shadow-sm">
    <div class="card-body p-4">
        <p class="welcome">
            <strong><?= htmlspecialchars($item['name']) ?></strong> —
            <?= (int)$item['damaged_stock'] ?> damaged unit(s) can be repaired.
            Repaired units return to available stock. Total stock is unchanged.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-danger message error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ((int)$item['damaged_stock'] === 0): ?>
            <div class="alert alert-warning message" role="alert">This item has no damaged stock to repair.</div>
            <a href="index.php" class="btn btn-outline-maroon cancel-btn">Back to Items</a>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="mb-3 form-group">
                    <label class="form-label" for="quantity">Quantity to repair</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1"
                           max="<?= (int)$item['damaged_stock'] ?>"
                           value="<?= (int)$item['damaged_stock'] ?>" required>
                </div>
                <div class="mb-3 form-group">
                    <label class="form-label" for="reason">Reason (e.g. "Costume seam repaired")</label>
                    <input type="text" class="form-control" id="reason" name="reason" maxlength="255" required>
                </div>
                <div class="d-flex flex-wrap gap-2 form-group">
                    <button type="submit" class="btn btn-maroon save-btn">Mark as Repaired</button>
                    <a href="index.php" class="btn btn-outline-maroon cancel-btn">Cancel</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
