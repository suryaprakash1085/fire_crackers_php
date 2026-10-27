<?php
namespace App\Core;

/** Runs PHP seeders from database/seeders (base) and database/seeders/demo (--demo). All seeders are idempotent. */
final class Seeder {
    public static function run(bool $demo, callable $log): void {
        $files = glob(ROOT . '/database/seeders/*.php') ?: [];
        if ($demo) $files = array_merge($files, glob(ROOT . '/database/seeders/demo/*.php') ?: []);
        sort($files);
        foreach ($files as $f) {
            $fn = require $f;
            $msg = $fn();
            $log('  seeded    ' . str_pad(basename(dirname($f)) === 'demo' ? 'demo/' . basename($f) : basename($f), 32) . ($msg ? " $msg" : ''));
        }
    }
}
