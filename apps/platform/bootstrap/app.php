<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureApiVersion;
use App\Http\Middleware\EnsureOnboardingIsCompleted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.version' => EnsureApiVersion::class,
            'account.active' => EnsureAccountIsActive::class,
            'onboarding.complete' => EnsureOnboardingIsCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, Throwable $e) => $request->is('api/*'));

        $exceptions->respond(function ($response, Throwable $e, $request) {
            if (! $request->is('api/*')) {
                return $response;
            }

            return response()->json([
                'success' => false,
                'message' => $response->exception?->getMessage() ?: 'The request could not be completed.',
                'code' => str($e::class)->classBasename()->snake()->upper()->toString(),
                'errors' => [],
                'trace_id' => (string) Str::uuid(),
            ], $response->getStatusCode());
        });
    })->create();
