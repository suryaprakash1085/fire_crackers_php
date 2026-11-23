<?php
namespace App\Services;

use App\Core\DB;

final class MediaService {
    /** Real file types we accept (decided from the file CONTENT, never from the extension or the browser MIME). */
    private const EXT = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
    private const LABEL = ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP', 'image/gif' => 'GIF', 'image/avif' => 'AVIF'];
    public const MAX_BYTES = 5 * 1024 * 1024;        // 5 MB per image
    private const MAX_W = 1920;                      // stored images are scaled down to this width
    private const THUMB_W = 400;                     // thumbnail width (file name gets "_thumb" before the extension)
    private const MAX_SIDE = 10000;                  // refuse absurd dimensions (decompression-bomb guard)
    private const MAX_PIXELS = 50000000;

    /** mime => extension that this server accepts. AVIF only when this PHP can read it (PHP 8.1+). */
    public static function allowedTypes(): array { $t = self::EXT; if (!defined('IMAGETYPE_AVIF')) unset($t['image/avif']); return $t; }
    /** "JPG, PNG, WebP, GIF or AVIF" – used in every "wrong file type" message and next to the upload box. */
    public static function allowedLabel(): string {
        $l = array_values(array_map(fn($m) => self::LABEL[$m], array_keys(self::allowedTypes())));
        return count($l) > 1 ? implode(', ', array_slice($l, 0, -1)) . ' or ' . end($l) : (string)($l[0] ?? '');
    }
    /** Value for <input type="file" accept="…">: MIME types plus extensions (Windows file dialogs filter by extension). */
    public static function acceptAttr(): string {
        $mimes = array_keys(self::allowedTypes()); $exts = array_map(fn($e) => '.' . $e, array_values(self::allowedTypes())); $exts[] = '.jpeg';
        return implode(',', array_merge($mimes, $exts));
    }
    public static function humanSize(int $b): string { return $b >= 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }
    /** php.ini size ("64M", "2G", "512K", "-1") -> bytes. 0 or -1 = no limit. */
    public static function iniBytes(string $v): int {
        $v = trim($v); if ($v === '') return 0; $n = (float)$v; $u = strtolower(substr($v, -1));
        return (int)($u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n)));
    }
    /** What this server really accepts: ['file' => upload_max_filesize, 'post' => post_max_size] in bytes (PHP_INT_MAX = unlimited). */
    public static function limits(): array {
        $f = self::iniBytes((string)ini_get('upload_max_filesize')); $p = self::iniBytes((string)ini_get('post_max_size'));
        return ['file' => $f > 0 ? $f : PHP_INT_MAX, 'post' => $p > 0 ? $p : PHP_INT_MAX];
    }
    /** Readable text for a PHP UPLOAD_ERR_* code. */
    public static function uploadErrorMessage(int $code, string $kind = 'Image'): string {
        $fl = self::limits()['file']; $lim = $fl === PHP_INT_MAX ? 'unlimited' : self::humanSize($fl);
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => $kind . ' is larger than this server allows (upload_max_filesize is ' . $lim . '). Choose a smaller file' . ($kind === 'Image' ? ' (images: max ' . self::humanSize(self::MAX_BYTES) . ').' : '.'),
            UPLOAD_ERR_FORM_SIZE => $kind . ' is larger than the size allowed by the form.',
            UPLOAD_ERR_PARTIAL => $kind . ' was only partly uploaded (the connection was interrupted). Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads (upload_tmp_dir is missing). Ask your hosting provider to fix this.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file to disk (disk full or folder permissions).',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload.',
            default => $kind . ' upload failed (error code ' . $code . ').',
        };
    }

    /** Handles a $_FILES entry. Returns ['ok'=>bool,'message'=>string,'media'=>?array] */
    public static function upload(array $f, string $source = 'upload'): array {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return ['ok' => false, 'message' => self::uploadErrorMessage((int)($f['error'] ?? UPLOAD_ERR_NO_FILE))];
        if (!is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'message' => 'Invalid upload.'];
        $bytes = file_get_contents($f['tmp_name']);
        return self::fromBytes((string)$bytes, (string)$f['name'], $source);
    }

    /** Looks at the real file content. Returns ['ok'=>true,'mime','ext','w','h'] or ['ok'=>false,'message']. Nothing is written. */
    public static function inspect(string $bytes): array {
        $no = fn(string $m) => ['ok' => false, 'message' => $m];
        $allowed = 'Only ' . self::allowedLabel() . ' images are allowed.';
        if ($bytes === '') return $no('The file is empty.');
        if (strlen($bytes) > self::MAX_BYTES) return $no('Image is ' . self::humanSize(strlen($bytes)) . ' – the limit is ' . self::humanSize(self::MAX_BYTES) . ' per image.');
        $mime = self::sniff($bytes);                                   // magic bytes: a .php file renamed to .png ends here
        if ($mime === null || !isset(self::allowedTypes()[$mime])) return $no('This file is not an allowed image type. ' . $allowed);
        if (class_exists('finfo')) {                                   // second opinion; "application/octet-stream" (odd XAMPP builds) is ignored, a different IMAGE type is not
            $fi = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (is_string($fi) && isset(self::EXT[$fi]) && $fi !== $mime) return $no('The file content does not match an allowed image type. ' . $allowed);
        }
        $info = @getimagesizefromstring($bytes);
        $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_GIF => 'image/gif', IMAGETYPE_WEBP => 'image/webp'];
        if (defined('IMAGETYPE_AVIF')) $types[IMAGETYPE_AVIF] = 'image/avif';
        if (!$info || ($types[$info[2]] ?? null) !== $mime) return $no('This file is not a valid image (it may be damaged). ' . $allowed);
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w > self::MAX_SIDE || $h > self::MAX_SIDE || $w * $h > self::MAX_PIXELS) return $no('Image dimensions are too large (max ' . self::MAX_SIDE . ' × ' . self::MAX_SIDE . ' px).');
        return ['ok' => true, 'mime' => $mime, 'ext' => self::EXT[$mime], 'w' => (int)$w, 'h' => (int)$h, 'message' => ''];
    }
    private static function sniff(string $b): ?string {
        if (strncmp($b, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
        if (strncmp($b, "\x89PNG\r\n\x1A\n", 8) === 0) return 'image/png';
        if (strncmp($b, 'GIF87a', 6) === 0 || strncmp($b, 'GIF89a', 6) === 0) return 'image/gif';
        if (strlen($b) > 12 && substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP') return 'image/webp';
        if (strlen($b) > 16 && substr($b, 4, 4) === 'ftyp') {          // ISO-BMFF: AVIF lists the brand "avif"/"avis" in its ftyp box
            $size = (int)(unpack('N', substr($b, 0, 4))[1] ?? 0); $size = max(16, min($size, 256, strlen($b)));
            if (preg_match('/avif|avis/', substr($b, 8, $size - 8))) return 'image/avif';
        }
        return null;
    }
    /** Can GD both read and write this format here? (Otherwise the original is stored as it is, after validation.) */
    private static function gdCan(string $ext): bool {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagetypes')) return false;
        $t = imagetypes();
        return match ($ext) {
            'jpg' => (bool)($t & IMG_JPG) && function_exists('imagejpeg'),
            'png' => (bool)($t & IMG_PNG) && function_exists('imagepng'),
            'gif' => (bool)($t & IMG_GIF) && function_exists('imagegif'),
            'webp' => defined('IMG_WEBP') && ($t & IMG_WEBP) && function_exists('imagewebp'),
            'avif' => defined('IMG_AVIF') && ($t & IMG_AVIF) && function_exists('imageavif'),
            default => false,
        };
    }
    private static function enoughMemory(int $w, int $h): bool {
        $limit = self::iniBytes((string)ini_get('memory_limit')); if ($limit <= 0) return true;
        return $w * $h * 10 + 16 * 1048576 < $limit - memory_get_usage(true);
    }
    /** Rotates a JPEG upright from its EXIF orientation (re-encoding drops EXIF, so phone photos would otherwise lie on their side). */
    private static function orient($img, string $bytes) {
        if (!function_exists('exif_read_data') || !function_exists('imagerotate')) return $img;
        $tmp = @tempnam(sys_get_temp_dir(), 'exf'); if (!$tmp) return $img;
        try { @file_put_contents($tmp, $bytes); $ex = @exif_read_data($tmp); } catch (\Throwable $e) { $ex = null; }
        @unlink($tmp);
        $deg = [3 => 180, 6 => -90, 8 => 90][(int)($ex['Orientation'] ?? 1)] ?? 0;
        if ($deg === 0) return $img;
        $r = @imagerotate($img, $deg, 0); return $r ?: $img;
    }
    private static function fit($img, int $maxW) {
        if (imagesx($img) <= $maxW) return $img;
        $r = @imagescale($img, $maxW, -1, IMG_BICUBIC); if (!$r) return $img;
        imagealphablending($r, false); imagesavealpha($r, true); return $r;
    }
    private static function encode($img, string $ext): string {
        if ($ext !== 'jpg') { imagealphablending($img, false); imagesavealpha($img, true); }
        ob_start();
        try {
            match ($ext) { 'jpg' => imagejpeg($img, null, 88), 'png' => imagepng($img, null, 6), 'webp' => imagewebp($img, null, 85), 'gif' => imagegif($img), 'avif' => imageavif($img, null, 60), default => null };
        } catch (\Throwable $e) { ob_end_clean(); return ''; }
        return (string)ob_get_clean();
    }
    /** Re-encodes through GD (drops metadata and anything hidden in the file), scales to MAX_W and makes a thumbnail. null = GD could not process it. */
    private static function reencode(string $bytes, string $ext): ?array {
        $img = @imagecreatefromstring($bytes); if (!$img) return null;          // (GD frees the image itself – imagedestroy() is deprecated in PHP 8.5)
        if ($ext === 'jpg') $img = self::orient($img, $bytes);
        if (!imageistruecolor($img)) imagepalettetotruecolor($img);               // GIF / 8-bit PNG: scale and keep transparency in true colour
        $full = self::fit($img, self::MAX_W); $main = self::encode($full, $ext); if ($main === '') return null;
        $thumb = imagesx($full) > self::THUMB_W ? self::encode(self::fit($full, self::THUMB_W), $ext) : '';
        return ['main' => $main, 'thumb' => $thumb !== '' ? $thumb : null];
    }

    public static function fromBytes(string $bytes, string $origName, string $source = 'upload'): array {
        $chk = self::inspect($bytes); if (!$chk['ok']) return ['ok' => false, 'message' => $chk['message']];
        $ext = $chk['ext']; $out = $bytes; $thumb = null;
        if (self::gdCan($ext) && self::enoughMemory($chk['w'], $chk['h'])) {
            try { $r = self::reencode($bytes, $ext); } catch (\Throwable $e) { log_error('image re-encode: ' . $e->getMessage()); $r = null; }
            if (!$r) return ['ok' => false, 'message' => 'The image could not be processed. Save it again as PNG or JPG and retry.'];
            $out = $r['main']; $thumb = $r['thumb'];
        } elseif (preg_match('/<\?(php|=)/i', $bytes)) {                          // no GD (or too big for memory): the original is stored, so refuse anything that carries code
            return ['ok' => false, 'message' => 'This image contains unsafe content and was rejected.'];
        }
        $dir = 'uploads/' . date('Y/m');
        if (!is_dir(PUBLIC_PATH . '/' . $dir) && !@mkdir(PUBLIC_PATH . '/' . $dir, 0775, true)) return ['ok' => false, 'message' => 'Upload folder is not writable.'];
        $base = bin2hex(random_bytes(12));                                       // random name; the extension comes from the real content type
        $name = $base . '.' . $ext;
        if (file_put_contents(PUBLIC_PATH . '/' . $dir . '/' . $name, $out) === false) return ['ok' => false, 'message' => 'Could not save the file.'];
        $path = $dir . '/' . $name; $thumbPath = null;
        if ($thumb !== null && @file_put_contents(PUBLIC_PATH . '/' . $dir . '/' . $base . '_thumb.' . $ext, $thumb) !== false) $thumbPath = $dir . '/' . $base . '_thumb.' . $ext;
        $label = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '', pathinfo($origName, PATHINFO_FILENAME)) ?: 'image', 0, 60) . '.' . $ext;
        $mime = array_search($ext, self::EXT, true) ?: 'image/jpeg';
        $id = DB::insert('media_library', ['filename' => $label, 'path' => $path, 'mime' => $mime, 'size_bytes' => strlen($out), 'source' => $source]);
        return ['ok' => true, 'message' => 'Uploaded.', 'media' => ['id' => $id, 'path' => $path, 'thumb' => $thumbPath, 'filename' => $label]];
    }

    private const VIDEO_MIME = [
        'video/mp4' => 'mp4', 'video/x-m4v' => 'mp4', 'video/quicktime' => 'mp4',
        'video/3gpp' => 'mp4', 'video/3gpp2' => 'mp4', 'video/webm' => 'webm',
    ];
    private const VIDEO_MAX = 50 * 1024 * 1024;

    /** Handles a product video upload (MP4/WebM, max 50 MB). Returns ['ok'=>bool,'message'=>string,'path'=>?string] */
    public static function uploadVideo(array $f): array {
        if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => self::uploadErrorMessage((int)($f['error'] ?? UPLOAD_ERR_NO_FILE), 'Video')];
        }
        if (!is_uploaded_file($f['tmp_name'])) return ['ok' => false, 'message' => 'Invalid upload.'];
        return self::fromVideoBytes((string)file_get_contents($f['tmp_name']), (string)$f['name']);
    }
    /** Stores a previously staged product video after validating its content. */
    public static function fromVideoBytes(string $bytes, string $origName): array {
        if ($bytes === '' || strlen($bytes) > self::VIDEO_MAX) return ['ok' => false, 'message' => 'Video must be under 50 MB.'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!isset(self::VIDEO_MIME[$mime])) {
            // finfo can return application/octet-stream on some XAMPP builds: check the real file header instead.
            if (substr($bytes, 4, 4) === 'ftyp') $mime = 'video/mp4';                      // MP4 / MOV / M4V / 3GP family
            elseif (substr($bytes, 0, 4) === "\x1A\x45\xDF\xA3") $mime = 'video/webm';       // WebM / Matroska
            else return ['ok' => false, 'message' => 'Only MP4 or WebM videos are allowed.'];
        }
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
        $t = PUBLIC_PATH . '/' . upload_thumb_path($m['path']); if (is_file($t)) @unlink($t);
        DB::exec('DELETE FROM media_library WHERE id=?', [$id]);
        return ['ok' => true, 'message' => 'Image deleted.'];
    }
    /** Resolve an image field value: only accept paths that exist in our media library. */
    public static function validPath(?string $p): ?string {
        $p = trim((string)$p); if ($p === '') return null;
        return DB::val('SELECT path FROM media_library WHERE path=?', [$p]) ?: null;
    }
}