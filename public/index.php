<?php

declare(strict_types=1);

use AccessSwitch\Application;
use AccessSwitch\Config;
use AccessSwitch\Http\Response;
use AccessSwitch\StateStore;

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
require is_file($vendorAutoload) ? $vendorAutoload : dirname(__DIR__) . '/src/autoload.php';

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$config = Config::fromEnvironment();
$app = new Application($config, new StateStore($config->stateFile, $config->defaultOpen));

try {
    $app->handle($method, $path)->send();
} catch (Throwable $e) {
    Response::json(['error' => 'internal error'], 500)->send();
}
