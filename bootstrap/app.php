<?php

use App\Exceptions\ModuleUnavailableException;
use App\Http\Middleware\AttachTraceId;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureApiVersion;
use App\Http\Middleware\EnsureLegalDocumentsAreAccepted;
use App\Http\Middleware\EnsureModuleIsAvailable;
use App\Http\Middleware\EnsureOnboardingIsCompleted;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\ResolvePlatformContext;
use App\Support\Observability\SensitiveDataSanitizer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AttachTraceId::class);

        $middleware->alias([
            'api.version' => EnsureApiVersion::class,
            'account.active' => EnsureAccountIsActive::class,
            'onboarding.complete' => EnsureOnboardingIsCompleted::class,
            'legal.accepted' => EnsureLegalDocumentsAreAccepted::class,
            'super.admin' => EnsureUserIsSuperAdmin::class,
            'platform.context' => ResolvePlatformContext::class,
            'module.available' => EnsureModuleIsAvailable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn ($request, Throwable $e) => $request->is('api/*'));
        $exceptions->render(function (ModuleUnavailableException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => 'MODULE_UNAVAILABLE',
                    'errors' => [],
                    'trace_id' => $request->attributes->get('trace_id') ?: (string) Str::uuid(),
                    'meta' => ['module_access' => $e->access->toArray()],
                ], 403);
            }

            return redirect()->route('platform.permission-denied')->with('module_unavailable', $e->getMessage());
        });
        $exceptions->report(function (Throwable $e): void {
            $request = request();

            Log::channel('exceptions')->error('platform.exception.unhandled', app(SensitiveDataSanitizer::class)->sanitize([
                'trace_id' => $request?->attributes->get('trace_id'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'path' => $request ? '/'.ltrim($request->path(), '/') : null,
                'route' => $request?->route()?->getName(),
                'user_id' => $request?->user()?->id,
            ]));
        });

        $exceptions->respond(function ($response, Throwable $e, $request) {
            if ($e instanceof ModuleUnavailableException) {
                return $response;
            }

            if (! $request->is('api/*')) {
                return $response;
            }

            return response()->json([
                'success' => false,
                'message' => $response->exception?->getMessage() ?: 'The request could not be completed.',
                'code' => str($e::class)->classBasename()->snake()->upper()->toString(),
                'errors' => [],
                'trace_id' => $request->attributes->get('trace_id') ?: (string) Str::uuid(),
            ], $response->getStatusCode());
        });
    })->create();
