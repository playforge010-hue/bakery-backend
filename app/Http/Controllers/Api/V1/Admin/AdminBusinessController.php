<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\WriteAdminAuditLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\AdminBusinessActionRequest;
use App\Http\Resources\Api\V1\Admin\BusinessSettingsResource;
use App\Models\BusinessHoliday;
use App\Models\BusinessSetting;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\OpeningHour;
use App\Models\Order;
use App\Services\Admin\AdminBusinessSettingsService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Manage bakery profile, opening schedule, delivery boundaries, slots and holidays. */
class AdminBusinessController extends Controller
{
    use ApiResponseTrait;

    /** Return editable bakery settings and configured public media URLs. */
    public function getBusinessSettings(): JsonResponse
    {
        $settings = BusinessSetting::query()->first();

        if (! $settings) {
            return $this->successResponse('Business settings loaded.', []);
        }

        return $this->successResponse('Business settings loaded.', (new BusinessSettingsResource($settings))->resolve());
    }

    /** Save business details, operating switches and optional logo/cover image uploads. */
    public function updateBusinessSettings(
        AdminBusinessActionRequest $request,
        WriteAdminAuditLog $audit,
        AdminBusinessSettingsService $settings
    ): JsonResponse
    {
        $record = $settings->updateSettings($request->validated());
        $audit->execute($request, 'business.settings_updated', $record);

        return $this->successResponse('Business settings updated.', (new BusinessSettingsResource($record))->resolve());
    }

    /** List all weekday opening windows in weekday order. */
    public function getOpeningHours(): JsonResponse
    {
        return $this->successResponse('Opening hours loaded.', OpeningHour::query()->orderBy('day_of_week')->get([
            'day_of_week',
            'opens_at',
            'closes_at',
            'is_closed',
        ]));
    }

    /** Save the seven weekday schedule rows as one atomic operation. */
    public function updateOpeningHours(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($data) {
            foreach ($data['hours'] as $hour) {
                OpeningHour::query()->updateOrCreate(['day_of_week' => $hour['day_of_week']], $hour);
            }
        });
        $audit->execute($request, 'business.opening_hours_updated');

