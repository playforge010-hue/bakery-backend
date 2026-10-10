<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StorefrontCatalogSeeder extends Seeder
{
    private const IMAGE_DIRECTORY = 'demo-catalog';

    public function run(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $categories = $this->seedCategories($now);
            $flavours = $this->seedFlavours($now);
            $addons = $this->seedAddons($now);

            $this->seedProducts($categories, $flavours, $addons, $now);
            $this->seedBusinessSettings($now);
            $this->seedOpeningHours($now);
            $this->seedDeliverySlots($now);
            $this->seedBanners($now);
        });
    }

    private function seedCategories(string $now): array
    {
        $definitions = [
            [
                'slug' => 'birthday-cakes',
                'name' => ['en' => 'Birthday Cakes', 'gu' => 'જન્મદિવસ કેક', 'hi' => 'जन्मदिन केक'],
                'image' => 'birthday-cake.jpg',
            ],
            [
                'slug' => 'chocolate-cakes',
                'name' => ['en' => 'Chocolate Cakes', 'gu' => 'ચોકલેટ કેક', 'hi' => 'चॉकलेट केक'],
                'image' => 'chocolate-berry-cake.jpg',
            ],
            [
                'slug' => 'pastries',
                'name' => ['en' => 'Pastries', 'gu' => 'પેસ્ટ્રી', 'hi' => 'पेस्ट्री'],
                'image' => 'bakery-pastries.jpg',
            ],
            [
                'slug' => 'cupcakes',
                'name' => ['en' => 'Cupcakes', 'gu' => 'કપકેક', 'hi' => 'कपकेक'],
                'image' => 'cupcake-display.jpg',
            ],
            [
                'slug' => 'snacks',
                'name' => ['en' => 'Bakery Snacks', 'gu' => 'બેકરી નાસ્તા', 'hi' => 'बेकरी स्नैक्स'],
                'image' => 'samosa.jpg',
            ],
            [
                'slug' => 'breads',
                'name' => ['en' => 'Breads & Bakes', 'gu' => 'બ્રેડ અને બેકરી', 'hi' => 'ब्रेड और बेकरी'],
                'image' => 'croissant-basket.jpg',
            ],
        ];

        $categoryIds = [];

        foreach ($definitions as $index => $definition) {
            $category = DB::table('product_categories')
                ->where('slug', $definition['slug'])
                ->first();

            if (! $category) {
                $categoryId = DB::table('product_categories')->insertGetId([
                    'name_translations' => $this->json($definition['name']),
                    'slug' => $definition['slug'],
                    'description_translations' => $this->json([
                        'en' => 'Freshly prepared in Jamnagar by Dwarkesh Bakers.',
                        'gu' => 'દ્વારકેશ બેકર્સ દ્વારા જામનગરમાં તાજી બનાવેલી.',
                        'hi' => 'द्वारकेश बेकर्स द्वारा जामनगर में ताज़ा तैयार।',
                    ]),
                    'image_path' => $this->imagePath($definition['image']),
                    'is_featured' => true,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $categoryId = $category->id;
            }

            $categoryIds[$definition['slug']] = $categoryId;
        }

        return $categoryIds;
    }

    private function seedFlavours(string $now): array
    {
        $definitions = [
            ['Chocolate', 0],
            ['Vanilla', 0],
            ['Butterscotch', 30],
            ['Black Forest', 50],
            ['Red Velvet', 80],
        ];
        $flavourIds = [];

        foreach ($definitions as [$name, $extraPrice]) {
            $translations = $this->translations($name);
            $flavour = DB::table('product_flavours')
                ->where('name_translations->en', $name)
                ->first();

            if (! $flavour) {
                $flavourId = DB::table('product_flavours')->insertGetId([
                    'name_translations' => $this->json($translations),
                    'description_translations' => $this->json([
                        'en' => 'Prepared fresh to order.',
                        'gu' => 'ઓર્ડર મળ્યા પછી તાજું તૈયાર કરવામાં આવે છે.',
                        'hi' => 'ऑर्डर मिलने पर ताज़ा तैयार किया जाता है।',
                    ]),
                    'extra_price' => $extraPrice,
                    'is_active' => true,
                    'sort_order' => count($flavourIds) + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $flavourId = $flavour->id;
            }

            $flavourIds[$name] = $flavourId;
        }

        return $flavourIds;
    }

    private function seedAddons(string $now): array
    {
        $definitions = [
            ['Birthday candles', 20],
            ['Greeting card', 35],
            ['Chocolate truffles (4 pcs)', 99],
        ];
        $addonIds = [];

        foreach ($definitions as [$name, $price]) {
            $translations = $this->translations($name);
            $addon = DB::table('product_addons')
                ->where('name_translations->en', $name)
                ->first();

            if (! $addon) {
                $addonId = DB::table('product_addons')->insertGetId([
                    'name_translations' => $this->json($translations),
                    'description_translations' => $this->json([
                        'en' => 'Add a little extra to your celebration.',
                        'gu' => 'તમારી ઉજવણીમાં થોડો વધુ આનંદ ઉમેરો.',
                        'hi' => 'अपने उत्सव में थोड़ी और मिठास जोड़ें।',
                    ]),
                    'price' => $price,
                    'is_active' => true,
                    'sort_order' => count($addonIds) + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $addonId = $addon->id;
            }

            $addonIds[$name] = $addonId;
        }

        return $addonIds;
    }

    private function seedProducts(array $categoryIds, array $flavourIds, array $addonIds, string $now): void
    {
        $products = [
            [
                'slug' => 'chocolate-truffle-cake',
                'sku' => 'DWK-CAKE-001',
                'name' => 'Chocolate Truffle Cake',
                'category' => 'chocolate-cakes',
                'image' => 'chocolate-berry-cake.jpg',
                'gallery' => ['chocolate-cake.jpg'],
                'price' => 499,
                'type' => 'cake',
                'food_type' => 'both',
                'description' => 'A soft chocolate sponge layered with smooth truffle cream and finished with rich chocolate ganache.',
                'preparation' => 180,
                'featured' => true,
                'bestseller' => true,
                'customizable' => true,
                'photo_upload' => true,
                'message' => true,
                'variants' => [
                    ['500 g', 499, 0.5, 'kg'],
                    ['1 kg', 899, 1, 'kg'],
                    ['1.5 kg', 1299, 1.5, 'kg'],
                    ['2 kg', 1699, 2, 'kg'],
                ],
                'flavours' => ['Chocolate', 'Vanilla', 'Butterscotch'],
                'addons' => ['Birthday candles', 'Greeting card', 'Chocolate truffles (4 pcs)'],
            ],
            [
                'slug' => 'photo-print-cake',
                'sku' => 'DWK-CAKE-002',
                'name' => 'Photo Print Cake',
                'category' => 'birthday-cakes',
                'image' => 'birthday-cake.jpg',
                'gallery' => ['chocolate-berry-cake.jpg'],
                'price' => 799,
                'type' => 'cake',
                'food_type' => 'eggless',
                'description' => 'A celebration cake finished with an edible photo print. Upload a clear image and add a short message at checkout.',
                'preparation' => 240,
                'featured' => true,
                'bestseller' => false,
                'customizable' => true,
                'photo_upload' => true,
                'message' => true,
                'variants' => [
                    ['500 g', 799, 0.5, 'kg'],
                    ['1 kg', 1299, 1, 'kg'],
                    ['1.5 kg', 1799, 1.5, 'kg'],
                ],
                'flavours' => ['Chocolate', 'Vanilla', 'Red Velvet'],
                'addons' => ['Birthday candles', 'Greeting card'],
            ],
            [
                'slug' => 'black-forest-cake',
                'sku' => 'DWK-CAKE-003',
                'name' => 'Black Forest Cake',
                'category' => 'chocolate-cakes',
                'image' => 'chocolate-cake.jpg',
                'gallery' => ['chocolate-berry-cake.jpg'],
                'price' => 549,
                'type' => 'cake',
                'food_type' => 'both',
                'description' => 'Chocolate sponge, whipped cream and cherry notes come together in this bakery classic.',
                'preparation' => 180,
                'featured' => true,
                'bestseller' => true,
                'customizable' => true,
                'photo_upload' => false,
                'message' => true,
                'variants' => [
                    ['500 g', 549, 0.5, 'kg'],
                    ['1 kg', 999, 1, 'kg'],
                    ['1.5 kg', 1449, 1.5, 'kg'],
                ],
                'flavours' => ['Black Forest', 'Chocolate'],
                'addons' => ['Birthday candles', 'Greeting card'],
            ],
            [
                'slug' => 'strawberry-celebration-cake',
                'sku' => 'DWK-CAKE-004',
                'name' => 'Strawberry Celebration Cake',
                'category' => 'birthday-cakes',
                'image' => 'birthday-cake.jpg',
                'gallery' => [],
                'price' => 599,
                'type' => 'cake',
                'food_type' => 'eggless',
                'description' => 'Light vanilla sponge with strawberry cream, finished with a fresh, celebratory look.',
                'preparation' => 180,
                'featured' => true,
                'bestseller' => false,
                'customizable' => true,
                'photo_upload' => false,
                'message' => true,
                'variants' => [
                    ['500 g', 599, 0.5, 'kg'],
                    ['1 kg', 1099, 1, 'kg'],
                    ['1.5 kg', 1599, 1.5, 'kg'],
                ],
                'flavours' => ['Vanilla'],
                'addons' => ['Birthday candles', 'Greeting card'],
            ],
            [
                'slug' => 'red-velvet-cake',
                'sku' => 'DWK-CAKE-005',
                'name' => 'Red Velvet Cake',
                'category' => 'birthday-cakes',
                'image' => 'cupcake-display.jpg',
                'gallery' => ['birthday-cake.jpg'],
                'price' => 699,
                'type' => 'cake',
                'food_type' => 'both',
                'description' => 'A tender cocoa sponge with a smooth cream-cheese-style frosting and a soft crumb.',
                'preparation' => 210,
                'featured' => false,
                'bestseller' => false,
                'customizable' => true,
                'photo_upload' => false,
                'message' => true,
                'variants' => [
                    ['500 g', 699, 0.5, 'kg'],
                    ['1 kg', 1299, 1, 'kg'],
                ],
                'flavours' => ['Red Velvet', 'Vanilla'],
                'addons' => ['Birthday candles', 'Greeting card'],
            ],
            [
                'slug' => 'celebration-cupcakes',
                'sku' => 'DWK-CUP-001',
                'name' => 'Celebration Cupcakes',
                'category' => 'cupcakes',
                'image' => 'cupcake-display.jpg',
                'gallery' => ['bakery-pastries.jpg'],
                'price' => 349,
                'type' => 'cupcake',
                'food_type' => 'both',
                'description' => 'A box of six soft cupcakes with a mix of smooth buttercream finishes.',
                'preparation' => 90,
                'featured' => true,
                'bestseller' => true,
                'customizable' => false,
                'photo_upload' => false,
                'message' => false,
                'variants' => [
                    ['Box of 6', 349, null, null],
                    ['Box of 12', 649, null, null],
                ],
                'flavours' => ['Chocolate', 'Vanilla'],
                'addons' => [],
            ],
            [
                'slug' => 'chocolate-brownies',
                'sku' => 'DWK-DES-001',
                'name' => 'Chocolate Brownies',
                'category' => 'pastries',
                'image' => 'chocolate-cake.jpg',
                'gallery' => ['chocolate-berry-cake.jpg'],
                'price' => 199,
                'type' => 'dessert',
                'food_type' => 'eggless',
                'description' => 'Dense, fudgy chocolate brownie squares baked in small batches.',
                'preparation' => 60,
                'featured' => false,
                'bestseller' => false,
                'customizable' => false,
                'photo_upload' => false,
                'message' => false,
                'variants' => [
                    ['Box of 2', 199, null, null],
                    ['Box of 6', 549, null, null],
                ],
                'flavours' => ['Chocolate'],
                'addons' => [],
            ],
            [
                'slug' => 'assorted-pastry-box',
                'sku' => 'DWK-PAS-001',
                'name' => 'Assorted Pastry Box',
                'category' => 'pastries',
                'image' => 'bakery-pastries.jpg',
                'gallery' => ['cupcake-display.jpg'],
                'price' => 299,
                'type' => 'pastry',
                'food_type' => 'both',
                'description' => 'A selection of individual bakery pastries, packed fresh for sharing.',
                'preparation' => 45,
                'featured' => false,
                'bestseller' => false,
                'customizable' => false,
                'photo_upload' => false,
                'message' => false,
                'variants' => [
                    ['Box of 4', 299, null, null],
                    ['Box of 8', 559, null, null],
                ],
                'flavours' => ['Chocolate', 'Vanilla'],
                'addons' => [],
            ],
            [
                'slug' => 'vegetable-samosa',
                'sku' => 'DWK-SNK-001',
                'name' => 'Vegetable Samosa',
                'category' => 'snacks',
                'image' => 'samosa.jpg',
                'gallery' => [],
                'price' => 40,
                'type' => 'snack',
                'food_type' => 'vegetarian',
                'description' => 'Crisp pastry filled with a savoury spiced potato and vegetable mixture.',
                'preparation' => 20,
                'featured' => false,
                'bestseller' => false,
                'customizable' => false,
                'photo_upload' => false,
                'message' => false,
                'variants' => [
                    ['1 piece', 40, null, null],
                    ['Pack of 4', 150, null, null],
                ],
                'flavours' => [],
                'addons' => [],
            ],
            [
                'slug' => 'butter-croissant',
                'sku' => 'DWK-BRD-001',
                'name' => 'Butter Croissant',
                'category' => 'breads',
                'image' => 'croissant-basket.jpg',
                'gallery' => [],
                'price' => 90,
                'type' => 'bread',
                'food_type' => 'vegetarian',
                'description' => 'A flaky, golden bakery croissant prepared for breakfast and tea time.',
                'preparation' => 30,
                'featured' => false,
                'bestseller' => false,
                'customizable' => false,
                'photo_upload' => false,
                'message' => false,
                'variants' => [
                    ['1 piece', 90, null, null],
                    ['Pack of 4', 340, null, null],
                ],
                'flavours' => [],
                'addons' => [],
            ],
        ];

        foreach ($products as $index => $definition) {
            $product = DB::table('products')
                ->where('slug', $definition['slug'])
                ->first();

            if (! $product) {
                $productId = DB::table('products')->insertGetId([
                    'product_category_id' => $categoryIds[$definition['category']],
                    'name_translations' => $this->json($this->translations($definition['name'])),
                    'slug' => $definition['slug'],
                    'short_description_translations' => $this->json($this->translations($this->shortDescription($definition))),
                    'description_translations' => $this->json($this->translations($definition['description'])),
                    'main_image_path' => $this->imagePath($definition['image']),
                    'base_price' => $definition['price'],
                    'price_starts_from' => count($definition['variants']) > 1,
                    'sku' => $definition['sku'],
                    'product_type' => $definition['type'],
                    'food_type' => $definition['food_type'],
                    'is_eggless_available' => in_array($definition['food_type'], ['both', 'eggless'], true),
                    'is_egg_available' => $definition['food_type'] === 'both',
                    'is_customizable' => $definition['customizable'],
                    'allow_message' => $definition['message'],
                    'allow_photo_upload' => $definition['photo_upload'],
                    'min_preparation_minutes' => $definition['preparation'],
                    'max_preparation_minutes' => $definition['preparation'] + 60,
                    'is_featured' => $definition['featured'],
                    'is_bestseller' => $definition['bestseller'],
                    'is_new' => $index >= 7,
                    'is_available' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $productId = $product->id;

                $this->updateEnglishFallbackTranslations(
                    $productId,
                    [
                        'short_description_translations' => $this->translations(
                            $this->shortDescription($definition)
                        ),
                        'description_translations' => $this->translations($definition['description']),
                    ],
                    $now
                );
            }

            $this->seedProductImages($productId, $definition, $now);
            $variantIds = $this->seedVariants($productId, $definition, $now);
            $this->seedProductOptions($productId, $definition, $flavourIds, $addonIds, $now);
            $this->seedCustomization($productId, $definition, $now);
            $this->seedInventory($productId, $variantIds, $now);
        }
    }

    private function seedProductImages(int $productId, array $definition, string $now): void
    {
        $images = array_values(array_unique(array_merge(
            [$definition['image']],
            $definition['gallery']
        )));

        foreach ($images as $index => $image) {
            $imagePath = $this->imagePath($image);
            $exists = DB::table('product_images')
                ->where('product_id', $productId)
                ->where('image_path', $imagePath)
                ->exists();

            if (! $exists) {
                DB::table('product_images')->insert([
                    'product_id' => $productId,
                    'image_path' => $imagePath,
                    'alt_text_translations' => $this->json($this->translations($definition['name'])),
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function updateEnglishFallbackTranslations(
        int $productId,
        array $translationsByColumn,
        string $now
    ): void {
        $product = DB::table('products')
            ->select(array_keys($translationsByColumn))
            ->find($productId);

        if (! $product) {
            return;
        }

        $updates = [];

        foreach ($translationsByColumn as $column => $translations) {
            $current = json_decode($product->{$column} ?? '', true);

            if (! is_array($current) || ! isset($current['en'], $current['gu'], $current['hi'])) {
                continue;
            }

            $gujaratiIsFallback = $current['gu'] === $current['en'];
            $hindiIsFallback = $current['hi'] === $current['en'];

            if ($gujaratiIsFallback && $hindiIsFallback) {
                $updates[$column] = $this->json($translations);
            }
        }

        if ($updates !== []) {
            $updates['updated_at'] = $now;

            DB::table('products')
                ->where('id', $productId)
                ->update($updates);
        }
    }

    private function seedVariants(int $productId, array $definition, string $now): array
    {
        $variantIds = [];

        foreach ($definition['variants'] as $index => [$label, $price, $weight, $unit]) {
            $sku = $definition['sku'].'-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $variant = DB::table('product_variants')->where('sku', $sku)->first();

            if (! $variant) {
                $variantId = DB::table('product_variants')->insertGetId([
                    'product_id' => $productId,
                    'name_translations' => $this->json($this->translations($label)),
                    'sku' => $sku,
                    'weight_value' => $weight,
                    'weight_unit' => $unit,
                    'price' => $price,
                    'min_preparation_minutes' => $definition['preparation'],
                    'is_default' => $index === 0,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $variantId = $variant->id;
            }

            $variantIds[] = $variantId;
        }

        return $variantIds;
    }

    private function seedProductOptions(
        int $productId,
        array $definition,
        array $flavourIds,
        array $addonIds,
        string $now
    ): void {
        foreach ($definition['flavours'] as $index => $flavourName) {
            $flavourId = $flavourIds[$flavourName];
            $exists = DB::table('product_flavour_options')
                ->where('product_id', $productId)
                ->where('product_flavour_id', $flavourId)
                ->exists();

            if (! $exists) {
                DB::table('product_flavour_options')->insert([
                    'product_id' => $productId,
                    'product_flavour_id' => $flavourId,
                    'is_default' => $index === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($definition['addons'] as $addonName) {
            $addonId = $addonIds[$addonName];
            $exists = DB::table('product_addon_options')
                ->where('product_id', $productId)
                ->where('product_addon_id', $addonId)
                ->exists();

            if (! $exists) {
                DB::table('product_addon_options')->insert([
                    'product_id' => $productId,
                    'product_addon_id' => $addonId,
                    'is_required' => false,
                    'max_quantity' => 3,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedCustomization(int $productId, array $definition, string $now): void
    {
        $exists = DB::table('product_customization_options')
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('product_customization_options')->insert([
            'product_id' => $productId,
            'allow_message' => $definition['message'],
            'message_label_translations' => $this->json($this->translations('Cake message')),
            'message_max_length' => $definition['message'] ? 60 : null,
            'allow_photo_upload' => $definition['photo_upload'],
            'photo_upload_label_translations' => $this->json($this->translations('Upload a reference photo')),
            'allow_flavour_selection' => count($definition['flavours']) > 0,
            'allow_egg_selection' => $definition['type'] === 'cake'
                && $definition['food_type'] === 'both',
            'allow_delivery_date_selection' => $definition['type'] === 'cake',
            'min_notice_minutes' => $definition['preparation'],
            'max_schedule_days' => 15,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedInventory(int $productId, array $variantIds, string $now): void
    {
        $inventoryVariants = $variantIds ?: [null];

        foreach ($inventoryVariants as $variantId) {
            $query = DB::table('product_inventory')->where('product_id', $productId);

            if ($variantId === null) {
                $query->whereNull('product_variant_id');
            } else {
                $query->where('product_variant_id', $variantId);
            }

            if (! $query->exists()) {
                DB::table('product_inventory')->insert([
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'track_stock' => false,
                    'stock_quantity' => null,
                    'low_stock_threshold' => null,
                    'is_sold_out' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedBusinessSettings(string $now): void
    {
        if (DB::table('business_settings')->exists()) {
            return;
        }

        DB::table('business_settings')->insert([
            'bakery_name' => 'Dwarkesh Bakers',
            'address_line_1' => 'Near Main Road',
            'address_line_2' => null,
            'area' => 'Patel Colony',
            'city' => 'Jamnagar',
            'state' => 'Gujarat',
            'pincode' => '361008',
            'latitude' => 22.4707000,
            'longitude' => 70.0577000,
            'phone_number' => '9870004055',
            'whatsapp_number' => '9870004055',
            'minimum_order_amount' => 199,
            'max_delivery_distance_km' => 10,
            'default_preparation_minutes' => 60,
            'delivery_available' => true,
            'takeaway_available' => true,
            'online_payment_available' => false,
            'cod_available' => true,
            'upi_available' => false,
            'maintenance_mode' => false,
            'social_links_json' => $this->json([]),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedOpeningHours(string $now): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $exists = DB::table('opening_hours')
                ->where('day_of_week', $day)
                ->exists();

            if (! $exists) {
                DB::table('opening_hours')->insert([
                    'day_of_week' => $day,
                    'opens_at' => '09:00:00',
                    'closes_at' => '22:00:00',
                    'is_closed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function seedDeliverySlots(string $now): void
    {
        $slotWindows = [
            ['09:00:00', '11:00:00'],
            ['11:00:00', '13:00:00'],
            ['13:00:00', '15:00:00'],
            ['15:00:00', '17:00:00'],
            ['17:00:00', '19:00:00'],
            ['19:00:00', '21:00:00'],
        ];

        foreach (['delivery', 'takeaway'] as $fulfillmentType) {
            foreach ($slotWindows as $index => [$startsAt, $endsAt]) {
                $exists = DB::table('delivery_slots')
                    ->where('fulfillment_type', $fulfillmentType)
                    ->where('starts_at', $startsAt)
                    ->exists();

                if (! $exists) {
                    DB::table('delivery_slots')->insert([
                        'fulfillment_type' => $fulfillmentType,
                        'label' => $startsAt.' – '.$endsAt,
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'max_orders' => 20,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    private function seedBanners(string $now): void
    {
        $bannerExists = DB::table('banners')
            ->where('link_type', 'category')
            ->where('link_value', 'birthday-cakes')
            ->exists();

        if (! $bannerExists) {
            DB::table('banners')->insert([
                'title_translations' => $this->json($this->translations('Fresh cakes, made for your moments')),
                'subtitle_translations' => $this->json($this->translations('Order cakes, treats and bakery favourites in Jamnagar.')),
                'image_path' => $this->imagePath('chocolate-berry-cake.jpg'),
                'link_type' => 'category',
                'link_value' => 'birthday-cakes',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function imagePath(string $filename): string
    {
        return self::IMAGE_DIRECTORY.'/'.$filename;
    }

    private function shortDescription(array $definition): string
    {
        return match ($definition['type']) {
            'cake' => 'Made fresh to order · '.$definition['preparation'].' min notice',
            'cupcake' => 'Freshly baked party favourite',
            'snack' => 'Crisp, savoury bakery snack',
            'bread' => 'Golden, flaky bakery bake',
            default => 'Fresh from the Dwarkesh Bakers kitchen',
        };
    }

    private function translations(string $english): array
    {
        $translations = [
            'Chocolate Truffle Cake' => ['gu' => 'ચોકલેટ ટ્રફલ કેક', 'hi' => 'चॉकलेट ट्रफल केक'],
            'Photo Print Cake' => ['gu' => 'ફોટો પ્રિન્ટ કેક', 'hi' => 'फोटो प्रिंट केक'],
            'Black Forest Cake' => ['gu' => 'બ્લેક ફોરેસ્ટ કેક', 'hi' => 'ब्लैक फॉरेस्ट केक'],
            'Strawberry Celebration Cake' => ['gu' => 'સ્ટ્રોબેરી સેલિબ્રેશન કેક', 'hi' => 'स्ट्रॉबेरी सेलिब्रेशन केक'],
            'Red Velvet Cake' => ['gu' => 'રેડ વેલ્વેટ કેક', 'hi' => 'रेड वेलवेट केक'],
            'Celebration Cupcakes' => ['gu' => 'સેલિબ્રેશન કપકેક', 'hi' => 'सेलिब्रेशन कपकेक'],
            'Chocolate Brownies' => ['gu' => 'ચોકલેટ બ્રાઉની', 'hi' => 'चॉकलेट ब्राउनी'],
            'Assorted Pastry Box' => ['gu' => 'મિક્સ પેસ્ટ્રી બોક્સ', 'hi' => 'मिक्स पेस्ट्री बॉक्स'],
            'Vegetable Samosa' => ['gu' => 'વેજ સમોસા', 'hi' => 'वेज समोसा'],
            'Butter Croissant' => ['gu' => 'બટર ક્રોસાં', 'hi' => 'बटर क्रोइसां'],
            '500 g' => ['gu' => '500 ગ્રામ', 'hi' => '500 ग्राम'],
            '1 kg' => ['gu' => '1 કિલો', 'hi' => '1 किलो'],
            '1.5 kg' => ['gu' => '1.5 કિલો', 'hi' => '1.5 किलो'],
            '2 kg' => ['gu' => '2 કિલો', 'hi' => '2 किलो'],
            'Box of 6' => ['gu' => '6 નો બોક્સ', 'hi' => '6 का बॉक्स'],
            'Box of 12' => ['gu' => '12 નો બોક્સ', 'hi' => '12 का बॉक्स'],
            'Box of 2' => ['gu' => '2 નો બોક્સ', 'hi' => '2 का बॉक्स'],
            'Box of 4' => ['gu' => '4 નો બોક્સ', 'hi' => '4 का बॉक्स'],
            'Box of 8' => ['gu' => '8 નો બોક્સ', 'hi' => '8 का बॉक्स'],
            '1 piece' => ['gu' => '1 નંગ', 'hi' => '1 पीस'],
            'Pack of 4' => ['gu' => '4 નો પેક', 'hi' => '4 का पैक'],
            'Chocolate' => ['gu' => 'ચોકલેટ', 'hi' => 'चॉकलेट'],
            'Vanilla' => ['gu' => 'વાનિલા', 'hi' => 'वनीला'],
            'Butterscotch' => ['gu' => 'બટર સ્કોચ', 'hi' => 'बटरस्कॉच'],
            'Black Forest' => ['gu' => 'બ્લેક ફોરેસ્ટ', 'hi' => 'ब्लैक फॉरेस्ट'],
            'Red Velvet' => ['gu' => 'રેડ વેલ્વેટ', 'hi' => 'रेड वेलवेट'],
            'Birthday candles' => ['gu' => 'જન્મદિવસની મીણબત્તીઓ', 'hi' => 'जन्मदिन की मोमबत्तियां'],
            'Greeting card' => ['gu' => 'શુભેચ્છા કાર્ડ', 'hi' => 'ग्रीटिंग कार्ड'],
            'Chocolate truffles (4 pcs)' => ['gu' => 'ચોકલેટ ટ્રફલ (4 નંગ)', 'hi' => 'चॉकलेट ट्रफल (4 पीस)'],
            'Cake message' => ['gu' => 'કેક પરનો સંદેશ', 'hi' => 'केक पर संदेश'],
            'Upload a reference photo' => ['gu' => 'સંદર્ભ ફોટો અપલોડ કરો', 'hi' => 'संदर्भ फोटो अपलोड करें'],
            'Made fresh to order · 180 min notice' => [
                'gu' => 'ઓર્ડર મળ્યા પછી તાજું બનાવીએ · 180 મિનિટ પહેલાં ઓર્ડર કરો',
                'hi' => 'ऑर्डर मिलने पर ताज़ा बनाते हैं · 180 मिनट पहले ऑर्डर करें',
            ],
            'Made fresh to order · 210 min notice' => [
                'gu' => 'ઓર્ડર મળ્યા પછી તાજું બનાવીએ · 210 મિનિટ પહેલાં ઓર્ડર કરો',
                'hi' => 'ऑर्डर मिलने पर ताज़ा बनाते हैं · 210 मिनट पहले ऑर्डर करें',
            ],
            'Made fresh to order · 240 min notice' => [
                'gu' => 'ઓર્ડર મળ્યા પછી તાજું બનાવીએ · 240 મિનિટ પહેલાં ઓર્ડર કરો',
                'hi' => 'ऑर्डर मिलने पर ताज़ा बनाते हैं · 240 मिनट पहले ऑर्डर करें',
            ],
            'Freshly baked party favourite' => [
                'gu' => 'ઉજવણી માટે તાજાં બેક કરેલા કપકેક',
                'hi' => 'जश्न के लिए ताज़ा बेक किए कपकेक',
            ],
            'Crisp, savoury bakery snack' => [
                'gu' => 'કરકરો અને સ્વાદિષ્ટ બેકરી નાસ્તો',
                'hi' => 'कुरकुरा और स्वादिष्ट बेकरी नाश्ता',
            ],
            'Golden, flaky bakery bake' => [
                'gu' => 'સોનેરી અને પડવાળું બેકરી બેક',
                'hi' => 'सुनहरा और परतदार बेकरी बेक',
            ],
            'Fresh from the Dwarkesh Bakers kitchen' => [
                'gu' => 'દ્વારકેશ બેકર્સના રસોડામાંથી તાજું',
                'hi' => 'द्वारकेश बेकर्स की रसोई से ताज़ा',
            ],
            'A soft chocolate sponge layered with smooth truffle cream and finished with rich chocolate ganache.' => [
                'gu' => 'નરમ ચોકલેટ સ્પોન્જમાં ટ્રફલ ક્રીમના સ્તર અને ઉપર ચોકલેટ ગણાશ.',
                'hi' => 'नरम चॉकलेट स्पॉन्ज की परतों में ट्रफल क्रीम और ऊपर चॉकलेट गनाश।',
            ],
            'A celebration cake finished with an edible photo print. Upload a clear image and add a short message at checkout.' => [
                'gu' => 'ખાઈ શકાય તેવી ફોટો પ્રિન્ટથી સજાવેલો ઉજવણીનો કેક. ઓર્ડર કરતી વખતે સ્પષ્ટ ફોટો અને સંદેશ ઉમેરો.',
                'hi' => 'खाने योग्य फोटो प्रिंट से सजा जश्न का केक। ऑर्डर करते समय साफ़ फोटो और संदेश जोड़ें।',
            ],
            'Chocolate sponge, whipped cream and cherry notes come together in this bakery classic.' => [
                'gu' => 'ચોકલેટ સ્પોન્જ, વ્હીપ્ડ ક્રીમ અને ચેરીનો સ્વાદ ધરાવતો બેકરી ક્લાસિક.',
                'hi' => 'चॉकलेट स्पॉन्ज, व्हिप्ड क्रीम और चेरी के स्वाद वाला बेकरी क्लासिक।',
            ],
            'Light vanilla sponge with strawberry cream, finished with a fresh, celebratory look.' => [
                'gu' => 'હળવા વાનિલા સ્પોન્જમાં સ્ટ્રોબેરી ક્રીમ અને ઉજવણી માટે સુંદર સજાવટ.',
                'hi' => 'हल्के वनीला स्पॉन्ज में स्ट्रॉबेरी क्रीम और जश्न के लिए सुंदर सजावट।',
            ],
            'A tender cocoa sponge with a smooth cream-cheese-style frosting and a soft crumb.' => [
                'gu' => 'નરમ કોકો સ્પોન્જ પર મખમલી ક્રીમ ચીઝ જેવી ફ્રોસ્ટિંગ.',
                'hi' => 'नरम कोको स्पॉन्ज पर मुलायम क्रीम-चीज़ जैसी फ्रॉस्टिंग।',
            ],
            'A box of six soft cupcakes with a mix of smooth buttercream finishes.' => [
                'gu' => 'મુલાયમ બટરક્રીમથી સજાવેલા છ નરમ કપકેકનો બોક્સ.',
                'hi' => 'मुलायम बटरक्रीम से सजे छह नरम कपकेक का बॉक्स।',
            ],
            'Dense, fudgy chocolate brownie squares baked in small batches.' => [
                'gu' => 'નાના બેચમાં બેક કરેલા ઘન અને ચોકલેટી બ્રાઉનીના ટુકડા.',
                'hi' => 'छोटे बैच में बेक किए गए गाढ़े और चॉकलेटी ब्राउनी के टुकड़े।',
            ],
            'A selection of individual bakery pastries, packed fresh for sharing.' => [
                'gu' => 'વહેંચવા માટે તાજી પેક કરેલી વિવિધ બેકરી પેસ્ટ્રી.',
                'hi' => 'साझा करने के लिए ताज़ा पैक की गई अलग-अलग बेकरी पेस्ट्री।',
            ],
            'Crisp pastry filled with a savoury spiced potato and vegetable mixture.' => [
                'gu' => 'મસાલેદાર બટાકા અને શાકભાજીના મિશ્રણથી ભરેલી કરકરી પેસ્ટ્રી.',
                'hi' => 'मसालेदार आलू और सब्ज़ियों के मिश्रण से भरी कुरकुरी पेस्ट्री।',
            ],
            'A flaky, golden bakery croissant prepared for breakfast and tea time.' => [
                'gu' => 'નાસ્તા અને ચા સાથે માણવા માટેનો સોનેરી, પડવાળો ક્રોસાં.',
                'hi' => 'नाश्ते और चाय के साथ खाने के लिए सुनहरा, परतदार क्रोइसां।',
            ],
        ];

        return [
            'en' => $english,
            'gu' => $translations[$english]['gu'] ?? $english,
            'hi' => $translations[$english]['hi'] ?? $english,
        ];
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
