<?php
namespace App\Core;

final class RateLimiter {
    public static function tooMany(string $bucket, string $id, int $max, int $windowSec): bool {
        DB::exec('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
        $n = (int)DB::val('SELECT COUNT(*) FROM rate_limits WHERE bucket=? AND identifier=? AND created_at > (NOW() - INTERVAL ' . (int)$windowSec . ' SECOND)', [$bucket, $id]);
        return $n >= $max;
    }
    public static function hit(string $bucket, string $id): void { DB::insert('rate_limits', ['bucket' => $bucket, 'identifier' => $id, 'created_at' => now()]); }
    public static function clear(string $bucket, string $id): void { DB::exec('DELETE FROM rate_limits WHERE bucket=? AND identifier=?', [$bucket, $id]); }
}
