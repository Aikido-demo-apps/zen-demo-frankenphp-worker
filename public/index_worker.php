<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Illuminate\Container\Container;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__ . '/../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$nbRequests = 0;

while (frankenphp_handle_request(function () use ($app, $kernel, &$nbRequests) {
    \aikido\worker_rinit();

    $sandbox = clone $app;
    $sandbox->instance('app', $sandbox);
    $sandbox->instance(Container::class, $sandbox);
    Container::setInstance($sandbox);
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication($sandbox);

    try {
        $request = Request::capture();
        $request->enableHttpMethodParameterOverride();
        $sandbox->instance('request', $request);

        giveNewAppToManagers($sandbox);

        $response = $kernel->handle($request);
        $response->send();
        $kernel->terminate($request, $response);

        if ($route = $request->route()) {
            if (method_exists($route, 'flushController')) {
                $route->flushController();
            }
        }
    } finally {
        if (method_exists($sandbox, 'forgetScopedInstances')) {
            $sandbox->forgetScopedInstances();
        }

        flushState($sandbox);

        $sandbox->flush();

        unset($sandbox, $request, $response, $route);

        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        if ((++$nbRequests % 100) === 0) {
            gc_collect_cycles();
            $nbRequests = 0;
        }
    }

    \aikido\worker_rshutdown();
})) {
    // keep looping
}

function giveNewAppToManagers(Application $sandbox): void
{
    $managers = ['db', 'cache', 'log', 'mail.manager', 'queue', 'filesystem', 'auth'];
    foreach ($managers as $binding) {
        if ($sandbox->resolved($binding)) {
            $manager = $sandbox->make($binding);
            if (method_exists($manager, 'setApplication')) {
                $manager->setApplication($sandbox);
            }
        }
    }

    if ($sandbox->resolved('router')) {
        $sandbox->make('router')->setContainer($sandbox);
    }

    if ($sandbox->resolved('url')) {
        $sandbox->make('url')->setRootControllerNamespace('');
    }

    if ($sandbox->resolved('view')) {
        $sandbox->make('view')->setContainer($sandbox);
    }

    if ($sandbox->resolved('validator')) {
        $sandbox->make('validator')->setContainer($sandbox);
    }
}

function flushState(Application $sandbox): void
{
    if ($sandbox->resolved('cookie')) {
        $sandbox->make('cookie')->flushQueuedCookies();
    }

    if ($sandbox->resolved('session')) {
        $driver = $sandbox->make('session');
        if (method_exists($driver, 'flush')) {
            $driver->flush();
        }
    }

    if ($sandbox->resolved('auth')) {
        $sandbox->make('auth')->forgetGuards();
    }

    if ($sandbox->resolved('db')) {
        foreach ($sandbox->make('db')->getConnections() as $connection) {
            $connection->flushQueryLog();
        }
    }

    if ($sandbox->resolved('log')) {
        $logger = $sandbox->make('log');
        if (method_exists($logger, 'flushSharedContext')) {
            $logger->flushSharedContext();
        }
    }

    Str::flushCache();

    if ($sandbox->resolved('view.engine.resolver')) {
        $resolver = $sandbox->make('view.engine.resolver');
        $resolver->forget('blade');
        $resolver->forget('php');
    }
}
