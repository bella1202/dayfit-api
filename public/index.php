<?php
declare(strict_types=1);

// =========================
// 개발용 에러 표시
// =========================
error_reporting(E_ALL);
ini_set('display_errors', '1');

// warning / notice → 예외
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// 치명 에러 JSON 응답
set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => [
            'code' => 'INTERNAL',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// =========================
// Core 로드
// =========================
require_once __DIR__ . '/../src/Env.php';
require_once __DIR__ . '/../src/Json.php';
require_once __DIR__ . '/../src/Router.php';
require_once __DIR__ . '/../src/Db.php';
require_once __DIR__ . '/../src/AuthService.php';
require_once __DIR__ . '/../src/WeatherService.php';
require_once __DIR__ . '/../src/LocationService.php';

// =========================
// Env / Timezone
// =========================
Env::load(__DIR__ . '/../.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Seoul'));

// =========================
// CORS
// =========================
header('Access-Control-Allow-Origin: ' . (Env::get('CORS_ALLOW_ORIGIN', '*') ?? '*'));
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// =========================
// DB
// =========================
Db::init();

// =========================
// Router
// =========================
$router = new Router();

// ---- Health
$router->get('/v1/ping', function () {
    Json::ok(['pong' => true, 'ts' => date('c')]);
});

// ---- Auth
$router->post('/v1/auth/email/check', 'AuthService@checkEmail');
$router->post('/v1/auth/email/request', 'AuthService@requestEmailVerify');
$router->post('/v1/auth/email/verify', 'AuthService@verifyEmail');
$router->post('/v1/auth/signup', 'AuthService@signup');
$router->post('/v1/auth/login', 'AuthService@login');
$router->post('/v1/auth/apple', 'AuthService@appleLogin');

$router->get('/v1/weather/current', 'WeatherService@current');
$router->get('/v1/location/primary', 'LocationService@getPrimary');
$router->get('/v1/location/recents', 'LocationService@getRecents');

$router->put('/v1/location/primary', 'LocationService@putPrimary');

$router->delete('/v1/location/recents', 'LocationService@clearRecents');
$router->delete('/v1/location/item', 'LocationService@deleteItem'); // ?id=
$router->delete('/v1/location/primary', 'LocationService@deletePrimary');

// =========================
// Dispatch (중요)
// =========================
$router->dispatch();
