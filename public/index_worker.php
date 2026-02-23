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
    $request = null;
    $response = null;

    \aikido\worker_rinit();

    try {
        if (class_exists(Facade::class)) {
            Facade::clearResolvedInstances();
        }

        $request = Request::capture();
        $app->instance('request', $request);

        $response = $app->handle($request);

        // Send response (may fail if client/proxy closed connection)
        $response->send();
    } catch (\Throwable $e) {
        // NEVER let exceptions escape the worker loop
        error_log((string) $e);
    } finally {
        // Laravel post-response lifecycle (only if we have both request and response)
        if ($request !== null && $response !== null) {
            try {
                $app->terminate($request, $response);
            } catch (\Throwable $e) {
                error_log((string) $e);
            }
        }

        // Periodic cycle collection for long-running workers
        if ((++$nbRequests % 100) === 0 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        // Always run your per-request shutdown emulation
        \aikido\worker_rshutdown();
    }
})) {
    // keep looping
}
