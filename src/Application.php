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
        private readonly RateLimiter $rateLimiter = new RateLimiter(),
    ) {
    }

    /**
     * @param string|null $body                 Override for tests (default: php://input on POST)
     * @param string|null $authorizationHeader  Override for tests (default: HTTP_AUTHORIZATION)
     * @param string|null $cookieHeader         Override for tests (default: HTTP_COOKIE)
     * @param string|null $clientIp             Override for tests (default: REMOTE_ADDR)
     */
    public function handle(
        string $method,
        string $path,
        ?string $body = null,
        ?string $authorizationHeader = null,
        ?string $cookieHeader = null,
        ?string $clientIp = null,
    ): Response {
        $path = rtrim($path, '/') ?: '/';

        return match ($method) {
            'GET' => $this->handleGet($path, $authorizationHeader, $cookieHeader),
            'POST' => $this->handlePost($path, $body, $authorizationHeader, $cookieHeader, $clientIp),
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
            '/ui' => $this->uiPage($cookieHeader),
            '/admin/status' => $this->adminStatus($authorizationHeader, $cookieHeader),
            default => Response::empty(404),
        };
    }

    private function handlePost(
        string $path,
        ?string $body,
        ?string $authorizationHeader,
        ?string $cookieHeader,
        ?string $clientIp,
    ): Response {
        return match ($path) {
            '/admin' => $this->admin($body, $authorizationHeader, $cookieHeader, $clientIp),
            '/ui/login' => $this->uiLogin($body, $cookieHeader, $clientIp),
            '/ui/logout' => $this->uiLogout(),
            '/ui/lang' => $this->uiSetLang($body),
            default => Response::empty(404),
        };
    }

    private function uiPage(?string $cookieHeader): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        try {
            $lang = $this->resolveUiLang($cookieHeader);

            return Response::html(UiPage::html($lang));
        } catch (Throwable) {
            $lang = $this->resolveUiLang($cookieHeader);

            return Response::json(['error' => $this->uiError($lang, 'error.ui_unavailable')], 503);
        }
    }

    private function adminStatus(?string $authorizationHeader, ?string $cookieHeader): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        $lang = $this->resolveUiLang($cookieHeader);

        if (!$this->isAdminAuthorized($authorizationHeader, $cookieHeader)) {
            return Response::json(['error' => $this->uiError($lang, 'error.unauthorized')], 401);
        }

        $services = [];
        foreach ($this->registry->all() as $serviceId) {
            if (!$this->registry->isAuthorizedForAdmin($serviceId)) {
                continue;
            }

            try {
                $state = $this->store->getState($serviceId);
            } catch (Throwable) {
                return Response::json(
                    [
                        'error' => $this->uiError($lang, 'error.state_read_failed', ['service' => $serviceId]),
                        'service' => $serviceId,
                    ],
                    503
                );
            }

            $services[] = [
                'service' => $serviceId,
                'open' => $state['open'],
                'updated_at' => $state['updated_at'],
            ];
        }

        return Response::json(['services' => $services]);
    }

    private function uiLogin(?string $body, ?string $cookieHeader, ?string $clientIp): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        $lang = $this->resolveUiLang($cookieHeader);

        if ($rateLimited = $this->rateLimitResponse($clientIp, $lang, true)) {
            return $rateLimited;
        }

        if ($this->config->accessSwitchToken === '') {
            return Response::json(
                ['error' => $this->uiError($lang, 'error.token_not_configured')],
                503
            );
        }

        $body ??= file_get_contents('php://input');
        if ($body === false || $body === '') {
            return Response::json(['error' => $this->uiError($lang, 'error.json_body_required')], 400);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return Response::json(['error' => $this->uiError($lang, 'error.invalid_json')], 400);
        }

        if (!is_array($data) || !array_key_exists('token', $data) || !is_string($data['token'])) {
            return Response::json(['error' => $this->uiError($lang, 'error.token_field_required')], 400);
        }

        if (!hash_equals($this->config->accessSwitchToken, $data['token'])) {
            return Response::json(['error' => $this->uiError($lang, 'error.login_denied')], 401);
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

    private function uiSetLang(?string $body): Response
    {
        if (!$this->config->uiEnabled) {
            return Response::empty(404);
        }

        $lang = $this->resolveUiLang(null);

        $body ??= file_get_contents('php://input');
        if ($body === false || $body === '') {
            return Response::json(['error' => $this->uiError($lang, 'error.json_body_required')], 400);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return Response::json(['error' => $this->uiError($lang, 'error.invalid_json')], 400);
        }

        if (!is_array($data) || !array_key_exists('lang', $data) || !is_string($data['lang'])) {
            return Response::json(['error' => $this->uiError($lang, 'error.invalid_lang')], 400);
        }

        if (!UiLocale::isSupported($data['lang'])) {
            return Response::json(['error' => $this->uiError($lang, 'error.invalid_lang')], 400);
        }

        return Response::jsonWithHeaders(
            ['ok' => true],
            200,
            UiLocale::setCookieHeaders($data['lang'], $this->config->uiCookieSecure)
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

    private function admin(?string $body, ?string $authorizationHeader, ?string $cookieHeader, ?string $clientIp): Response
    {
        $lang = $this->adminErrorLang($cookieHeader);

        if ($rateLimited = $this->rateLimitResponse($clientIp, $lang, false)) {
            return $rateLimited;
        }

        if ($this->config->accessSwitchToken === '') {
            return Response::json(
                ['error' => $this->adminError($lang, 'error.token_not_configured')],
                503
            );
        }

        if (!$this->isAdminAuthorized($authorizationHeader, $cookieHeader)) {
            $key = UiLocale::extractFromCookie($cookieHeader) !== null
                ? 'error.session_expired'
                : 'error.unauthorized';

            return Response::json(['error' => $this->adminError($lang, $key)], 401);
        }

        $body ??= file_get_contents('php://input');
        if ($body === false || $body === '') {
            return Response::json(['error' => $this->adminError($lang, 'error.json_body_required')], 400);
        }

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return Response::json(['error' => $this->adminError($lang, 'error.invalid_json')], 400);
        }

        if (!is_array($data) || !array_key_exists('open', $data)) {
            return Response::json(['error' => $this->adminError($lang, 'error.open_field_required')], 400);
        }

        if (!is_bool($data['open'])) {
            return Response::json(['error' => $this->adminError($lang, 'error.open_must_be_boolean')], 400);
        }

        $serviceId = ServiceRegistry::DEFAULT_SERVICE_ID;
        if (array_key_exists('service', $data)) {
            if (!is_string($data['service'])) {
                return Response::json(['error' => $this->adminError($lang, 'error.service_must_be_string')], 400);
            }
            $serviceId = $data['service'];
        }

        if (!$this->registry->validateServiceId($serviceId)) {
            return Response::json(['error' => $this->adminError($lang, 'error.invalid_service_id')], 400);
        }

        if (!$this->registry->isAuthorizedForAdmin($serviceId)) {
            return Response::json(['error' => $this->adminError($lang, 'error.unknown_service')], 400);
        }

        try {
            $this->store->setOpen($serviceId, $data['open']);
        } catch (Throwable $e) {
            return Response::json(
                ['error' => $this->adminError($lang, 'error.persistence_failed'), 'detail' => $e->getMessage()],
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

    private function resolveUiLang(?string $cookieHeader): string
    {
        $cookie = $cookieHeader ?? ($_SERVER['HTTP_COOKIE'] ?? '');
        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null;

        return UiLocale::resolve($cookie !== '' ? $cookie : null, $acceptLanguage);
    }

    private function adminErrorLang(?string $cookieHeader): ?string
    {
        $cookie = $cookieHeader ?? ($_SERVER['HTTP_COOKIE'] ?? '');

        return UiLocale::extractFromCookie($cookie !== '' ? $cookie : null);
    }

    /** @param array<string, string|int> $vars */
    private function uiError(string $lang, string $key, array $vars = []): string
    {
        return UiLocale::get($lang, $key, $vars);
    }

    /** @param array<string, string|int> $vars */
    private function adminError(?string $lang, string $key, array $vars = []): string
    {
        if ($lang === null) {
            return UiLocale::get('en', $key, $vars);
        }

        return UiLocale::get($lang, $key, $vars);
    }

    private function rateLimitResponse(?string $clientIp, ?string $lang, bool $uiContext): ?Response
    {
        $ip = $clientIp ?? ClientIp::resolve(
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['HTTP_X_REAL_IP'] ?? null,
            $this->config->trustedProxies,
        );
        if ($ip === '') {
            return null;
        }

        $key = 'auth:' . $ip;
        if ($this->rateLimiter->isAllowed(
            $key,
            $this->config->rateLimitMaxAttempts,
            $this->config->rateLimitWindowSeconds,
        )) {
            return null;
        }

        $errorKey = 'error.rate_limited';
        $error = $uiContext
            ? $this->uiError($lang ?? 'en', $errorKey)
            : $this->adminError($lang, $errorKey);

        return Response::json(['error' => $error], 429);
    }
}
