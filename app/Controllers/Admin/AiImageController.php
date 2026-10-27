<?php
namespace App\Controllers\Admin;

use App\Core\{DB, RateLimiter};
use App\Services\{AiImageService, MediaService};

final class AiImageController extends AdminController {
    protected string $minRole = 'admin';
    private function dir(): string { $d = ROOT . '/storage/ai-drafts'; if (!is_dir($d)) @mkdir($d, 0775, true); return $d; }

    public function index(): void {
        $this->render('admin/ai-image', ['title' => 'AI Image Generator', 'configured' => AiImageService::configured(), 'model' => AiImageService::model(), 'styles' => array_keys(AiImageService::styles()),
            'aspects' => AiImageService::aspects(), 'qualities' => AiImageService::qualities(), 'history' => DB::all('SELECT * FROM ai_image_history ORDER BY id DESC LIMIT 24')]);
    }
    public function generate(): void {
        $this->requireCsrf(true);
        $prompt = mb_substr(trim((string)($_POST['prompt'] ?? '')), 0, 1500);
        $style = (string)($_POST['style'] ?? ''); $aspect = (string)($_POST['aspect'] ?? 'landscape'); $quality = (string)($_POST['quality'] ?? '');
        if (mb_strlen($prompt) < 5) $this->json(['ok' => false, 'message' => 'Please describe the image (at least 5 characters).'], 422);
        if (RateLimiter::tooMany('ai_gen', (string)$this->admin['id'], 20, 3600)) $this->json(['ok' => false, 'message' => 'Hourly generation limit reached.'], 429);
        RateLimiter::hit('ai_gen', (string)$this->admin['id']);
        set_time_limit(180);
        $r = AiImageService::generate($prompt, $style, $aspect, $quality);
        $base = ['admin_id' => $this->admin['id'], 'prompt' => $prompt, 'style' => $style ?: null, 'aspect' => $aspect, 'quality' => $quality ?: null, 'provider' => 'openai', 'model' => AiImageService::model()];
        if (!$r['ok']) { DB::insert('ai_image_history', $base + ['status' => 'failed', 'error_message' => mb_substr($r['error'], 0, 500)]); $this->json(['ok' => false, 'message' => $r['error']], 502); }
        // validate that the bytes really are an image before keeping them
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($r['bytes']);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) { DB::insert('ai_image_history', $base + ['status' => 'failed', 'error_message' => 'Invalid image data returned']); $this->json(['ok' => false, 'message' => 'The API returned invalid image data.'], 502); }
        $id = DB::insert('ai_image_history', $base + ['status' => 'success']);
        $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'][$mime];
        $file = "storage/ai-drafts/$id.$ext"; file_put_contents(ROOT . '/' . $file, $r['bytes']);
        DB::update('ai_image_history', ['file_path' => $file], 'id=?', [$id]);
        $this->json(['ok' => true, 'id' => $id, 'url' => url("/admin/ai-image/$id/file"), 'message' => 'Image generated. It is a private draft until you save it to the Media Library.']);
    }
    public function file(string $id): void {
        $h = DB::one("SELECT file_path FROM ai_image_history WHERE id=? AND status='success'", [(int)$id]);
        $f = $h ? ROOT . '/' . $h['file_path'] : '';
        if (!$h || !is_file($f)) { http_response_code(404); exit('Not found'); }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($f)); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600');
        if (isset($_GET['download'])) header('Content-Disposition: attachment; filename="ai-image-' . (int)$id . '.' . pathinfo($f, PATHINFO_EXTENSION) . '"');
        readfile($f); exit;
    }
    public function save(string $id): void {
        $this->requireCsrf(true);
        $h = DB::one("SELECT * FROM ai_image_history WHERE id=? AND status='success'", [(int)$id]);
        if (!$h || !is_file(ROOT . '/' . $h['file_path'])) $this->json(['ok' => false, 'message' => 'Draft not found.'], 404);
        if ($h['media_id']) $this->json(['ok' => true, 'message' => 'Already saved to the Media Library.', 'media_id' => (int)$h['media_id']]);
        $r = MediaService::fromBytes((string)file_get_contents(ROOT . '/' . $h['file_path']), 'ai-image-' . $h['id'], 'ai');
        if (!$r['ok']) $this->json(['ok' => false, 'message' => $r['message']], 422);
        DB::update('ai_image_history', ['media_id' => $r['media']['id']], 'id=?', [$h['id']]);
        $this->json(['ok' => true, 'message' => 'Saved to the Media Library. Use it from Banners or Products — it is not live anywhere until you choose it there.', 'media_id' => $r['media']['id']]);
    }
}
