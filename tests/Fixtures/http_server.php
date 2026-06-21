<?php

/**
 * Echo / test-fixture router for the PHP built-in dev server used by
 * HttpClientTest. Every endpoint returns JSON describing what it received,
 * so the test can introspect what HttpClient actually sent.
 *
 * Boot with:  php -S 127.0.0.1:PORT tests/Fixtures/http_server.php
 */

declare(strict_types=1);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI']    ?? '/';
$path   = parse_url($uri, PHP_URL_PATH) ?: '/';
$query  = $_SERVER['QUERY_STRING']   ?? '';

$headers = function_exists('getallheaders') ? getallheaders() : [];
$body    = file_get_contents('php://input') ?: '';

header('Content-Type: application/json');

switch ($path) {
    case '/status/201':
        http_response_code(201);
        echo json_encode(['created' => true]);
        return;

    case '/status/404':
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        return;

    case '/status/500':
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
        return;

    case '/redirect/once':
        http_response_code(302);
        header('Location: /echo');
        return;

    case '/cookies/many':
        // Multiple Set-Cookie headers. `header(..., false)` appends instead of replacing.
        header('Set-Cookie: a=1; Path=/', false);
        header('Set-Cookie: b=2; Path=/', false);
        header('Set-Cookie: c=3; Path=/', false);
        echo json_encode(['ok' => true]);
        return;

    case '/case-headers':
        header('X-Mixed-Case: hello');
        echo json_encode(['ok' => true]);
        return;

    case '/multipart/inspect':
        // Reveal what arrived as $_POST / $_FILES so the test can verify
        // that file uploads were parsed as actual files by PHP.
        $files = [];
        foreach ($_FILES as $name => $f) {
            $files[$name] = [
                'name'  => $f['name'],
                'size'  => $f['size'],
                'error' => $f['error'],
                'type'  => $f['type'] ?? '',
            ];
        }
        echo json_encode([
            'post'        => $_POST,
            'files'       => $files,
            'raw_preview' => substr($body, 0, 200),
        ]);
        return;
}

// Default echo endpoint — returns everything we observed about the request.
echo json_encode([
    'method'  => $method,
    'path'    => $path,
    'query'   => $query,
    'headers' => $headers,
    'body'    => $body,
]);