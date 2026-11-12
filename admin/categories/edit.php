<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt->execute([$id]);
$category = $stmt->fetch();

if (!$category) {
    die("Category not found.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    if ($name === '') {
        $error = 'Category name is required.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);

            header('Location: index.php?saved=1');
            exit();
        } catch (PDOException $e) {
            if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                $error = 'That category already exists.';
            } else {
                $error = 'Something went wrong while saving. Please try again.';
            }
        }
    }
}

$page_title = 'Edit Category';
$active_nav = 'categories';

require __DIR__ . '/../../includes/layout_start.php';
?>

<div class="edit-panel">
    <h3 class="section-title">Edit Category</h3>

    <?php if ($error): ?>
        <p class="message error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label class="form-label" for="name">Category Name</label>
            <input type="text" id="name" name="name" required
                   value="<?= htmlspecialchars($category['name']) ?>"
                   placeholder="e.g. Costumes, Props, Shoes">
        </div>

        <div class="form-group-spaced">
            <button type="submit" class="save-btn">Save Changes</button>
            <a href="index.php" class="cancel-btn">Cancel</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
