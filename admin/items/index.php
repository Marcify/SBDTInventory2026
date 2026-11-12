<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

// Fetch all items with their category names
$sql = "SELECT items.*, categories.name AS category_name 
        FROM items 
        LEFT JOIN categories ON items.category_id = categories.id 
        ORDER BY items.name ASC";
$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Items</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }

        th {
            background: #f4f4f4;
        }

        .btn {
            text-decoration: none;
            padding: 5px 10px;
            background: #333;
            color: white;
            border-radius: 3px;
            font-size: 14px;
        }

        .btn-danger {
            background: #c00;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>

<body>
    <div class="top-bar">
        <h1>Manage Items</h1>
        <div>
            <a href="/SBDTInventory/admin/dashboard.php">Back to Dashboard</a> |
            <a href="create.php" class="btn">+ Add New Item</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Total Stock</th>
                <th>Available</th>
                <th>Borrowed</th>
                <th>Damaged</th>
                <th>Lost</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="10" style="text-align: center;">No items found. Add one!</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $item['id'] ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= htmlspecialchars($item['category_name'] ?? 'None') ?></td>
                        <td><?= $item['total_stock'] ?></td>
                        <td><strong><?= $item['available_stock'] ?></strong></td>
                        <td><?= $item['borrowed_stock'] ?></td>
                        <td><?= $item['damaged_stock'] ?></td>
                        <td><?= $item['lost_stock'] ?></td>
                        <td><?= $item['is_active'] ? 'Active' : 'Archived' ?></td>
                        <td>
                            <a href="edit.php?id=<?= $item['id'] ?>" class="btn">Edit</a>

                            <!-- Archive/Unarchive button -->
                            <form action="toggle_status.php" method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <input type="hidden" name="current_status" value="<?= $item['is_active'] ?>">
                                <button type="submit" class="btn btn-danger">
                                    <?= $item['is_active'] ? 'Archive' : 'Unarchive' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>