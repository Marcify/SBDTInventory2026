<?php
require_once __DIR__ . '/../../includes/auth.php';
require_admin();
require_csrf();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: index.php');
    exit();
}

// Fetch the item
$stmt = $pdo->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    die("Item not found.");
}

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $stmt->fetchAll();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $new_total = (int)($_POST['total_stock'] ?? 0);

    if ($name === '' || $category_id === '' || $new_total < 0) {
        $error = 'Please fill in all fields and ensure total stock is 0 or more.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock the row; the edit must be based on current balances.
            $stmt = $pdo->prepare(
                "SELECT id, total_stock, available_stock, borrowed_stock,
                        damaged_stock, lost_stock
                 FROM items
                 WHERE id = ?
                 FOR UPDATE"
            );
            $stmt->execute([$id]);
            $item = $stmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                $error = 'Item not found.';
            } else {
                // Verify the new category exists.
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);

                if (!$stmt->fetch()) {
                    $pdo->rollBack();
                    $error = 'That category does not exist.';
                } else {
                    $total = (int)$item['total_stock'];
                    $available = (int)$item['available_stock'];
                    $borrowed = (int)$item['borrowed_stock'];
                    $damaged = (int)$item['damaged_stock'];
                    $lost = (int)$item['lost_stock'];

                    $difference = $new_total - $total;
                    $new_available = $available + $difference;

                    // Total can never be less than what is not available.
                    if ($new_available < 0) {
                        $pdo->rollBack();
                        $unavailable = $borrowed + $damaged + $lost;
                        $error = "Total stock cannot be less than $unavailable "
                            . "($borrowed borrowed, $damaged damaged, $lost lost).";
                    } else {
                        $stmt = $pdo->prepare(
                            "UPDATE items
                             SET name = ?, category_id = ?, total_stock = ?, available_stock = ?
                             WHERE id = ?"
                        );
                        $stmt->execute([$name, $category_id, $new_total, $new_available, $id]);

                        // Log increases as IN and decreases as OUT,
                        // so the audit trail reflects both directions.
                        if ($difference > 0) {
                            $stmt = $pdo->prepare(
                                "INSERT INTO stock_movements (item_id, user_id, type, quantity, reason)
                                 VALUES (?, ?, 'IN', ?, ?)"
                            );
                            $stmt->execute([$id, $_SESSION['user_id'], $difference, 'Added stock via item edit']);
                        } elseif ($difference < 0) {
                            $stmt = $pdo->prepare(
                                "INSERT INTO stock_movements (item_id, user_id, type, quantity, reason)
                                 VALUES (?, ?, 'OUT', ?, ?)"
                            );
                            $stmt->execute([$id, $_SESSION['user_id'], -$difference, 'Removed stock via item edit']);
                        }

                        $pdo->commit();
                        header('Location: index.php');
                        exit();
                    }
                }
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Something went wrong while saving. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Item</title>
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
    </style>
</head>

<body>
    <h1>Edit Item</h1>
    <a href="index.php">&larr; Back to Items List</a>
    <br><br>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <div class="form-group">
            <label>Item Name</label>
            <input type="text" name="name" value="<?= htmlspecialchars($item['name']) ?>" required>
        </div>

        <div class="form-group">
            <label>Category</label>
            <select name="category_id" required>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>" <?= $cat['id'] == $item['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Total Stock (Changing this will adjust available stock)</label>
            <input type="number" name="total_stock" min="0" value="<?= $item['total_stock'] ?>" required>
        </div>

        <button type="submit">Update Item</button>
    </form>
</body>

</html>