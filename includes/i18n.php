<?php
declare(strict_types=1);

final class I18n
{
    private static array $translations = [];
    private static string $currentLang = DEFAULT_LANG;

    public static function init(): void
    {
        if (isset($_GET['lang']) && array_key_exists($_GET['lang'], SUPPORTED_LANGUAGES)) {
            $_SESSION['app_lang'] = $_GET['lang'];
        }

        self::$currentLang = $_SESSION['app_lang'] ?? DEFAULT_LANG;
        $file = __DIR__ . '/../languages/' . self::$currentLang . '.php';

        if (file_exists($file)) {
            self::$translations = require $file;
        } else {
            self::$translations = require __DIR__ . '/../languages/en.php';
        }
    }

    public static function get(string $key, array $replace = []): string
    {
        $text = self::$translations[$key] ?? $key;
        foreach ($replace as $placeholder => $val) {
            $text = str_replace(':' . $placeholder, (string)$val, $text);
        }
        return $text;
    }

    public static function currentLanguage(): string
    {
        return self::$currentLang;
    }
}

function __(string $key, array $replace = []): string
{
    return I18n::get($key, $replace);
}