        return $this->getOpeningHours();
    }

    /** List delivery zones and their fee, distance, and pincode criteria. */
    public function getDeliveryZones(): JsonResponse
    {
        $zones = DeliveryZone::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (DeliveryZone $zone) => [
                'id' => $zone->id,
                'name' => $zone->name,
                'area_names' => $zone->area_names_json,
                'pincodes' => $zone->pincodes_json,
                'center_latitude' => $zone->center_latitude,
                'center_longitude' => $zone->center_longitude,
                'radius_km' => $zone->radius_km,
                'minimum_order_amount' => $zone->minimum_order_amount,
                'delivery_fee' => $zone->delivery_fee,
                'free_delivery_above' => $zone->free_delivery_above,
                'estimated_delivery_minutes' => $zone->estimated_delivery_minutes,
                'is_active' => $zone->is_active,
                'sort_order' => $zone->sort_order,
            ]);

        return $this->successResponse('Delivery zones loaded.', $zones);
    }

    /** Create or update a delivery zone from explicit form fields. */
    public function saveDeliveryZone(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        $data['area_names_json'] = $data['area_names'] ?? null;
        $data['pincodes_json'] = $data['pincodes'] ?? null;
        unset($data['area_names'], $data['pincodes']);
        $zone = DeliveryZone::query()->updateOrCreate(['id' => $id], $data);
        $audit->execute($request, 'delivery_zone.saved', $zone);

        return $this->successResponse('Delivery zone saved.', [
            'id' => $zone->id,
            'name' => $zone->name,
            'area_names_json' => $zone->area_names_json,
            'pincodes_json' => $zone->pincodes_json,
            'delivery_fee' => (float) $zone->delivery_fee,
            'minimum_order_amount' => $zone->minimum_order_amount
                ? (float) $zone->minimum_order_amount
                : null,
            'is_active' => $zone->is_active,
        ]);
    }

    /** Create a delivery zone. */
    public function createDeliveryZone(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveDeliveryZone($request, $audit);
    }

    /** Update a delivery zone. */
    public function updateDeliveryZone(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveDeliveryZone($request, $audit);
    }

    /** Toggle a zone's eligibility without changing its historical orders. */
    public function updateDeliveryZoneStatus(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $d = $request->validated();
        $zone = DeliveryZone::query()->findOrFail($d['id']);
        $zone->is_active = $d['is_active'];
        $zone->save();
        $audit->execute($request, 'delivery_zone.status_changed', $zone);

        return $this->successResponse('Delivery zone status updated.', ['id' => $zone->id, 'is_active' => $zone->is_active]);
    }

    /** Delete a zone only when no historical order points to it. */
    public function deleteDeliveryZone(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $deliveryZoneId = $request->validated()['id'];
        $deliveryZone = DeliveryZone::query()->findOrFail($deliveryZoneId);
        $hasOrderHistory = Order::query()
            ->where('delivery_zone_id', $deliveryZone->id)
            ->exists();

        if ($hasOrderHistory) {
            return $this->errorResponse('This zone has order history and cannot be deleted.', [], [], 422);
        }

        $deliveryZone->delete();
        $audit->execute($request, 'delivery_zone.deleted', $deliveryZone);

        return $this->successResponse('Delivery zone deleted.', []);
    }

    /** List configured holiday dates and exception hours. */
    public function getHolidayList(): JsonResponse
    {
        $holidays = BusinessHoliday::query()
            ->orderBy('holiday_date')
            ->get(['id', 'holiday_date', 'title', 'opens_at', 'closes_at', 'is_closed', 'note']);

        return $this->successResponse('Business holidays loaded.', $holidays);
    }

    /** Add or edit a holiday and its special opening behavior. */
    public function saveBusinessHoliday(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        $holiday = BusinessHoliday::query()->updateOrCreate(['id' => $id], $data);
        $audit->execute($request, 'business.holiday_saved', $holiday);

        return $this->successResponse('Holiday saved.', [
            'id' => $holiday->id,
            'holiday_date' => $holiday->holiday_date?->toDateString(),
            'title' => $holiday->title,
            'is_closed' => $holiday->is_closed,
        ]);
    }

    /** Route handler for creating a holiday. */
    public function createHoliday(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        return $this->saveBusinessHoliday($request, $audit);
    }

    /** Delete a holiday schedule entry. */
    public function deleteHoliday(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $holiday = BusinessHoliday::query()->findOrFail($request->validated()['id']);
        $holiday->delete();
        $audit->execute($request, 'business.holiday_deleted', $holiday);

        return $this->successResponse('Business holiday deleted.', []);
    }

    /** List delivery/takeaway time slots for the slot management screen. */
    public function getDeliverySlots(): JsonResponse
    {
        $deliverySlots = DeliverySlot::query()
            ->orderBy('sort_order')
            ->get(['id', 'fulfillment_type', 'label', 'starts_at', 'ends_at', 'max_orders', 'is_active', 'sort_order']);

        return $this->successResponse('Delivery slots loaded.', $deliverySlots);
    }

    /** Create or update a fulfillment slot and return its public schedule fields. */
    public function saveDeliverySlot(AdminBusinessActionRequest $request, WriteAdminAuditLog $audit): JsonResponse
    {
        $data = $request->validated();
        $id = $data['id'] ?? null;
        unset($data['id']);
        $slot = DeliverySlot::query()->updateOrCreate(['id' => $id], $data);
        $audit->execute($request, 'delivery_slot.saved', $slot);

        return $this->successResponse(
            'Delivery slot saved.',
            $slot->only(['id', 'fulfillment_type', 'label', 'starts_at', 'ends_at', 'max_orders', 'is_active'])
        );
    }
}
