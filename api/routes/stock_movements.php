<?php
declare(strict_types=1);

// List stock movements with optional filtering and pagination.
route('GET', '/stock-movements', function () {
    api_require_admin();

    $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT);
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);

    $itemId = filter_var(
        $_GET['item_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $type = $_GET['type'] ?? null;
    $userId = filter_var(
        $_GET['user_id'] ?? '',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $errors = [];

    if ($limit === false || $limit < 1 || $limit > 200) {
        $errors['limit'] = 'Limit must be an integer from 1 to 200';
    }

    if ($offset === false || $offset < 0) {
        $errors['offset'] = 'Offset must be a nonnegative integer';
    }

    if (
        isset($_GET['item_id']) &&
        $itemId === false
    ) {
        $errors['item_id'] = 'Item ID must be a positive integer';
    }

    if (
        isset($_GET['user_id']) &&
        $userId === false
    ) {
        $errors['user_id'] = 'User ID must be a positive integer';
    }

    if (
        $type !== null &&
        (
            !is_string($type) ||
            !in_array($type, ['IN', 'OUT', 'ADJUSTMENT'], true)
        )
    ) {
        $errors['type'] = 'Type must be IN, OUT, or ADJUSTMENT';
    }

    if ($errors) {
        json_validation($errors);
    }

    $conditions = [];
    $parameters = [];

    if ($itemId !== false) {
        $conditions[] = 'sm.item_id = :item_id';
        $parameters[':item_id'] = $itemId;
    }

    if ($userId !== false) {
        $conditions[] = 'sm.user_id = :user_id';
        $parameters[':user_id'] = $userId;
    }

    if (is_string($type)) {
        $conditions[] = 'sm.type = :type';
        $parameters[':type'] = $type;
    }

    $where = $conditions
        ? 'WHERE ' . implode(' AND ', $conditions)
        : '';

    global $pdo;

    $stmt = $pdo->prepare(
        "SELECT
            sm.id,
            sm.item_id,
            i.name AS item_name,
            sm.user_id,
            u.name AS user_name,
            sm.type,
            sm.quantity,
            sm.reason,
            sm.created_at
         FROM stock_movements sm
         JOIN items i ON i.id = sm.item_id
         JOIN users u ON u.id = sm.user_id
         $where
         ORDER BY sm.created_at DESC, sm.id DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($parameters as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            $key === ':type' ? PDO::PARAM_STR : PDO::PARAM_INT
        );
    }


    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $movements = array_map(
        static function (array $movement): array {
            $movement['id'] = (int)$movement['id'];
            $movement['item_id'] = (int)$movement['item_id'];
            $movement['user_id'] = (int)$movement['user_id'];
            $movement['quantity'] = (int)$movement['quantity'];

            return $movement;
        },
        $stmt->fetchAll()
    );

    json_success([
        'movements' => $movements,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'count' => count($movements),
        ],
    ]);
});
