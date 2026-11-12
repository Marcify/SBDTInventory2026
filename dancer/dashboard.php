<?php
require_once __DIR__ . '/../includes/auth.php';
require_dancer();

$page_title = 'Dancer Dashboard';
$active_nav = 'dashboard';

require __DIR__ . '/../includes/layout_start.php';
?>

<h1 class="page-title">Dancer Dashboard</h1>
<p class="welcome">Welcome, <?= htmlspecialchars($user['username']) ?>! Browse what's available, then submit a borrow request.</p>

<div class="summary-container">
    <div class="summary-card">
        <div class="card-heading">
            <h6>Browse</h6>
        </div>
        <p class="welcome">See all items with available stock.</p>
        <a href="items/index.php" class="edit-btn">Browse Available Items</a>
    </div>
    <div class="summary-card">
        <div class="card-heading">
            <h6>Borrow</h6>
        </div>
        <p class="welcome">Request one or more items in a single borrowing.</p>
        <a href="borrowings/create.php" class="borrow-btn">Borrow an Item</a>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout_end.php'; ?>
