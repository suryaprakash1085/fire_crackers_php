<?php
namespace App\Core;

final class Auth {
    private const LEVEL = ['staff' => 1, 'admin' => 2, 'superadmin' => 3];
    private const IDLE = 3600;

    public static function user(): ?array {
        static $u = false;
        if ($u !== false) return $u;
        $u = null;
        if (!empty($_SESSION['admin_id'])) {
            if (time() - ($_SESSION['admin_seen'] ?? 0) > self::IDLE || ($_SESSION['admin_ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
                self::logout();
            } else {
                $u = DB::one('SELECT id,name,email,role FROM admins WHERE id=? AND is_active=1', [$_SESSION['admin_id']]);
                if ($u) $_SESSION['admin_seen'] = time(); else self::logout();
            }
        }
        return $u;
    }
    public static function attempt(string $email, string $password): bool|string {
        $key = strtolower($email) . '|' . client_ip();
        if (RateLimiter::tooMany('admin_login', $key, 5, 900) || RateLimiter::tooMany('admin_login_ip', client_ip(), 20, 900)) return 'Too many attempts. Try again in 15 minutes.';
        $a = DB::one('SELECT * FROM admins WHERE email=? AND is_active=1', [$email]);
        $hash = $a['password_hash'] ?? password_hash('dummy-timing-equalizer', PASSWORD_DEFAULT);
        if (!password_verify($password, $hash) || !$a) {
            RateLimiter::hit('admin_login', $key); RateLimiter::hit('admin_login_ip', client_ip());
            return 'Invalid email or password.';
        }
        RateLimiter::clear('admin_login', $key);
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$a['id'];
        $_SESSION['admin_seen'] = time();
        $_SESSION['admin_ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) DB::update('admins', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id=?', [$a['id']]);
        DB::update('admins', ['last_login_at' => now()], 'id=?', [$a['id']]);
        return true;
    }
    public static function logout(): void {
        unset($_SESSION['admin_id'], $_SESSION['admin_seen'], $_SESSION['admin_ua']);
        if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true);
    }
    public static function require(string $role = 'staff'): array {
        $u = self::user();
        if (!$u) redirect('/admin/login');
        if (self::LEVEL[$u['role']] < self::LEVEL[$role]) { http_response_code(403); View::render('admin/403', ['title' => 'Forbidden', 'admin' => $u], 'layouts/admin'); exit; }
        return $u;
    }
}
