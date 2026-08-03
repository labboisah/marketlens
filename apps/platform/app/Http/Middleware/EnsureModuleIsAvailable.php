<?php

namespace App\Http\Middleware;

use App\Domain\Modules\Services\ModuleAccessService;
use App\Domain\Tenancy\Models\Branch;
use App\Domain\Tenancy\Models\Business;
use App\Domain\Tenancy\Services\BranchContext;
use App\Domain\Tenancy\Services\BusinessContext;
use App\Enums\BusinessModule;
use App\Exceptions\ModuleUnavailableException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleIsAvailable
{
    public function __construct(
        private readonly BusinessContext $businessContext,
        private readonly BranchContext $branchContext,
        private readonly ModuleAccessService $moduleAccess,
    ) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $module = BusinessModule::from($module);
        $business = $request->attributes->get('platform.business')
            ?: Business::query()->find($this->businessContext->id());
        $branch = $request->attributes->get('platform.branch')
            ?: Branch::query()->find($this->branchContext->id());

        if (! $business) {
            throw new ModuleUnavailableException(new \App\Domain\Modules\Data\ModuleAccessResult(
                module: $module,
                planAllows: false,
                businessEnabled: false,
                branchEnabled: false,
                available: false,
                source: 'business',
                branchStatus: null,
                reason: 'business_context_missing',
            ));
        }

        $result = $this->moduleAccess->resolve($business, $module, $branch);

        if (! $result->available) {
            throw new ModuleUnavailableException($result);
        }

        return $next($request);
    }
}
