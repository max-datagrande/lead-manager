<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\BlockWebOnApiSubdomain;
use App\Http\Middleware\AuthHost;
use App\Http\Middleware\ForceApiHeaders;
use App\Http\Middleware\DomainWhitelistMiddleware;

return Application::configure(basePath: dirname(__DIR__))
  ->withRouting(
    health: '/up',
    apiPrefix: 'v1',
    web: __DIR__ . '/../routes/web.php',
    api: __DIR__ . '/../routes/api.php',
    commands: __DIR__ . '/../routes/console.php',
  )
  ->withMiddleware(function (Middleware $middleware) {
    $middleware->prepend(ForceApiHeaders::class);
    $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
    $middleware->web(
      prepend: [BlockWebOnApiSubdomain::class],
      append: [HandleAppearance::class, HandleInertiaRequests::class, AddLinkHeadersForPreloadedAssets::class],
    );
    $middleware->alias([
      'admin' => AdminMiddleware::class,
      'auth.host' => AuthHost::class,
      'domain.whitelist' => DomainWhitelistMiddleware::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    //
  })
  ->create();
