<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

// POST only: the Delete button on index.php.
$id = (int)($_POST['id'] ?? 0);

if ($id < 1) {
    header('Location: index.php');
    exit();
}

try {
    $pdo->beginTransaction();

    // Lock the row so an item cannot be assigned between check and delete.
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode('Category not found.'));
        exit();
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
    $stmt->execute([$id]);
    $itemCount = (int)$stmt->fetchColumn();

    if ($itemCount > 0) {
        $pdo->rollBack();
        header('Location: index.php?error=' . urlencode(
            'Category is in use by ' . $itemCount . ' item(s) and cannot be deleted.'
        ));
        exit();
    }

    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);

    $pdo->commit();
    header('Location: index.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: index.php?error=' . urlencode('Something went wrong while deleting.'));
}
exit();
