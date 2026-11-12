<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);

            header('Location: index.php');
            exit();
        } catch (PDOException $e) {
            // This catches the UNIQUE constraint error if they type a duplicate name
            $error = 'That category already exists.';
        }
    }
}

$page_title = 'Add Category';
$active_nav = 'categories';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="edit-panel">
    <h3 class="section-title">Add New Category</h3>

    <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Category Name</label>
            <input type="text" id="name" name="name" required placeholder="e.g. Costumes, Props, Shoes">
        </div>

        <div class="form-group-spaced">
            <button type="submit" class="save-btn">Save Category</button>
            <a href="index.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
