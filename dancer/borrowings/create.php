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
    $reason = trim($_POST['reason'] ?? '');

    // Rows arrive as parallel arrays: item_id[], quantity[].
    $itemIds = is_array($_POST['item_id'] ?? null) ? $_POST['item_id'] : [];
    $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];

    $lines = [];
    $lineErrors = [];

    // Ignore trailing empty rows the browser may submit.
    $rowCount = max(count($itemIds), count($quantities));

    if ($rowCount > 20) {
        $error = 'A borrowing may contain at most 20 items.';
    } else {
        $seenItemIds = [];

        for ($i = 0; $i < $rowCount; $i++) {
            $itemId = trim((string)($itemIds[$i] ?? ''));
            $quantity = (int)($quantities[$i] ?? 0);

            // Skip fully empty rows (e.g., an added row left blank).
            if ($itemId === '' && $quantity === 0) {
                continue;
            }

            if ($itemId === '' || !ctype_digit($itemId) || (int)$itemId < 1) {
                $lineErrors[] = 'Row ' . ($i + 1) . ': please choose an item.';
                continue;
            }

            if ($quantity < 1) {
                $lineErrors[] = 'Row ' . ($i + 1) . ': quantity must be at least 1.';
                continue;
            }

            $itemId = (int)$itemId;

            if (isset($seenItemIds[$itemId])) {
                $lineErrors[] = 'Row ' . ($i + 1) . ': that item is already in the list — combine the quantities instead.';
                continue;
            }

            $seenItemIds[$itemId] = true;
            $lines[] = ['item_id' => $itemId, 'quantity' => $quantity];
        }

        if ($lineErrors) {
            $error = implode(' ', $lineErrors);
        } elseif (!$lines) {
            $error = 'Add at least one item to borrow.';
        } elseif ($reason === '') {
            $error = 'Please enter a reason for borrowing.';
        } else {
            try {
                $pdo->beginTransaction();

                // Sort lines by item ID for consistent lock ordering.
                usort($lines, static fn(array $a, array $b): int => $a['item_id'] <=> $b['item_id']);

                // Lock every requested item row up front.
                $placeholders = implode(', ', array_fill(0, count($lines), '?'));
                $stmt = $pdo->prepare(
                    "SELECT id, name, available_stock, is_active
                     FROM items
                     WHERE id IN ($placeholders)
                     FOR UPDATE"
                );
                $stmt->execute(array_column($lines, 'item_id'));

                $lockedItems = [];
                foreach ($stmt->fetchAll() as $row) {
                    $lockedItems[(int)$row['id']] = $row;
                }

                // Validate every line against the locked rows.
                $stockProblems = [];

                foreach ($lines as $line) {
                    $item = $lockedItems[$line['item_id']] ?? null;

                    if (!$item || !(bool)$item['is_active']) {
                        $stockProblems[] = htmlspecialchars($item['name'] ?? ('Item #' . $line['item_id']))
                            . ' is not available for borrowing.';
                    } elseif ($line['quantity'] > (int)$item['available_stock']) {
                        $stockProblems[] = 'Only ' . (int)$item['available_stock']
                            . ' of ' . htmlspecialchars($item['name']) . ' available.';
                    }
                }

                if ($stockProblems) {
                    $pdo->rollBack();
                    $error = 'Could not borrow: ' . implode(' ', $stockProblems);
                } else {
                    $reference_code = 'BRW-' . date('Y') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);

                    $stmt = $pdo->prepare("INSERT INTO borrowings (reference_code, user_id, reason) VALUES (?, ?, ?)");
                    $stmt->execute([$reference_code, $user['id'], $reason]);
                    $borrowing_id = $pdo->lastInsertId();

                    $detailStmt = $pdo->prepare("INSERT INTO borrowing_details (borrowing_id, item_id, quantity_borrowed) VALUES (?, ?, ?)");
                    $stockStmt = $pdo->prepare(
                        "UPDATE items
                         SET available_stock = available_stock - ?,
                             borrowed_stock = borrowed_stock + ?
                         WHERE id = ?
                           AND is_active = TRUE
                           AND available_stock >= ?"
                    );
                    $movementStmt = $pdo->prepare(
                        "INSERT INTO stock_movements (item_id, user_id, type, quantity, reason)
                         VALUES (?, ?, 'OUT', ?, ?)"
                    );

                    $lineCount = count($lines);

                    foreach ($lines as $line) {
                        $detailStmt->execute([$borrowing_id, $line['item_id'], $line['quantity']]);

                        $stockStmt->execute([$line['quantity'], $line['quantity'], $line['item_id'], $line['quantity']]);

                        if ($stockStmt->rowCount() !== 1) {
                            $pdo->rollBack();
                            $error = 'Stock changed while processing. Please try again.';
                            break;
                        }

                        $movementStmt->execute([
                            $line['item_id'],
                            $user['id'],
                            $line['quantity'],
                            "Borrowed by " . $user['username'] . " - " . $reference_code,
                        ]);
                    }

                    if (!$error) {
                        $pdo->commit();
                        $success = "Successfully borrowed $lineCount item line(s)! Reference Code: " . $reference_code;
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
}

$page_title = 'Borrow Items';
$active_nav = 'borrow';

require __DIR__ . '/../../includes/layout_start.php';
?>

<h1 class="page-title">Borrow an Item</h1>
<p class="welcome">Add each item you need. All lines are borrowed together under one reference code.</p>

<?php if ($error): ?>
    <p class="message error"><?= htmlspecialchars($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="message success"><?= htmlspecialchars($success) ?></p>
<?php endif; ?>

<div class="content-box">
    <form method="POST" id="borrow-form">
        <?= csrf_field() ?>

        <div id="item-rows">
            <div class="item-row">
                <span class="item-line">
                    <select name="item_id[]" required>
                        <option value="">-- Choose an item --</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= $item['id'] ?>">
                                <?= htmlspecialchars($item['name']) ?> (<?= $item['available_stock'] ?> available)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="quantity[]" min="1" placeholder="Qty" required>
                    <button type="button" class="remove-btn remove-row">Remove</button>
                </span>
            </div>
        </div>

        <button type="button" id="add-row" class="add-btn add-row-btn">+ Add another item</button>

        <div class="form-group form-group-spaced">
            <label class="form-label" for="reason">Reason for Borrowing</label>
            <input type="text" id="reason" name="reason" required placeholder="e.g., Practice for Recital">
        </div>

        <div class="form-group-spaced">
            <button type="submit" class="borrow-btn">Submit Borrow Request</button>
        </div>
    </form>
</div>

<script>
    (function () {
        var container = document.getElementById('item-rows');
        var addBtn = document.getElementById('add-row');

        function wireRemove(row) {
            row.querySelector('.remove-row').addEventListener('click', function () {
                // Keep at least one row on the page.
                if (container.children.length > 1) {
                    row.remove();
                }
            });
        }

        addBtn.addEventListener('click', function () {
            var first = container.querySelector('.item-row');
            var clone = first.cloneNode(true);

            // Reset the cloned row's values.
            clone.querySelector('select').value = '';
            clone.querySelector('input').value = '';

            container.appendChild(clone);
            wireRemove(clone);
        });

        wireRemove(container.querySelector('.item-row'));
    })();
</script>

<?php require __DIR__ . '/../../includes/layout_end.php'; ?>
