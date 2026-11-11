<?php

require_once __DIR__ . '/../../includes/auth.php';
require_admin();

// Fetch active borrowings with the dancer's name
$sql = "SELECT borrowings.*, users.name AS user_name
        FROM borrowings
        JOIN users ON borrowings.user_id = users.id
        WHERE borrowings.status IN ('borrowed', 'partial')
        ORDER BY borrowings.borrowed_at DESC";
$stmt = $pdo->query($sql);
$borrowings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Active Borrowings</title>
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
    <h1>Active Borrowings</h1>
    <a href="/SBDTInventory/admin/dashboard.php">Back to Dashboard</a>

    <table>
        <thead>
            <tr>
                <th>Reference Code</th>
                <th>Dancer</th>
                <th>Reason</th>
                <th>Borrowed At</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($borrowings)): ?>
                <tr>
                    <td colspan="6" style="text-align: center;">No active borrowings found.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($borrowings as $b): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($b['reference_code']) ?></strong></td>
                        <td><?= htmlspecialchars($b['user_name']) ?></td>
                        <td><?= htmlspecialchars($b['reason']) ?></td>
                        <td><?= date('M d, Y h:i A', strtotime($b['borrowed_at'])) ?></td>
                        <td><span style="color: <?= $b['status'] === 'partial' ? 'orange' : 'red' ?>"><?= ucfirst($b['status']) ?></span></td>
                        <td><a href="view.php?id=<?= $b['id'] ?>" class="btn">View / Return</a></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>

</html>