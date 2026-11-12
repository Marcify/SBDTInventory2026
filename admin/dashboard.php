<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

// Get some quick stats for the dashboard
$itemCount = $pdo->query("SELECT COUNT(*) FROM items WHERE is_active = TRUE")->fetchColumn();
$activeBorrowings = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed', 'partial')")->fetchColumn();
$totalStock = $pdo->query("SELECT SUM(total_stock) FROM items WHERE is_active = TRUE")->fetchColumn();

$page_title = 'Admin Dashboard';
$active_nav = 'dashboard';

require __DIR__ . '/../includes/layout_start.php';
?>

<h1 class="page-title">Admin Dashboard</h1>
<p class="welcome">Welcome back, <strong><?= htmlspecialchars($user['name'] ?? $user['username']) ?></strong>!</p>

<div class="summary-container">
    <div class="summary-card">
        <div class="card-heading">
            <h6>Active Items</h6>
        </div>
        <h3><?= (int)$itemCount ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Total Physical Stock</h6>
        </div>
        <h3><?= (int)$totalStock ?></h3>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Items Out (Borrowed)</h6>
        </div>
        <h3><?= (int)$activeBorrowings ?></h3>
    </div>
</div>

<div class="content-box">
    <h3 class="section-title">Quick Actions</h3>
    <p class="welcome">Use the sidebar to navigate. If dancers are returning items, go to <strong>Borrowings</strong>.</p>
</div>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>
