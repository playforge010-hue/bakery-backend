<?php

namespace App\Actions\Customer;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartItemAddon;
use App\Models\CartItemUpload;
use App\Models\Product;
use App\Services\CustomerCheckoutService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Create a cart line from trusted catalog options and persist its customization photo. */
class AddConfiguredProductToCart
{
    /** Inject the checkout rules used to price and check inventory for cart lines. */
    public function __construct(private readonly CustomerCheckoutService $checkout)
    {
    }

    /** Validate selected option ownership and create priced cart and addon records. */
    public function execute(int $userId, array $data, ?UploadedFile $image): CartItem
    {
        $storedPath = null;
        try {
            return DB::transaction(function () use ($userId, $data, $image, &$storedPath) {
                $product = Product::query()->available()->with([
                    'variants',
                    'flavourOptions.flavour',
                    'addonOptions.addon',
                    'customization',
                ])->lockForUpdate()->find($data['product_id']);
                if (! $product) {
                    throw ValidationException::withMessages(['product_id' => ['This product is currently unavailable.']]);
                }
                $variant = null;
                if (! empty($data['product_variant_id'])) {
                    $variant = $product->variants->first(fn ($option) => (int) $option->id === (int) $data['product_variant_id'] && $option->is_active);
                    if (! $variant) {
                        throw ValidationException::withMessages(['product_variant_id' => ['Choose a valid option for this product.']]);
                    }
                }
                $flavour = null;
                if (! empty($data['flavour_id'])) {
                    $flavourOption = $product->flavourOptions->first(
                        fn ($option) => (int) $option->product_flavour_id === (int) $data['flavour_id']
                            && $option->flavour?->is_active
                    );
                    $flavour = $flavourOption?->flavour;
                    if (! $flavour) {
                        throw ValidationException::withMessages(['flavour_id' => ['Choose a valid flavour for this product.']]);
                    }
                }
                $customization = $product->customization;
                if (! empty($data['egg_option'])) {
                    $eggAllowed = match (strtolower($data['egg_option'])) {
                        'eggless' => $product->is_eggless_available,
                        'with egg' => $product->is_egg_available,
                        default => false,
                    };
                    if (! $eggAllowed) {
                        throw ValidationException::withMessages(['egg_option' => ['Choose an egg preference available for this product.']]);
                    }
                }
                if (! empty($data['cake_message']) && ! ($customization?->allow_message || $product->allow_message)) {
                    throw ValidationException::withMessages(['cake_message' => ['This product does not support a cake message.']]);
                }
                if ($image && ! ($customization?->allow_photo_upload || $product->allow_photo_upload)) {
                    throw ValidationException::withMessages(['custom_image' => ['This product does not accept a reference image.']]);
                }
                if ($customization?->message_max_length && mb_strlen($data['cake_message'] ?? '') > $customization->message_max_length) {
                    throw ValidationException::withMessages(['cake_message' => ['The message is too long for this product.']]);
                }

                $quantity = (int) $data['quantity'];
                $this->checkout->assertStock($product->id, $variant?->id, $quantity, true);
                $unitPrice = (float) ($variant?->price ?? $product->base_price) + (float) ($flavour?->extra_price ?? 0);
                $cart = Cart::query()->where('user_id', $userId)->latest('id')->lockForUpdate()->first();
                if (! $cart) {
                    $cart = Cart::query()->create(['user_id' => $userId, 'fulfillment_type' => $data['fulfillment_type'] ?? 'delivery', 'currency' => 'INR']);
                }
                if (! empty($data['fulfillment_type'])) {
                    $cart->fulfillment_type = $data['fulfillment_type'];
                }
                $cart->save();

                $addons = [];
                $addonTotal = 0.0;
                $requestedAddonIds = collect($data['addons'] ?? [])->pluck('id')->map(fn ($id) => (int) $id);
                $missingRequiredAddon = $product->addonOptions->first(
                    fn ($option) => $option->is_required
                        && ! $requestedAddonIds->contains((int) $option->product_addon_id)
                );
                if ($missingRequiredAddon) {
                    throw ValidationException::withMessages(['addons' => ['Select all required product add-ons before adding this item.']]);
                }
                foreach ($data['addons'] ?? [] as $selection) {
                    $option = $product->addonOptions->first(fn ($available) => (int) $available->product_addon_id === (int) $selection['id']);
                    if (! $option || ! $option->addon->is_active || ($option->max_quantity && $selection['quantity'] > $option->max_quantity)) {
                        throw ValidationException::withMessages(['addons' => ['One of the selected add-ons is unavailable or exceeds its limit.']]);
                    }
                    $addonTotal += (float) $option->addon->price * (int) $selection['quantity'];
                    $addons[] = [$option->addon, (int) $selection['quantity']];
                }
                $line = CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'addon_total' => $addonTotal,
                    'line_total' => $unitPrice * $quantity + $addonTotal,
                    'selected_flavour_id' => $flavour?->id,
                    'egg_option' => $data['egg_option'] ?? null,
                    'cake_message' => $data['cake_message'] ?? null,
                    'customization_summary_json' => [
                        'variant_name' => $variant
                            ? $this->checkout->translated($variant->name_translations)
                            : null,
                        'flavour_name' => $flavour
                            ? $this->checkout->translated($flavour->name_translations)
                            : null,
                        'egg_option' => $data['egg_option'] ?? null,
                    ],
                ]);
                foreach ($addons as [$addon, $addonQuantity]) {
                    CartItemAddon::query()->create([
                        'cart_item_id' => $line->id,
                        'product_addon_id' => $addon->id,
                        'quantity' => $addonQuantity,
                        'unit_price' => $addon->price,
                        'line_total' => (float) $addon->price * $addonQuantity,
                    ]);
                }

                if ($image) {
                    $customUploadDirectory = config(
                        'imagepath.directories.product_custom_uploads',
                        config('imagepath.directories.custom_order_uploads')
                    );
                    $imageDisk = config('imagepath.disk', 'public');
                    $storedPath = $image->store($customUploadDirectory, $imageDisk);
                    CartItemUpload::query()->create([
                        'cart_item_id' => $line->id,
                        'file_path' => $storedPath,
                        'original_name' => $image->getClientOriginalName(),
                        'mime_type' => $image->getMimeType(),
                        'file_size' => $image->getSize(),
                    ]);
                }
                $cart->refresh();
                $cart->update([
                    'subtotal' => (float) $cart->items()->sum('line_total'),
                    'addon_total' => (float) $cart->items()->sum('addon_total'),
                    'coupon_id' => null,
                    'discount_total' => 0,
                    'updated_at' => now(),
                ]);
                $cart->refresh()->update([
                    'grand_total' => max(0,
                        (float) $cart->subtotal + (float) $cart->delivery_fee + (float) $cart->tax_total - (float) $cart->discount_total),
                ]);

                return $line->load('addons');
            });
        } catch (\Throwable $error) {
            if ($storedPath) {
                Storage::disk(config('imagepath.disk', 'public'))->delete($storedPath);
            }
            throw $error;
        }
    }
}
