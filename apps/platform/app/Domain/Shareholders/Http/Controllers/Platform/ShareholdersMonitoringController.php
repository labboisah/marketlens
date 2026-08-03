<?php

namespace App\Domain\Shareholders\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ShareholdersMonitoringController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function show(string $uuid)
    {
        return response()->json(['data' => null]);
    }

    public function ownership(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function profitPeriods(Request $request)
    {
        return response()->json(['data' => []]);
    }

    public function profitDistributions(Request $request)
    {
        return response()->json(['data' => []]);
    }
}
