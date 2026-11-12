<?php
declare(strict_types=1);

function request_body(): array {
    static $parsed = null;

    if ($parsed !== null) {
        return $parsed;
    }

    $raw = file_get_contents('php://input');

    if ($raw === false) {
        json_error('Unable to read request body', 400);
    }

    if (trim($raw) === '') {
        return $parsed = [];
    }

    $contentType = strtolower(trim(
        explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]
    ));

    if ($contentType !== 'application/json') {
        json_error('Content-Type must be application/json', 415);
    }

    try {
        $decoded = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    } catch (JsonException $e) {
        json_error('Invalid JSON body', 400);
    }

    if (!is_array($decoded)) {
        json_error('Request body must be a JSON object', 400);
    }

    return $parsed = $decoded;
}

function body(string $key, mixed $default = null): mixed {
    $data = request_body();

    return $data[$key] ?? $default;
}
