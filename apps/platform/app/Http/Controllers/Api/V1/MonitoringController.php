<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Monitoring\DashboardRequest;
use App\Http\Resources\Api\SuccessResource;

class MonitoringController extends Controller
{
    public function dashboard(DashboardRequest $request): SuccessResource
    {
        return new SuccessResource([
            'filters' => $request->validated(),
            'sales_summary' => null,
            'stock_alerts' => [],
            'branches' => [],
            'last_synchronization' => null,
        ]);
    }

    public function branches(): SuccessResource
    {
        return new SuccessResource(['branches' => []]);
    }
}
