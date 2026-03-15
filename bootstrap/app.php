<?php

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTeamAccess;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        if (($_SERVER['APP_ENV'] ?? null) === 'testing') {
            $middleware->validateCsrfTokens(except: ['*']);
        }

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Register middleware aliases for route-level protection
        $middleware->alias([
            'role' => EnsureRole::class,
            'team.access' => EnsureTeamAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 404) {
                return Inertia::render('errors/NotFound')
                    ->toResponse(request())
                    ->setStatusCode(404);
            }

            if ($response->getStatusCode() === 403) {
                return Inertia::render('errors/Forbidden')
                    ->toResponse(request())
                    ->setStatusCode(403);
            }

            return $response;
        });
    })->create();
