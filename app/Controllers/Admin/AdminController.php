<?php
namespace App\Controllers\Admin;

use App\Core\{Auth, Controller, View};

abstract class AdminController extends Controller {
    protected array $admin = [];
    protected string $minRole = 'staff';
    public function __construct() { $this->admin = Auth::require($this->minRole); }
    protected function render(string $tpl, array $data = []): void { View::render($tpl, $data + ['admin' => $this->admin], 'layouts/admin'); }
    protected function post(): void { if (!is_post()) redirect('/admin'); $this->requireCsrf(); }
    protected function back(string $fallback): never { redirect($fallback); }
    protected function ids(string $key): array { $v = $_POST[$key] ?? []; return is_array($v) ? array_values(array_filter(array_map('intval', $v))) : []; }
}
