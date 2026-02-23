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

/** @var Application $baseApp */
$baseApp = require __DIR__ . '/../bootstrap/app.php';

$nbRequests = 0;

while (frankenphp_handle_request(function () use ($baseApp, &$nbRequests) {
    \aikido\worker_rinit();

    // Clone the application for this request(like Octane)
    // This provides better isolation than just clearing facades
    $app = clone $baseApp;
    
    try {
        Facade::clearResolvedInstances();
        
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        
        $request = Request::capture();
        $response = $kernel->handle($request);
        
        $response->send();
        
        $kernel->terminate($request, $response);
        
        $nbRequests++;
        
    } finally {
        if (method_exists($app, 'flush')) {
            $app->flush();
        }
        
        $baseApp->make('view.engine.resolver')->forget('blade');
        $baseApp->make('view.engine.resolver')->forget('php');
        
        unset($app, $kernel, $request, $response);
        
        // Periodic garbage collection
        if (($nbRequests % 100) === 0 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    \aikido\worker_rshutdown();
})) {
    // keep looping
}