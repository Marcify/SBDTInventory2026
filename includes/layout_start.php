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
            <button type="button" class="profile-button" data-open-pw>Change Password</button>
            <form action="/SBDTInventory/logout.php" method="POST">
                <?= csrf_field() ?>
                <button type="submit" class="profile-button">Log out</button>
            </form>
        </div>
    </header>

    <div class="modal" id="pw-modal">
        <div class="modal-dialog">
            <div class="password-modal-card">
                <h2 class="modal-title">Change Password</h2>
                <form action="/SBDTInventory/change_password.php" method="POST">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label class="form-label" for="pw-current">Current password</label>
                        <input type="password" id="pw-current" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pw-new">New password</label>
                        <input type="password" id="pw-new" name="new_password" minlength="8" maxlength="72" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pw-confirm">Confirm new password</label>
                        <input type="password" id="pw-confirm" name="confirm_password" required>
                    </div>
                    <div class="password-modal-actions">
                        <button type="button" class="cancel-btn" data-close-pw>Cancel</button>
                        <button type="submit" class="save-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var modal = document.getElementById('pw-modal');
            if (!modal) return;

            document.querySelectorAll('[data-open-pw]').forEach(function (el) {
                el.addEventListener('click', function () {
                    modal.classList.add('show');
                });
            });

            modal.querySelectorAll('[data-close-pw]').forEach(function (el) {
                el.addEventListener('click', function () {
                    modal.classList.remove('show');
                });
            });

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
    </script>

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
        <?php if (($_GET['pw'] ?? '') === 'changed'): ?>
            <p class="message success">Password updated successfully.</p>
        <?php elseif (isset($_GET['pw_error'])): ?>
            <p class="message error"><?= htmlspecialchars($_GET['pw_error']) ?></p>
        <?php endif; ?>
