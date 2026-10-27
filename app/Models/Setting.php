<?php
namespace App\Models;

use App\Core\DB;

final class Setting {
    private static ?array $cache = null;
    public static function all(): array {
        if (self::$cache === null) {
            self::$cache = [];
            try { foreach (DB::all('SELECT setting_key, setting_value FROM site_settings') as $r) self::$cache[$r['setting_key']] = $r['setting_value']; } catch (\Throwable $e) { log_error('settings: ' . $e->getMessage()); }
        }
        return self::$cache;
    }
    public static function get(string $k, $d = '') { $a = self::all(); return isset($a[$k]) && $a[$k] !== null ? $a[$k] : $d; }
    public static function set(string $k, ?string $v): void {
        DB::run('INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', [$k, $v]);
        self::$cache = null;
    }
    public static function setMany(array $kv): void { foreach ($kv as $k => $v) self::set((string)$k, (string)$v); }
}
