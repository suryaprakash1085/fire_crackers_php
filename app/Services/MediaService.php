<?php
namespace App\Services;

use App\Core\DB;

final class MediaService {
    private const MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_BYTES = 6 * 1024 * 1024;
    private const MAX_W = 1920;

    /** Handles a $_FILES entry. Returns ['ok'=>bool,'message'=>string,'media'=>?array] */
    public static function upload(array $f, string $source = 'upload'): array {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $m = [UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit.', UPLOAD_ERR_FORM_SIZE => 'File too large.', UPLOAD_ERR_NO_FILE => 'No file selected.'][$f['error'] ?? 4] ?? 'Upload failed.';
            return ['ok' => false, 'message' => $m];
        }
        if (!is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'message' => 'Invalid upload.'];
        $bytes = file_get_contents($f['tmp_name']);
        return self::fromBytes($bytes, (string)$f['name'], $source);
    }

    public static function fromBytes(string $bytes, string $origName, string $source = 'upload'): array {
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return ['ok' => false, 'message' => 'Image must be under 6 MB.'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!isset(self::MIME[$mime])) return ['ok' => false, 'message' => 'Only JPG, PNG or WebP images are allowed.'];
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 10000 || $info[1] > 10000) return ['ok' => false, 'message' => 'Invalid or oversized image.'];
        $ext = self::MIME[$mime];
        $out = $bytes;
        if (function_exists('imagecreatefromstring')) { // re-encode: strips metadata / embedded payloads and limits size
            $img = @imagecreatefromstring($bytes);
            if (!$img) return ['ok' => false, 'message' => 'Image could not be processed.'];
            if (imagesx($img) > self::MAX_W) { $r = imagescale($img, self::MAX_W); if ($r) { imagedestroy($img); $img = $r; } }
            imagealphablending($img, false); imagesavealpha($img, true);
            ob_start();
            if ($ext === 'png') imagepng($img, null, 6); elseif ($ext === 'webp' && function_exists('imagewebp')) imagewebp($img, null, 85); else { $ext = $ext === 'webp' ? 'png' : 'jpg'; $ext === 'png' ? imagepng($img, null, 6) : imagejpeg($img, null, 88); }
            $out = (string)ob_get_clean(); imagedestroy($img);
        }
        $dir = 'uploads/' . date('Y/m');
        if (!is_dir(PUBLIC_PATH . '/' . $dir) && !@mkdir(PUBLIC_PATH . '/' . $dir, 0775, true)) return ['ok' => false, 'message' => 'Upload folder is not writable.'];
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        if (file_put_contents(PUBLIC_PATH . '/' . $dir . '/' . $name, $out) === false) return ['ok' => false, 'message' => 'Could not save the file.'];
        $path = $dir . '/' . $name;
        $label = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '', pathinfo($origName, PATHINFO_FILENAME)) ?: 'image', 0, 60) . '.' . $ext;
        $id = DB::insert('media_library', ['filename' => $label, 'path' => $path, 'mime' => 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext), 'size_bytes' => strlen($out), 'source' => $source]);
        return ['ok' => true, 'message' => 'Uploaded.', 'media' => ['id' => $id, 'path' => $path, 'filename' => $label]];
    }

    private const VIDEO_MIME = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
    private const VIDEO_MAX = 50 * 1024 * 1024;

    /** Handles a product video upload (MP4/WebM, max 50 MB). Returns ['ok'=>bool,'message'=>string,'path'=>?string] */
    public static function uploadVideo(array $f): array {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $m = [UPLOAD_ERR_INI_SIZE => 'Video exceeds the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'Video is too large.'][$f['error'] ?? 4] ?? 'Video upload failed.';
            return ['ok' => false, 'message' => $m];
        }
        if (!is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'message' => 'Invalid upload.'];
        return self::fromVideoBytes((string)file_get_contents($f['tmp_name']), (string)$f['name']);
    }
    /** Stores a previously staged product video after validating its content. */
    public static function fromVideoBytes(string $bytes, string $origName): array {
        if ($bytes === '' || strlen($bytes) > self::VIDEO_MAX) return ['ok' => false, 'message' => 'Video must be under 50 MB.'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!isset(self::VIDEO_MIME[$mime])) return ['ok' => false, 'message' => 'Only MP4 or WebM videos are allowed.'];
        $dir = 'uploads/videos/' . date('Y/m');
        if (!is_dir(PUBLIC_PATH . '/' . $dir) && !@mkdir(PUBLIC_PATH . '/' . $dir, 0775, true)) return ['ok' => false, 'message' => 'Upload folder is not writable.'];
        $path = $dir . '/' . bin2hex(random_bytes(12)) . '.' . self::VIDEO_MIME[$mime];
        if (file_put_contents(PUBLIC_PATH . '/' . $path, $bytes, LOCK_EX) === false) return ['ok' => false, 'message' => 'Could not save the video.'];
        return ['ok' => true, 'message' => 'Uploaded.', 'path' => $path];
    }
    public static function deleteVideo(?string $path): void {
        if (!$path || !str_starts_with($path, 'uploads/videos/')) return;
        $f = PUBLIC_PATH . '/' . $path;
        if (str_starts_with(realpath(dirname($f)) ?: '', realpath(PUBLIC_PATH . '/uploads/videos') ?: '#') && is_file($f)) @unlink($f);
    }

    public static function usage(string $path): int {
        return (int)DB::val('SELECT (SELECT COUNT(*) FROM banners WHERE image=?) + (SELECT COUNT(*) FROM categories WHERE image=?) + (SELECT COUNT(*) FROM services WHERE image=?)
            + (SELECT COUNT(*) FROM product_images WHERE path=?) + (SELECT COUNT(*) FROM pages WHERE image=?) + (SELECT COUNT(*) FROM site_settings WHERE setting_key IN ("logo","favicon") AND setting_value=?)',
            [$path, $path, $path, $path, $path, $path]);
    }
    public static function delete(int $id): array {
        $m = DB::one('SELECT * FROM media_library WHERE id=?', [$id]);
        if (!$m) return ['ok' => false, 'message' => 'Not found.'];
        if (self::usage($m['path']) > 0) return ['ok' => false, 'message' => 'Image is in use (banner, product, category, service, page or logo). Remove it there first.'];
        $f = PUBLIC_PATH . '/' . $m['path'];
        if (str_starts_with(realpath(dirname($f)) ?: '', realpath(PUBLIC_PATH . '/uploads') ?: '#') && is_file($f)) @unlink($f);
        DB::exec('DELETE FROM media_library WHERE id=?', [$id]);
        return ['ok' => true, 'message' => 'Image deleted.'];
    }
    /** Resolve an image field value: only accept paths that exist in our media library. */
    public static function validPath(?string $p): ?string {
        $p = trim((string)$p); if ($p === '') return null;
        return DB::val('SELECT path FROM media_library WHERE path=?', [$p]) ?: null;
    }
}
