<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminDashboardFiltersRequest;
use App\Services\Admin\AdminDashboardService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

/** Return date-filtered business KPIs and chart data for the admin dashboard. */
class AdminDashboardController extends Controller
{
    use ApiResponseTrait;

    /** Build registered-customer, order, paid-revenue and status statistics. */
    public function getDashboardSummary(
        AdminDashboardFiltersRequest $request,
        AdminDashboardService $dashboard
    ): JsonResponse {
        return $this->successResponse(
            'Dashboard summary loaded.',
            $dashboard->summarize($request->validated())
        );
    }
}
