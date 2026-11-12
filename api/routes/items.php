<?php
declare(strict_types=1);

// List items.
// Dancers see only active items with available stock.
// Admins see all items.
route('GET', '/items', function () {
    $user = api_require_login();

    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);

    if (
        $limit === false || $limit < 1 || $limit > 200 ||
        $offset === false || $offset < 0
    ) {
        json_validation([
            'pagination' => 'Limit must be 1–200 and offset must be zero or greater',
        ]);
    }

    global $pdo;

    $where = $user['role'] === 'admin'
        ? ''
        : 'WHERE i.is_active = TRUE AND i.available_stock > 0';

    $stmt = $pdo->prepare(
        "SELECT
            i.id,
            i.category_id,
            c.name AS category_name,
            i.name,
            i.total_stock,
            i.available_stock,
            i.borrowed_stock,
            i.damaged_stock,
            i.lost_stock,
            i.is_active
         FROM items i
         JOIN categories c ON c.id = i.category_id
         $where
         ORDER BY i.name ASC, i.id ASC
         LIMIT :limit OFFSET :offset"
    );

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $items = array_map('api_item_payload', $stmt->fetchAll());

    json_success([
        'items' => $items,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($items),
        ],
    ]);
});

// Retrieve one item.
route('GET', '/items/{id}', function ($id) {
    $user = api_require_login();

    $id = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        json_validation(['id' => 'Item ID must be a positive integer']);
    }

    global $pdo;

    $stmt = $pdo->prepare(
        'SELECT
            i.id,
            i.category_id,
            c.name AS category_name,
            i.name,
            i.total_stock,
            i.available_stock,
            i.borrowed_stock,
            i.damaged_stock,
            i.lost_stock,
            i.is_active
         FROM items i
         JOIN categories c ON c.id = i.category_id
         WHERE i.id = ?'
    );

    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (
        !$item ||
        (
            $user['role'] !== 'admin' &&
            (!$item['is_active'] || (int)$item['available_stock'] < 1)
        )
    ) {
        json_error('Item not found', 404);
    }

    json_success(['item' => api_item_payload($item)]);
});

// Create an item and record its initial stock.
route('POST', '/items', function () {
    $user = api_require_admin();
    api_require_csrf();

    $name = body('name');
    $rawCategoryId = body('category_id');
    $rawStock = body('total_stock');

    $errors = [];

    if (!is_string($name) || trim($name) === '') {
        $errors['name'] = 'Item name is required and must be a string';
    } else {
        $name = trim($name);
        $length = preg_match_all('/./us', $name);

        if ($length === false || $length > 150) {
            $errors['name'] = 'Item name must not exceed 150 characters';
        }
    }

    // JSON clients must send these as integers, not strings or decimals.
    if (!is_int($rawCategoryId) || $rawCategoryId < 1 ||
        $rawCategoryId > 2147483647) {
        $errors['category_id'] = 'Category ID must be a positive integer';
    }

    if (!is_int($rawStock) || $rawStock < 1 ||
        $rawStock > 2147483647) {
        $errors['total_stock'] = 'Total stock must be an integer from 1 to 2147483647';
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$rawCategoryId]);

    if (!$stmt->fetch()) {
        json_validation(['category_id' => 'Category does not exist']);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO items
                (category_id, name, total_stock, available_stock)
             VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $rawCategoryId,
            $name,
            $rawStock,
            $rawStock,
        ]);

        $itemId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            "INSERT INTO stock_movements
                (item_id, user_id, type, quantity, reason)
             VALUES (?, ?, 'IN', ?, ?)"
        );

        $stmt->execute([
            $itemId,
            $user['id'],
            $rawStock,
            'Initial stock on item creation',
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    header('Location: /SBDTInventory/api/v1/items/' . $itemId);

    json_success([
        'item' => [
            'id' => $itemId,
            'category_id' => $rawCategoryId,
            'name' => $name,
            'total_stock' => $rawStock,
            'available_stock' => $rawStock,
            'borrowed_stock' => 0,
            'damaged_stock' => 0,
            'lost_stock' => 0,
            'is_active' => true,
        ],
    ], 201);
});

