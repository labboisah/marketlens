<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $businessIds = $user->ownedBusinesses()->pluck('businesses.id')->all();

        $validated = $request->validate([
            'business_id' => ['required', 'integer', Rule::in($businessIds)],
            'branch_id' => ['nullable', 'integer'],
            'date_range' => ['nullable', 'string', Rule::in(['today', '7_days', '30_days', 'month'])],
        ]);

        $business = $user->ownedBusinesses()
            ->with('branches')
            ->whereKey($validated['business_id'])
            ->firstOrFail();

        $branchIds = $business->branches->pluck('id')->all();
        $branchId = (int) ($validated['branch_id'] ?? 0);

        if ($branchId !== 0 && ! in_array($branchId, $branchIds, true)) {
            abort(403);
        }

        $request->session()->put('platform.business_id', $business->id);
        $request->session()->put('platform.branch_id', $branchId ?: ($branchIds[0] ?? null));
        $request->session()->put('platform.date_range', $validated['date_range'] ?? 'today');

        return back();
    }
}
