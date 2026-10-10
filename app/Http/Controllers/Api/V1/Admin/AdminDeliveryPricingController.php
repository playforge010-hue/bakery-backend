<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\WriteAdminAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\DeliveryFeeRangeRequest;
use App\Http\Resources\Api\V1\Admin\DeliveryFeeRangeResource;
use App\Models\DeliveryFeeRange;
use App\Services\Admin\AdminDeliveryPricingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

/** Explicit GET/POST handlers for bakery distance fee administration. */
class AdminDeliveryPricingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly AdminDeliveryPricingService $pricing)
    {
    }

    /** List distance ranges for the administration table, ordered by distance. */
    public function getDeliveryFeeRanges(): JsonResponse
    {
        $ranges = DeliveryFeeRange::query()->orderBy('minimum_distance_km')->orderBy('sort_order')->get();

        return $this->successResponse('Delivery fee ranges loaded.', DeliveryFeeRangeResource::collection($ranges)->resolve());
    }

    /** Create a distance fee using a complete validated range payload. */
    public function createDeliveryFeeRange(DeliveryFeeRangeRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $range = $this->pricing->saveRange($request->validated());
        $audit->execute($request, 'delivery_fee_range.created', $range);

        return $this->successResponse('Delivery fee range created.', (new DeliveryFeeRangeResource($range))->resolve(), [], 201);
    }

    /** Update the fee, interval and delivery estimate of the range identified by id. */
    public function updateDeliveryFeeRange(DeliveryFeeRangeRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $range = $this->pricing->saveRange($data, (int) $data['id']);
        $audit->execute($request, 'delivery_fee_range.updated', $range);

        return $this->successResponse('Delivery fee range updated.', (new DeliveryFeeRangeResource($range))->resolve());
    }

    /** Change an existing range's active flag after overlap validation. */
    public function updateDeliveryFeeRangeStatus(DeliveryFeeRangeRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $range = $this->pricing->changeStatus((int) $data['id'], (bool) $data['is_active']);
        $audit->execute($request, 'delivery_fee_range.status_changed', $range);

        return $this->successResponse('Delivery fee range status updated.', (new DeliveryFeeRangeResource($range))->resolve());
    }

    /** Remove a range identified by id without changing captured order fees. */
    public function deleteDeliveryFeeRange(DeliveryFeeRangeRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $range = $this->pricing->deleteRange((int) $request->validated()['id']);
        $audit->execute($request, 'delivery_fee_range.deleted', $range);

        return $this->successResponse('Delivery fee range deleted.', []);
    }
}