// Normalize database values for consistent JSON types.
function api_item_payload(array $item): array {
    foreach ([
        'id',
        'category_id',
        'total_stock',
        'available_stock',
        'borrowed_stock',
        'damaged_stock',
        'lost_stock',
    ] as $field) {
        $item[$field] = (int)$item[$field];
    }

    $item['is_active'] = (bool)$item['is_active'];

    return $item;
}

// Edit item metadata or archive/unarchive an item.
route('PATCH', '/items/{id}', function ($id) {
    api_require_admin();
    api_require_csrf();

    $itemId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($itemId === false) {
        json_validation([
            'id' => 'Item ID must be a positive integer',
        ]);
    }

    $data = request_body();

    $allowedFields = ['name', 'category_id', 'is_active'];
    $unknownFields = array_diff(array_keys($data), $allowedFields);

    if ($unknownFields) {
        json_validation([
            'fields' => 'Unsupported fields: ' . implode(', ', $unknownFields),
        ]);
    }

    if ($data === []) {
        json_validation([
            'fields' => 'Provide at least one field to update',
        ]);
    }

    $errors = [];
    $updates = [];
    $parameters = [];

    if (array_key_exists('name', $data)) {
        if (!is_string($data['name']) || trim($data['name']) === '') {
            $errors['name'] = 'Item name must be a nonempty string';
        } else {
            $name = trim($data['name']);
            $length = preg_match_all('/./us', $name);

            if ($length === false || $length > 150) {
                $errors['name'] = 'Item name must not exceed 150 characters';
            } else {
                $updates[] = 'name = ?';
                $parameters[] = $name;
            }
        }
    }

    if (array_key_exists('category_id', $data)) {
        if (
            !is_int($data['category_id']) ||
            $data['category_id'] < 1 ||
            $data['category_id'] > 2147483647
        ) {
            $errors['category_id'] = 'Category ID must be a positive integer';
        } else {
            $updates[] = 'category_id = ?';
            $parameters[] = $data['category_id'];
        }
    }

    if (array_key_exists('is_active', $data)) {
        if (!is_bool($data['is_active'])) {
            $errors['is_active'] = 'Active status must be true or false';
        } else {
            $updates[] = 'is_active = ?';
            $parameters[] = $data['is_active'] ? 1 : 0;
        }
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        // Serialize this edit with borrowing and stock operations.
        $stmt = $pdo->prepare(
            'SELECT id FROM items WHERE id = ? FOR UPDATE'
        );

        $stmt->execute([$itemId]);

        if (!$stmt->fetch()) {
            $pdo->rollBack();
            json_error('Item not found', 404);
        }

        if (array_key_exists('category_id', $data)) {
            $stmt = $pdo->prepare(
                'SELECT id FROM categories WHERE id = ?'
            );

            $stmt->execute([$data['category_id']]);

            if (!$stmt->fetch()) {
                $pdo->rollBack();

                json_validation([
                    'category_id' => 'Category does not exist',
                ]);
            }
        }

        // Column names come only from the fixed field checks above.
        $parameters[] = $itemId;

        $stmt = $pdo->prepare(
            'UPDATE items SET ' . implode(', ', $updates) . ' WHERE id = ?'
        );

        $stmt->execute($parameters);

        $stmt = $pdo->prepare(
            'SELECT
                i.id,
                i.category_id,
                c.name AS category_name,
                i.name,
                i.total_stock,
                i.available_stock,
                i.borrowed_stock,
                i.damaged_stock,
                i.lost_stock,
                i.is_active
             FROM items i
             JOIN categories c ON c.id = i.category_id
             WHERE i.id = ?'
        );

        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success(['item' => api_item_payload($item)]);
});

// Add or remove available stock, with an audit reason.
route('POST', '/items/{id}/stock-adjustments', function ($id) {
    $user = api_require_admin();
    api_require_csrf();

    $itemId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $data = request_body();
    $action = $data['action'] ?? null;
    $quantity = $data['quantity'] ?? null;
    $reason = $data['reason'] ?? null;

    $errors = [];

    $unknownFields = array_diff(
        array_keys($data),
        ['action', 'quantity', 'reason']
    );

    if ($unknownFields) {
        $errors['fields'] =
            'Unsupported fields: ' . implode(', ', $unknownFields);
    }

    if ($itemId === false) {
        $errors['id'] = 'Item ID must be a positive integer';
    }

    if (!in_array($action, ['add', 'remove', 'repair'], true)) {
        $errors['action'] = 'Action must be add, remove, or repair';
    }


    if (
        !is_int($quantity) ||
        $quantity < 1 ||
        $quantity > 2147483647
    ) {
        $errors['quantity'] = 'Quantity must be a positive integer';
    }

    if (!is_string($reason) || trim($reason) === '') {
        $errors['reason'] = 'Reason is required and must be a string';
    } else {
        $reason = trim($reason);
        $length = preg_match_all('/./us', $reason);

        if ($length === false || $length > 255) {
            $errors['reason'] = 'Reason must not exceed 255 characters';
        }
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT
                id,
                total_stock,
                available_stock,
                borrowed_stock,
                damaged_stock,
                lost_stock
             FROM items
             WHERE id = ?
             FOR UPDATE'
        );

        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            $pdo->rollBack();
            json_error('Item not found', 404);
        }

        $balances = [];

        foreach ([
            'total_stock',
            'available_stock',
            'borrowed_stock',
            'damaged_stock',
            'lost_stock',
        ] as $field) {
            $balances[$field] = (int)$item[$field];
        }

        $componentTotal =
            $balances['available_stock'] +
            $balances['borrowed_stock'] +
            $balances['damaged_stock'] +
            $balances['lost_stock'];

        // Do not build further adjustments on inconsistent data.
        if (
            min($balances) < 0 ||
            $balances['total_stock'] !== $componentTotal
        ) {
            $pdo->rollBack();

            json_error(
                'Inventory balances are inconsistent. Reconcile this item before adjusting stock',
                409
            );
        }

        if ($action === 'add') {
            // Prevent overflow of the database's signed INT column.
            if ($quantity > 2147483647 - $balances['total_stock']) {
                $pdo->rollBack();
                json_error('Adjustment would exceed the stock limit', 409);
            }

            $newTotal = $balances['total_stock'] + $quantity;
            $newAvailable = $balances['available_stock'] + $quantity;
            $movementType = 'IN';
        } elseif ($action === 'repair') {
            // A damaged unit has been fixed and can be borrowed again.
            // total_stock is unchanged; one unit moves damaged -> available.
            if ($quantity > $balances['damaged_stock']) {
                $pdo->rollBack();

                json_error('Cannot repair more than the damaged stock', 409, [
                    'damaged_stock' => $balances['damaged_stock'],
                ]);
            }

            $newTotal = $balances['total_stock'];
            $newAvailable = $balances['available_stock'] + $quantity;
            $newDamaged = $balances['damaged_stock'] - $quantity;
            $movementType = 'IN';
        } else {
            if ($quantity > $balances['available_stock']) {
                $pdo->rollBack();

                json_error('Cannot remove more than the available stock', 409, [
                    'available_stock' => $balances['available_stock'],
                ]);
            }

            $newTotal = $balances['total_stock'] - $quantity;
            $newAvailable = $balances['available_stock'] - $quantity;
            $movementType = 'OUT';
        }


        if ($action === 'repair') {
            $stmt = $pdo->prepare(
                'UPDATE items
                 SET total_stock = ?, available_stock = ?, damaged_stock = ?
                 WHERE id = ?'
            );

            $stmt->execute([$newTotal, $newAvailable, $newDamaged, $itemId]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE items
                 SET total_stock = ?, available_stock = ?
                 WHERE id = ?'
            );

            $stmt->execute([$newTotal, $newAvailable, $itemId]);
        }


        $stmt = $pdo->prepare(
            'INSERT INTO stock_movements
                (item_id, user_id, type, quantity, reason)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $itemId,
            $user['id'],
            $movementType,
            $quantity,
            $reason,
        ]);

        $movementId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'SELECT
                i.id,
                i.category_id,
                c.name AS category_name,
                i.name,
                i.total_stock,
                i.available_stock,
                i.borrowed_stock,
                i.damaged_stock,
                i.lost_stock,
                i.is_active
             FROM items i
             JOIN categories c ON c.id = i.category_id
             WHERE i.id = ?'
        );

        $stmt->execute([$itemId]);
        $updatedItem = $stmt->fetch();

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success([
        'adjustment' => [
            'id' => $movementId,
            'item_id' => $itemId,
            'action' => $action,
            'quantity' => $quantity,
            'reason' => $reason,
        ],
        'item' => api_item_payload($updatedItem),
    ], 201);
});
