<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('PUBLIC_PATH', ROOT . '/public');

spl_autoload_register(function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) return;
    $file = ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

require ROOT . '/app/helpers.php';
App\Core\Env::load(ROOT . '/.env');

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Kolkata'));
$debug = filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
error_reporting(E_ALL);
set_error_handler(function ($no, $str, $file, $line) {
    if (!(error_reporting() & $no)) return false;
    throw new ErrorException($str, 0, $no, $file, $line);
});
set_exception_handler(function (Throwable $e) use ($debug) {
    log_error($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) http_response_code(500);
    $wantsJson = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($wantsJson) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'message' => $debug ? $e->getMessage() : 'Server error. Please try again.']); return; }
    echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:sans-serif;padding:3rem;text-align:center"><h1>Something went wrong</h1><p>Please try again shortly.</p>';
    if ($debug) echo '<pre style="text-align:left">' . e((string)$e) . '</pre>';
});

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('FCSESS');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
ini_set('session.use_strict_mode', '1');
if (PHP_SAPI !== 'cli') session_start();
