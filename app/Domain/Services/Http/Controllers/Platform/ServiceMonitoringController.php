<?php

namespace App\Domain\Services\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceMonitoringController extends Controller
{
    public function dashboard(Request $request)
    {
        // Read-only dashboard JSON placeholder. Real implementation should
        // use cached summaries from ServiceMonitoringSummary.
        return response()->json([
            'status' => 'ok',
            'data' => [
                'services_rendered' => 0,
                'services_completed' => 0,
                'in_progress' => 0,
                'service_revenue' => 0,
                'direct_expenses' => 0,
                'distributable_amount' => 0,
                'staff_share' => 0,
                'business_share' => 0,
                'expense_reserve' => 0,
                'outstanding_payments' => 0,
                'pending_staff_earnings' => 0,
                'settled_staff_earnings' => 0,
                'average_service_value' => 0,
                'last_sync' => null,
            ],
        ]);
    }

    public function orders(Request $request)
    {
        // Return paginated replicated_service_orders records (read-only)
        $perPage = (int) $request->get('per_page', 25);
        // Placeholder: real implementation should query replicated_service_orders
        return response()->json([ 'data' => [], 'meta' => ['per_page' => $perPage] ]);
    }

    public function showOrder(string $uuid)
    {
        // Show a single replicated service order by source UUID
        return response()->json(['data' => null]);
    }

    public function payments(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function expenses(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function allocations(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function settlements(Request $request)
    {
        return response()->json(['data' => []]);
    }
}
