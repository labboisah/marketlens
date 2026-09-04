<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->accessibleBusinesses()->isEmpty()) {
            return redirect()->route('onboarding.wizard');
        }

        return $next($request);
    }
}
