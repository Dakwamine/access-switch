<?php

declare(strict_types=1);

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\Http\Response;
use AccessSwitch\Paths;
use AccessSwitch\ServiceRegistry;
use AccessSwitch\ServiceStateStore;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
require is_file($vendorAutoload) ? $vendorAutoload : dirname(__DIR__) . '/src/autoload.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$config = Config::fromEnvironment();
$paths = new Paths();
$registry = ServiceRegistry::fromConfig($config, $paths);
$store = new ServiceStateStore($paths, $config->defaultOpen);
$app = new Application($config, $registry, $store);

try {
    $app->handle($method, $path)->send();
} catch (Throwable $e) {
    Response::json(['error' => 'internal error'], 500)->send();
}
