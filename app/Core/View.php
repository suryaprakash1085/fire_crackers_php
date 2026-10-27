<?php
namespace App\Core;

final class View {
    public static function render(string $tpl, array $data = [], ?string $layout = null): void { echo self::capture($tpl, $data, $layout); }
    public static function capture(string $tpl, array $data = [], ?string $layout = null): string {
        $content = self::partial($tpl, $data);
        if ($layout === null) return $content;
        return self::partial($layout, $data + ['content' => $content]);
    }
    public static function partial(string $tpl, array $data = []): string {
        extract($data, EXTR_SKIP);
        ob_start();
        require ROOT . '/app/Views/' . $tpl . '.php';
        return (string)ob_get_clean();
    }
}
