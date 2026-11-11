<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $current_status = $_POST['current_status'] ?? null;

    // Safety check: Prevent admin from deactivating themselves
    if ($id == $_SESSION['user_id']) {
        die("You cannot deactivate your own account.");
    }

    if ($id && $current_status !== null) {
        $new_status = $current_status == 1 ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
    }
}

header('Location: index.php');
exit();