<?php

declare(strict_types=1);

namespace AccessSwitch\Http;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        /** @var array<string, string> */
        public readonly array $headers = [],
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->body !== '') {
            echo $this->body;
        }
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            $status,
            (string) json_encode($data, JSON_THROW_ON_ERROR),
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function empty(int $status): self
    {
        return new self($status);
    }

    /** @param array<string, string> $extraHeaders */
    public static function html(string $body, int $status = 200, array $extraHeaders = []): self
    {
        return new self(
            $status,
            $body,
            array_merge(
                [
                    'Content-Type' => 'text/html; charset=utf-8',
                    'Cache-Control' => 'no-store',
                    'X-Content-Type-Options' => 'nosniff',
                    'X-Frame-Options' => 'DENY',
                    'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'",
                ],
                $extraHeaders
            )
        );
    }

    /** @param array<string, string> $extraHeaders */
    public static function jsonWithHeaders(mixed $data, int $status, array $extraHeaders): self
    {
        return new self(
            $status,
            (string) json_encode($data, JSON_THROW_ON_ERROR),
            array_merge(['Content-Type' => 'application/json; charset=utf-8'], $extraHeaders)
        );
    }
}
