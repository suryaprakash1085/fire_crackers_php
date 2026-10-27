<?php
namespace App\Core;

final class Env {
    private static array $vars = [];
    public static function load(string $file): void {
        if (!is_file($file)) return;
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = substr($v, 1, -1);
            self::$vars[trim($k)] = $v;
        }
    }
    public static function get(string $k, $default = null) { $e = getenv($k); return $e !== false ? $e : (self::$vars[$k] ?? $default); } // real environment variables win over .env
}
