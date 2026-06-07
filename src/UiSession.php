<?php

declare(strict_types=1);

namespace AccessSwitch;

final class UiSession
{
    public const COOKIE_NAME = 'access_switch_ui';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
        private readonly bool $cookieSecure,
    ) {
    }

    public function createValue(): string
    {
        $payload = json_encode(
            ['exp' => time() + $this->ttlSeconds],
            JSON_THROW_ON_ERROR
        );
        $encoded = $this->base64UrlEncode($payload);

        return $encoded . '.' . $this->sign($encoded);
    }

    public function isValid(?string $cookieHeader): bool
    {
        if ($cookieHeader === null || $cookieHeader === '') {
            return false;
        }

        $value = $this->extractCookie($cookieHeader);
        if ($value === null) {
            return false;
        }

        $parts = explode('.', $value, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$encoded, $signature] = $parts;
        if (!hash_equals($this->sign($encoded), $signature)) {
            return false;
        }

        $payload = json_decode($this->base64UrlDecode($encoded), true);
        if (!is_array($payload) || !isset($payload['exp']) || !is_int($payload['exp'])) {
            return false;
        }

        return $payload['exp'] >= time();
    }

    /** @return array<string, string> */
    public function setCookieHeaders(string $value): array
    {
        $flags = 'HttpOnly; Path=/; SameSite=Strict; Max-Age=' . $this->ttlSeconds;
        if ($this->cookieSecure) {
            $flags .= '; Secure';
        }

        return ['Set-Cookie' => self::COOKIE_NAME . '=' . $value . '; ' . $flags];
    }

    /** @return array<string, string> */
    public function clearCookieHeaders(): array
    {
        $flags = 'HttpOnly; Path=/; SameSite=Strict; Max-Age=0';
        if ($this->cookieSecure) {
            $flags .= '; Secure';
        }

        return ['Set-Cookie' => self::COOKIE_NAME . '=; ' . $flags];
    }

    private function sign(string $encodedPayload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $encodedPayload, $this->secret, true));
    }

    private function extractCookie(string $cookieHeader): ?string
    {
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
            if ($name === self::COOKIE_NAME) {
                return trim(substr($part, $eq + 1));
            }
        }

        return null;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        return $decoded === false ? '' : $decoded;
    }
}
