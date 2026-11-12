<?php
require_once __DIR__ . '/../../includes/auth.php';
require_dancer();
require_csrf();

$user = current_user();

// Fetch available items for the dropdown
$stmt = $pdo->query("SELECT id, name, available_stock FROM items WHERE is_active = TRUE AND available_stock > 0 ORDER BY name ASC");
$items = $stmt->fetchAll();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = $_POST['item_id'] ?? '';
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($item_id === '' || $quantity < 1 || $reason === '') {
        $error = 'Please fill in all fields and ensure quantity is at least 1.';
    } else {
        try {
            // Transaction FIRST, so the stock check and the deduction
            // happen atomically against a locked row.
            $pdo->beginTransaction();

            // Lock the item row; concurrent borrowers wait here.
            $stmt = $pdo->prepare(
                "SELECT id, name, available_stock, is_active
                 FROM items
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if (!$item || !(bool)$item['is_active']) {
                $pdo->rollBack();
                $error = 'That item is not available for borrowing.';
            } elseif ($quantity > (int)$item['available_stock']) {
                $pdo->rollBack();
                $error = 'Not enough stock available. Only ' . (int)$item['available_stock'] . ' left.';
            } else {
                // Reference code (unique, 20 chars max)
                $reference_code = 'BRW-' . date('Y') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

                $stmt = $pdo->prepare("INSERT INTO borrowings (reference_code, user_id, reason) VALUES (?, ?, ?)");
                $stmt->execute([$reference_code, $user['id'], $reason]);
                $borrowing_id = $pdo->lastInsertId();

                $stmt = $pdo->prepare("INSERT INTO borrowing_details (borrowing_id, item_id, quantity_borrowed) VALUES (?, ?, ?)");
                $stmt->execute([$borrowing_id, $item_id, $quantity]);

                // Conditional update: only succeeds if stock is still sufficient.
                $stmt = $pdo->prepare(
                    "UPDATE items
                     SET available_stock = available_stock - ?,
                         borrowed_stock = borrowed_stock + ?
                     WHERE id = ?
                       AND is_active = TRUE
                       AND available_stock >= ?"
                );
                $stmt->execute([$quantity, $quantity, $item_id, $quantity]);

                if ($stmt->rowCount() !== 1) {
                    $pdo->rollBack();
                    $error = 'Stock changed while processing. Please try again.';
                } else {
                    $move_reason = "Borrowed by " . $user['username'] . " - " . $reference_code;
                    $stmt = $pdo->prepare("INSERT INTO stock_movements (item_id, user_id, type, quantity, reason) VALUES (?, ?, 'OUT', ?, ?)");
                    $stmt->execute([$item_id, $user['id'], $quantity, $move_reason]);

                    $pdo->commit();
                    $success = "Successfully borrowed! Reference Code: " . $reference_code;
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Something went wrong while borrowing. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Borrow Item</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 40px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        input[type="text"],
        input[type="number"],
        select {
            padding: 8px;
            width: 300px;
        }

        button {
            padding: 10px 15px;
            background: #333;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 3px;
        }

        .error {
            color: red;
            margin-bottom: 15px;
        }

        .success {
            color: green;
            margin-bottom: 15px;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h1>Borrow an Item</h1>
    <a href="/SBDTInventory/dancer/dashboard.php">&larr; Back to Dashboard</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Select Item</label>
            <select name="item_id" required>
                <option value="">-- Choose an item --</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?= $item['id'] ?>">
                        <?= htmlspecialchars($item['name']) ?> (<?= $item['available_stock'] ?> available)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Quantity</label>
            <input type="number" name="quantity" min="1" required>
        </div>

        <div class="form-group">
            <label>Reason for Borrowing</label>
            <input type="text" name="reason" required placeholder="e.g., Practice for Recital">
        </div>

        <button type="submit">Submit Borrow Request</button>
    </form>
</body>

</html>