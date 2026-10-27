<?php
declare(strict_types=1);

use App\Core\Env;
use App\Models\Setting;

function env(string $key, $default = null) { return Env::get($key, $default); }
function e($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function base_path(): string {
    static $b = null;
    if ($b === null) $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    return $b;
}
function url(string $path = ''): string { return base_path() . '/' . ltrim($path, '/'); }
function asset(string $path): string { $f = PUBLIC_PATH . '/' . ltrim($path, '/'); return url($path) . (is_file($f) ? '?v=' . filemtime($f) : ''); }
function upload_url(?string $path): string { return $path ? url($path) : ''; }
function money($n): string { return '₹' . number_format((float)$n, 2); }
function setting(string $key, $default = '') { return Setting::get($key, $default); }
function now(): string { return date('Y-m-d H:i:s'); }
function redirect(string $to): never { header('Location: ' . (preg_match('#^https?://#', $to) ? $to : url($to))); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function slugify(string $s): string { $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-')); return $s !== '' ? $s : 'item'; }
function log_error(string $m): void {
    $dir = ROOT . '/storage/logs'; if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', '[' . date('c') . '] ' . $m . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">'; }
function csrf_valid(): bool {
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && $t !== '' && hash_equals(csrf_token(), $t);
}

function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flashes(): array { $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
function old(string $k, $default = '') { return $_SESSION['_old'][$k] ?? $default; }
function keep_old(array $data): void { $_SESSION['_old'] = $data; }
function clear_old(): void { unset($_SESSION['_old']); }

function client_ip(): string { return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45); }


/** Tailwind CSS tags. Default: the compiled file (public/assets/css/tailwind.css, rebuilt with `npm run build:css`).
 *  Set TAILWIND_CDN=true in .env to use the Tailwind CDN instead (handy while you are editing views and have no Node installed). */
function tailwind_assets(): string {
    if (filter_var(env('TAILWIND_CDN', 'false'), FILTER_VALIDATE_BOOLEAN)) {
        return '<script src="https://cdn.tailwindcss.com/3.4.17"></script><script>tailwind.config={theme:{extend:{colors:{primary:"rgb(var(--c-primary-rgb) / <alpha-value>)",secondary:"rgb(var(--c-secondary-rgb) / <alpha-value>)",accent:"rgb(var(--c-accent-rgb) / <alpha-value>)",ink:"rgb(var(--c-text-rgb) / <alpha-value>)"},fontFamily:{sans:["Inter","Noto Sans Tamil","ui-sans-serif","system-ui","sans-serif"],display:["Poppins","Noto Sans Tamil","ui-sans-serif","system-ui","sans-serif"]},boxShadow:{soft:"0 1px 2px rgb(16 24 40 / .04), 0 6px 20px rgb(16 24 40 / .06)",lift:"0 14px 40px rgb(16 24 40 / .12)"}}}}</script>';
    }
    return '<link rel="stylesheet" href="' . e(asset('assets/css/tailwind.css')) . '">';
}

function icon(string $name, string $cls = 'w-5 h-5'): string { return '<i data-lucide="' . e($name) . '" class="' . e($cls) . '"></i>'; }

function hex_to_rgb(string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) $hex = '000000';
    return hexdec(substr($hex, 0, 2)) . ' ' . hexdec(substr($hex, 2, 2)) . ' ' . hexdec(substr($hex, 4, 2));
}
function valid_hex(string $c): bool { return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c); }

function order_statuses(): array {
    return ['placed' => 'Order Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'packed' => 'Packed', 'shipped' => 'Shipped / Dispatched', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
}
function status_badge(string $s): string {
    $map = ['placed' => 'bg-amber-100 text-amber-800', 'confirmed' => 'bg-blue-100 text-blue-800', 'processing' => 'bg-indigo-100 text-indigo-800', 'packed' => 'bg-purple-100 text-purple-800', 'shipped' => 'bg-cyan-100 text-cyan-800', 'delivered' => 'bg-green-100 text-green-800', 'cancelled' => 'bg-red-100 text-red-800'];
    return '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold ' . ($map[$s] ?? 'bg-gray-100 text-gray-700') . '">' . e(order_statuses()[$s] ?? $s) . '</span>';
}
function indian_states(): array {
    return ['Andaman and Nicobar Islands','Andhra Pradesh','Arunachal Pradesh','Assam','Bihar','Chandigarh','Chhattisgarh','Dadra and Nagar Haveli and Daman and Diu','Delhi','Goa','Gujarat','Haryana','Himachal Pradesh','Jammu and Kashmir','Jharkhand','Karnataka','Kerala','Ladakh','Lakshadweep','Madhya Pradesh','Maharashtra','Manipur','Meghalaya','Mizoram','Nagaland','Odisha','Puducherry','Punjab','Rajasthan','Sikkim','Tamil Nadu','Telangana','Tripura','Uttar Pradesh','Uttarakhand','West Bengal'];
}
function encrypt_secret(string $plain): string {
    if ($plain === '') return '';
    $key = hash('sha256', (string)env('APP_KEY', ''), true); $iv = random_bytes(12); $tag = '';
    $c = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $c);
}
function decrypt_secret(string $blob): string {
    if ($blob === '') return '';
    $raw = base64_decode($blob, true); if ($raw === false || strlen($raw) < 28) return '';
    $key = hash('sha256', (string)env('APP_KEY', ''), true);
    $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $p === false ? '' : $p;
}

/** Firecracker-themed SVG illustration used where a category/product has no photo. Key is guessed from the name. */
function art(string $name, string $cls = 'w-16 h-16'): string {
    $n = mb_strtolower($name); $k = 'sky';
    foreach (['rocket' => 'rocket', 'pot' => 'flower', 'flower' => 'flower', 'sparkler' => 'sparkler', 'mathappu' => 'sparkler', 'chakra' => 'chakra', 'wheel' => 'chakra', 'sound' => 'sound', 'bomb' => 'sound', 'atom' => 'sound', 'gift' => 'gift', 'box' => 'gift', 'combo' => 'gift', 'kid' => 'kids', 'fountain' => 'flower'] as $w => $key) if (str_contains($n, $w)) { $k = $key; break; }
    $P = 'var(--c-primary)'; $S = 'var(--c-secondary)'; $A = 'var(--c-accent)';
    $svg = [
        'rocket' => "<g class='art-float'><path d='M60 14c14 12 20 30 18 52H42c-2-22 4-40 18-52z' fill='$P'/><circle cx='60' cy='44' r='8' fill='#fff'/><path d='M42 66l-12 16 14-4zM78 66l12 16-14-4z' fill='$S'/><path d='M50 70h20l-10 26z' fill='$A'/></g><g fill='$A'><circle cx='24' cy='30' r='3'/><circle cx='98' cy='40' r='2.5'/><circle cx='20' cy='92' r='2'/><circle cx='100' cy='96' r='3'/></g>",
        'flower' => "<g class='art-float'><path d='M38 70h44l-6 34H44z' fill='$P'/><rect x='34' y='64' width='52' height='9' rx='4' fill='$S'/></g><g stroke='$A' stroke-width='4' stroke-linecap='round'><path d='M60 56V16'/><path d='M60 56L30 26'/><path d='M60 56L90 26'/><path d='M60 56L16 44'/><path d='M60 56L104 44'/></g><g fill='$P'><circle cx='60' cy='14' r='4'/><circle cx='29' cy='25' r='4'/><circle cx='91' cy='25' r='4'/><circle cx='15' cy='44' r='3.5'/><circle cx='105' cy='44' r='3.5'/></g>",
        'sparkler' => "<g transform='rotate(35 60 60)'><rect x='56' y='34' width='8' height='76' rx='3' fill='$S'/><rect x='54' y='94' width='12' height='12' rx='3' fill='$P'/></g><g stroke='$A' stroke-width='3.5' stroke-linecap='round' class='art-twinkle'><path d='M34 30l-14-8M30 44l-16 0M46 22l-4-16M58 20V4M72 24l8-14M36 56l-18 8'/></g><g fill='$P'><circle cx='24' cy='22' r='3'/><circle cx='12' cy='44' r='2.5'/><circle cx='42' cy='6' r='2.5'/></g>",
        'chakra' => "<g class='art-spin' style='transform-origin:60px 60px'><circle cx='60' cy='60' r='38' fill='none' stroke='$P' stroke-width='6'/><circle cx='60' cy='60' r='8' fill='$A'/><g stroke='$S' stroke-width='5' stroke-linecap='round'><path d='M60 22v20M60 78v20M22 60h20M78 60h20M33 33l14 14M73 73l14 14M33 87l14-14M73 47l14-14'/></g></g>",
        'sound' => "<circle cx='58' cy='72' r='28' fill='$P'/><path d='M44 60a20 20 0 0 1 14-10' stroke='#fff' stroke-opacity='.6' stroke-width='5' fill='none' stroke-linecap='round'/><path d='M70 46c6-10 16-12 20-22' stroke='$S' stroke-width='4' fill='none' stroke-linecap='round'/><g class='art-twinkle' fill='$A'><path d='M92 14l4 10 10 4-10 4-4 10-4-10-10-4 10-4z'/></g><circle cx='24' cy='24' r='3' fill='$A'/>",
        'gift' => "<rect x='22' y='50' width='76' height='54' rx='6' fill='$P'/><rect x='18' y='38' width='84' height='18' rx='5' fill='$S'/><rect x='54' y='38' width='12' height='66' fill='$A'/><path d='M60 38c-8-18-28-14-22-4 4 6 16 4 22 4zM60 38c8-18 28-14 22-4-4 6-16 4-22 4z' fill='none' stroke='$A' stroke-width='5' stroke-linejoin='round'/>",
        'kids' => "<g class='art-float'><path d='M60 14l12 26 28 4-20 20 5 28-25-14-25 14 5-28-20-20 28-4z' fill='$A'/><circle cx='50' cy='52' r='3.5' fill='#fff'/><circle cx='70' cy='52' r='3.5' fill='#fff'/><path d='M50 64q10 9 20 0' stroke='#fff' stroke-width='4' fill='none' stroke-linecap='round'/></g>",
        'sky' => "<g stroke='$P' stroke-width='4' stroke-linecap='round' class='art-twinkle'><path d='M60 60V16M60 60l32-32M60 60l44 0M60 60L92 92M60 60V104M60 60L28 92M60 60H16M60 60L28 28'/></g><g fill='$A'><circle cx='60' cy='12' r='4'/><circle cx='95' cy='25' r='4'/><circle cx='108' cy='60' r='4'/><circle cx='95' cy='95' r='4'/><circle cx='60' cy='108' r='4'/><circle cx='25' cy='95' r='4'/><circle cx='12' cy='60' r='4'/><circle cx='25' cy='25' r='4'/></g><circle cx='60' cy='60' r='9' fill='$S'/>",
    ][$k];
    return '<svg class="' . e($cls) . '" viewBox="0 0 120 120" fill="none" aria-hidden="true">' . $svg . '</svg>';
}
