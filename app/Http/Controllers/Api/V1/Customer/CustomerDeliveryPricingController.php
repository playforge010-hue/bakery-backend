<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\DeliveryQuoteRequest;
use App\Services\DeliveryPricingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;

/** Share the authoritative distance quote used again when checkout places an order. */
class CustomerDeliveryPricingController extends Controller
{
    use ApiResponseTrait;

    /** Quote a supplied latitude/longitude pair; unavailable delivery returns validation errors. */
    public function checkDeliveryAvailability(DeliveryQuoteRequest $request, DeliveryPricingService $pricing): JsonResponse
    {
        $data = $request->validated();

        return $this->successResponse(
            'Delivery availability checked successfully.',
            $pricing->quote((float) $data['latitude'], (float) $data['longitude'])
        );
    }
}
