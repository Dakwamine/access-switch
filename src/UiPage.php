<?php

declare(strict_types=1);

namespace AccessSwitch;

final class UiPage
{
    public static function html(string $lang): string
    {
        $path = dirname(__DIR__) . '/resources/ui.html';
        if (!is_readable($path)) {
            throw new \RuntimeException('UI template not found');
        }

        $html = file_get_contents($path);
        if ($html === false) {
            throw new \RuntimeException('Cannot read UI template');
        }

        if (!UiLocale::isSupported($lang)) {
            $lang = UiLocale::DEFAULT;
        }

        $uiJson = json_encode(UiLocale::uiStrings($lang), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $localeTag = self::localeTag($lang);

        return str_replace(
            ['{{LANG}}', '{{LOCALE}}', '{{I18N}}', '{{LANG_OPTIONS}}'],
            [
                htmlspecialchars($lang, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($localeTag, ENT_QUOTES, 'UTF-8'),
                $uiJson,
                UiLocale::htmlLangOptions($lang),
            ],
            $html
        );
    }

    private static function localeTag(string $lang): string
    {
        return match ($lang) {
            'en' => 'en-GB',
            'fr' => 'fr-FR',
            'es' => 'es-ES',
            'de' => 'de-DE',
            'pt' => 'pt-PT',
            'it' => 'it-IT',
            'zh' => 'zh-CN',
            'ja' => 'ja-JP',
            'ru' => 'ru-RU',
            'ar' => 'ar',
            'hi' => 'hi-IN',
            default => $lang,
        };
    }
}
