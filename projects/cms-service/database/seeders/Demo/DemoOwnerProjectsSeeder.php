<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The project group belonging to owner1@example.com.
 *
 *   Nova Marketplace  [cms, ecommerce, booking]  ← the comprehensive one
 *   Nova Journal      [cms]
 *   Nova Studio       [cms, booking]
 *
 * Nova Marketplace is the project meant to be explored: four data types with
 * a relation between them, entries in every status, two languages, SEO rows,
 * ratings, and both a manual and a dynamic collection. The E-Commerce and
 * Booking demo seeders attach their own data to this same project id.
 *
 * Run: php artisan db:seed --class="Database\Seeders\Demo\DemoOwnerProjectsSeeder"
 *
 * Safe to re-run — each project is purged before it is rebuilt.
 */
class DemoOwnerProjectsSeeder extends Seeder
{
    use DemoContentBuilder;

    public function run(): void
    {
        DB::transaction(function () {
            $this->mirrorDemoUsers();

            $this->seedMarketplace();
            $this->seedJournal();
            $this->seedStudio();
        });

        $this->flushReadCaches();

        $this->command?->info('Owner (owner1@example.com) projects seeded:');
        $this->command?->table(
            ['id', 'name', 'modules', 'X-Project-Id'],
            [
                [DemoIds::OWNER_PROJECT_MARKETPLACE, 'Nova Marketplace (comprehensive)', 'cms, ecommerce, booking', self::demoPublicId(DemoIds::OWNER_PROJECT_MARKETPLACE)],
                [DemoIds::OWNER_PROJECT_JOURNAL, 'Nova Journal', 'cms', self::demoPublicId(DemoIds::OWNER_PROJECT_JOURNAL)],
                [DemoIds::OWNER_PROJECT_STUDIO, 'Nova Studio', 'cms, booking', self::demoPublicId(DemoIds::OWNER_PROJECT_STUDIO)],
            ]
        );
        $this->command?->comment('Run the E-Commerce and Booking demo seeders next to fill the marketplace modules.');
    }

    // =========================================================
    // The comprehensive project
    // =========================================================
    private function seedMarketplace(): void
    {
        $projectId = DemoIds::OWNER_PROJECT_MARKETPLACE;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::OWNER_USER_ID,
            name: 'Nova Marketplace',
            slug: 'nova-marketplace',
            description: 'A marketplace that sells products, publishes articles and takes bookings — every module enabled, every content state represented.',
            languages: ['en', 'ar'],
            modules: ['cms', 'ecommerce', 'booking'],
        );

