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

// Worker loop: FrankenPHP will call this callback for each HTTP request
while (frankenphp_handle_request(function () use ($app) {
    \aikido\worker_rinit();

    if (class_exists(Facade::class)) {
        Facade::clearResolvedInstances();
    }

    $request = Request::capture();

    $app->instance('request', $request);

    $response = $app->handle($request);
    
    $app->terminate($request, $response);

    \aikido\worker_rshutdown();
    
    return $response;
})) {
    // keep looping
}
