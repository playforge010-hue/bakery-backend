<?php

namespace App\Http\Controllers\Api\V1\Customer;

use Illuminate\Http\JsonResponse;
use App\Http\Requests\Api\V1\Customer\CheckDeliveryAvailabilityRequest;
use App\Http\Requests\Api\V1\Customer\SearchDeliveryAreaRequest;
use App\Services\DeliveryPricingService;

/** Customer-facing location API operations. */
class CustomerLocationController extends CustomerApiControllerBase
{
    /** Return matching delivery areas for location autocomplete. */
    public function searchDeliveryArea(SearchDeliveryAreaRequest $request): JsonResponse
    {
        $search = strtolower((string) ($request->validated('search') ?? ''));
        $areas = collect($this->deliveryAreas())
            ->filter(fn (array $area) => $search === '' || str_contains(strtolower($area['name']), $search))
            ->values()
            ->all();

        return $this->successResponse('Delivery areas loaded successfully.', $areas);
    }

    /** Quote delivery for a map point using the configured distance ranges. */
    public function checkDeliveryAvailability(
        CheckDeliveryAvailabilityRequest $request,
        DeliveryPricingService $pricing
    ): JsonResponse
    {
        $data = $request->validated();

        return $this->successResponse(
            'Delivery availability checked successfully.',
            $pricing->quote((float) $data['latitude'], (float) $data['longitude'])
        );
    }
}
