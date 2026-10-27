<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Services\MediaService;

/** Generic CRUD for categories, services and banners, driven by a config array. */
final class CrudController extends AdminController {
    protected string $minRole = 'admin';

    private function cfg(string $e): array {
        $c = [
            'categories' => ['table' => 'categories', 'singular' => 'Category', 'plural' => 'Categories', 'order' => 'display_order, name', 'slug' => 'name',
                'cols' => ['image' => 'Image', 'name' => 'Name', 'products' => 'Products', 'display_order' => 'Order', 'is_active' => 'Status'],
                'fields' => [
                    'name' => ['Name', 'text', 'req' => 1, 'max' => 120], 'description' => ['Description', 'textarea'], 'image' => ['Category image', 'image'],
                    'display_order' => ['Display order', 'number'], 'is_active' => ['Active', 'check']]],
            'services' => ['table' => 'services', 'singular' => 'Service', 'plural' => 'Services', 'order' => 'display_order, id',
                'cols' => ['image' => 'Image', 'title' => 'Title', 'availability' => 'Availability', 'display_order' => 'Order', 'is_active' => 'Status'],
                'fields' => [
                    'title' => ['Service title', 'text', 'req' => 1, 'max' => 160], 'description' => ['Description', 'textarea'], 'icon' => ['Icon name (Lucide, e.g. truck, gift, shield-check)', 'text', 'max' => 60],
                    'image' => ['Image (optional, shown instead of icon)', 'image'], 'availability' => ['Service availability', 'text', 'max' => 160],
                    'display_order' => ['Display order', 'number'], 'is_active' => ['Active', 'check']]],
            'banners' => ['table' => 'banners', 'singular' => 'Banner', 'plural' => 'Banners', 'order' => 'display_order, id',
                'cols' => ['image' => 'Image', 'title' => 'Title', 'schedule' => 'Schedule', 'display_order' => 'Order', 'is_active' => 'Status'],
                'fields' => [
                    'title' => ['Title', 'text', 'req' => 1, 'max' => 190], 'subtitle' => ['Subtitle', 'text', 'max' => 255], 'promo_text' => ['Promotional text', 'text', 'max' => 255],
                    'image' => ['Banner image (recommended 1600×600)', 'image'], 'cta_label' => ['Button label', 'text', 'max' => 60], 'cta_url' => ['Button URL (e.g. /products)', 'text', 'max' => 255],
                    'display_order' => ['Display order', 'number'], 'starts_at' => ['Start date/time (optional)', 'datetime'], 'ends_at' => ['End date/time (optional)', 'datetime'], 'is_active' => ['Active', 'check']]],
        ];
        return $c[$e];
    }
    public function __call(string $name, array $args) {
        [$action, $e] = explode('_', $name, 2);
        return $this->$action($e, ...$args);
    }
    private function index(string $e): void {
        $c = $this->cfg($e);
        $rows = DB::all("SELECT * FROM {$c['table']} ORDER BY {$c['order']}");
        if ($e === 'categories') foreach ($rows as &$r) $r['products'] = (int)DB::val('SELECT COUNT(*) FROM products WHERE category_id=?', [$r['id']]);
        $this->render('admin/crud/index', ['title' => $c['plural'], 'e' => $e, 'c' => $c, 'rows' => $rows]);
    }
    private function form(string $e, string $id = ''): void {
        $c = $this->cfg($e);
        $row = $id !== '' ? DB::one("SELECT * FROM {$c['table']} WHERE id=?", [(int)$id]) : null;
        if ($id !== '' && !$row) redirect("/admin/$e");
        $this->render('admin/crud/form', ['title' => ($row ? 'Edit ' : 'Add ') . strtolower($c['singular']), 'e' => $e, 'c' => $c, 'row' => $row ?? ['is_active' => 1, 'display_order' => 0, 'icon' => 'sparkles'], 'errors' => []]);
    }
    private function save(string $e): void {
        $this->post();
        $c = $this->cfg($e); $id = (int)($_POST['id'] ?? 0); $d = []; $err = [];
        foreach ($c['fields'] as $f => $def) {
            $type = $def[1]; $v = $_POST[$f] ?? '';
            if ($type === 'check') { $d[$f] = empty($_POST[$f]) ? 0 : 1; continue; }
            if ($type === 'number') { $d[$f] = (int)$v; continue; }
            if ($type === 'image') {
                $cur = $id ? (string)DB::val("SELECT $f FROM {$c['table']} WHERE id=?", [$id]) : '';
                $new = $cur !== '' ? $cur : null;
                if (!empty($_FILES[$f . '_file']['name'])) { $r = MediaService::upload($_FILES[$f . '_file']); if ($r['ok']) $new = $r['media']['path']; else $err[$f] = $r['message']; }
                elseif (!empty($_POST[$f . '_lib'])) { $p = MediaService::validPath((string)$_POST[$f . '_lib']); if ($p) $new = $p; }
                if (!empty($_POST[$f . '_remove'])) $new = null;
                $d[$f] = $new; continue;
            }
            $v = is_string($v) ? trim($v) : '';
            if ($type === 'datetime') { $d[$f] = $v !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null; continue; }
            if (!empty($def['req']) && $v === '') $err[$f] = $def[0] . ' is required.';
            if (isset($def['max']) && mb_strlen($v) > $def['max']) $err[$f] = 'Too long.';
            $d[$f] = $v === '' && $type !== 'text' ? null : $v;
            if ($f === 'cta_url' && $v !== '' && !preg_match('#^(/|https?://)#', $v)) $err[$f] = 'URL must start with / or https://';
        }
        if ($e === 'banners' && $d['starts_at'] && $d['ends_at'] && $d['ends_at'] < $d['starts_at']) $err['ends_at'] = 'End must be after start.';
        if ($err) { $this->render('admin/crud/form', ['title' => $c['singular'], 'e' => $e, 'c' => $c, 'row' => $d + ['id' => $id], 'errors' => $err]); return; }
        if (isset($c['slug'])) {
            $base = slugify($d[$c['slug']]); $slug = $base; $n = 1;
            while (DB::val("SELECT 1 FROM {$c['table']} WHERE slug=? AND id<>?", [$slug, $id])) $slug = $base . '-' . (++$n);
            $d['slug'] = $slug;
        }
        if ($id) DB::update($c['table'], $d, 'id=?', [$id]); else DB::insert($c['table'], $d);
        flash('success', $c['singular'] . ' saved.');
        redirect("/admin/$e");
    }
    private function delete(string $e, string $id): void {
        $this->post(); $c = $this->cfg($e);
        if ($e === 'categories' && (int)DB::val('SELECT COUNT(*) FROM products WHERE category_id=?', [(int)$id]) > 0) { flash('error', 'This category has products. Move or delete them first, or just deactivate the category.'); redirect('/admin/categories'); }
        DB::exec("DELETE FROM {$c['table']} WHERE id=?", [(int)$id]);
        flash('success', $c['singular'] . ' deleted.'); redirect("/admin/$e");
    }
}
