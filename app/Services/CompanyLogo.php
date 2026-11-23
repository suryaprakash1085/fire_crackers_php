<?php
namespace App\Services;

/**
 * Company logo upload: JPG, PNG, WebP or SVG, max 2 MB.
 * - The type is decided from the file CONTENT (finfo / image header / SVG parse), never from the extension or the browser's Content-Type.
 * - Files are stored with a random name in public/uploads/company/ (scripts are blocked there by .htaccess).
 * - Raster images are re-encoded with GD (when available) to strip metadata/embedded payloads and to keep them small.
 * - SVG is parsed and sanitised: scripts, event handlers, external references and DOCTYPE/ENTITY tricks are removed or rejected.
 */
final class CompanyLogo {
    public const DIR = 'uploads/company';
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const RASTER = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    private const MAX_SIDE = 800;

    /** @param array $f one $_FILES entry. @return array{ok:bool,message:string,path:?string} */
    public static function store(array $f): array {
        $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $m = [UPLOAD_ERR_INI_SIZE => 'The logo is larger than the server upload limit.', UPLOAD_ERR_FORM_SIZE => 'The logo is larger than 2 MB.', UPLOAD_ERR_NO_FILE => 'No logo file selected.'][$err] ?? 'Logo upload failed.';
            return self::fail($m);
        }
        if (!is_uploaded_file((string)$f['tmp_name'])) return self::fail('Invalid upload.');
        if ((int)$f['size'] > self::MAX_BYTES) return self::fail('The logo must be 2 MB or smaller.');
        $bytes = (string)file_get_contents($f['tmp_name']);
        if ($bytes === '' || strlen($bytes) > self::MAX_BYTES) return self::fail('The logo must be 2 MB or smaller.');
        return self::storeBytes($bytes);
    }

    /** Validates and saves raw bytes (also used by tests). */
    public static function storeBytes(string $bytes): array {
        $mime = (string)(new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $head = ltrim(substr($bytes, 0, 512), "\xEF\xBB\xBF \t\r\n");
        $looksSvg = stripos($head, '<svg') === 0 || (stripos($head, '<?xml') === 0 && stripos($bytes, '<svg') !== false) || stripos($head, '<!--') === 0 && stripos($bytes, '<svg') !== false || stripos($head, '<!doctype svg') === 0;

        if (isset(self::RASTER[$mime])) {
            $info = @getimagesizefromstring($bytes);
            if (!$info || $info[0] < 1 || $info[1] < 1 || $info[0] > 8000 || $info[1] > 8000) return self::fail('This is not a valid image.');
            [$out, $ext] = self::reencode($bytes, self::RASTER[$mime]);
        } elseif ($looksSvg || $mime === 'image/svg+xml') {
            $clean = self::sanitizeSvg($bytes);
            if ($clean === null) return self::fail('This SVG file is not valid or contains unsafe content (scripts or external links).');
            $out = $clean; $ext = 'svg';
        } else {
            return self::fail('Only JPG, PNG, WebP or SVG logos are allowed.');
        }

        $dir = PUBLIC_PATH . '/' . self::DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) return self::fail('The uploads folder is not writable.');
        self::ensureHtaccess($dir);
        $rel = self::DIR . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        if (@file_put_contents(PUBLIC_PATH . '/' . $rel, $out, LOCK_EX) === false) return self::fail('Could not save the logo file.');
        return ['ok' => true, 'message' => 'Logo uploaded.', 'path' => $rel];
    }

    /** Deletes a previously stored logo. Only files inside uploads/company are ever removed. */
    public static function delete(?string $path): void {
        if (!$path || !str_starts_with($path, self::DIR . '/')) return;
        $f = PUBLIC_PATH . '/' . $path;
        $base = realpath(PUBLIC_PATH . '/' . self::DIR);
        $real = is_file($f) ? realpath($f) : false;
        if ($base && $real && str_starts_with($real, $base . DIRECTORY_SEPARATOR)) @unlink($real);
    }

    /** @return array{0:string,1:string} [bytes, extension] */
    private static function reencode(string $bytes, string $ext): array {
        if (!function_exists('imagecreatefromstring')) return [$bytes, $ext];
        $img = @imagecreatefromstring($bytes);
        if (!$img) return [$bytes, $ext];
        $w = imagesx($img); $h = imagesy($img);
        if (max($w, $h) > self::MAX_SIDE) {
            $r = @imagescale($img, $w >= $h ? self::MAX_SIDE : (int)max(1, round($w * self::MAX_SIDE / $h)), $h > $w ? self::MAX_SIDE : (int)max(1, round($h * self::MAX_SIDE / $w)));
            if ($r) { imagedestroy($img); $img = $r; }
        }
        imagealphablending($img, false); imagesavealpha($img, true);
        ob_start();
        if ($ext === 'jpg') imagejpeg($img, null, 90);
        elseif ($ext === 'webp' && function_exists('imagewebp')) imagewebp($img, null, 90);
        else { $ext = 'png'; imagepng($img, null, 6); }
        $out = (string)ob_get_clean(); imagedestroy($img);
        return $out === '' ? [$bytes, $ext] : [$out, $ext];
    }

    /** Returns clean SVG markup or null when the file is not a safe, valid SVG. */
    public static function sanitizeSvg(string $svg): ?string {
        $svg = preg_replace('/^\xEF\xBB\xBF/', '', $svg);
        if (preg_match('/<!ENTITY/i', $svg)) return null;                                        // XXE / billion-laughs
        $svg = preg_replace('/<!DOCTYPE[^>\[]*>/i', '', $svg);                                   // plain DOCTYPE (Illustrator) is harmless once removed
        if (stripos($svg, '<!DOCTYPE') !== false) return null;                                   // DOCTYPE with an internal subset
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $ok = $dom->loadXML($svg, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors(); libxml_use_internal_errors($prev);
        $root = $ok ? $dom->documentElement : null;
        if (!$root || strtolower($root->localName) !== 'svg') return null;

        $kill = ['script', 'foreignobject', 'iframe', 'object', 'embed', 'link', 'meta', 'audio', 'video', 'canvas', 'animate', 'set', 'animatetransform', 'animatemotion', 'handler', 'listener'];
        $xp = new \DOMXPath($dom);
        $remove = [];
        foreach ($xp->query('//*') as $el) {
            $name = strtolower($el->localName);
            if (in_array($name, $kill, true)) { $remove[] = $el; continue; }
            if ($name === 'style' && preg_match('/@import|expression\s*\(|javascript:|url\s*\(\s*[\'"]?\s*(?!#)/i', $el->textContent)) { $remove[] = $el; continue; }
            $drop = [];
            foreach ($el->attributes as $a) {
                $an = strtolower($a->nodeName); $v = trim($a->value);
                if (str_starts_with($an, 'on')) { $drop[] = $a->nodeName; continue; }
                if (in_array($an, ['href', 'xlink:href', 'src'], true) && $v !== '' && $v[0] !== '#' && !preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $v)) { $drop[] = $a->nodeName; continue; }
                if ($an === 'style' && preg_match('/expression\s*\(|javascript:|@import|url\s*\(\s*[\'"]?\s*(?!#)/i', $v)) { $drop[] = $a->nodeName; continue; }
                if (preg_match('/javascript:|vbscript:/i', $v)) $drop[] = $a->nodeName;
            }
            foreach ($drop as $n) $el->removeAttribute($n);
        }
        foreach ($remove as $el) if ($el->parentNode) $el->parentNode->removeChild($el);
        $out = $dom->saveXML($root);
        return $out === false || strlen($out) > self::MAX_BYTES ? null : '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out;
    }

    private static function ensureHtaccess(string $dir): void {
        $f = $dir . '/.htaccess';
        if (is_file($f)) return;
        @file_put_contents($f, "# Logos only: no scripts, and SVG files are served sandboxed so they can never run code if opened directly.\n"
            . "Options -Indexes -ExecCGI\n"
            . "<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)\$\">\n  Require all denied\n</FilesMatch>\n"
            . "<IfModule mod_headers.c>\n  Header set X-Content-Type-Options \"nosniff\"\n  Header set Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox\"\n</IfModule>\n");
    }

    private static function fail(string $m): array { return ['ok' => false, 'message' => $m, 'path' => null]; }
}
