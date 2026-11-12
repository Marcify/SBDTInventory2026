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

<div class="card edit-panel border-0 shadow-sm">
    <div class="card-body p-4">
        <h1 class="section-title h4">Add New Category</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger message error" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>
            <div class="mb-3 form-group">
                <label class="form-label" for="name">Category Name</label>
                <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Costumes, Props, Shoes">
            </div>

            <div class="d-flex flex-wrap gap-2 form-group-spaced">
                <button type="submit" class="btn btn-maroon save-btn">Save Category</button>
                <a href="index.php" class="btn btn-outline-maroon cancel-btn">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
