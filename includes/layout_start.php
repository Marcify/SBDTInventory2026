<?php
// Expected (optional) variables: $page_title, $active_nav.
// $user comes from current_user(); role determines the sidebar.
$user = current_user();
$page_title = $page_title ?? 'SBDT Inventory';
$active_nav = $active_nav ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - SBDT Inventory</title>
    <link rel="stylesheet" href="/SBDTInventory/assets/css/styles.css">
</head>
<body>
    <header class="header">
        <img class="logo" src="/SBDTInventory/assets/images/sbdt-logo.png" alt="SBDT">
        <span class="brand">Inventory Management System</span>
        <div class="header-actions">
            <div class="user-chip" title="Logged in as <?= htmlspecialchars($user['name'] ?? $user['username']) ?>">
                <span class="user-avatar"><?= strtoupper(mb_substr(htmlspecialchars($user['name'] ?? $user['username']), 0, 1)) ?></span>
                <span class="user-name"><?= htmlspecialchars($user['name'] ?? $user['username']) ?></span>
            </div>
            <form action="/SBDTInventory/logout.php" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="profile-button">Log out</button>
            </form>
        </div>
    </header>

    <div class="layout">
        <aside class="sidebar">
            <?php if ($user['role'] === 'admin'): ?>
                <h2 class="sidebar-title">Admin</h2>
                <a href="/SBDTInventory/admin/dashboard.php"
                   class="<?= $active_nav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="/SBDTInventory/admin/items/index.php"
                   class="<?= $active_nav === 'items' ? 'active' : '' ?>">Items</a>
                <a href="/SBDTInventory/admin/categories/index.php"
                   class="<?= $active_nav === 'categories' ? 'active' : '' ?>">Categories</a>
                <a href="/SBDTInventory/admin/borrowings/index.php"
                   class="<?= $active_nav === 'borrowings' ? 'active' : '' ?>">Borrowings</a>
                <a href="/SBDTInventory/admin/stock_movements/index.php"
                   class="<?= $active_nav === 'stock' ? 'active' : '' ?>">Stock History</a>
                <a href="/SBDTInventory/admin/users/index.php"
                   class="<?= $active_nav === 'users' ? 'active' : '' ?>">Users</a>
            <?php else: ?>
                <h2 class="sidebar-title">Dancer</h2>
                <a href="/SBDTInventory/dancer/dashboard.php"
                   class="<?= $active_nav === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
                <a href="/SBDTInventory/dancer/items/index.php"
                   class="<?= $active_nav === 'items' ? 'active' : '' ?>">Browse Items</a>
                <a href="/SBDTInventory/dancer/borrowings/create.php"
                   class="<?= $active_nav === 'borrow' ? 'active' : '' ?>">Borrow Items</a>
                <a href="/SBDTInventory/dancer/borrowings/index.php"
                   class="<?= $active_nav === 'my-borrowings' ? 'active' : '' ?>">My Borrowings</a>
            <?php endif; ?>
        </aside>

        <main class="main">
