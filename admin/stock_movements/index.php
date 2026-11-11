<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();

// Fetch all stock movements, joining with items and users to get names
$sql = "SELECT stock_movements.*, items.name AS item_name, users.username, users.name AS user_name 
        FROM stock_movements 
        JOIN items ON stock_movements.item_id = items.id 
        JOIN users ON stock_movements.user_id = users.id 
        ORDER BY stock_movements.created_at DESC";
$stmt = $pdo->query($sql);
$movements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Stock History</title>
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

        .type-IN {
            color: green;
            font-weight: bold;
        }

        .type-OUT {
            color: red;
            font-weight: bold;
        }

        .type-ADJUSTMENT {
            color: orange;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Stock Movements History</h1>
    <a href="/SBDTInventory/admin/dashboard.php">Back to Dashboard</a>

    <table>
        <thead>
            <tr>
                <th>Date & Time</th>
                <th>Item</th>
                <th>Type</th>
                <th>Quantity</th>
                <th>Performed By</th>
                <th>Reason</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movements)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No stock movements recorded yet.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($movements as $m): ?>
                    <tr>
                        <td><?= date('M d, Y h:i A', strtotime($m['created_at'])) ?></td>
                        <td><?= htmlspecialchars($m['item_name']) ?></td>
                        <td>
                            <span class="type-<?= $m['type'] ?>">
                                <?= $m['type'] ?>
                            </span>
                        </td>
                        <td><?= $m['quantity'] ?></td>
                        <td><?= htmlspecialchars($m['user_name']) ?></td>
                        <td><?= htmlspecialchars($m['reason']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>