<?php

namespace App\Http\Requests\Api\V1\Admin;

/** Validate bakery settings, opening hours, delivery zones, slot schedules and holidays. */
class AdminBusinessActionRequest extends AdminFormRequest
{
    /** Route role middleware authorizes admin access before settings validation. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> Field allowlists for the requested business operation. */
    public function rules(): array
    {
        return match (basename($this->path())) {
            'update-business-settings' => ['bakery_name' => [
                'required',
                'string',
                'max:190',
            ],
                'address_line_1' => [
                    'required',
                    'string',
                    'max:190',
                ],
                'address_line_2' => [
                    'nullable',
                    'string',
                    'max:190',
                ],
                'area' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'city' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'state' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'pincode' => [
                    'required',
                    'string',
                    'max:20',
                ],
                'latitude' => [
                    'nullable',
                    'required_if:delivery_available,1,true',
                    'required_with:longitude',
                    'numeric',
                    'between:-90,90',
                ],
                'longitude' => [
                    'nullable',
                    'required_if:delivery_available,1,true',
                    'required_with:latitude',
                    'numeric',
                    'between:-180,180',
                ],
                'phone_number' => [
                    'required',
                    'string',
                    'max:30',
                ],
                'whatsapp_number' => [
                    'required',
                    'string',
                    'max:30',
                ],
                'email' => [
                    'nullable',
                    'email',
                ],
                'gst_number' => [
                    'nullable',
                    'string',
                    'max:40',
                ],
                'fssai_number' => [
                    'nullable',
                    'string',
                    'max:40',
                ],
                'minimum_order_amount' => [
                    'required',
                    'numeric',
                    'decimal:0,2',
                    'min:0',
                    'max:99999999.99',
                ],
                'max_delivery_distance_km' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999.99'],
                'default_preparation_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                    'max:2147483647',
                ],
                'delivery_available' => [
                    'required',
                    'boolean',
                ],
                'takeaway_available' => [
                    'required',
                    'boolean',
                ],
                'online_payment_available' => [
                    'required',
                    'boolean',
                ],
                'cod_available' => [
                    'required',
                    'boolean',
                ],
                'upi_available' => [
                    'required',
                    'boolean',
                ],
                'maintenance_mode' => [
                    'required',
                    'boolean',
                ],
                'maintenance_message' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
                'social_links_json' => [
                    'nullable',
                    'array:instagram,facebook,youtube,website',
                ],
                'social_links_json.*' => ['nullable', 'url:http,https', 'max:2048'],
                'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],
                'cover_image' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:8192',
                ]],
            'update-opening-hours' => ['hours' => [
                'required',
                'array',
                'size:7',
            ],
                'hours.*.day_of_week' => [
                    'required',
                    'integer',
                    'between:0,6',
                    'distinct',
                ],
                'hours.*.opens_at' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'hours.*.closes_at' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'hours.*.is_closed' => [
                    'required',
                    'boolean',
                ]],
            'create-zone',
            'update-zone' => ['id' => [
                'sometimes',
                'integer',
                'exists:delivery_zones,id',
            ],
                'name' => [
                    'required',
                    'string',
                    'max:190',
                ],
                'area_names' => [
                    'nullable',
                    'array',
                ],
                'pincodes' => [
                    'nullable',
                    'array',
                ],
                'center_latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],
                'center_longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],
                'radius_km' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                ],
                'minimum_order_amount' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'delivery_fee' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
                'free_delivery_above' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'estimated_delivery_minutes' => [
                    'required',
                    'integer',
                    'min:0',
                ],
                'is_active' => [
                    'sometimes',
                    'boolean',
                ],
                'sort_order' => [
                    'sometimes',
                    'integer',
                    'min:0',
                ]],
            'update-zone-status' => ['id' => ['required', 'integer', 'exists:delivery_zones,id'],
                'is_active' => ['required', 'boolean']],
            'delete-zone',
            'delete-holiday' => ['id' => ['required', 'integer', 'min:1']],
            'create-holiday' => ['id' => [
                'sometimes',
                'integer',
                'exists:business_holidays,id',
            ],
                'holiday_date' => [
                    'required',
                    'date',
                ],
                'title' => [
                    'required',
                    'string',
                    'max:190',
                ],
                'opens_at' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'closes_at' => [
                    'nullable',
                    'date_format:H:i',
                ],
                'is_closed' => [
                    'sometimes',
                    'boolean',
                ],
                'note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ]],
            'save-delivery-slot' => ['id' => [
                'sometimes',
                'integer',
                'exists:delivery_slots,id',
            ],
                'fulfillment_type' => [
                    'required',
                    'in:delivery,takeaway',
                ],
                'label' => [
                    'required',
                    'string',
                    'max:120',
                ],
                'starts_at' => [
                    'required',
                    'date_format:H:i',
                ],
                'ends_at' => [
                    'required',
                    'date_format:H:i',
                    'after:starts_at',
                ],
                'max_orders' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
                'is_active' => [
                    'required',
                    'boolean',
                ],
                'sort_order' => [
                    'sometimes',
                    'integer',
                    'min:0',
                ]],
            default => [],
        };
    }
}
