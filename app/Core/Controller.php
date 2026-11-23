<?php
namespace App\Core;

abstract class Controller {
    protected function view(string $tpl, array $data = [], string $layout = 'layouts/front'): void { View::render($tpl, $data, $layout); }
    protected function json(array $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, max-age=0');   // cart/stock answers must never come from a browser or proxy cache
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    protected function requireCsrf(bool $json = false): void {
        if (csrf_valid()) return;
        if ($json) $this->json(['ok' => false, 'message' => 'Session expired. Please refresh the page.'], 419);
        flash('error', 'Your session expired. Please try again.');
        redirect($_SERVER['HTTP_REFERER'] ?? '/');
    }
    protected function input(string $k, string $d = ''): string { $v = $_POST[$k] ?? $_GET[$k] ?? $d; return is_string($v) ? trim($v) : $d; }
    protected function page(): int { return max(1, (int)($_GET['page'] ?? 1)); }
}
