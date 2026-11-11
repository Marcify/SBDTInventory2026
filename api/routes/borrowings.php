<?php
declare(strict_types=1);

route('POST', '/borrowings', function () {
    $user = api_require_dancer();
    api_require_csrf();

    $itemId = body('item_id');
    $quantity = body('quantity');
    $reason = body('reason');

    $errors = [];

    if (
        !is_int($itemId) ||
        $itemId < 1 ||
        $itemId > 2147483647
    ) {
        $errors['item_id'] = 'Item ID must be a positive integer';
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

        // Lock the item so competing requests cannot borrow
        // against the same available stock simultaneously.
        $stmt = $pdo->prepare(
            'SELECT id, name, available_stock, is_active
             FROM items
             WHERE id = ?
             FOR UPDATE'
        );

        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if (!$item || !(bool)$item['is_active']) {
            $pdo->rollBack();
            json_error('Item not found or unavailable for borrowing', 404);
        }

        if ($quantity > (int)$item['available_stock']) {
            $available = (int)$item['available_stock'];

            $pdo->rollBack();

            json_error('Not enough available stock', 409, [
                'available_stock' => $available,
            ]);
        }

        // 20 characters, matching reference_code VARCHAR(20).
        $referenceCode = 'BRW-' . strtoupper(bin2hex(random_bytes(8)));

        $stmt = $pdo->prepare(
            'INSERT INTO borrowings
                (reference_code, user_id, reason)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $referenceCode,
            $user['id'],
            $reason,
        ]);

        $borrowingId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO borrowing_details
                (borrowing_id, item_id, quantity_borrowed)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([
            $borrowingId,
            $itemId,
            $quantity,
        ]);

        $detailId = (int)$pdo->lastInsertId();

        // Keep a stock condition on the update as an extra safeguard.
        $stmt = $pdo->prepare(
            'UPDATE items
             SET available_stock = available_stock - ?,
                 borrowed_stock = borrowed_stock + ?
             WHERE id = ?
               AND is_active = TRUE
               AND available_stock >= ?'
        );

        $stmt->execute([
            $quantity,
            $quantity,
            $itemId,
            $quantity,
        ]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            json_error('Stock changed. Please try again', 409);
        }

        $stmt = $pdo->prepare(
            "INSERT INTO stock_movements
                (item_id, user_id, type, quantity, reason)
             VALUES (?, ?, 'OUT', ?, ?)"
        );

        $stmt->execute([
            $itemId,
            $user['id'],
            $quantity,
            'Borrowed - ' . $referenceCode,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success([
        'borrowing' => [
            'id' => $borrowingId,
            'reference_code' => $referenceCode,
            'user_id' => $user['id'],
            'reason' => $reason,
            'status' => 'borrowed',
            'details' => [
                [
                    'id' => $detailId,
                    'item_id' => $itemId,
                    'item_name' => $item['name'],
                    'quantity_borrowed' => $quantity,
                    'quantity_returned' => 0,
                ],
            ],
        ],
    ], 201);
});

// List borrowings, with optional status filtering and pagination.
route('GET', '/borrowings', function () {
    $user = api_require_login();

    $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
    $status = $_GET['status'] ?? null;

    $errors = [];

    if ($limit === false || $limit < 1 || $limit > 200) {
        $errors['limit'] = 'Limit must be an integer from 1 to 200';
    }

    if ($offset === false || $offset < 0) {
        $errors['offset'] = 'Offset must be a nonnegative integer';
    }

    if (
        $status !== null &&
        (
            !is_string($status) ||
            !in_array($status, ['borrowed', 'partial', 'returned'], true)
        )
    ) {
        $errors['status'] = 'Status must be borrowed, partial, or returned';
    }

    if ($errors) {
        json_validation($errors);
    }

    $conditions = [];
    $parameters = [];

    // Never accept a dancer's user ID from the request.
    if ($user['role'] !== 'admin') {
        $conditions[] = 'b.user_id = :user_id';
        $parameters[':user_id'] = $user['id'];
    }

    if ($status !== null) {
        $conditions[] = 'b.status = :status';
        $parameters[':status'] = $status;
    }

    $where = $conditions
        ? 'WHERE ' . implode(' AND ', $conditions)
        : '';

    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT
            b.id,
            b.reference_code,
            b.user_id,
            u.name AS borrower_name,
            b.reason,
            b.status,
            b.borrowed_at
         FROM borrowings b
         JOIN users u ON u.id = b.user_id
         $where
         ORDER BY b.borrowed_at DESC, b.id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($parameters as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $borrowings = array_map(
        'api_borrowing_payload',
        $stmt->fetchAll()
    );

    json_success([
        'borrowings' => $borrowings,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($borrowings),
        ],
    ]);
});

// Retrieve a borrowing and its item details.
route('GET', '/borrowings/{id}', function ($id) {
    $user = api_require_login();

    $id = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($id === false) {
        json_validation([
            'id' => 'Borrowing ID must be a positive integer',
        ]);
    }

    global $pdo;

    $sql = 'SELECT
                b.id,
                b.reference_code,
                b.user_id,
                u.name AS borrower_name,
                b.reason,
                b.status,
                b.borrowed_at
            FROM borrowings b
            JOIN users u ON u.id = b.user_id
            WHERE b.id = ?';

    $parameters = [$id];

    // Scope the database query itself to the current dancer.
    if ($user['role'] !== 'admin') {
        $sql .= ' AND b.user_id = ?';
        $parameters[] = $user['id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    $borrowing = $stmt->fetch();

    if (!$borrowing) {
        // Use the same response for missing and inaccessible records.
        json_error('Borrowing not found', 404);
    }

    $stmt = $pdo->prepare(
        'SELECT
            bd.id,
            bd.item_id,
            i.name AS item_name,
            bd.quantity_borrowed,
            bd.quantity_returned
         FROM borrowing_details bd
         JOIN items i ON i.id = bd.item_id
         WHERE bd.borrowing_id = ?
         ORDER BY bd.id ASC'
    );

    $stmt->execute([$id]);

    $details = array_map(
        static function (array $detail): array {
            $detail['id'] = (int)$detail['id'];
            $detail['item_id'] = (int)$detail['item_id'];
            $detail['quantity_borrowed'] =
                (int)$detail['quantity_borrowed'];
            $detail['quantity_returned'] =
                (int)$detail['quantity_returned'];

            $detail['quantity_remaining'] =
                $detail['quantity_borrowed'] -
                $detail['quantity_returned'];

            return $detail;
        },
        $stmt->fetchAll()
    );

        // Fetch all return records belonging to this borrowing.
    $stmt = $pdo->prepare(
        'SELECT
            r.id,
            r.borrowing_detail_id AS detail_id,
            r.condition_enum AS condition_value,
            r.quantity,
            r.returned_at
         FROM item_returns r
         JOIN borrowing_details bd
             ON bd.id = r.borrowing_detail_id
         WHERE bd.borrowing_id = ?
         ORDER BY r.returned_at ASC, r.id ASC'
    );

    $stmt->execute([$id]);

    $returnsByDetail = [];

    foreach ($stmt->fetchAll() as $return) {
        $detailId = (int)$return['detail_id'];

        $returnsByDetail[$detailId][] = [
            'id' => (int)$return['id'],
            'condition' => $return['condition_value'],
            'quantity' => (int)$return['quantity'],
            'returned_at' => $return['returned_at'],
        ];
    }

    // Attach history to each detail, including an empty array
    // when no returns have been recorded.
    foreach ($details as &$detail) {
        $detail['returns'] = $returnsByDetail[$detail['id']] ?? [];
    }

    unset($detail);

    $borrowing = api_borrowing_payload($borrowing);
    $borrowing['details'] = $details;

    json_success(['borrowing' => $borrowing]);
});

// Normalize database IDs for JSON responses.
function api_borrowing_payload(array $borrowing): array {
    $borrowing['id'] = (int)$borrowing['id'];
    $borrowing['user_id'] = (int)$borrowing['user_id'];

    return $borrowing;
}

// Process a return for one borrowing detail.
route('POST', '/borrowings/{id}/returns', function ($id) {
    $user = api_require_admin();
    api_require_csrf();

    $borrowingId = filter_var(
        $id,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $detailId = body('detail_id');
    $quantity = body('quantity');
    $condition = body('condition');

    $errors = [];

    if ($borrowingId === false) {
        $errors['id'] = 'Borrowing ID must be a positive integer';
    }

    if (
        !is_int($detailId) ||
        $detailId < 1 ||
        $detailId > 2147483647
    ) {
        $errors['detail_id'] = 'Detail ID must be a positive integer';
    }

    if (
        !is_int($quantity) ||
        $quantity < 1 ||
        $quantity > 2147483647
    ) {
        $errors['quantity'] = 'Quantity must be a positive integer';
    }

    if (
        !is_string($condition) ||
        !in_array($condition, ['good', 'damaged', 'lost'], true)
    ) {
        $errors['condition'] = 'Condition must be good, damaged, or lost';
    }

    if ($errors) {
        json_validation($errors);
    }

    global $pdo;

    try {
        $pdo->beginTransaction();

        // Serialize returns belonging to the same borrowing.
        $stmt = $pdo->prepare(
            'SELECT id, reference_code
             FROM borrowings
             WHERE id = ?
             FOR UPDATE'
        );

        $stmt->execute([$borrowingId]);
        $borrowing = $stmt->fetch();

        if (!$borrowing) {
            $pdo->rollBack();
            json_error('Borrowing not found', 404);
        }

        // The detail must belong to the borrowing in the URL.
        $stmt = $pdo->prepare(
            'SELECT
                id,
                item_id,
                quantity_borrowed,
                quantity_returned
             FROM borrowing_details
             WHERE id = ? AND borrowing_id = ?
             FOR UPDATE'
        );

        $stmt->execute([$detailId, $borrowingId]);
        $detail = $stmt->fetch();

        if (!$detail) {
            $pdo->rollBack();
            json_error('Borrowing detail not found', 404);
        }

        $remaining =
            (int)$detail['quantity_borrowed'] -
            (int)$detail['quantity_returned'];

        if ($quantity > $remaining) {
            $pdo->rollBack();

            json_error('Return quantity exceeds the remaining quantity', 409, [
                'quantity_remaining' => $remaining,
            ]);
        }

        // Lock the inventory row before changing its balances.
        $stmt = $pdo->prepare(
            'SELECT id, borrowed_stock
             FROM items
             WHERE id = ?
             FOR UPDATE'
        );

        $stmt->execute([$detail['item_id']]);
        $item = $stmt->fetch();

        if (!$item || (int)$item['borrowed_stock'] < $quantity) {
            $pdo->rollBack();

            json_error(
                'Inventory balance is inconsistent. Review the item stock before returning',
                409
            );
        }

        $stmt = $pdo->prepare(
            'INSERT INTO item_returns
                (borrowing_detail_id, condition_enum, quantity)
             VALUES (?, ?, ?)'
        );

        $stmt->execute([$detailId, $condition, $quantity]);
        $returnId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'UPDATE borrowing_details
             SET quantity_returned = quantity_returned + ?
             WHERE id = ?'
        );

        $stmt->execute([$quantity, $detailId]);

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

        $stmt->execute([
            $quantity,
            $quantity,
            $detail['item_id'],
        ]);

        // Good returns replenish usable stock.
        // Damaged/lost resolutions are adjustments, not usable stock IN.
        $movementType = $condition === 'good' ? 'IN' : 'ADJUSTMENT';

        $stmt = $pdo->prepare(
            'INSERT INTO stock_movements
                (item_id, user_id, type, quantity, reason)
             VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $detail['item_id'],
            $user['id'],
            $movementType,
            $quantity,
            'Return (' . $condition . ') - ' . $borrowing['reference_code'],
        ]);

        // Lock/read all details to calculate the current status.
        $stmt = $pdo->prepare(
            'SELECT quantity_borrowed, quantity_returned
             FROM borrowing_details
             WHERE borrowing_id = ?
             ORDER BY id ASC
             FOR UPDATE'
        );

        $stmt->execute([$borrowingId]);

        $totalRemaining = 0;

        foreach ($stmt->fetchAll() as $row) {
            $totalRemaining +=
                (int)$row['quantity_borrowed'] -
                (int)$row['quantity_returned'];
        }

        $status = $totalRemaining === 0 ? 'returned' : 'partial';

        $stmt = $pdo->prepare(
            'UPDATE borrowings SET status = ? WHERE id = ?'
        );

        $stmt->execute([$status, $borrowingId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    json_success([
        'return' => [
            'id' => $returnId,
            'borrowing_id' => $borrowingId,
            'detail_id' => $detailId,
            'item_id' => (int)$detail['item_id'],
            'condition' => $condition,
            'quantity' => $quantity,
            'quantity_remaining' => $remaining - $quantity,
        ],
        'borrowing_status' => $status,
    ], 201);
});
