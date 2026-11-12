<?php
// Presentation only. Expects $user and $active_nav from the layout.
// Link targets match the previous sidebar exactly.
$isAdminNav = (($user['role'] ?? '') === 'admin');
?>
<p class="sidebar-label"><?= $isAdminNav ? 'Admin' : 'Dancer' ?></p>
<nav class="nav flex-column sidebar-nav pb-4">
    <?php if ($isAdminNav): ?>
        <a href="/SBDTInventory/admin/dashboard.php"
           class="nav-link<?= $active_nav === 'dashboard' ? ' active' : '' ?>"
           <?= $active_nav === 'dashboard' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-speedometer2" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
        <a href="/SBDTInventory/admin/items/index.php"
           class="nav-link<?= $active_nav === 'items' ? ' active' : '' ?>"
           <?= $active_nav === 'items' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-box-seam" aria-hidden="true"></i>
            <span>Items</span>
        </a>
        <a href="/SBDTInventory/admin/categories/index.php"
           class="nav-link<?= $active_nav === 'categories' ? ' active' : '' ?>"
           <?= $active_nav === 'categories' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-tags" aria-hidden="true"></i>
            <span>Categories</span>
        </a>
        <a href="/SBDTInventory/admin/borrowings/index.php"
           class="nav-link<?= $active_nav === 'borrowings' ? ' active' : '' ?>"
           <?= $active_nav === 'borrowings' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
            <span>Borrowings</span>
        </a>
        <a href="/SBDTInventory/admin/stock_movements/index.php"
           class="nav-link<?= $active_nav === 'stock' ? ' active' : '' ?>"
           <?= $active_nav === 'stock' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>Stock History</span>
        </a>
        <a href="/SBDTInventory/admin/users/index.php"
           class="nav-link<?= $active_nav === 'users' ? ' active' : '' ?>"
           <?= $active_nav === 'users' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-people" aria-hidden="true"></i>
            <span>Users</span>
        </a>
    <?php else: ?>
        <a href="/SBDTInventory/dancer/dashboard.php"
           class="nav-link<?= $active_nav === 'dashboard' ? ' active' : '' ?>"
           <?= $active_nav === 'dashboard' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-speedometer2" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>
        <a href="/SBDTInventory/dancer/items/index.php"
           class="nav-link<?= $active_nav === 'items' ? ' active' : '' ?>"
           <?= $active_nav === 'items' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-search" aria-hidden="true"></i>
            <span>Browse Items</span>
        </a>
        <a href="/SBDTInventory/dancer/borrowings/create.php"
           class="nav-link<?= $active_nav === 'borrow' ? ' active' : '' ?>"
           <?= $active_nav === 'borrow' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-bag-plus" aria-hidden="true"></i>
            <span>Borrow Items</span>
        </a>
        <a href="/SBDTInventory/dancer/borrowings/index.php"
           class="nav-link<?= $active_nav === 'my-borrowings' ? ' active' : '' ?>"
           <?= $active_nav === 'my-borrowings' ? 'aria-current="page"' : '' ?>>
            <i class="bi bi-journal-text" aria-hidden="true"></i>
            <span>My Borrowings</span>
        </a>
    <?php endif; ?>
</nav>
