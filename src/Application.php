<?php

declare(strict_types=1);

namespace AccessSwitch;

use AccessSwitch\Http\Response;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Config $config,
        private readonly ServiceRegistry $registry,
        private readonly ServiceStateStore $store,
        private readonly UiSession $uiSession,
    ) {
    }

    /**
     * @param string|null $body                 Override for tests (default: php://input on POST)
     * @param string|null $authorizationHeader  Override for tests (default: HTTP_AUTHORIZATION)
     * @param string|null $cookieHeader         Override for tests (default: HTTP_COOKIE)
     */
    public function handle(
        string $method,
        string $path,
        ?string $body = null,
        ?string $authorizationHeader = null,
        ?string $cookieHeader = null,
    ): Response {
        $path = rtrim($path, '/') ?: '/';

        return match ($method) {
            'GET' => $this->handleGet($path, $authorizationHeader, $cookieHeader),
            'POST' => $this->handlePost($path, $body, $authorizationHeader, $cookieHeader),
            default => Response::empty(405),
        };
    }

    private function handleGet(
        string $path,
        ?string $authorizationHeader,
        ?string $cookieHeader,
    ): Response {
        if ($path === '/check') {
            return $this->check(ServiceRegistry::DEFAULT_SERVICE_ID);
        }

        if (preg_match('#^/check/([^/]+)$#', $path, $matches) === 1) {
            return $this->check($matches[1]);
        }

        return match ($path) {
            '/health' => Response::json(['status' => 'ok']),
            '/ui' => $this->uiPage(),
            '/admin/status' => $this->adminStatus($authorizationHeader, $cookieHeader),
            default => Response::empty(404),
        };
    }

    private function handlePost(
        string $path,
        ?string $body,
        ?string $authorizationHeader,
        ?string $cookieHeader,
    ): Response {
        return match ($path) {
            '/admin' => $this->admin($body, $authorizationHeader, $cookieHeader),
            '/ui/login' => $this->uiLogin($body),
            '/ui/logout' => $this->uiLogout(),
            default => Response::empty(404),
        };
    }

    private function uiPage(): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        try {
            return Response::html(UiPage::html());
        } catch (Throwable) {
            return Response::json(['error' => 'UI unavailable'], 503);
        }
    }

    private function adminStatus(?string $authorizationHeader, ?string $cookieHeader): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        if (!$this->isAdminAuthorized($authorizationHeader, $cookieHeader)) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $services = [];
        foreach ($this->registry->all() as $serviceId) {
            if (!$this->registry->isAuthorizedForAdmin($serviceId)) {
                continue;
            }

            try {
                $state = $this->store->getState($serviceId);
            } catch (Throwable) {
                return Response::json(['error' => 'state read failed', 'service' => $serviceId], 503);
            }

            $services[] = [
                'service' => $serviceId,
                'open' => $state['open'],
                'updated_at' => $state['updated_at'],
            ];
        }

        return Response::json(['services' => $services]);
    }

    private function uiLogin(?string $body): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        if ($this->config->accessSwitchToken === '') {
            return Response::json(
                ['error' => 'ACCESS_SWITCH_TOKEN not configured'],
                503
            );
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

        if (!is_array($data) || !array_key_exists('token', $data) || !is_string($data['token'])) {
            return Response::json(['error' => '"token" field required (string)'], 400);
        }

        if (!hash_equals($this->config->accessSwitchToken, $data['token'])) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $sessionValue = $this->uiSession->createValue();

        return Response::jsonWithHeaders(
            ['ok' => true],
            200,
            $this->uiSession->setCookieHeaders($sessionValue)
        );
    }

    private function uiLogout(): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        return Response::jsonWithHeaders(
            ['ok' => true],
            200,
            $this->uiSession->clearCookieHeaders()
        );
    }

    private function check(string $serviceId): Response
    {
        if (!$this->registry->validateServiceId($serviceId) || !$this->registry->isAuthorizedForCheck($serviceId)) {
            return Response::empty(503);
        }

        try {
            if ($this->store->isOpen($serviceId)) {
                return Response::empty(200);
            }
        } catch (Throwable) {
            return Response::empty(503);
        }

        return Response::empty(503);
    }

    private function admin(?string $body, ?string $authorizationHeader, ?string $cookieHeader): Response
    {
        if ($this->config->accessSwitchToken === '') {
            return Response::json(
                ['error' => 'ACCESS_SWITCH_TOKEN not configured'],
                503
            );
        }

        if (!$this->isAdminAuthorized($authorizationHeader, $cookieHeader)) {
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

        $serviceId = ServiceRegistry::DEFAULT_SERVICE_ID;
        if (array_key_exists('service', $data)) {
            if (!is_string($data['service'])) {
                return Response::json(['error' => '"service" must be a string'], 400);
            }
            $serviceId = $data['service'];
        }

        if (!$this->registry->validateServiceId($serviceId)) {
            return Response::json(['error' => 'invalid service id'], 400);
        }

        if (!$this->registry->isAuthorizedForAdmin($serviceId)) {
            return Response::json(['error' => 'unknown or unauthorized service'], 400);
        }

        try {
            $this->store->setOpen($serviceId, $data['open']);
        } catch (Throwable $e) {
            return Response::json(
                ['error' => 'persistence failed', 'detail' => $e->getMessage()],
                500
            );
        }

        return Response::json([
            'service' => $serviceId,
            'open' => $data['open'],
            'updated_at' => gmdate('c'),
        ]);
    }

    private function isAdminAuthorized(?string $authorizationHeader, ?string $cookieHeader): bool
    {
        if ($this->authorizeBearer($authorizationHeader)) {
            return true;
        }

        if (!$this->config->uiEnabled) {
            return false;
        }

        $cookie = $cookieHeader ?? ($_SERVER['HTTP_COOKIE'] ?? '');

        return $this->uiSession->isValid($cookie !== '' ? $cookie : null);
    }

    private function authorizeBearer(?string $authorizationHeader = null): bool
    {
        $header = $authorizationHeader ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return false;
        }

        return hash_equals($this->config->accessSwitchToken, $matches[1]);
    }
}
