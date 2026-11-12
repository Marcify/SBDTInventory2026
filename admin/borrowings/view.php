<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

// Fetch the main borrowing
$stmt = $pdo->prepare("SELECT borrowings.*, users.name AS user_name FROM borrowings JOIN users ON borrowings.user_id = users.id WHERE borrowings.id = ?");
$stmt->execute([$id]);
$borrowing = $stmt->fetch();

if (!$borrowing) {
    die("Borrowing not found.");
}

// Fetch the items inside this borrowing
$stmt = $pdo->prepare("
    SELECT bd.*, items.name AS item_name, items.available_stock, items.borrowed_stock 
    FROM borrowing_details bd 
    JOIN items ON bd.item_id = items.id 
    WHERE bd.borrowing_id = ?
");
$stmt->execute([$id]);
$details = $stmt->fetchAll();

// Handle Return Submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $detail_id = (int)($_POST['detail_id'] ?? 0);
    $return_qty = (int)($_POST['quantity'] ?? 0);
    $condition = $_POST['condition'] ?? '';

    if (!in_array($condition, ['good', 'damaged', 'lost'], true)) {
        $error = 'Invalid return condition.';
    } elseif ($return_qty < 1) {
        $error = 'Return quantity must be at least 1.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the borrowing row to serialize returns on it.
            $stmt = $pdo->prepare(
                "SELECT id, reference_code
                 FROM borrowings
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$id]);
            $locked_borrowing = $stmt->fetch();

            if (!$locked_borrowing) {
                $pdo->rollBack();
                $error = 'Borrowing not found.';
            } else {
                // The detail MUST belong to the borrowing in the URL.
                $stmt = $pdo->prepare(
                    "SELECT id, item_id, quantity_borrowed, quantity_returned
                     FROM borrowing_details
                     WHERE id = ? AND borrowing_id = ?
                     FOR UPDATE"
                );
                $stmt->execute([$detail_id, $id]);
                $detail = $stmt->fetch();

                if (!$detail) {
                    $pdo->rollBack();
                    $error = 'That item line does not belong to this borrowing.';
                } else {
                    $remaining = (int)$detail['quantity_borrowed'] - (int)$detail['quantity_returned'];

                    if ($return_qty > $remaining) {
                        $pdo->rollBack();
                        $error = "Invalid return quantity. You can only return up to $remaining items.";
                    } else {
                        // Lock the inventory row before changing balances.
                        $stmt = $pdo->prepare(
                            "SELECT id, borrowed_stock
                             FROM items
                             WHERE id = ?
                             FOR UPDATE"
                        );
                        $stmt->execute([$detail['item_id']]);
                        $item = $stmt->fetch();

                        if (!$item || (int)$item['borrowed_stock'] < $return_qty) {
                            $pdo->rollBack();
                            $error = 'Inventory balance is inconsistent. Review the item stock before returning.';
                        } else {
                            // 1. Insert the return record
                            $stmt = $pdo->prepare("INSERT INTO item_returns (borrowing_detail_id, condition_enum, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$detail_id, $condition, $return_qty]);

                            // 2. Update the detail's quantity_returned
                            $stmt = $pdo->prepare("UPDATE borrowing_details SET quantity_returned = quantity_returned + ? WHERE id = ?");
                            $stmt->execute([$return_qty, $detail_id]);

                            // 3. Update Item Stocks based on condition
                            // Column names come only from this fixed allowlist.
                            $stockColumns = [
                                'good' => 'available_stock',
                                'damaged' => 'damaged_stock',
                                'lost' => 'lost_stock',
                            ];
                            $stockColumn = $stockColumns[$condition];

                            $stmt = $pdo->prepare(
                                "UPDATE items
                                 SET borrowed_stock = borrowed_stock - ?,
                                     $stockColumn = $stockColumn + ?
                                 WHERE id = ?"
                            );
                            $stmt->execute([$return_qty, $return_qty, $detail['item_id']]);

                            // 4. Log the stock movement.
                            // Good returns replenish usable stock (IN);
                            // damaged/lost are adjustments, not stock IN.
                            $move_type = $condition === 'good' ? 'IN' : 'ADJUSTMENT';
                            $move_reason = "Returned (" . $condition . ") from " . $locked_borrowing['reference_code'];
                            $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, user_id, type, quantity, reason) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$detail['item_id'], $_SESSION['user_id'], $move_type, $return_qty, $move_reason]);

                            // 5. Recompute status from all locked details.
                            $stmt = $pdo->prepare(
                                "SELECT quantity_borrowed, quantity_returned
                                 FROM borrowing_details
                                 WHERE borrowing_id = ?
                                 ORDER BY id ASC
                                 FOR UPDATE"
                            );
                            $stmt->execute([$id]);

                            $remaining_total = 0;
                            foreach ($stmt->fetchAll() as $row) {
                                $remaining_total += (int)$row['quantity_borrowed'] - (int)$row['quantity_returned'];
                            }

                            $new_status = $remaining_total === 0 ? 'returned' : 'partial';

                            $stmt = $pdo->prepare("UPDATE borrowings SET status = ? WHERE id = ?");
                            $stmt->execute([$new_status, $id]);

                            $pdo->commit();
                            header("Location: view.php?id=" . $id);
                            exit();
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Something went wrong while processing the return. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Borrowing</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            margin-bottom: 20px;
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
            padding: 8px 12px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 3px;
        }

        .form-inline {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        select,
        input[type="number"] {
            padding: 5px;
        }

        .error {
            color: red;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Borrowing: <?= htmlspecialchars($borrowing['reference_code']) ?></h1>
    <a href="index.php">&larr; Back to Active Borrowings</a>

    <p><strong>Dancer:</strong> <?= htmlspecialchars($borrowing['user_name']) ?></p>
    <p><strong>Reason:</strong> <?= htmlspecialchars($borrowing['reason']) ?></p>
    <p><strong>Status:</strong> <?= ucfirst($borrowing['status']) ?></p>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty Borrowed</th>
                <th>Qty Returned</th>
                <th>Still Out</th>
                <th>Process Return</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($details as $d):
                $remaining = $d['quantity_borrowed'] - $d['quantity_returned'];
            ?>
                <tr>
                    <td><?= htmlspecialchars($d['item_name']) ?></td>
                    <td><?= $d['quantity_borrowed'] ?></td>
                    <td><?= $d['quantity_returned'] ?></td>
                    <td><strong><?= $remaining ?></strong></td>
                    <td>
                        <?php if ($remaining > 0): ?>
                            <form method="POST" class="form-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="detail_id" value="<?= $d['id'] ?>">
                                <input type="number" name="quantity" min="1" max="<?= $remaining ?>" value="<?= $remaining ?>" required style="width: 70px;">
                                <select name="condition" required>
                                    <option value="good">Good</option>
                                    <option value="damaged">Damaged</option>
                                    <option value="lost">Lost</option>
                                </select>
                                <button type="submit" class="btn">Return</button>
                            </form>
                        <?php else: ?>
                            <span style="color: green; font-weight: bold;">Fully Returned</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>