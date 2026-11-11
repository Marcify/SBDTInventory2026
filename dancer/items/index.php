<?php
require_once __DIR__ . '/../../includes/auth.php';
require_dancer();

// Only show items that are active and have available stock
$stmt = $pdo->query("
    SELECT items.*, categories.name AS category_name 
    FROM items 
    JOIN categories ON items.category_id = categories.id 
    WHERE items.is_active = TRUE AND items.available_stock > 0
    ORDER BY categories.name ASC, items.name ASC
");
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Browse Items</title>
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
    </style>
</head>

<body>
    <h1>Browse Items</h1>
    <a href="/SBDTInventory/dancer/dashboard.php">Back to Dashboard</a> |
    <a href="../borrowings/create.php" class="btn">Go to Borrow Form</a>

    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Item Name</th>
                <th>Available Stock</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="3" style="text-align: center;">No items available for borrowing right now.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['category_name']) ?></td>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><strong><?= $item['available_stock'] ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>