<?php
namespace App\Controllers\Admin;

use App\Core\DB;
use App\Models\Setting;
use App\Services\MediaService;

final class ContentController extends AdminController {
    protected string $minRole = 'admin';
    private const FIELDS = ['promo_text', 'offer_1_title', 'offer_1_text', 'offer_2_title', 'offer_2_text', 'offer_3_title', 'offer_3_text', 'safety_homepage', 'tagline',
        'contact_phone', 'contact_email', 'contact_address', 'whatsapp_number', 'business_hours', 'license_info', 'footer_text', 'social_facebook', 'social_instagram', 'social_youtube'];

    public function index(): void {
        $this->render('admin/content', ['title' => 'Website Content', 'pages' => DB::all('SELECT * FROM pages ORDER BY FIELD(slug,"about","privacy","terms","shipping","refund","safety")'), 'fields' => self::FIELDS]);
    }
    public function savePage(): void {
        $this->post(); $id = (int)($_POST['id'] ?? 0);
        $page = DB::one('SELECT * FROM pages WHERE id=?', [$id]); if (!$page) redirect('/admin/content');
        $title = trim((string)($_POST['title'] ?? '')); if ($title === '') { flash('error', 'Title is required.'); redirect('/admin/content#p' . $id); }
        $img = $page['image'];
        if (!empty($_FILES['image_file']['name'])) { $r = MediaService::upload($_FILES['image_file']); if ($r['ok']) $img = $r['media']['path']; else flash('error', $r['message']); }
        elseif (!empty($_POST['image_lib'])) $img = MediaService::validPath((string)$_POST['image_lib']) ?: $img;
        if (!empty($_POST['image_remove'])) $img = null;
        DB::update('pages', ['title' => mb_substr($title, 0, 190), 'content' => self::cleanHtml((string)($_POST['content'] ?? '')), 'image' => $img], 'id=?', [$id]);
        flash('success', $page['title'] . ' saved.'); redirect('/admin/content#p' . $id);
    }
    public function saveSettings(): void {
        $this->post(); $kv = [];
        foreach (self::FIELDS as $k) if (isset($_POST[$k]) && is_string($_POST[$k])) $kv[$k] = mb_substr(trim($_POST[$k]), 0, 1000);
        if (!empty($kv['contact_email']) && !filter_var($kv['contact_email'], FILTER_VALIDATE_EMAIL)) { flash('error', 'Contact email is invalid.'); redirect('/admin/content#settings'); }
        if (isset($kv['whatsapp_number'])) $kv['whatsapp_number'] = preg_replace('/\D+/', '', $kv['whatsapp_number']);
        foreach (['social_facebook', 'social_instagram', 'social_youtube'] as $s) if (!empty($kv[$s]) && !preg_match('#^https?://#', $kv[$s])) $kv[$s] = '';
        Setting::setMany($kv); flash('success', 'Website content saved.'); redirect('/admin/content#settings');
    }
    /** Allow only a safe subset of HTML for CMS pages (admins are trusted, but defence in depth). */
    public static function cleanHtml(string $html): string {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|form|link|meta)\b[^>]*/?>#is', '', $html);
        $html = strip_tags($html, '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><u><a><blockquote><table><thead><tbody><tr><th><td><img><hr><span>');
        $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*("|\')\s*(javascript|data|vbscript):[^"\']*\2/i', '$1=$2#$2', $html);
        return $html;
    }
}
