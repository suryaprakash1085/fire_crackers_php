<?php
namespace App\Core;

use PDO;
use PDOStatement;

final class DB {
    private static ?PDO $pdo = null;
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'firecracker_shop'));
            self::$pdo = new PDO($dsn, (string)env('DB_USER', 'root'), (string)env('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+05:30'");
        }
        return self::$pdo;
    }
    public static function run(string $sql, array $p = []): PDOStatement { $s = self::pdo()->prepare($sql); $s->execute($p); return $s; }
    public static function all(string $sql, array $p = []): array { return self::run($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { $r = self::run($sql, $p)->fetch(); return $r ?: null; }
    public static function val(string $sql, array $p = []) { $r = self::run($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
    public static function exec(string $sql, array $p = []): int { return self::run($sql, $p)->rowCount(); }
    public static function insert(string $table, array $data): int {
        $cols = array_keys($data);
        self::run('INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($data));
        return (int)self::pdo()->lastInsertId();
    }
    public static function update(string $table, array $data, string $where, array $wp = []): int {
        $set = implode(',', array_map(fn($c) => "`$c`=?", array_keys($data)));
        return self::exec("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $wp));
    }
}
