<?php

namespace App\Domain\Services\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StaffEarningsController extends Controller
{
    public function index(Request $request)
    {
        // Return paginated staff earnings ledger (read-only)
        return response()->json(['data' => []]);
    }

    public function show(string $staffUuid)
    {
        // Return ledger for staff
        return response()->json(['data' => null]);
    }

    public function settlements(Request $request)
    {
        return response()->json(['data' => []]);
    }
}
