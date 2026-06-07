<?php

declare(strict_types=1);

namespace AccessSwitch;

final class UiLocale
{
    public const COOKIE_NAME = 'access_switch_lang';

    public const DEFAULT = 'fr';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'fr', 'es', 'de', 'pt', 'it', 'zh', 'ja', 'ru', 'ar', 'hi'];

    /** @var array<string, string> */
    private const NATIVE_NAMES = [
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
        'de' => 'Deutsch',
        'pt' => 'Português',
        'it' => 'Italiano',
        'zh' => '中文',
        'ja' => '日本語',
        'ru' => 'Русский',
        'ar' => 'العربية',
        'hi' => 'हिन्दी',
    ];

    /** @var array<string, array<string, string>> */
    private static array $catalogCache = [];

    public static function isSupported(string $lang): bool
    {
        return self::normalize($lang) !== null;
    }

    public static function resolve(?string $cookieHeader, ?string $acceptLanguage = null): string
    {
        $fromCookie = self::extractFromCookie($cookieHeader);
        if ($fromCookie !== null) {
            return $fromCookie;
        }

        if ($acceptLanguage !== null && $acceptLanguage !== '') {
            foreach (self::parseAcceptLanguage($acceptLanguage) as $tag) {
                $normalized = self::normalize($tag);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return self::DEFAULT;
    }

    public static function extractFromCookie(?string $cookieHeader): ?string
    {
        if ($cookieHeader === null || $cookieHeader === '') {
            return null;
        }

        foreach (explode(';', $cookieHeader) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $eq = strpos($part, '=');
            if ($eq === false) {
                continue;
            }
            $name = trim(substr($part, 0, $eq));
            if ($name !== self::COOKIE_NAME) {
                continue;
            }
            $value = trim(substr($part, $eq + 1));
            if ($value === '') {
                return null;
            }

            $normalized = self::normalize($value);
            if ($normalized === null) {
                return null;
            }

            return $normalized;
        }

        return null;
    }

    /** @return array<string, string> */
    public static function catalog(string $lang): array
    {
        $lang = self::normalize($lang) ?? self::DEFAULT;

        if (isset(self::$catalogCache[$lang])) {
            return self::$catalogCache[$lang];
        }

        $path = dirname(__DIR__) . '/resources/i18n/' . $lang . '.json';
        if (!is_readable($path)) {
            $path = dirname(__DIR__) . '/resources/i18n/' . self::DEFAULT . '.json';
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('UI translation catalog not found');
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid UI translation catalog');
        }

        /** @var array<string, string> $catalog */
        $catalog = [];
        foreach ($data as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $catalog[$key] = $value;
            }
        }

        self::$catalogCache[$lang] = $catalog;

        return $catalog;
    }

    /** @param array<string, string|int> $vars */
    public static function get(string $lang, string $key, array $vars = []): string
    {
        $catalog = self::catalog($lang);
        $text = $catalog[$key] ?? self::catalog(self::DEFAULT)[$key] ?? self::catalog('en')[$key] ?? $key;

        foreach ($vars as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string, string> UI strings only (no error.* keys). */
    public static function uiStrings(string $lang): array
    {
        $strings = [];
        foreach (self::catalog($lang) as $key => $value) {
            if (!str_starts_with($key, 'error.')) {
                $strings[$key] = $value;
            }
        }

        return $strings;
    }

    public static function htmlLangOptions(string $selectedLang): string
    {
        $html = '';
        foreach (self::SUPPORTED as $code) {
            $label = self::NATIVE_NAMES[$code] ?? $code;
            $selected = $code === $selectedLang ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
                . '</option>';
        }

        return $html;
    }

    /** @return array<string, string> */
    public static function setCookieHeaders(string $lang, bool $secure): array
    {
        if (!self::isSupported($lang)) {
            throw new \InvalidArgumentException('Unsupported UI language');
        }

        $flags = 'Path=/; SameSite=Strict; Max-Age=31536000';
        if ($secure) {
            $flags .= '; Secure';
        }

        return ['Set-Cookie' => self::COOKIE_NAME . '=' . $lang . '; ' . $flags];
    }

    /** @return list<string> */
    private static function parseAcceptLanguage(string $header): array
    {
        $tags = [];
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $semi = strpos($part, ';');
            if ($semi !== false) {
                $part = trim(substr($part, 0, $semi));
            }
            if ($part !== '') {
                $tags[] = $part;
            }
        }

        return $tags;
    }

    private static function normalize(string $tag): ?string
    {
        $tag = strtolower(str_replace('_', '-', trim($tag)));
        if ($tag === '') {
            return null;
        }

        if (in_array($tag, self::SUPPORTED, true)) {
            return $tag;
        }

        $primary = explode('-', $tag)[0];

        return in_array($primary, self::SUPPORTED, true) ? $primary : null;
    }
}
