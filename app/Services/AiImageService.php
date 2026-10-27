<?php
namespace App\Services;

/** Real OpenAI Images API integration (server-side key only). */
final class AiImageService {
    public static function model(): string { return (string)env('AI_IMAGE_MODEL', 'gpt-image-1'); }
    public static function configured(): bool { return trim((string)env('OPENAI_API_KEY', '')) !== ''; }
    public static function isDalle3(): bool { return str_starts_with(self::model(), 'dall-e-3'); }
    public static function styles(): array {
        return ['festive' => 'vibrant festive celebration, warm glowing colours', 'realistic' => 'photorealistic, professional commercial photography', 'illustration' => 'clean modern flat illustration',
            'night-sky' => 'night sky fireworks bursting with colourful sparks, cinematic', 'product' => 'studio product shot on a clean light background, soft shadows'];
    }
    public static function aspects(): array {
        $d = self::isDalle3();
        return ['square' => ['label' => 'Square 1:1', 'size' => '1024x1024'], 'landscape' => ['label' => 'Landscape (banner)', 'size' => $d ? '1792x1024' : '1536x1024'], 'portrait' => ['label' => 'Portrait', 'size' => $d ? '1024x1792' : '1024x1536']];
    }
    public static function qualities(): array { return self::isDalle3() ? ['standard' => 'Standard', 'hd' => 'HD'] : ['low' => 'Low (fast)', 'medium' => 'Medium', 'high' => 'High']; }

    /** @return array{ok:bool,bytes?:string,error?:string} */
    public static function generate(string $prompt, string $style, string $aspect, string $quality): array {
        if (!self::configured()) return ['ok' => false, 'error' => 'AI image generation is not configured. Add OPENAI_API_KEY to your .env file (see docs/AI_SETUP.md).'];
        $aspects = self::aspects(); $styles = self::styles();
        $size = ($aspects[$aspect] ?? $aspects['landscape'])['size'];
        $full = trim($prompt) . (isset($styles[$style]) ? '. Style: ' . $styles[$style] : '') . '. No text, no logos, no watermarks.';
        $body = ['model' => self::model(), 'prompt' => mb_substr($full, 0, 3500), 'n' => 1, 'size' => $size];
        if (isset(self::qualities()[$quality])) $body['quality'] = $quality;
        if (self::isDalle3()) $body['response_format'] = 'b64_json';
        $ch = curl_init('https://api.openai.com/v1/images/generations');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 150, CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . trim((string)env('OPENAI_API_KEY'))], CURLOPT_POSTFIELDS => json_encode($body)]);
        $resp = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
        if ($resp === false) return ['ok' => false, 'error' => 'Could not reach the image API: ' . $cerr];
        $j = json_decode((string)$resp, true);
        if ($code !== 200 || !is_array($j)) {
            $msg = is_array($j) ? ($j['error']['message'] ?? 'Unknown API error') : 'Unexpected API response';
            log_error("ai image HTTP $code: $msg");
            return ['ok' => false, 'error' => "Image API error ($code): " . mb_substr($msg, 0, 300)];
        }
        $b64 = $j['data'][0]['b64_json'] ?? null;
        if (!$b64 && !empty($j['data'][0]['url'])) { $img = @file_get_contents($j['data'][0]['url']); if ($img) return ['ok' => true, 'bytes' => $img]; }
        $bytes = $b64 ? base64_decode($b64, true) : false;
        if (!$bytes) return ['ok' => false, 'error' => 'The API returned no image data.'];
        return ['ok' => true, 'bytes' => $bytes];
    }
}
