<?php
namespace App\Services;

/** Keeps uploaded product sheets between the dry-run preview step and user confirmation. */
final class ProductImportStash {
    private const DIR = ROOT . '/storage/product-sheet-uploads';
    private const TTL = 7200; // 2 hours

    public static function prune(): void {
        foreach (array_keys($_SESSION['product_import_stash'] ?? []) as $token) {
            $created = (int)($_SESSION['product_import_stash'][$token]['created'] ?? 0);
            if (!preg_match('/^[a-f0-9]{32}$/', (string)$token) || $created < time() - self::TTL) {
                self::clear((string)$token);
            }
        }
        foreach (glob(self::DIR . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ((int)@filemtime($dir) >= time() - self::TTL) continue;
            foreach (glob($dir . '/*') ?: [] as $file) {
                if (is_file($file)) @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * Stash the uploaded file for confirm step.
     * @return array{token:?string,error:?string}
     */
    public static function stage(string $tmpPath, string $origName, array $meta = []): array {
        self::prune();
        $token = bin2hex(random_bytes(16));
        $dir = self::DIR . '/' . $token;
        if (!is_dir(self::DIR) && !@mkdir(self::DIR, 0700, true) && !is_dir(self::DIR)) {
            return ['token' => null, 'error' => 'Could not create storage directory for sheet upload.'];
        }
        @chmod(self::DIR, 0700);
        if (!is_dir($dir) && !@mkdir($dir, 0700) && !is_dir($dir)) {
            return ['token' => null, 'error' => 'Could not create preview storage directory.'];
        }
        @chmod($dir, 0700);

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $targetFile = $dir . '/sheet.' . ($ext === 'csv' ? 'csv' : 'xlsx');
        if (!@copy($tmpPath, $targetFile) && !@move_uploaded_file($tmpPath, $targetFile)) {
            @rmdir($dir);
            return ['token' => null, 'error' => 'Could not save the uploaded sheet for preview.'];
        }
        @chmod($targetFile, 0600);
        @touch($dir);

        $_SESSION['product_import_stash'][$token] = [
            'file' => $targetFile,
            'name' => $origName,
            'created' => time(),
            'meta' => $meta,
        ];

        return ['token' => $token, 'error' => null];
    }

    /** @return array{file:string,name:string,created:int,meta:array}|null */
    public static function get(?string $token): ?array {
        self::prune();
        if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        $entry = $_SESSION['product_import_stash'][$token] ?? null;
        if (!is_array($entry) || empty($entry['file']) || !is_file($entry['file'])) return null;
        return $entry;
    }

    public static function clear(?string $token): void {
        if (!$token || !preg_match('/^[a-f0-9]{32}$/', $token)) return;
        $entry = $_SESSION['product_import_stash'][$token] ?? [];
        $dir = self::DIR . '/' . $token;
        if (!empty($entry['file']) && is_file($entry['file'])) @unlink($entry['file']);
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                if (is_file($f)) @unlink($f);
            }
            @rmdir($dir);
        }
        unset($_SESSION['product_import_stash'][$token]);
    }
}
