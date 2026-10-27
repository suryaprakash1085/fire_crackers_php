<?php
namespace App\Core;

final class Router {
    private array $routes = [];
    public function get(string $p, array $h): void { $this->add('GET', $p, $h); }
    public function post(string $p, array $h): void { $this->add('POST', $p, $h); }
    public function any(string $p, array $h): void { $this->add('GET', $p, $h); $this->add('POST', $p, $h); }
    private function add(string $m, string $p, array $h): void {
        $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $p) . '$#';
        $this->routes[$m][] = [$re, $h];
    }
    public function dispatch(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) $path = substr($path, strlen($base));
        $path = '/' . trim($path, '/');
        foreach ($this->routes[$method] ?? [] as [$re, $h]) {
            if (preg_match($re, $path, $m)) {
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                [$class, $action] = $h;
                (new $class())->$action(...array_values($params));
                return;
            }
        }
        http_response_code(404);
        View::render('front/404', ['title' => 'Page not found'], 'layouts/front');
    }
}
