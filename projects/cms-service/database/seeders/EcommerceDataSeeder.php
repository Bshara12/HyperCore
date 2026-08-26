<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EcommerceDataSeeder extends Seeder
{
    private int $ownerId;
    private int $projectId;
    private int $categoryTypeId;
    private int $productTypeId;
    private int $categoryNameFieldId;
    private int $productTitleFieldId;
    private int $productPriceFieldId;
    private int $productSkuFieldId;
    private int $productCategoryFieldId;
    private int $relationId;

    /** @var array<int> demo customers لتوليد ratings واقعية */
    private array $customerIds = [];

    public function run(): void
    {
        DB::transaction(function () {
            $this->setupOwnerAndProject();
            $this->setupCustomers();
            $this->setupDataTypes();
            $this->setupFields();
            $this->setupRelation();

            $categoryIds = $this->seedCategories($this->categories());
            $productEntryIds = $this->seedProducts($this->products(), $categoryIds);

            $this->seedRatings($productEntryIds);
            $this->seedCollections($productEntryIds);

            $this->printSummary($categoryIds, $productEntryIds);
        });
    }

    // ─────────────────────────────────────────────────────────────
    // Setup: Owner / Project / DataTypes / Fields / Relation
    // ─────────────────────────────────────────────────────────────

    private function setupOwnerAndProject(): void
    {
        $now = now();

        // Resolved from the Auth service — projects.owner_id holds an Auth
        // user id, and the mirror keeps the users-table foreign keys valid.
        $this->ownerId = (new SeedContext)->ownerId('shop-owner@hypercore.test');

        $this->projectId = DB::table('projects')->where('slug', 'ecommerce-demo')->value('id')
            ?? DB::table('projects')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'slug' => 'ecommerce-demo',
                'name' => 'E-Commerce Demo Project',
                'owner_id' => $this->ownerId,
                'supported_languages' => json_encode(['en', 'ar']),
                'enabled_modules' => json_encode(['cms', 'ecommerce']),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        DB::table('project_user')->updateOrInsert([
            'project_id' => $this->projectId,
            'user_id' => $this->ownerId,
        ], []);
    }

    private function setupCustomers(): void
    {
        $now = now();
        $names = ['Layla Hassan', 'Omar Khaled', 'Sara Ahmad', 'Yousef Nabil', 'Mona Fares', 'Karim Adel'];

        foreach ($names as $i => $name) {
            $email = 'customer' . ($i + 1) . '@ecommerce.test';

            $id = DB::table('users')->where('email', $email)->value('id')
                ?? DB::table('users')->insertGetId([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make('password'),
                    'created_at' => $now->copy()->subDays(rand(10, 200)),
                    'updated_at' => $now,
                ]);

            $this->customerIds[] = $id;

            DB::table('project_user')->updateOrInsert([
                'project_id' => $this->projectId,
                'user_id' => $id,
            ], []);
        }
    }

    private function setupDataTypes(): void
    {
        $now = now();

        $this->categoryTypeId = DB::table('data_types')
            ->where('project_id', $this->projectId)->where('slug', 'category')->value('id')
            ?? DB::table('data_types')->insertGetId([
                'project_id' => $this->projectId,
                'name' => 'Category',
                'slug' => 'category',
                'description' => 'Product categories',
                'is_active' => true,
                'settings' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        $this->productTypeId = DB::table('data_types')
            ->where('project_id', $this->projectId)->where('slug', 'product')->value('id')
            ?? DB::table('data_types')->insertGetId([
                'project_id' => $this->projectId,
                'name' => 'Product',
                'slug' => 'product',
                'description' => 'Products',
                'is_active' => true,
                'settings' => json_encode([]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
    }

    private function setupFields(): void
    {
        $now = now();

        $this->categoryNameFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $this->categoryTypeId)->where('name', 'name')->value('id')
            ?? DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $this->categoryTypeId,
                'name' => 'name', 'type' => 'string',
                'required' => true, 'translatable' => true,
                'settings' => json_encode([]), 'sort_order' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

        $this->productTitleFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $this->productTypeId)->where('name', 'title')->value('id')
            ?? DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $this->productTypeId,
                'name' => 'title', 'type' => 'string',
                'required' => true, 'translatable' => true,
                'settings' => json_encode([]), 'sort_order' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

        $this->productPriceFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $this->productTypeId)->where('name', 'price')->value('id')
            ?? DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $this->productTypeId,
                'name' => 'price', 'type' => 'number',
                'required' => true, 'translatable' => false,
                'settings' => json_encode([]), 'sort_order' => 2,
                'created_at' => $now, 'updated_at' => $now,
            ]);

        $this->productSkuFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $this->productTypeId)->where('name', 'sku')->value('id')
            ?? DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $this->productTypeId,
                'name' => 'sku', 'type' => 'string',
                'required' => true, 'translatable' => false,
                'settings' => json_encode([]), 'sort_order' => 3,
                'created_at' => $now, 'updated_at' => $now,
            ]);

        $this->productCategoryFieldId = DB::table('data_type_fields')
            ->where('data_type_id', $this->productTypeId)->where('name', 'category')->value('id')
            ?? DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $this->productTypeId,
                'name' => 'category', 'type' => 'relation',
                'required' => true, 'translatable' => false,
                'settings' => json_encode(['related_data_type_id' => $this->categoryTypeId]),
                'sort_order' => 4,
                'created_at' => $now, 'updated_at' => $now,
            ]);
    }

    private function setupRelation(): void
    {
        $now = now();

        $this->relationId = DB::table('data_type_relations')
            ->where('data_type_id', $this->productTypeId)
            ->where('related_data_type_id', $this->categoryTypeId)
            ->where('relation_name', 'category')
            ->value('id')
            ?? DB::table('data_type_relations')->insertGetId([
                'data_type_id' => $this->productTypeId,
                'related_data_type_id' => $this->categoryTypeId,
                'relation_type' => 'many_to_one',
                'relation_name' => 'category',
                'created_at' => $now, 'updated_at' => $now,
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // Data: Categories & Products (بيانات واقعية)
    // ─────────────────────────────────────────────────────────────

    private function categories(): array
    {
        return [
            ['slug' => 'smartphones', 'name_en' => 'Smartphones', 'name_ar' => 'هواتف ذكية'],
            ['slug' => 'laptops', 'name_en' => 'Laptops', 'name_ar' => 'حواسيب محمولة'],
            ['slug' => 'electronics', 'name_en' => 'Electronics & Accessories', 'name_ar' => 'إلكترونيات وملحقات'],
            ['slug' => 'shoes', 'name_en' => 'Shoes', 'name_ar' => 'أحذية'],
            ['slug' => 'fashion', 'name_en' => 'Fashion', 'name_ar' => 'أزياء'],
            ['slug' => 'home-kitchen', 'name_en' => 'Home & Kitchen', 'name_ar' => 'المنزل والمطبخ'],
            ['slug' => 'sports', 'name_en' => 'Sports & Fitness', 'name_ar' => 'رياضة ولياقة'],
            ['slug' => 'books', 'name_en' => 'Books', 'name_ar' => 'كتب'],
        ];
    }

    private function products(): array
    {
        return [
            'smartphones' => [
                ['iphone-15-pro-max', 'iPhone 15 Pro Max', 'آيفون 15 برو ماكس', '1399', 'IP15PM'],
                ['iphone-14', 'iPhone 14', 'آيفون 14', '799', 'IP14'],
                ['samsung-galaxy-s24-ultra', 'Samsung Galaxy S24 Ultra', 'سامسونج جالاكسي S24 الترا', '1299', 'SGS24U'],
                ['samsung-galaxy-a54', 'Samsung Galaxy A54', 'سامسونج جالاكسي A54', '449', 'SGA54'],
                ['google-pixel-8-pro', 'Google Pixel 8 Pro', 'جوجل بيكسل 8 برو', '999', 'GP8PRO'],
                ['xiaomi-redmi-note-13', 'Xiaomi Redmi Note 13', 'شاومي ريدمي نوت 13', '299', 'XRN13'],
            ],
            'laptops' => [
                ['macbook-pro-16', 'MacBook Pro 16"', 'ماك بوك برو 16 إنش', '2499', 'MBP16'],
                ['macbook-air-m2', 'MacBook Air M2', 'ماك بوك اير M2', '1199', 'MBAM2'],
                ['dell-xps-15', 'Dell XPS 15', 'ديل XPS 15', '1599', 'DXPS15'],
                ['hp-pavilion-15', 'HP Pavilion 15', 'اتش بي بافليون 15', '699', 'HPP15'],
                ['lenovo-thinkpad-x1', 'Lenovo ThinkPad X1 Carbon', 'لينوفو ثينك باد X1', '1799', 'LTPX1'],
                ['asus-rog-strix', 'Asus ROG Strix G16', 'أسوس ROG ستريكس G16', '1899', 'AROGS'],
            ],
            'electronics' => [
                ['sony-wh1000xm5', 'Sony WH-1000XM5 Headphones', 'سماعات سوني WH-1000XM5', '399', 'SWH5'],
                ['apple-watch-9', 'Apple Watch Series 9', 'ساعة أبل سيريس 9', '429', 'AW9'],
                ['ipad-pro-129', 'iPad Pro 12.9"', 'آيباد برو 12.9 إنش', '1099', 'IPADP129'],
                ['samsung-tv-65-qled', 'Samsung 65" QLED TV', 'تلفزيون سامسونج QLED 65 إنش', '1199', 'STV65Q'],
                ['jbl-flip-6', 'JBL Flip 6 Bluetooth Speaker', 'سماعة جي بي ال فليب 6', '129', 'JBLF6'],
                ['anker-powerbank-20k', 'Anker PowerBank 20000mAh', 'شاحن أنكر 20000 مللي أمبير', '59', 'ANK20K'],
            ],
            'shoes' => [
                ['nike-air-max-270', 'Nike Air Max 270', 'نايك اير ماكس 270', '150', 'NAM270'],
                ['adidas-ultraboost', 'Adidas Ultraboost 22', 'أديداس الترا بوست 22', '180', 'AUB22'],
                ['puma-rsx', 'Puma RS-X', 'بوما RS-X', '110', 'PRSX'],
                ['new-balance-574', 'New Balance 574', 'نيو بالانس 574', '90', 'NB574'],
                ['nike-air-force-1', 'Nike Air Force 1', 'نايك اير فورس 1', '115', 'NAF1'],
                ['adidas-stan-smith', 'Adidas Stan Smith', 'أديداس ستان سميث', '95', 'ASS'],
            ],
            'fashion' => [
                ['levis-501-jeans', "Levi's 501 Original Jeans", 'بنطلون ليفايز 501 الأصلي', '69', 'LV501'],
                ['zara-cotton-tshirt', 'Zara Cotton T-Shirt', 'تيشيرت قطن زارا', '19', 'ZCT'],
                ['hm-hoodie', 'H&M Oversized Hoodie', 'هوديي واسع H&M', '35', 'HMH'],
                ['uniqlo-down-jacket', 'Uniqlo Ultra Light Down Jacket', 'جاكيت يونيكلو خفيف', '79', 'UDJ'],
                ['nike-windbreaker', 'Nike Windrunner Jacket', 'جاكيت نايك ويندرانر', '99', 'NWJ'],
                ['polo-shirt', 'Ralph Lauren Polo Shirt', 'قميص بولو رالف لورين', '89', 'RLPS'],
            ],
            'home-kitchen' => [
                ['instant-pot-duo', 'Instant Pot Duo 7-in-1', 'قدر إنستنت بوت دوو', '99', 'IPD7'],
                ['dyson-v15', 'Dyson V15 Detect Vacuum', 'مكنسة دايسون V15', '749', 'DYV15'],
                ['philips-airfryer', 'Philips Airfryer XXL', 'قلاية هوائية فيليبس XXL', '229', 'PAFXXL'],
                ['ninja-blender', 'Ninja Professional Blender', 'خلاط نينجا احترافي', '89', 'NPB'],
                ['nespresso-vertuo', 'Nespresso Vertuo Coffee Machine', 'ماكينة قهوة نسبريسو فيرتو', '179', 'NSVC'],
                ['kitchenaid-mixer', 'KitchenAid Stand Mixer', 'خلاط كيتشن ايد', '399', 'KASM'],
            ],
            'sports' => [
                ['wilson-tennis-racket', 'Wilson Pro Staff Tennis Racket', 'مضرب تنس ويلسون برو ستاف', '219', 'WPSTR'],
                ['spalding-basketball', 'Spalding NBA Basketball', 'كرة سلة سبالدينج NBA', '35', 'SNBAB'],
                ['yoga-mat-premium', 'Premium Non-Slip Yoga Mat', 'سجادة يوغا فاخرة', '29', 'YMPRM'],
                ['dumbbells-set', 'Adjustable Dumbbells Set 20kg', 'مجموعة دمبل قابلة للتعديل 20كغ', '149', 'ADS20'],
                ['treadmill-pro-x', 'Treadmill Pro X Folding', 'جهاز مشي كهربائي قابل للطي', '699', 'TPX'],
                ['bike-helmet-aero', 'Aero Road Bike Helmet', 'خوذة دراجة رياضية', '59', 'ARBH'],
            ],
            'books' => [
                ['atomic-habits', 'Atomic Habits', 'العادات الذرية', '18', 'BK-AH'],
                ['psychology-of-money', 'The Psychology of Money', 'سيكولوجية المال', '16', 'BK-POM'],
                ['clean-code', 'Clean Code', 'الكود النظيف', '35', 'BK-CC'],
                ['sapiens', 'Sapiens: A Brief History of Humankind', 'العاقل: تاريخ موجز للبشرية', '20', 'BK-SAP'],
                ['lean-startup', 'The Lean Startup', 'الشركة الناشئة الرشيقة', '17', 'BK-LS'],
                ['deep-work', 'Deep Work', 'العمل العميق', '19', 'BK-DW'],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Seeding logic
    // ─────────────────────────────────────────────────────────────

    /** @return array<string,int> [slug => entryId] */
    private function seedCategories(array $categories): array
    {
        $now = now();
        $ids = [];

        foreach ($categories as $c) {
            $entryId = DB::table('data_entries')
                ->where('project_id', $this->projectId)
                ->where('data_type_id', $this->categoryTypeId)
                ->where('slug', $c['slug'])
                ->value('id');

            if (! $entryId) {
                $entryId = DB::table('data_entries')->insertGetId([
                    'slug' => $c['slug'],
                    'data_type_id' => $this->categoryTypeId,
                    'project_id' => $this->projectId,
                    'status' => 'published',
                    'published_at' => $now,
                    'created_by' => $this->ownerId,
                    'updated_by' => $this->ownerId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $ids[$c['slug']] = $entryId;

            foreach (['en' => $c['name_en'], 'ar' => $c['name_ar']] as $lang => $value) {
                DB::table('data_entry_values')->updateOrInsert([
                    'data_entry_id' => $entryId,
                    'data_type_field_id' => $this->categoryNameFieldId,
                    'language' => $lang,
                ], ['value' => $value, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        return $ids;
    }

    /**
     * @param  array<string,array>  $productsByCategory
     * @param  array<string,int>  $categoryIds
     * @return array<int> كل الـ entry ids الناتجة
     */
    private function seedProducts(array $productsByCategory, array $categoryIds): array
    {
        $now = now();
        $entryIds = [];

        foreach ($productsByCategory as $categorySlug => $products) {
            $categoryEntryId = $categoryIds[$categorySlug] ?? null;

            foreach ($products as [$slug, $titleEn, $titleAr, $price, $sku]) {
                $entryId = DB::table('data_entries')
                    ->where('project_id', $this->projectId)
                    ->where('data_type_id', $this->productTypeId)
                    ->where('slug', $slug)
                    ->value('id');

                if (! $entryId) {
                    $entryId = DB::table('data_entries')->insertGetId([
                        'slug' => $slug,
                        'data_type_id' => $this->productTypeId,
                        'project_id' => $this->projectId,
                        'status' => 'published',
                        'published_at' => $now->copy()->subDays(rand(1, 120)),
                        'created_by' => $this->ownerId,
                        'updated_by' => $this->ownerId,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }

                $entryIds[] = $entryId;

                foreach (['en' => $titleEn, 'ar' => $titleAr] as $lang => $value) {
                    DB::table('data_entry_values')->updateOrInsert([
                        'data_entry_id' => $entryId,
                        'data_type_field_id' => $this->productTitleFieldId,
                        'language' => $lang,
                    ], ['value' => $value, 'created_at' => $now, 'updated_at' => $now]);
                }

                DB::table('data_entry_values')->updateOrInsert([
                    'data_entry_id' => $entryId,
                    'data_type_field_id' => $this->productPriceFieldId,
                    'language' => null,
                ], ['value' => $price, 'created_at' => $now, 'updated_at' => $now]);

                DB::table('data_entry_values')->updateOrInsert([
                    'data_entry_id' => $entryId,
                    'data_type_field_id' => $this->productSkuFieldId,
                    'language' => null,
                ], ['value' => $sku, 'created_at' => $now, 'updated_at' => $now]);

                if ($categoryEntryId) {
                    DB::table('data_entry_relations')->updateOrInsert([
                        'data_entry_id' => $entryId,
                        'related_entry_id' => $categoryEntryId,
                        'data_type_relation_id' => $this->relationId,
                    ], ['created_at' => $now, 'updated_at' => $now]);
                }
            }
        }

        return $entryIds;
    }

    /**
     * تقييمات واقعية (2-6 تقييمات لكل منتج) + تحديث ratings_count/ratings_avg
     */
    private function seedRatings(array $productEntryIds): void
    {
        if (empty($this->customerIds)) {
            return;
        }

        $now = now();
        $ratingPool = [3, 4, 4, 5, 5, 5, 4, 3, 5];
        $reviews = [
            5 => ['Excellent product, highly recommended!', 'ممتاز جداً وينصح فيه بشدة'],
            4 => ['Good value for the price.', 'قيمة جيدة مقابل السعر'],
            3 => ['Decent, does the job.', 'مقبول ويؤدي الغرض'],
        ];

        foreach ($productEntryIds as $entryId) {
            $reviewersCount = rand(2, 6);
            $reviewers = collect($this->customerIds)->shuffle()->take($reviewersCount);

            foreach ($reviewers as $userId) {
                $rating = $ratingPool[array_rand($ratingPool)];

                DB::table('ratings')->updateOrInsert([
                    'user_id' => $userId,
                    'rateable_type' => 'data',
                    'rateable_id' => $entryId,
                ], [
                    'rating' => $rating,
                    'review' => $reviews[$rating][array_rand([0, 1])] ?? null,
                    'created_at' => $now->copy()->subDays(rand(1, 90)),
                    'updated_at' => $now,
                ]);
            }

            $stats = DB::table('ratings')
                ->where('rateable_type', 'data')
                ->where('rateable_id', $entryId)
                ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
                ->first();

            DB::table('data_entries')->where('id', $entryId)->update([
                'ratings_count' => $stats->cnt ?? 0,
                'ratings_avg' => round($stats->avg ?? 0, 2),
            ]);
        }
    }

    /**
     * Collections: Featured / Sale (dynamic) / New Arrivals
     */
    private function seedCollections(array $productEntryIds): void
    {
        $now = now();

        // ─── Featured Products (manual - أول 10 منتجات) ──────────
        $featuredId = DB::table('data_collections')
            ->where('project_id', $this->projectId)->where('slug', 'featured-products')->value('id')
            ?? DB::table('data_collections')->insertGetId([
                'project_id' => $this->projectId,
                'data_type_id' => $this->productTypeId,
                'name' => 'Featured Products',
                'slug' => 'featured-products',
                'type' => 'manual',
                'conditions_logic' => 'and',
                'description' => 'Hand-picked featured products',
                'is_active' => true, 'is_offer' => true,
                'settings' => json_encode([]),
                'created_at' => $now, 'updated_at' => $now,
            ]);

        foreach (array_slice($productEntryIds, 0, 10) as $idx => $entryId) {
            DB::table('data_collection_items')->updateOrInsert([
                'collection_id' => $featuredId,
                'item_id' => $entryId,
            ], ['sort_order' => $idx + 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        // ─── Sale Products (dynamic - price > 500) ────────────────
        $saleId = DB::table('data_collections')
            ->where('project_id', $this->projectId)->where('slug', 'sale-products')->value('id')
            ?? DB::table('data_collections')->insertGetId([
                'project_id' => $this->projectId,
                'data_type_id' => $this->productTypeId,
                'name' => 'Sale Products',
                'slug' => 'sale-products',
                'type' => 'dynamic',
                'conditions' => json_encode([['field' => 'price', 'operator' => '>', 'value' => 500]]),
                'conditions_logic' => 'and',
                'description' => 'Dynamic products where price > 500',
                'is_active' => true, 'is_offer' => true,
                'settings' => json_encode([]),
                'created_at' => $now, 'updated_at' => $now,
            ]);

        DB::table('data_collection_items')->where('collection_id', $saleId)->delete();

        $expensive = array_values(array_filter($productEntryIds, function ($entryId) {
            $price = DB::table('data_entry_values')
                ->where('data_entry_id', $entryId)
                ->where('data_type_field_id', $this->productPriceFieldId)
                ->whereNull('language')->value('value');

            return $price !== null && (float) $price > 500;
        }));

        foreach ($expensive as $idx => $entryId) {
            DB::table('data_collection_items')->insert([
                'collection_id' => $saleId, 'item_id' => $entryId,
                'sort_order' => $idx + 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ─── New Arrivals (manual - آخر 10 منتجات بالإدخال) ──────
        $newArrivalsId = DB::table('data_collections')
            ->where('project_id', $this->projectId)->where('slug', 'new-arrivals')->value('id')
            ?? DB::table('data_collections')->insertGetId([
                'project_id' => $this->projectId,
                'data_type_id' => $this->productTypeId,
                'name' => 'New Arrivals',
                'slug' => 'new-arrivals',
                'type' => 'manual',
                'conditions_logic' => 'and',
                'description' => 'Recently added products',
                'is_active' => true, 'is_offer' => false,
                'settings' => json_encode([]),
                'created_at' => $now, 'updated_at' => $now,
            ]);

        foreach (array_slice($productEntryIds, -10) as $idx => $entryId) {
            DB::table('data_collection_items')->updateOrInsert([
                'collection_id' => $newArrivalsId,
                'item_id' => $entryId,
            ], ['sort_order' => $idx + 1, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function printSummary(array $categoryIds, array $productEntryIds): void
    {
        $this->command->info('✅ EcommerceDataSeeder completed.');
        $this->command->table(
            ['Metric', 'Value'],
            [
                ['Project ID', $this->projectId],
                ['Categories', count($categoryIds)],
                ['Products', count($productEntryIds)],
                ['Customers (for ratings)', count($this->customerIds)],
                ['Owner Email', 'ecommerce-owner@test.com | password'],
            ]
        );
    }
}