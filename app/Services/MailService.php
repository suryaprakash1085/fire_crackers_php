<?php
namespace App\Services;

use App\Core\DB;

require_once ROOT . '/app/Vendor/PHPMailer/Exception.php';
require_once ROOT . '/app/Vendor/PHPMailer/PHPMailer.php';
require_once ROOT . '/app/Vendor/PHPMailer/SMTP.php';

final class MailService {
    public static function config(): array {
        $db = DB::one('SELECT * FROM email_settings WHERE id=1') ?: [];
        return [
            'host' => $db['host'] ?? '' ?: (string)env('SMTP_HOST', ''),
            'port' => (int)(($db['host'] ?? '') ? ($db['port'] ?? 587) : env('SMTP_PORT', 587)),
            'encryption' => ($db['host'] ?? '') ? ($db['encryption'] ?? 'tls') : (string)env('SMTP_ENCRYPTION', 'tls'),
            'username' => ($db['host'] ?? '') ? ($db['username'] ?? '') : (string)env('SMTP_USERNAME', ''),
            'password' => ($db['host'] ?? '') ? decrypt_secret($db['password_enc'] ?? '') : (string)env('SMTP_PASSWORD', ''),
            'from_email' => ($db['sender_email'] ?? '') ?: (string)env('MAIL_FROM_EMAIL', ''),
            'from_name' => ($db['sender_name'] ?? '') ?: (string)env('MAIL_FROM_NAME', setting('brand_name', 'Shop')),
        ];
    }
    public static function configured(): bool { $c = self::config(); return $c['host'] !== '' && $c['from_email'] !== ''; }

    /** @return true|string true on success, error string on failure */
    public static function send(string $to, string $subject, string $html): bool|string {
        $c = self::config();
        if (!self::configured()) return 'SMTP is not configured.';
        try {
            $m = new \PHPMailer\PHPMailer\PHPMailer(true);
            $m->isSMTP(); $m->Host = $c['host']; $m->Port = $c['port']; $m->CharSet = 'UTF-8'; $m->Timeout = 15;
            if ($c['encryption'] === 'tls') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            elseif ($c['encryption'] === 'ssl') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }
            if ($c['username'] !== '') { $m->SMTPAuth = true; $m->Username = $c['username']; $m->Password = $c['password']; }
            $m->setFrom($c['from_email'], $c['from_name']);
            $m->addAddress($to);
            $m->isHTML(true); $m->Subject = $subject; $m->Body = $html; $m->AltBody = trim(strip_tags(str_replace(['<br>', '</tr>', '</p>'], "\n", $html)));
            $m->send();
            return true;
        } catch (\Throwable $e) {
            log_error('mail: ' . $e->getMessage());
            return $e->getMessage();
        }
    }
    public static function orderConfirmation(array $order, array $items): void {
        if (empty($order['email']) || !self::configured()) return;
        $rows = '';
        foreach ($items as $i) $rows .= '<tr><td style="padding:6px;border-bottom:1px solid #eee">' . e($i['name']) . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:center">' . (int)$i['quantity'] . '</td><td style="padding:6px;border-bottom:1px solid #eee;text-align:right">' . money($i['line_total']) . '</td></tr>';
        $html = '<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto"><h2 style="color:#E8590C">Thank you, ' . e($order['customer_name']) . '!</h2>'
            . '<p>We received your order <b>' . e($order['reference']) . '</b>. Payment status: <b>' . e($order['payment_status']) . '</b>.</p>'
            . '<table style="width:100%;border-collapse:collapse"><tr><th align="left">Item</th><th>Qty</th><th align="right">Amount</th></tr>' . $rows . '</table>'
            . '<p style="text-align:right">Delivery: ' . money($order['delivery_charge']) . '<br><b>Total: ' . money($order['total']) . '</b></p>'
            . '<p>Track your order with your reference and mobile number at ' . e(rtrim((string)env('APP_URL', ''), '/')) . '/track</p></div>';
        $r = self::send($order['email'], 'Order ' . $order['reference'] . ' received – ' . setting('brand_name'), $html);
        if ($r !== true) log_error('order email failed for ' . $order['reference'] . ': ' . $r);
    }
}
