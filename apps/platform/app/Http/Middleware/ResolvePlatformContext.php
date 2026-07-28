<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePlatformContext
{
    public function __construct(
        private readonly BusinessContext $businessContext,
        private readonly BranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $businesses = $user->ownedBusinesses()->with('branches')->orderBy('name')->get();
        $selectedBusinessId = (int) $request->session()->get('platform.business_id');
        $business = $businesses->firstWhere('id', $selectedBusinessId) ?? $businesses->first();

        $this->businessContext->set($business?->id);
        $request->attributes->set('platform.business', $business);

        $branches = $business?->branches->sortBy('name')->values() ?? collect();
        $selectedBranchId = (int) $request->session()->get('platform.branch_id');
        $branch = $branches->firstWhere('id', $selectedBranchId) ?? $branches->first();

        $this->branchContext->set($branch?->id);
        $request->attributes->set('platform.branch', $branch);

        if ($business) {
            $request->session()->put('platform.business_id', $business->id);
        }

        if ($branch) {
            $request->session()->put('platform.branch_id', $branch->id);
        }

        return $next($request);
    }
}
