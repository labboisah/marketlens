<?php

namespace App\Http\Middleware;

use App\Domain\Owners\Models\BusinessOwner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! BusinessOwner::query()->where('user_id', $request->user()?->id)->exists()) {
            return redirect()->route('onboarding.wizard');
        }

        return $next($request);
    }
}
