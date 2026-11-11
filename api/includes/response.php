<?php
declare(strict_types=1);

function json_send(int $status, array $payload): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_success(array $data = [], int $status = 200): never {
    json_send($status, ['success' => true, 'data' => $data]);
}

function json_error(string $message, int $status = 400, array $extra = []): never {
    json_send($status, array_merge(['success' => false, 'error' => $message], $extra));
}

function json_validation(array $errors): never {
    json_send(422, ['success' => false, 'error' => 'Validation failed', 'errors' => $errors]);
}