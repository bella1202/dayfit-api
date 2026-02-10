<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../data/php_error.log');

set_exception_handler(function (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => 'INTERNAL',
            'message' => $e->getMessage(),
            'extra' => ['file' => $e->getFile(), 'line' => $e->getLine()],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (in_array($e['type'], $fatalTypes, true)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => [
                'code' => 'FATAL',
                'message' => $e['message'],
                'extra' => ['file' => $e['file'], 'line' => $e['line']],
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

// static file이면 그대로
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) {
    return false;
}

// 나머지는 index.php로
require __DIR__ . '/index.php';
