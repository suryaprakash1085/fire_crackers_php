<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Services\MediaService;

final class MediaController extends AdminController {
    protected string $minRole = 'admin';
    public function index(): void {
        $per = 24; $page = $this->page(); $total = (int)DB::val('SELECT COUNT(*) FROM media_library');
        $rows = DB::all("SELECT * FROM media_library ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per));
        foreach ($rows as &$r) $r['used'] = MediaService::usage($r['path']);
        $this->render('admin/media', ['title' => 'Media Library', 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per))]);
    }
    public function listJson(): void {
        $rows = DB::all('SELECT id,filename,path FROM media_library ORDER BY id DESC LIMIT 120');
        $this->json(['ok' => true, 'items' => array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['filename'], 'path' => $r['path'], 'url' => upload_url($r['path'])], $rows)]);
    }
    public function upload(): void {
        $this->post(); $ok = 0;
        $f = $_FILES['files'] ?? null;
        if ($f && !empty($f['name'][0])) foreach ($f['name'] as $k => $n) {
            $r = MediaService::upload(['name' => $n, 'tmp_name' => $f['tmp_name'][$k], 'error' => $f['error'][$k]]);
            if ($r['ok']) $ok++; else flash('error', $n . ': ' . $r['message']);
        } else flash('error', 'Choose at least one image.');
        if ($ok) flash('success', "$ok image(s) uploaded.");
        redirect('/admin/media');
    }
    public function delete(string $id): void { $this->post(); $r = MediaService::delete((int)$id); flash($r['ok'] ? 'success' : 'error', $r['message']); redirect('/admin/media'); }
}
