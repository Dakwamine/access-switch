<?php

declare(strict_types=1);

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\Http\Response;
use AccessSwitch\Paths;
use AccessSwitch\ServiceManager;
use AccessSwitch\RateLimiter;
use AccessSwitch\UiSession;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
require is_file($vendorAutoload) ? $vendorAutoload : dirname(__DIR__) . '/src/autoload.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$config = Config::fromEnvironment();
$paths = new Paths();
$services = ServiceManager::fromConfig($config, $paths);
$uiSession = new UiSession($config->uiSessionSecret, $config->uiSessionTtl, $config->uiCookieSecure);
$rateLimiter = new RateLimiter($paths->rateLimitDir());
$app = new Application($config, $services, $uiSession, $rateLimiter);

try {
    $app->handle($method, $path)->send();
} catch (Throwable $e) {
    Response::json(['error' => 'internal error'], 500)->send();
}
