<?php

namespace App\Http\Middleware;

use App\Domain\Legal\Services\LegalDocumentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalDocumentsAreAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('legal.*') || $request->routeIs('logout') || $request->routeIs('verification.*')) {
            return $next($request);
        }

        if (app(LegalDocumentService::class)->userRequiresAcceptance($user)) {
            return redirect()->route('legal.acceptance');
        }

        return $next($request);
    }
}
