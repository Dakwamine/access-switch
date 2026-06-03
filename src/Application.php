<?php

declare(strict_types=1);

namespace AccessSwitch;

use AccessSwitch\Http\Response;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Config $config,
        private readonly StateStore $store,
    ) {
    }

    /**
     * @param string|null $body                 Override for tests (default: php://input on POST /admin)
     * @param string|null $authorizationHeader  Override for tests (default: HTTP_AUTHORIZATION)
     */
    public function handle(
        string $method,
        string $path,
        ?string $body = null,
        ?string $authorizationHeader = null,
    ): Response {
        $path = rtrim($path, '/') ?: '/';

        return match ($method) {
            'GET' => $this->handleGet($path),
            'POST' => $this->handlePost($path, $body, $authorizationHeader),
            default => Response::empty(405),
        };
    }

    private function handleGet(string $path): Response
    {
        return match ($path) {
            '/check' => $this->check(),
            '/health' => Response::json(['status' => 'ok']),
            default => Response::empty(404),
        };
    }

    private function handlePost(string $path, ?string $body, ?string $authorizationHeader): Response
    {
        return match ($path) {
            '/admin' => $this->admin($body, $authorizationHeader),
            default => Response::empty(404),
        };
    }

    private function check(): Response
    {
        try {
            if ($this->store->isOpen()) {
                return Response::empty(200);
            }
        } catch (Throwable) {
            return Response::empty(503);
        }

        return Response::empty(503);
    }

    private function admin(?string $body, ?string $authorizationHeader): Response
    {
        if ($this->config->accessSwitchToken === '') {
            return Response::json(
                ['error' => 'ACCESS_SWITCH_TOKEN not configured'],
                503
            );
        }

        if (!$this->authorize($authorizationHeader)) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $body ??= file_get_contents('php://input');
        if ($body === false || $body === '') {
            return Response::json(['error' => 'JSON body required'], 400);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return Response::json(['error' => 'invalid JSON'], 400);
        }

        if (!is_array($data) || !array_key_exists('open', $data)) {
            return Response::json(['error' => '"open" field required (boolean)'], 400);
        }

        if (!is_bool($data['open'])) {
            return Response::json(['error' => '"open" must be a boolean'], 400);
        }

        try {
            $this->store->setOpen($data['open']);
        } catch (Throwable $e) {
            return Response::json(
                ['error' => 'persistence failed', 'detail' => $e->getMessage()],
                500
            );
        }

        return Response::json([
            'open' => $data['open'],
            'updated_at' => gmdate('c'),
        ]);
    }

    private function authorize(?string $authorizationHeader = null): bool
    {
        $header = $authorizationHeader ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return false;
        }

        return hash_equals($this->config->accessSwitchToken, $matches[1]);
    }
}
