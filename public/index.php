<?php
if (PHP_VERSION_ID < 80100) { http_response_code(500); exit('PHP 8.1 or newer is required. Current: ' . PHP_VERSION); }
// Built-in server support: let PHP serve existing static files
if (PHP_SAPI === 'cli-server') {
    $f = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($f) && !str_ends_with($f, '.php')) return false;
}
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli-server' && filter_var(env('FORCE_HTTPS', 'false'), FILTER_VALIDATE_BOOLEAN) && empty($_SERVER['HTTPS']) && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== 'https')) { header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301); exit; }
$router = new App\Core\Router();
require ROOT . '/app/routes.php';
$router->dispatch();
