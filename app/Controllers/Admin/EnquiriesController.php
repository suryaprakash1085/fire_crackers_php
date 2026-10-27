<?php
namespace App\Controllers\Admin;

use App\Core\DB;

final class EnquiriesController extends AdminController {
    public function index(): void {
        $st = $this->input('status'); $w = '1=1'; $p = [];
        if (in_array($st, ['new', 'in_progress', 'resolved'], true)) { $w = 'status=?'; $p[] = $st; }
        $per = 15; $page = $this->page(); $total = (int)DB::val("SELECT COUNT(*) FROM contact_enquiries WHERE $w", $p);
        $rows = DB::all("SELECT * FROM contact_enquiries WHERE $w ORDER BY id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);
        $this->render('admin/enquiries', ['title' => 'Enquiries', 'rows' => $rows, 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'st' => $st]);
    }
    public function update(string $id): void {
        $this->post(); $st = (string)($_POST['status'] ?? 'new');
        if (!in_array($st, ['new', 'in_progress', 'resolved'], true)) $st = 'new';
        DB::update('contact_enquiries', ['status' => $st, 'internal_notes' => trim((string)($_POST['internal_notes'] ?? '')) ?: null], 'id=?', [(int)$id]);
        flash('success', 'Enquiry updated.'); redirect('/admin/enquiries' . ($this->input('back') ? '?status=' . urlencode($this->input('back')) : ''));
    }
}
