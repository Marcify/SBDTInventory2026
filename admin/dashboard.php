<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$user = current_user();

// Get some quick stats for the dashboard
$itemCount = $pdo->query("SELECT COUNT(*) FROM items WHERE is_active = TRUE")->fetchColumn();
$activeBorrowings = $pdo->query("SELECT COUNT(*) FROM borrowings WHERE status IN ('borrowed', 'partial')")->fetchColumn();
$totalStock = $pdo->query("SELECT SUM(total_stock) FROM items WHERE is_active = TRUE")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - SBDT Inventory</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            margin: 0;
            background: #f4f7f6;
            display: flex;
            height: 100vh;
        }

        /* Sidebar Navigation */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            display: flex;
            flex-direction: column;
        }

        .sidebar h2 {
            text-align: center;
            padding: 20px 0;
            margin: 0;
            background: #1a252f;
            font-size: 20px;
            letter-spacing: 1px;
        }

        .sidebar a {
            padding: 15px 20px;
            text-decoration: none;
            color: #bdc3c7;
            border-bottom: 1px solid #34495e;
            transition: 0.2s;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .logout {
            margin-top: auto;
            color: #e74c3c !important;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .header h1 {
            margin: 0;
            color: #2c3e50;
        }

        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border-left: 5px solid #3498db;
        }

        .card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #7f8c8d;
            text-transform: uppercase;
        }

        .card p {
            margin: 0;
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }

        .card.warning {
            border-left-color: #e74c3c;
        }
    </style>
</head>

<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>SBDT Admin</h2>
        <a href="dashboard.php" class="active">Dashboard</a>
        <a href="items/index.php">Manage Items</a>
        <a href="categories/index.php">Categories</a>
        <a href="borrowings/index.php">Borrowings & Returns</a>
        <a href="stock_movements/index.php">Stock History</a>
        <a href="users/index.php">Manage Users</a>
        <a href="/SBDTInventory/logout.php" class="logout">Log out</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>Admin Dashboard</h1>
            <p>Welcome back, <strong><?= htmlspecialchars($user['username']) ?></strong>!</p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-container">
            <div class="card">
                <h3>Active Items</h3>
                <p><?= $itemCount ?></p>
            </div>
            <div class="card">
                <h3>Total Physical Stock</h3>
                <p><?= $totalStock ?? 0 ?></p>
            </div>
            <div class="card warning">
                <h3>Items Out (Borrowed)</h3>
                <p><?= $activeBorrowings ?></p>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h2>Quick Actions</h2>
            <p>Use the sidebar to navigate. If dancers are returning items, go to <strong>Borrowings & Returns</strong>.</p>
        </div>
    </div>

</body>

</html>