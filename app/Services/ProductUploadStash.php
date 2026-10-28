<?php
namespace App\Services;

/** Keeps product form uploads privately between a validation error and its retry. */
final class ProductUploadStash {
    private const DIR = ROOT . '/storage/product-form-uploads';
    private const TTL = 7200;

    public static function prune(): void {
        foreach (array_keys($_SESSION['product_upload_stash'] ?? []) as $token) {
            $created = (int)($_SESSION['product_upload_stash'][$token]['created'] ?? 0);
            if (!preg_match('/^[a-f0-9]{32}$/', (string)$token) || $created < time() - self::TTL) self::clear((string)$token);
        }
        foreach (glob(self::DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ((int)@filemtime($dir) >= time() - self::TTL) continue;
            foreach (glob($dir . '/*') ?: [] as $file) if (is_file($file)) @unlink($file);
            @rmdir($dir);
        }
    }

    /** @return array{images:array<array{path:string,name:string}>,video:?array{path:string,name:string}} */
    public static function get(?string $token): array {
        self::prune();
        $empty = ['images' => [], 'video' => null];
        if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) return $empty;
        $entry = $_SESSION['product_upload_stash'][$token] ?? null;
        if (!is_array($entry)) return $empty;
        $dir = self::DIR . '/' . $token;
        $files = [];
        foreach (($entry['images'] ?? []) as $image) {
            $file = self::resolve($dir, $image);
            if ($file) $files['images'][] = ['path' => $file, 'name' => (string)$image['name']];
        }
        $video = self::resolve($dir, $entry['video'] ?? null);
        return ['images' => $files['images'] ?? [], 'video' => $video ? ['path' => $video, 'name' => (string)$entry['video']['name']] : null];
    }

    /**
     * @param array<array{name:string,tmp_name:string,error:int}> $images
     * @param array{name:string,tmp_name:string,error:int}|null $video
     * @return array{token:?string,errors:array<string>}
     */
    public static function stage(?string $token, array $images, ?array $video): array {
        self::prune();
        $validToken = $token && preg_match('/^[a-f0-9]{32}$/', $token) && isset($_SESSION['product_upload_stash'][$token]) ? $token : null;
        $entry = $validToken ? $_SESSION['product_upload_stash'][$validToken] : ['created' => time(), 'images' => [], 'video' => null];
        $hasUpload = false;
        foreach ($images as $image) if (($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $hasUpload = true;
        if ($video && ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $hasUpload = true;
        if (!$validToken && !$hasUpload) return ['token' => null, 'errors' => []];

        $token = $validToken ?? bin2hex(random_bytes(16));
        $dir = self::DIR . '/' . $token;
        if (!is_dir(self::DIR) && !@mkdir(self::DIR, 0700, true) && !is_dir(self::DIR)) return ['token' => $validToken, 'errors' => ['Could not preserve the uploaded files. Please try again.']];
        @chmod(self::DIR, 0700);
        if (!is_dir($dir) && !@mkdir($dir, 0700) && !is_dir($dir)) return ['token' => $validToken, 'errors' => ['Could not preserve the uploaded files. Please try again.']];
        @chmod($dir, 0700);
        $errors = [];

        foreach ($images as $image) {
            if (($image['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $stored = self::store($image, $dir, 6 * 1024 * 1024, 'Image');
            if ($stored) $entry['images'][] = $stored;
            else $errors[] = self::lastError();
        }
        if ($video && ($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = self::store($video, $dir, 50 * 1024 * 1024, 'Video');
            if ($stored) {
                if (!empty($entry['video']['file'])) @unlink($dir . '/' . $entry['video']['file']);
                $entry['video'] = $stored;
            } else $errors[] = self::lastError();
        }
        $entry['created'] = time();
        @touch($dir);
        $_SESSION['product_upload_stash'][$token] = $entry;
        return ['token' => $token, 'errors' => $errors];
    }

    public static function clear(?string $token): void {
        if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) return;
        $entry = $_SESSION['product_upload_stash'][$token] ?? [];
        $dir = self::DIR . '/' . $token;
        foreach (($entry['images'] ?? []) as $image) {
            if ($file = self::resolve($dir, $image)) @unlink($file);
        }
        if ($video = self::resolve($dir, $entry['video'] ?? null)) @unlink($video);
        if (is_dir($dir)) @rmdir($dir);
        unset($_SESSION['product_upload_stash'][$token]);
    }

    private static string $error = '';

    private static function store(array $upload, string $dir, int $maxBytes, string $kind): ?array {
        $err = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            self::$error = $kind . ' upload failed' . ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? ' (file too large).' : '.');
            return null;
        }
        $tmp = (string)($upload['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) { self::$error = $kind . ' upload was invalid.'; return null; }
        $size = @filesize($tmp);
        if ($size === false || $size > $maxBytes) { self::$error = $kind . ' exceeds the upload size limit.'; return null; }
        $name = trim((string)($upload['name'] ?? ''));
        $stored = ['file' => bin2hex(random_bytes(12)) . '.tmp', 'name' => mb_substr($name ?: strtolower($kind), 0, 180)];
        if (!@move_uploaded_file($tmp, $dir . '/' . $stored['file'])) { self::$error = 'Could not preserve ' . strtolower($kind) . ' upload.'; return null; }
        return $stored;
    }

    private static function lastError(): string { return self::$error; }

    private static function resolve(string $dir, $file): ?string {
        if (!is_array($file) || !preg_match('/^[a-f0-9]{24}\.tmp$/', (string)($file['file'] ?? ''))) return null;
        $path = $dir . '/' . $file['file'];
        return is_file($path) ? $path : null;
    }
}