        // ─── Data types ───────────────────────────────────────
        $categoryFields = $this->createDataType(
            id: DemoIds::MARKETPLACE_TYPE_CATEGORY,
            projectId: $projectId,
            name: 'Category',
            slug: 'categories',
            description: 'Groups products together.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'summary', 'type' => 'text', 'translatable' => true, 'rules' => ['string', 'max:500']],
            ],
        );

        $productFields = $this->createDataType(
            id: DemoIds::MARKETPLACE_TYPE_PRODUCT,
            projectId: $projectId,
            name: 'Product',
            slug: 'products',
            description: 'A sellable item. price and count are the two fields the ecommerce module reads.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'description', 'type' => 'text', 'translatable' => true, 'rules' => ['string']],
                // The pricing and stock paths look these up by name.
                ['name' => 'price', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'count', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'sku', 'type' => 'text', 'rules' => ['string', 'max:64']],
                ['name' => 'is_featured', 'type' => 'boolean', 'rules' => ['boolean'], 'settings' => ['default' => false]],
                ['name' => 'gallery', 'type' => 'file', 'settings' => ['multiple' => true, 'allowed_types' => ['jpg', 'png', 'webp']]],
                ['name' => 'category', 'type' => 'relation', 'settings' => [
                    'relation_type' => 'belongs_to',
                    'related_data_type_id' => DemoIds::MARKETPLACE_TYPE_CATEGORY,
                    'multiple' => false,
                ]],
            ],
        );

        $articleFields = $this->createDataType(
            id: DemoIds::MARKETPLACE_TYPE_ARTICLE,
            projectId: $projectId,
            name: 'Article',
            slug: 'articles',
            description: 'Editorial content published alongside the catalogue.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'body', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string']],
                ['name' => 'reading_minutes', 'type' => 'number', 'rules' => ['numeric', 'min:1']],
            ],
        );

        $serviceFields = $this->createDataType(
            id: DemoIds::MARKETPLACE_TYPE_SERVICE,
            projectId: $projectId,
            name: 'Service',
            slug: 'services',
            description: 'A bookable service. The booking module mirrors these as resources.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'description', 'type' => 'text', 'translatable' => true, 'rules' => ['string']],
                ['name' => 'price', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'duration_minutes', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:5']],
            ],
        );

        // Product → Category, declared so relation-typed reads resolve.
        $relationId = DB::table('data_type_relations')->insertGetId([
            'data_type_id' => DemoIds::MARKETPLACE_TYPE_PRODUCT,
            'related_data_type_id' => DemoIds::MARKETPLACE_TYPE_CATEGORY,
            'relation_type' => 'belongs_to',
            'relation_name' => 'category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('data_type_fields')
            ->where('data_type_id', DemoIds::MARKETPLACE_TYPE_PRODUCT)
            ->where('name', 'category')
            ->update(['settings' => json_encode([
                'relation_type' => 'belongs_to',
                'related_data_type_id' => DemoIds::MARKETPLACE_TYPE_CATEGORY,
                'data_type_relation_id' => $relationId,
                'multiple' => false,
            ])]);

        // ─── Categories ───────────────────────────────────────
        $categories = [
            [DemoIds::MARKETPLACE_CATEGORY_FIRST, 'audio', 'Audio', 'صوتيات', 'Headphones, speakers and everything that makes noise.', 'سماعات ومكبرات صوت.'],
            [DemoIds::MARKETPLACE_CATEGORY_FIRST + 1, 'workspace', 'Workspace', 'مساحة العمل', 'Desks, lamps and chairs.', 'مكاتب وإضاءة وكراسي.'],
            [DemoIds::MARKETPLACE_CATEGORY_FIRST + 2, 'outdoor', 'Outdoor', 'الأنشطة الخارجية', 'Gear for being outside.', 'معدات للأنشطة الخارجية.'],
        ];

        foreach ($categories as $index => [$id, $slug, $titleEn, $titleAr, $summaryEn, $summaryAr]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: DemoIds::MARKETPLACE_TYPE_CATEGORY,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $categoryFields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'summary' => ['en' => $summaryEn, 'ar' => $summaryAr],
                ],
                createdAt: now()->subDays(60 - ($index * 2)),
            );
        }

        // ─── Products ─────────────────────────────────────────
        // Prices deliberately straddle 100 so the dynamic collection below
        // ("under 100") has both matches and non-matches, and so the numeric
        // comparison is actually exercised rather than trivially true.
        $products = [
            [9411, 'nova-headphones',   'Nova Headphones',    'سماعات نوفا',        'Over-ear wireless headphones.', 249.00, 35, 'NVA-AUD-001', true,  9401],
            [9412, 'nova-earbuds',      'Nova Earbuds',       'سماعات أذن نوفا',    'Compact wireless earbuds.',      89.00, 120, 'NVA-AUD-002', true,  9401],
            [9413, 'nova-speaker',      'Nova Desk Speaker',  'مكبر صوت نوفا',      'A small speaker for a desk.',    59.50, 64, 'NVA-AUD-003', false, 9401],
            [9414, 'nova-desk-lamp',    'Nova Desk Lamp',     'مصباح مكتب نوفا',    'Warm adjustable desk lamp.',     42.00, 90, 'NVA-WRK-001', false, 9402],
            [9415, 'nova-standing-desk', 'Nova Standing Desk', 'مكتب نوفا المتحرك', 'Height adjustable desk.',       640.00, 12, 'NVA-WRK-002', true,  9402],
            [9416, 'nova-chair',        'Nova Task Chair',    'كرسي نوفا',          'Ergonomic task chair.',         310.00, 25, 'NVA-WRK-003', false, 9402],
            [9417, 'nova-bottle',       'Nova Bottle',        'زجاجة نوفا',         'Insulated 750ml bottle.',        26.00, 200, 'NVA-OUT-001', false, 9403],
            [9418, 'nova-backpack',     'Nova Backpack',      'حقيبة نوفا',         '28L weather-resistant backpack.', 135.00, 48, 'NVA-OUT-002', true,  9403],
        ];

        foreach ($products as $index => [$id, $slug, $titleEn, $titleAr, $descEn, $price, $count, $sku, $featured, $categoryId]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: DemoIds::MARKETPLACE_TYPE_PRODUCT,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $productFields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'description' => ['en' => $descEn, 'ar' => "وصف {$titleAr} باللغة العربية."],
                    'price' => $price,
                    'count' => $count,
                    'sku' => $sku,
                    'is_featured' => $featured ? '1' : '0',
                    'gallery' => "projects/{$projectId}/products/{$slug}.jpg",
                ],
                createdAt: now()->subDays(50 - ($index * 3)),
            );

            DB::table('data_entry_relations')->insert([
                'data_entry_id' => $id,
                'related_entry_id' => $categoryId,
                'data_type_relation_id' => $relationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->addSeo($id, 'en', $titleEn, $descEn, $slug);
        }

        // ─── Articles: one of each status ─────────────────────
        $articles = [
            [9421, 'choosing-headphones', 'published', 'Choosing Your First Headphones', 'كيف تختار سماعتك الأولى', 7, null],
            [9422, 'desk-setup-guide',    'published', 'A Desk Setup That Lasts',        'إعداد مكتب يدوم',          11, null],
            [9423, 'winter-gear-preview', 'draft',     'Winter Gear Preview',            'معاينة معدات الشتاء',       5, null],
            [9424, 'spring-collection',   'scheduled', 'Spring Collection Announcement', 'إعلان تشكيلة الربيع',       4, '+10 days'],
        ];

        foreach ($articles as $index => [$id, $slug, $status, $titleEn, $titleAr, $minutes, $scheduleOffset]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: DemoIds::MARKETPLACE_TYPE_ARTICLE,
                slug: $slug,
                status: $status,
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $articleFields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'body' => [
                        'en' => "{$titleEn}. ".str_repeat('This paragraph exists so the search index has something to tokenise. ', 5),
                        'ar' => "{$titleAr}. ".str_repeat('هذه الفقرة موجودة ليكون لدى فهرس البحث نص عربي حقيقي. ', 5),
                    ],
                    'reading_minutes' => $minutes,
                ],
                createdAt: now()->subDays(40 - ($index * 6)),
                scheduledAt: $scheduleOffset ? now()->modify($scheduleOffset) : null,
            );

            if ($status === 'published') {
                $this->addSeo($id, 'en', $titleEn, "Read {$titleEn} on Nova Marketplace.", $slug);
                $this->addSeo($id, 'ar', $titleAr, "اقرأ {$titleAr} على نوفا ماركتبليس.", $slug.'-ar');
            }
        }

        // ─── Services (mirrored as booking resources) ─────────
        $services = [
            [9431, 'studio-session',    'Recording Studio Session', 'جلسة استوديو تسجيل', 'One hour in the recording booth.', 120.00, 60],
            [9432, 'setup-consultation', 'Workspace Consultation',  'استشارة مساحة عمل',   'A specialist plans your desk.',     80.00, 45],
            [9433, 'gear-fitting',      'Outdoor Gear Fitting',     'قياس معدات خارجية',   'Get your pack fitted properly.',    35.00, 30],
        ];

        foreach ($services as $index => [$id, $slug, $titleEn, $titleAr, $descEn, $price, $duration]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: DemoIds::MARKETPLACE_TYPE_SERVICE,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $serviceFields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'description' => ['en' => $descEn, 'ar' => "وصف {$titleAr}."],
                    'price' => $price,
                    'duration_minutes' => $duration,
                ],
                createdAt: now()->subDays(35 - ($index * 4)),
            );
        }

        // ─── Collections ──────────────────────────────────────
        DB::table('data_collections')->insert([
            [
                'id' => DemoIds::MARKETPLACE_COLLECTION_FEATURED,
                'project_id' => $projectId,
                'data_type_id' => DemoIds::MARKETPLACE_TYPE_PRODUCT,
                'name' => 'Featured Products',
                'slug' => 'featured-products',
                'type' => 'manual',
                'conditions' => null,
                'conditions_logic' => 'and',
                'description' => 'Hand-picked products for the home page.',
                'is_active' => true,
                'is_offer' => false,
                'settings' => json_encode([]),
                'created_at' => now()->subDays(30),
                'updated_at' => now(),
            ],
            [
                'id' => DemoIds::MARKETPLACE_COLLECTION_AFFORDABLE,
                'project_id' => $projectId,
                'data_type_id' => DemoIds::MARKETPLACE_TYPE_PRODUCT,
                'name' => 'Under 100',
                'slug' => 'under-100',
                'type' => 'dynamic',
                // Numeric comparison against a text column: this is the case
                // that used to return nothing for two-digit prices.
                'conditions' => json_encode([
                    ['field' => 'price', 'operator' => '<', 'value' => '100'],
                ]),
                'conditions_logic' => 'and',
                'description' => 'Everything below 100 — regenerated from the price field.',
                'is_active' => true,
                'is_offer' => false,
                'settings' => json_encode([]),
                'created_at' => now()->subDays(28),
                'updated_at' => now(),
            ],
        ]);

        // Manual collection: the four flagged products, in a deliberate order.
        $featured = [9411, 9412, 9415, 9418];

        foreach ($featured as $sortOrder => $entryId) {
            DB::table('data_collection_items')->insert([
                'collection_id' => DemoIds::MARKETPLACE_COLLECTION_FEATURED,
                'item_id' => $entryId,
                'sort_order' => $sortOrder + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Dynamic collection materialised to match its own condition
        // (89.00, 59.50, 42.00, 26.00 are the prices under 100).
        $affordable = [9412, 9413, 9414, 9417];

        foreach ($affordable as $sortOrder => $entryId) {
            DB::table('data_collection_items')->insert([
                'collection_id' => DemoIds::MARKETPLACE_COLLECTION_AFFORDABLE,
                'item_id' => $entryId,
                'sort_order' => $sortOrder + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ─── Ratings ──────────────────────────────────────────
        $this->rate('data', 9411, [
            ['user' => DemoIds::CUSTOMER_ONE_ID, 'rating' => 5, 'review' => 'Sound is excellent, battery lasts.'],
            ['user' => DemoIds::CUSTOMER_TWO_ID, 'rating' => 4, 'review' => 'Comfortable, slightly heavy.'],
            ['user' => DemoIds::CUSTOMER_THREE_ID, 'rating' => 5, 'review' => 'Worth the price.'],
        ]);

        $this->rate('data', 9415, [
            ['user' => DemoIds::CUSTOMER_TWO_ID, 'rating' => 4, 'review' => 'Sturdy, assembly took a while.'],
            ['user' => DemoIds::CUSTOMER_THREE_ID, 'rating' => 3, 'review' => 'Good, but the motor is loud.'],
        ]);

        $this->rate('data', 9421, [
            ['user' => DemoIds::CUSTOMER_ONE_ID, 'rating' => 5, 'review' => 'Answered exactly what I was asking.'],
        ]);

        $this->rate('project', $projectId, [
            ['user' => DemoIds::CUSTOMER_ONE_ID, 'rating' => 5, 'review' => 'Easy to browse.'],
            ['user' => DemoIds::CUSTOMER_TWO_ID, 'rating' => 4, 'review' => 'Good range, fast checkout.'],
            ['user' => DemoIds::CUSTOMER_THREE_ID, 'rating' => 4, 'review' => 'Booking a session was simple.'],
        ]);
    }

    // =========================================================
    private function seedJournal(): void
    {
        $projectId = DemoIds::OWNER_PROJECT_JOURNAL;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::OWNER_USER_ID,
            name: 'Nova Journal',
            slug: 'nova-journal',
            description: 'Long-form writing only.',
            languages: ['en'],
            modules: ['cms'],
        );

        $fields = $this->createDataType(
            id: 9211,
            projectId: $projectId,
            name: 'Essay',
            slug: 'essays',
            description: 'A long-form piece.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'body', 'type' => 'text', 'required' => true, 'rules' => ['string']],
            ],
        );

        $essays = [
            [9241, 'on-slow-software', 'published', 'On Slow Software'],
            [9242, 'the-cost-of-defaults', 'published', 'The Cost of Defaults'],
            [9243, 'unfinished-notes', 'draft', 'Unfinished Notes'],
        ];

        foreach ($essays as $index => [$id, $slug, $status, $title]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: 9211,
                slug: $slug,
                status: $status,
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $fields,
                values: [
                    'title' => $title,
                    'body' => "{$title}. ".str_repeat('An essay paragraph. ', 8),
                ],
                createdAt: now()->subDays(45 - ($index * 10)),
            );
        }
    }

    // =========================================================
    private function seedStudio(): void
    {
        $projectId = DemoIds::OWNER_PROJECT_STUDIO;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::OWNER_USER_ID,
            name: 'Nova Studio',
            slug: 'nova-studio',
            description: 'Rooms and equipment rented by the hour.',
            languages: ['en', 'ar'],
            modules: ['cms', 'booking'],
        );

        $fields = $this->createDataType(
            id: 9212,
            projectId: $projectId,
            name: 'Room',
            slug: 'rooms',
            description: 'A rentable room.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'price', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'capacity', 'type' => 'number', 'rules' => ['numeric', 'min:1']],
            ],
        );

        $rooms = [
            [9251, 'live-room', 'Live Room', 'غرفة التسجيل', 95.00, 6],
            [9252, 'edit-suite', 'Edit Suite', 'غرفة المونتاج', 55.00, 2],
        ];

        foreach ($rooms as $index => [$id, $slug, $titleEn, $titleAr, $price, $capacity]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: 9212,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::OWNER_USER_ID,
                fieldIds: $fields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'price' => $price,
                    'capacity' => $capacity,
                ],
                createdAt: now()->subDays(22 - ($index * 5)),
            );
        }
    }
}
