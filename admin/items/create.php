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
        // Insert the item. Available stock starts the same as total stock.
        $sql = "INSERT INTO items (category_id, name, total_stock, available_stock) 
                VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $name, $total_stock, $total_stock]);

        header('Location: index.php');
        exit();
    }
}

$page_title = 'Add New Item';
$active_nav = 'items';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="edit-panel">
    <h3 class="section-title">Add New Item</h3>
    <p class="welcome">New items start with their available stock equal to total stock.</p>

    <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Item Name</label>
            <input type="text" id="name" name="name" required>
        </div>

        <div class="form-group">
            <label class="form-label" for="category_id">Category</label>
            <select id="category_id" name="category_id" required>
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="total_stock">Total Stock</label>
            <input type="number" id="total_stock" name="total_stock" min="1" required>
        </div>

        <div class="form-group-spaced">
            <button type="submit" class="save-btn">Save Item</button>
            <a href="index.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
