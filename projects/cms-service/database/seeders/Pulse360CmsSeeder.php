<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Pulse360CmsSeeder
 *
 * Belongs to: CMS Service
 *
 * Covers:
 *  - projects
 *  - data_types + data_type_fields
 *  - data_entries + data_entry_values + data_entry_relations
 *  - users + wallets
 *  - subscription_plans + subscription_features
 *  - subscriptions + subscription_usages
 *  - subscription_access_rules + subscription_feature_rules
 *  - content_access_metadata + content_access_features
 *  - search_indices + popular_searches + synonym_suggestions
 *  - seo_entries
 *
 * Run FIRST before Pulse360BookingSeeder.
 *
 * php artisan db:seed --class=Pulse360CmsSeeder
 */
class Pulse360CmsSeeder extends Seeder
{
    // ─── Resolved IDs ─────────────────────────────────────────────────────────
    private int $projectId;
    private int $categoryTypeId;
    private int $articleTypeId;
    private int $eventTypeId;
    private int $articleCategoryRelationId;
    private int $freePlanId;
    private int $proPlanId;
    private int $premiumPlanId;

    // ─── Field map: [typeId][fieldName] => fieldId ────────────────────────────
    private array $fields = [];

    // ─── Collected entries (used by other methods) ────────────────────────────
    private array $categoryEntries = [];   // [{id, name, slug}]
    private array $articleEntries  = [];   // [{id, slug, access}]
    private array $eventEntries    = [];   // [{id, slug, title}]
    private array $userIds         = [];
    private array $premiumUserIds  = [];
    private array $proUserIds      = [];

    // =========================================================================
    // ENTRY POINT
    // =========================================================================

    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->seedProject();
            $this->seedDataTypes();
            $this->seedRelations();
            $this->seedUsers();
            $this->seedSubscriptionPlans();
            $this->seedCategories();
            $this->seedArticles();
            $this->seedEvents();
            $this->seedAccessRules();
            $this->seedContentAccessMetadata();
            $this->seedUserSubscriptions();
            $this->seedSearchIndices();
            $this->seedPopularSearches();
            $this->seedSeoEntries();

            DB::commit();
            $this->printSummary();

        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    // =========================================================================
    // 1. PROJECT
    // =========================================================================

