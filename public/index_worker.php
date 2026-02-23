<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

define('LARAVEL_START', microtime(true));

// Maintenance mode
if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Autoload + bootstrap once
require __DIR__ . '/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$nbRequests = 0;

while (frankenphp_handle_request(function () use ($app, &$nbRequests) {
    \aikido\worker_rinit();

    $request = Request::capture();
    $app->instance('request', $request);

    $response = $app->handle($request);

    $response->send();
    
    $app->terminate($request, $response);

    if ((++$nbRequests % 100) === 0 && function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }

    \aikido\worker_rshutdown();
})) {
    // keep looping
}