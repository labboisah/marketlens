<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasActiveAccount()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account cannot access business records.',
                    'code' => 'ACCOUNT_INACTIVE',
                    'errors' => [],
                    'trace_id' => (string) str()->uuid(),
                ], 403);
            }

            return redirect()->route('account.disabled');
        }

        return $next($request);
    }
}