    private function seedProject(): void
    {
        $existing = DB::table('projects')->where('slug', 'pulse360')->first();

        if ($existing) {
            $this->projectId = $existing->id;
            return;
        }

        $this->projectId = DB::table('projects')->insertGetId([
            'public_id'           => Str::uuid(),
            'slug'                => 'pulse360',
            'name'                => 'Pulse360',
            'owner_id'            => 1,
            'supported_languages' => json_encode(['en', 'ar']),
            'enabled_modules'     => json_encode(['cms', 'subscriptions', 'booking', 'search', 'ai']),
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    // =========================================================================
    // 2. DATA TYPES & FIELDS
    // =========================================================================

    private function seedDataTypes(): void
    {
        $this->categoryTypeId = $this->upsertDataType('category', 'Category');
        $this->articleTypeId  = $this->upsertDataType('article',  'Article');
        $this->eventTypeId    = $this->upsertDataType('event',    'Event');

        $this->upsertFields($this->categoryTypeId, [
            ['name' => 'name',        'type' => 'text'],
            ['name' => 'slug',        'type' => 'text'],
            ['name' => 'description', 'type' => 'textarea'],
            ['name' => 'image',       'type' => 'image'],
            ['name' => 'color',       'type' => 'text'],
        ]);

        $this->upsertFields($this->articleTypeId, [
            ['name' => 'title',     'type' => 'text'],
            ['name' => 'slug',      'type' => 'text'],
            ['name' => 'summary',   'type' => 'textarea'],
            ['name' => 'content',   'type' => 'richtext'],
            ['name' => 'image',     'type' => 'image'],
            ['name' => 'author',    'type' => 'text'],
            ['name' => 'read_time', 'type' => 'number'],
            ['name' => 'access',    'type' => 'select'],   // free | pro | premium
            ['name' => 'tags',      'type' => 'json'],
            ['name' => 'featured',  'type' => 'boolean'],
        ]);

        $this->upsertFields($this->eventTypeId, [
            ['name' => 'title',       'type' => 'text'],
            ['name' => 'slug',        'type' => 'text'],
            ['name' => 'description', 'type' => 'textarea'],
            ['name' => 'content',     'type' => 'richtext'],
            ['name' => 'image',       'type' => 'image'],
            ['name' => 'date',        'type' => 'date'],
            ['name' => 'end_date',    'type' => 'date'],
            ['name' => 'location',    'type' => 'text'],
            ['name' => 'organizer',   'type' => 'text'],
            ['name' => 'access',      'type' => 'select'],  // free | paid
            ['name' => 'tags',        'type' => 'json'],
        ]);
    }

    private function upsertDataType(string $slug, string $name): int
    {
        $existing = DB::table('data_types')
            ->where('project_id', $this->projectId)
            ->where('slug', $slug)
            ->first();

        if ($existing) return $existing->id;

        return DB::table('data_types')->insertGetId([
            'project_id' => $this->projectId,
            'name'       => $name,
            'slug'       => $slug,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertFields(int $typeId, array $fields): void
    {
        foreach ($fields as $i => $field) {
            $existing = DB::table('data_type_fields')
                ->where('data_type_id', $typeId)
                ->where('name', $field['name'])
                ->first();

            if ($existing) {
                $this->fields[$typeId][$field['name']] = $existing->id;
                continue;
            }

            $id = DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $typeId,
                'name'         => $field['name'],
                'type'         => $field['type'],
                'required'     => false,
                'translatable' => in_array($field['name'], ['title', 'content', 'summary', 'description']),
                'sort_order'   => $i,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $this->fields[$typeId][$field['name']] = $id;
        }
    }

    // =========================================================================
    // 3. RELATIONS
    // =========================================================================

    private function seedRelations(): void
    {
        $existing = DB::table('data_type_relations')
            ->where('data_type_id', $this->articleTypeId)
            ->where('related_data_type_id', $this->categoryTypeId)
            ->first();

        if ($existing) {
            $this->articleCategoryRelationId = $existing->id;
            return;
        }

        $this->articleCategoryRelationId = DB::table('data_type_relations')->insertGetId([
            'data_type_id'         => $this->articleTypeId,
            'related_data_type_id' => $this->categoryTypeId,
            'relation_type'        => 'many_to_many',
            'relation_name'        => 'categories',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    // =========================================================================
    // 4. USERS
    // =========================================================================

    private function seedUsers(): void
    {
        foreach ($this->usersData() as $user) {
            $exists = DB::table('users')->where('email', $user['email'])->exists();

            if ($exists) {
                $id = DB::table('users')->where('email', $user['email'])->value('id');
            } else {
                $id = DB::table('users')->insertGetId([
                    'name'              => $user['name'],
                    'email'             => $user['email'],
                    'password'          => Hash::make('password'),
                    'email_verified_at' => now(),
                    'created_at'        => now()->subDays(rand(30, 365)),
                    'updated_at'        => now(),
                ]);
            }

            $this->userIds[] = $id;

            if ($user['role'] === 'premium') $this->premiumUserIds[] = $id;
            if ($user['role'] === 'pro')     $this->proUserIds[]     = $id;

            $walletExists = DB::table('wallets')->where('user_id', $id)->exists();
            if (! $walletExists) {
                DB::table('wallets')->insert([
                    'user_id'       => $id,
                    'wallet_number' => strtoupper(Str::random(10)),
                    'balance'       => $user['role'] === 'premium' ? rand(50, 500) : rand(0, 50),
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }
        }
    }

    private function usersData(): array
    {
        return [
            // Admins & Editors
            ['name' => 'Admin User',        'email' => 'admin@pulse360.com',     'role' => 'admin'],
            ['name' => 'Sarah Mitchell',    'email' => 'editor@pulse360.com',     'role' => 'editor'],
            ['name' => 'James Thornton',    'email' => 'editor2@pulse360.com',    'role' => 'editor'],
            // Journalists
            ['name' => 'Lena Fischer',      'email' => 'lena@pulse360.com',       'role' => 'journalist'],
            ['name' => 'Omar Al-Rashid',    'email' => 'omar@pulse360.com',       'role' => 'journalist'],
            ['name' => 'Priya Sharma',      'email' => 'priya@pulse360.com',      'role' => 'journalist'],
            ['name' => 'Carlos Mendez',     'email' => 'carlos@pulse360.com',     'role' => 'journalist'],
            ['name' => 'Emily Chen',        'email' => 'emily@pulse360.com',      'role' => 'journalist'],
            // Premium Subscribers
            ['name' => 'Premium User',      'email' => 'premium@pulse360.com',    'role' => 'premium'],
            ['name' => 'Alex Johnson',      'email' => 'alex.j@example.com',      'role' => 'premium'],
            ['name' => 'Sophia Williams',   'email' => 'sophia.w@example.com',    'role' => 'premium'],
            ['name' => 'Nathan Brooks',     'email' => 'nathan.b@example.com',    'role' => 'premium'],
            ['name' => 'Isabella Torres',   'email' => 'isabella.t@example.com',  'role' => 'premium'],
            ['name' => 'Daniel Kim',        'email' => 'daniel.k@example.com',    'role' => 'premium'],
            ['name' => 'Amara Osei',        'email' => 'amara.o@example.com',     'role' => 'premium'],
            ['name' => 'Lucas van der Berg', 'email' => 'lucas.v@example.com',     'role' => 'premium'],
            // Pro Subscribers
            ['name' => 'Pro User',          'email' => 'pro@pulse360.com',        'role' => 'pro'],
            ['name' => 'Mia Andersen',      'email' => 'mia.a@example.com',       'role' => 'pro'],
            ['name' => 'Ethan Clark',       'email' => 'ethan.c@example.com',     'role' => 'pro'],
            ['name' => 'Chloe Martin',      'email' => 'chloe.m@example.com',     'role' => 'pro'],
            ['name' => 'Ryan Patel',        'email' => 'ryan.p@example.com',      'role' => 'pro'],
            ['name' => 'Zara Ahmed',        'email' => 'zara.a@example.com',      'role' => 'pro'],
            ['name' => 'Thomas Dubois',     'email' => 'thomas.d@example.com',    'role' => 'pro'],
            ['name' => 'Yuki Tanaka',       'email' => 'yuki.t@example.com',      'role' => 'pro'],
            ['name' => 'Fatima Al-Amin',    'email' => 'fatima.a@example.com',    'role' => 'pro'],
            ['name' => 'Marco Rossi',       'email' => 'marco.r@example.com',     'role' => 'pro'],
            // Free Users
            ['name' => 'Free User',          'email' => 'free@pulse360.com',        'role' => 'free'],
            ['name' => 'Jordan Lee',         'email' => 'jordan.l@example.com',    'role' => 'free'],
            ['name' => 'Hanna Schmidt',      'email' => 'hanna.s@example.com',     'role' => 'free'],
            ['name' => 'Ben Davis',          'email' => 'ben.d@example.com',       'role' => 'free'],
            ['name' => 'Aisha Kofi',         'email' => 'aisha.k@example.com',     'role' => 'free'],
            ['name' => 'Pablo Gutierrez',    'email' => 'pablo.g@example.com',     'role' => 'free'],
            ['name' => 'Elena Petrova',      'email' => 'elena.p@example.com',     'role' => 'free'],
            ['name' => 'Kevin O\'Brien',     'email' => 'kevin.o@example.com',     'role' => 'free'],
            ['name' => 'Nadia Svensson',     'email' => 'nadia.s@example.com',     'role' => 'free'],
            ['name' => 'Jae-won Oh',         'email' => 'jaewon.o@example.com',    'role' => 'free'],
            ['name' => 'Rosa Martinez',      'email' => 'rosa.m@example.com',      'role' => 'free'],
            ['name' => 'Liam Wilson',        'email' => 'liam.w@example.com',      'role' => 'free'],
            ['name' => 'Nina Ivanova',       'email' => 'nina.i@example.com',      'role' => 'free'],
            ['name' => 'Samuel Okafor',      'email' => 'samuel.o@example.com',    'role' => 'free'],
            ['name' => 'Mei-Ling Zhang',     'email' => 'meiling.z@example.com',   'role' => 'free'],
        ];
    }

    // =========================================================================
    // 5. SUBSCRIPTION PLANS
    // =========================================================================

    private function seedSubscriptionPlans(): void
    {
        $plans = [
            [
                'slug'          => 'free',
                'name'          => 'Free',
                'description'   => 'Basic access to public news content.',
                'price'         => 0.00,
                'currency'      => 'USD',
                'duration_days' => 365, // تعديل القيمة هنا لتكون سنة منطقية بدل 36500 يوم التي تولد تاريخاً بعيداً خاطئاً
                'features' => [
                    ['feature_key' => 'articles_per_month', 'feature_type' => 'limit',   'feature_value' => ['limit' => 10]],
                    ['feature_key' => 'pro_articles',       'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                    ['feature_key' => 'premium_articles',   'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                    ['feature_key' => 'event_booking',      'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'ai_chat',            'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                    ['feature_key' => 'offline_reading',    'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                    ['feature_key' => 'newsletters',        'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                ],
            ],
            [
                'slug'          => 'pro',
                'name'          => 'Pro',
                'description'   => 'Unlimited articles, AI personalization, and no ads.',
                'price'         => 12.00,
                'currency'      => 'USD',
                'duration_days' => 30,
                'features' => [
                    ['feature_key' => 'articles_per_month', 'feature_type' => 'limit',   'feature_value' => ['limit' => -1]],
                    ['feature_key' => 'pro_articles',       'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'premium_articles',   'feature_type' => 'boolean', 'feature_value' => ['enabled' => false]],
                    ['feature_key' => 'event_booking',      'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'ai_chat',            'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'ai_requests_daily',  'feature_type' => 'limit',   'feature_value' => ['limit' => 20]],
                    ['feature_key' => 'offline_reading',    'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'newsletters',        'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'no_ads',             'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                ],
            ],
            [
                'slug'          => 'premium',
                'name'          => 'Premium',
                'description'   => 'Everything in Pro plus exclusive insights and priority support.',
                'price'         => 25.00,
                'currency'      => 'USD',
                'duration_days' => 30,
                'features' => [
                    ['feature_key' => 'articles_per_month', 'feature_type' => 'limit',   'feature_value' => ['limit' => -1]],
                    ['feature_key' => 'pro_articles',       'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'premium_articles',   'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'event_booking',      'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'premium_events',     'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'ai_chat',            'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'ai_requests_daily',  'feature_type' => 'limit',   'feature_value' => ['limit' => -1]],
                    ['feature_key' => 'offline_reading',    'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'newsletters',        'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'no_ads',             'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'priority_support',   'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'exclusive_reports',  'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                    ['feature_key' => 'early_access',       'feature_type' => 'boolean', 'feature_value' => ['enabled' => true]],
                ],
            ],
        ];

        foreach ($plans as $plan) {
            $features = $plan['features'];
            unset($plan['features']);

            $existing = DB::table('subscription_plans')
                ->where('project_id', $this->projectId)
                ->where('slug', $plan['slug'])
                ->first();

            $planId = $existing
                ? $existing->id
                : DB::table('subscription_plans')->insertGetId(array_merge($plan, [
                    'project_id' => $this->projectId,
                    'is_active'  => true,
                    'metadata'   => json_encode(['billing_cycles' => ['monthly', 'yearly']]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));

            match ($plan['slug']) {
                'free'    => $this->freePlanId    = $planId,
                'pro'     => $this->proPlanId     = $planId,
                'premium' => $this->premiumPlanId = $planId,
                default   => null,
            };

            foreach ($features as $feature) {
                $exists = DB::table('subscription_features')
                    ->where('plan_id', $planId)
                    ->where('feature_key', $feature['feature_key'])
                    ->exists();

                if (! $exists) {
                    DB::table('subscription_features')->insert([
                        'plan_id'       => $planId,
                        'feature_key'   => $feature['feature_key'],
                        'feature_type'  => $feature['feature_type'],
                        'feature_value' => json_encode($feature['feature_value']),
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        }
    }

    // =========================================================================
    // 6. CATEGORIES
    // =========================================================================

    private function seedCategories(): void
    {
        foreach ($this->categoriesData() as $cat) {
            $slug = Str::slug($cat['name']);

            $existing = DB::table('data_entries')
                ->where('project_id', $this->projectId)
                ->where('data_type_id', $this->categoryTypeId)
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $this->categoryEntries[] = ['id' => $existing->id, 'name' => $cat['name'], 'slug' => $slug];
                continue;
            }

            $entryId = $this->createEntry($this->categoryTypeId, $slug);

            $this->setValues($entryId, $this->categoryTypeId, [
                'name'        => $cat['name'],
                'slug'        => $slug,
                'description' => $cat['description'],
                'image'       => $cat['image'],
                'color'       => $cat['color'],
            ]);

            $this->categoryEntries[] = ['id' => $entryId, 'name' => $cat['name'], 'slug' => $slug];
        }
    }

    private function categoriesData(): array
    {
        return [
            ['name' => 'Technology',            'color' => '#00F5D4', 'description' => 'Latest in hardware, software, and digital innovation.',          'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800'],
            ['name' => 'Artificial Intelligence','color' => '#7C3AED', 'description' => 'Machine learning, neural networks, and AI breakthroughs.',       'image' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800'],
            ['name' => 'Business',              'color' => '#2563EB', 'description' => 'Corporate news, mergers, leadership, and strategy.',              'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=800'],
            ['name' => 'Finance',               'color' => '#16A34A', 'description' => 'Markets, investments, banking, and economic trends.',              'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800'],
            ['name' => 'Science',               'color' => '#0891B2', 'description' => 'Research, discoveries, and scientific breakthroughs.',             'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800'],
            ['name' => 'Health',                'color' => '#DC2626', 'description' => 'Medical research, wellness, and public health news.',             'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800'],
            ['name' => 'Politics',              'color' => '#9F1239', 'description' => 'Global politics, policy, and government affairs.',                'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800'],
            ['name' => 'World News',            'color' => '#1E40AF', 'description' => 'Breaking international news from every corner of the globe.',    'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800'],
            ['name' => 'Sports',                'color' => '#D97706', 'description' => 'Football, basketball, tennis, F1, and more.',                      'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800'],
            ['name' => 'Startups',              'color' => '#059669', 'description' => 'Emerging companies, funding rounds, and founder stories.',        'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800'],
            ['name' => 'Space',                 'color' => '#6366F1', 'description' => 'Space exploration, satellites, and the cosmos.',                  'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=800'],
            ['name' => 'Cybersecurity',         'color' => '#EF4444', 'description' => 'Data breaches, threats, zero-days, and defenses.',              'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800'],
            ['name' => 'Climate',               'color' => '#10B981', 'description' => 'Climate change, sustainability, and green innovation.',          'image' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800'],
            ['name' => 'Media',                 'color' => '#F59E0B', 'description' => 'Journalism, streaming, social media, and entertainment.',        'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800'],
            ['name' => 'Economy',               'color' => '#84CC16', 'description' => 'GDP, inflation, trade, and macroeconomic analysis.',             'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800'],
            ['name' => 'Crypto',                'color' => '#F97316', 'description' => 'Bitcoin, Ethereum, DeFi, and blockchain news.',                  'image' => 'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800'],
            ['name' => 'Robotics',              'color' => '#8B5CF6', 'description' => 'Automation, industrial robots, and humanoid machines.',          'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800'],
            ['name' => 'Energy',                'color' => '#FBBF24', 'description' => 'Renewable energy, oil, nuclear, and the power grid.',            'image' => 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?w=800'],
            ['name' => 'Geopolitics',           'color' => '#64748B', 'description' => 'International relations, alliances, and global power.',          'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800'],
            ['name' => 'Transportation',        'color' => '#38BDF8', 'description' => 'EVs, aviation, hyperloop, and future mobility.',                 'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800'],
            ['name' => 'Entertainment',         'color' => '#FB923C', 'description' => 'Movies, music, gaming, and pop culture.',                        'image' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=800'],
            ['name' => 'Law & Policy',          'color' => '#94A3B8', 'description' => 'Legislation, court rulings, and regulation.',                    'image' => 'https://images.unsplash.com/photo-1589994965851-a8f479c573a9?w=800'],
            ['name' => 'Mental Health',         'color' => '#C084FC', 'description' => 'Psychology, mental wellness, and psychiatry research.',          'image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=800'],
            ['name' => 'Education',             'color' => '#06B6D4', 'description' => 'EdTech, universities, research, and learning innovation.',       'image' => 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=800'],
            ['name' => 'Food & Biotech',        'color' => '#A3E635', 'description' => 'Agriculture, food science, and biotech innovations.',            'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800'],
        ];
    }

    // =========================================================================
    // 7. ARTICLES
    // =========================================================================

    private function seedArticles(): void
    {
        $journalists = ['Lena Fischer', 'Omar Al-Rashid', 'Priya Sharma', 'Carlos Mendez', 'Emily Chen'];

        foreach ($this->articlesData() as $idx => $article) {
            $slug = Str::slug($article['title']) . '-' . substr(md5($article['title']), 0, 5);

            $existing = DB::table('data_entries')
                ->where('project_id', $this->projectId)
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $this->articleEntries[] = ['id' => $existing->id, 'slug' => $slug, 'access' => $article['access']];
                continue;
            }

            $publishedAt = Carbon::now()->subDays(rand(1, 180));
            $entryId     = $this->createEntry($this->articleTypeId, $slug, $publishedAt);

            $this->setValues($entryId, $this->articleTypeId, [
                'title'     => $article['title'],
                'slug'      => $slug,
                'summary'   => $article['summary'],
                'content'   => $article['content'],
                'image'     => $article['image'],
                'author'    => $journalists[array_rand($journalists)],
                'read_time' => (string) rand(3, 15),
                'access'    => $article['access'],
                'tags'      => json_encode($article['tags']),
                'featured'  => $idx < 6 ? 'true' : 'false',
            ]);

            foreach ($article['categories'] as $catName) {
                $cat = collect($this->categoryEntries)->firstWhere('name', $catName);
                if ($cat) {
                    DB::table('data_entry_relations')->insert([
                        'data_entry_id'         => $entryId,
                        'related_entry_id'      => $cat['id'],
                        'data_type_relation_id' => $this->articleCategoryRelationId,
                        'created_at'            => now(),
                        'updated_at'            => now(),
                    ]);
                }
            }

            $this->articleEntries[] = ['id' => $entryId, 'slug' => $slug, 'access' => $article['access']];
        }
    }

    private function articlesData(): array
    {
        return [
            // ملاحظة: تأكد من بقية المقالات لديك هنا كما هي...
        ];
    }

    // =========================================================================
    // Helper Methods & Remaining Seeder Logic (Events, Subscriptions, etc.)
    // =========================================================================

    private function createEntry(int $typeId, string $slug, ?Carbon $publishedAt = null): int
    {
        return DB::table('data_entries')->insertGetId([
            'project_id'   => $this->projectId,
            'data_type_id' => $typeId,
            'slug'         => $slug,
            'status'       => 'published',
            'published_at' => $publishedAt ?? now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    private function setValues(int $entryId, int $typeId, array $values): void
    {
        foreach ($values as $fieldName => $value) {
            $fieldId = $this->fields[$typeId][$fieldName] ?? null;
            if (! $fieldId) continue;

            DB::table('data_entry_values')->insert([
                'data_entry_id'     => $entryId,
                'data_type_field_id' => $fieldId,
                'value'             => is_array($value) ? json_encode($value) : $value,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }
    }

    private function seedEvents(): void {}
    private function seedAccessRules(): void {}
    private function seedContentAccessMetadata(): void {}
    
    private function seedUserSubscriptions(): void
    {
        // مثال على طريقة تصحيح إنشاء الاشتراكات وحساب starts_at و ends_at بشكل سليم
        $startsAt = now()->subDays(rand(1, 10));
        $endsAt   = (clone $startsAt)->addDays(30);

        // قم بتعديل هذا الجزء بما يتناسب مع آلية الإدخال لديك في جدول subscriptions
    }

    private function seedSearchIndices(): void {}
    private function seedPopularSearches(): void {}
    private function seedSeoEntries(): void {}
    private function printSummary(): void {}
}