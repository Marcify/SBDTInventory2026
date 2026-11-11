<?php
declare(strict_types=1);

/**
 * Minimal regex router. Routes registered as:
 *   route('GET', '/items/{id}', fn($id) => ...);
 * Handlers return arrays (sent as JSON) — or call json_*() themselves.
 */
function route(string $method, string $pattern, callable $handler): void {
    static $routes = null;
    if ($routes === null) $routes = [];
    // Store in a global for dispatch
    $GLOBALS['__routes'][] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
}

function dispatch(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // Strip /api/v1 prefix
    $path   = preg_replace('#^/SBDTInventory/api/v1#', '', $path) ?: '/';
    $path   = rtrim($path, '/') ?: '/';

    $allowedMethods = [];
    foreach ($GLOBALS['__routes'] ?? [] as $r) {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $r['pattern']) . '$#';
        if (!preg_match($regex, $path, $m)) continue;

        if ($r['method'] !== $method) {
            $allowedMethods[] = $r['method'];
            continue;
        }
        $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        try {
            $result = ($r['handler'])(...array_values($params));
            if (is_array($result)) {
                json_success($result);
            }
        } catch (Throwable $e) {
            throw $e;
        }
        return;
    }

    if ($allowedMethods) {
        header('Allow: ' . implode(', ', array_unique($allowedMethods)));
        json_error('Method not allowed', 405);
    }
    json_error('Not found', 404);
}