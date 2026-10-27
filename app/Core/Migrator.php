<?php
namespace App\Core;

/** Minimal SQL-file migrator. Files in database/migrations run once, in filename order. */
final class Migrator {
    private static function dir(): string { return ROOT . '/database/migrations'; }
    private static function ensure(): void {
        DB::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, batch INT NOT NULL, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    public static function files(): array { $f = glob(self::dir() . '/*.sql') ?: []; sort($f); return array_map('basename', $f); }
    public static function applied(): array { self::ensure(); return array_column(DB::all('SELECT migration FROM schema_migrations ORDER BY migration'), 'migration'); }
    public static function status(): array { $a = self::applied(); return array_map(fn($f) => ['file' => $f, 'applied' => in_array($f, $a, true)], self::files()); }

    /** @return string[] names applied now */
    public static function run(?callable $log = null): array {
        $done = self::applied(); $batch = (int)DB::val('SELECT COALESCE(MAX(batch),0) FROM schema_migrations') + 1; $ran = [];
        foreach (self::files() as $f) {
            if (in_array($f, $done, true)) continue;
            $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents(self::dir() . '/' . $f));
            foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $stmt) {
                $stmt = trim($stmt);
                if ($stmt === '') continue;
                try { DB::pdo()->exec($stmt); }
                catch (\Throwable $e) { throw new \RuntimeException("Migration $f failed: " . $e->getMessage() . "\nStatement: " . mb_substr($stmt, 0, 160), 0, $e); }
            }
            DB::insert('schema_migrations', ['migration' => $f, 'batch' => $batch]);
            $ran[] = $f; if ($log) $log("  migrated  $f");
        }
        return $ran;
    }
    public static function dropAll(): int {
        $pdo = DB::pdo(); $pdo->exec('SET FOREIGN_KEY_CHECKS=0'); $n = 0;
        foreach ($pdo->query('SHOW FULL TABLES WHERE Table_type="BASE TABLE"')->fetchAll(\PDO::FETCH_NUM) as [$t]) { $pdo->exec("DROP TABLE `$t`"); $n++; }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1'); return $n;
    }
}
