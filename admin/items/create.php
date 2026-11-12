<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

// Fetch categories for the dropdown
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $total_stock = (int)($_POST['total_stock'] ?? 0);

    if ($name === '' || $category_id === '' || $total_stock < 1) {
        $error = 'Please fill in all fields and ensure total stock is at least 1.';
    } else {
        try {
            $pdo->beginTransaction();

            // Insert the item. Available stock starts the same as total stock.
            $stmt = $pdo->prepare(
                "INSERT INTO items (category_id, name, total_stock, available_stock)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$category_id, $name, $total_stock, $total_stock]);
            $item_id = (int)$pdo->lastInsertId();

            // Log the initial stock as an IN movement (same as the API).
            $stmt = $pdo->prepare(
                "INSERT INTO stock_movements (item_id, user_id, type, quantity, reason)
                 VALUES (?, ?, 'IN', ?, ?)"
            );
            $stmt->execute([
                $item_id,
                $_SESSION['user_id'],
                $total_stock,
                'Initial stock on item creation',
            ]);

            $pdo->commit();

            header('Location: index.php');
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to create the item. Please try again.';
        }
    }
}

$page_title = 'Add New Item';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="card edit-panel border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="section-title h4">Add New Item</h1>
        <p class="welcome">New items start with their available stock equal to total stock.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger message error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3 form-group">
                <label class="form-label" for="name">Item Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>

            <div class="mb-3 form-group">
                <label class="form-label" for="category_id">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3 form-group">
                <label class="form-label" for="total_stock">Total Stock</label>
                <input type="number" class="form-control" id="total_stock" name="total_stock" min="1" required>
            </div>

            <div class="d-flex flex-wrap gap-2 form-group-spaced">
                <button type="submit" class="btn btn-maroon save-btn">Save Item</button>
                <a href="index.php" class="btn btn-outline-maroon cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
