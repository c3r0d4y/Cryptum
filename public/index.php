<?php
/*
 * Archivo: public/index.php
 * Autor:   C3r0d4y
 *
 * Front Controller de Cryptum.
 * Aplica cabeceras de seguridad HTTP, carga la app y despacha
 * cada petición al controlador correcto.
 *
 * Rutas:
 *   GET  /              → HomeController::index  (interfaz principal)
 *   GET  /?d=<token>    → HomeController::index  (abre flujo de descifrado)
 *   POST /api/upload    → VaultController::upload
 *   GET  /api/download  → VaultController::download
 *   GET  /api/status    → VaultController::status
 *   GET  /api/list-usb  → VaultController::listUsb
 */

declare(strict_types=1);

/* ── Cabeceras de seguridad HTTP ────────────────────────────────────────── */
// Nota: X-XSS-Protection se eliminó — es una cabecera obsoleta que los
// navegadores modernos ignoran; la protección real la da la CSP de abajo.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
// form-action 'none' y base-uri 'none' cierran vectores de exfiltración
// por formularios inyectados o etiquetas <base> maliciosas.
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; frame-src 'none'; img-src 'self' data:; form-action 'none'; base-uri 'none';");
header('Cache-Control: no-store, no-cache');

// HSTS solo cuando la conexión ya es HTTPS (enviarla por HTTP no tiene efecto)
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}

/* ── Carga base ─────────────────────────────────────────────────────────── */
define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/config.php';
require_once APP_ROOT . '/app/core/Controller.php';
require_once APP_ROOT . '/app/models/Vault.php';
require_once APP_ROOT . '/app/models/Descargas.php';

/* ── Autocarga de controladores ─────────────────────────────────────────── */
spl_autoload_register(static function (string $class): void {
    $path = APP_ROOT . "/app/controllers/{$class}.php";
    if (is_file($path)) {
        require_once $path;
    }
});

/* ── Lectura y normalización de la ruta ─────────────────────────────────── */
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$base   = rtrim(BASE_URL, '/');
$route  = $base !== '' && str_starts_with($uri, $base)
    ? trim(substr($uri, strlen($base)), '/')
    : trim($uri, '/');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ── Tabla de rutas ─────────────────────────────────────────────────────── */
$routes = [
    'GET' => [
        ''              => [HomeController::class,  'index'],
        'api/status'    => [VaultController::class, 'status'],
        'api/download'  => [VaultController::class, 'download'],
        'api/list-usb'  => [VaultController::class, 'listUsb'],
    ],
    'POST' => [
        'api/upload'    => [VaultController::class, 'upload'],
    ],
];

$handler = $routes[$method][$route] ?? null;

if ($handler === null) {
    http_response_code(404);
    exit('404 — Ruta no encontrada.');
}

[$controllerClass, $action] = $handler;
(new $controllerClass())->$action();
