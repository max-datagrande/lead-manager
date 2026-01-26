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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
      'role' => \App\Http\Middleware\CheckRole::class,
    ]);
  })
  ->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
      $previous = $e->getPrevious();
      $controlledModels = [
        \App\Models\OfferwallMix::class,
      ];

      if ($previous instanceof ModelNotFoundException) {
        $modelClass = $previous->getModel();
        if (in_array($modelClass, $controlledModels)) {
          $ids = $previous->getIds();
          $id = is_array($ids) ? $ids[0] : $ids;
          $modelName = class_basename($modelClass);
          return response()->json([
            'message' => "$modelName with ID $id was not found.",
            'errors' => [
              'id' => ["No record with ID $id exists in $modelName."]
            ],
          ], 404);
        }
      }
    });
  })
  ->create();
