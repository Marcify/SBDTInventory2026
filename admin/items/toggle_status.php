<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $current_status = $_POST['current_status'] ?? null;

    if ($id && $current_status !== null) {
        // Flip the status (if 1, make 0. If 0, make 1)
        $new_status = $current_status == 1 ? 0 : 1;

        $stmt = $pdo->prepare("UPDATE items SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
    }
}

header('Location: index.php');
exit();
