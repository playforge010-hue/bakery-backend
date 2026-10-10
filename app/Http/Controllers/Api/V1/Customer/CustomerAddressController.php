<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Models\BusinessSetting;
use App\Http\Requests\Api\V1\Customer\CreateCustomerAddressRequest;
use App\Http\Requests\Api\V1\Customer\DeleteCustomerAddressRequest;
use App\Http\Requests\Api\V1\Customer\UpdateCustomerAddressRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** Customer-facing address API operations. */
class CustomerAddressController extends CustomerApiControllerBase
{
    /** Return saved addresses belonging to the authenticated customer. */
    public function getAddressList(): JsonResponse
    {
        return $this->successResponse('Address list loaded successfully.', $this->addresses());
    }

    /** Save a customer-selected delivery address. */
    public function createAddress(CreateCustomerAddressRequest $request): JsonResponse
    {
        $user = $this->customerUser();
        $data = $request->validated();
        $business = BusinessSetting::query()->first();
        $id = DB::table('customer_addresses')->insertGetId([
            'user_id' => $user->id,
            'label' => $data['label'] ?? null,
            'receiver_name' => $data['receiver_name'],
            'receiver_mobile_number' => $data['receiver_mobile_number'],
            'address_line_1' => $data['address_line_1'] ?? $data['address'],
            'address_line_2' => $data['address_line_2'] ?? null,
            'landmark' => $data['landmark'] ?? null,
            'area' => $data['area'],
            'city' => $data['city'] ?? $business?->city,
            'state' => $data['state'] ?? $business?->state,
            'pincode' => $data['pincode'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->successResponse('Address saved successfully.', ['id' => $id], [], 201);
    }

    /** Update an address owned by the authenticated customer. */
    public function updateAddress(UpdateCustomerAddressRequest $request): JsonResponse
    {
        $data = $request->validated();
        $address = DB::table('customer_addresses')
            ->where('id', $data['address_id'])
            ->where('user_id', $this->customerUser()->id)
            ->whereNull('deleted_at')
            ->first();

        if (! $address) {
            return $this->errorResponse('Address not found.', [], [], 404);
        }

        unset($data['address_id']);
        if (array_key_exists('address', $data)) {
            $data['address_line_1'] = $data['address'];
            unset($data['address']);
        }
        $data['updated_at'] = now();
        DB::table('customer_addresses')->where('id', $address->id)->update($data);

        return $this->successResponse('Address updated successfully.', ['id' => $address->id]);
    }

    /** Soft-delete an address owned by the authenticated customer. */
    public function deleteAddress(DeleteCustomerAddressRequest $request): JsonResponse
    {
        $addressId = $request->validated('address_id');
        $updated = DB::table('customer_addresses')
            ->where('id', $addressId)
            ->where('user_id', $this->customerUser()->id)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        if (! $updated) {
            return $this->errorResponse('Address not found.', [], [], 404);
        }

        return $this->successResponse('Address deleted successfully.', ['address_id' => (int) $addressId]);
    }
}
