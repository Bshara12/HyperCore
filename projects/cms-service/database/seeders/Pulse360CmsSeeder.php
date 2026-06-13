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
            ['name' => 'Admin User',         'email' => 'admin@pulse360.com',      'role' => 'admin'],
            ['name' => 'Sarah Mitchell',     'email' => 'editor@pulse360.com',     'role' => 'editor'],
            ['name' => 'James Thornton',     'email' => 'editor2@pulse360.com',    'role' => 'editor'],
            // Journalists
            ['name' => 'Lena Fischer',       'email' => 'lena@pulse360.com',       'role' => 'journalist'],
            ['name' => 'Omar Al-Rashid',     'email' => 'omar@pulse360.com',       'role' => 'journalist'],
            ['name' => 'Priya Sharma',       'email' => 'priya@pulse360.com',      'role' => 'journalist'],
            ['name' => 'Carlos Mendez',      'email' => 'carlos@pulse360.com',     'role' => 'journalist'],
            ['name' => 'Emily Chen',         'email' => 'emily@pulse360.com',      'role' => 'journalist'],
            // Premium Subscribers
            ['name' => 'Premium User',       'email' => 'premium@pulse360.com',    'role' => 'premium'],
            ['name' => 'Alex Johnson',       'email' => 'alex.j@example.com',      'role' => 'premium'],
            ['name' => 'Sophia Williams',    'email' => 'sophia.w@example.com',    'role' => 'premium'],
            ['name' => 'Nathan Brooks',      'email' => 'nathan.b@example.com',    'role' => 'premium'],
            ['name' => 'Isabella Torres',    'email' => 'isabella.t@example.com',  'role' => 'premium'],
            ['name' => 'Daniel Kim',         'email' => 'daniel.k@example.com',    'role' => 'premium'],
            ['name' => 'Amara Osei',         'email' => 'amara.o@example.com',     'role' => 'premium'],
            ['name' => 'Lucas van der Berg', 'email' => 'lucas.v@example.com',     'role' => 'premium'],
            // Pro Subscribers
            ['name' => 'Pro User',           'email' => 'pro@pulse360.com',        'role' => 'pro'],
            ['name' => 'Mia Andersen',       'email' => 'mia.a@example.com',       'role' => 'pro'],
            ['name' => 'Ethan Clark',        'email' => 'ethan.c@example.com',     'role' => 'pro'],
            ['name' => 'Chloe Martin',       'email' => 'chloe.m@example.com',     'role' => 'pro'],
            ['name' => 'Ryan Patel',         'email' => 'ryan.p@example.com',      'role' => 'pro'],
            ['name' => 'Zara Ahmed',         'email' => 'zara.a@example.com',      'role' => 'pro'],
            ['name' => 'Thomas Dubois',      'email' => 'thomas.d@example.com',    'role' => 'pro'],
            ['name' => 'Yuki Tanaka',        'email' => 'yuki.t@example.com',      'role' => 'pro'],
            ['name' => 'Fatima Al-Amin',     'email' => 'fatima.a@example.com',    'role' => 'pro'],
            ['name' => 'Marco Rossi',        'email' => 'marco.r@example.com',     'role' => 'pro'],
            // Free Users
            ['name' => 'Free User',          'email' => 'free@pulse360.com',       'role' => 'free'],
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
                'duration_days' => 36500,
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
            ['name' => 'Finance',               'color' => '#16A34A', 'description' => 'Markets, investments, banking, and economic trends.',             'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800'],
            ['name' => 'Science',               'color' => '#0891B2', 'description' => 'Research, discoveries, and scientific breakthroughs.',            'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800'],
            ['name' => 'Health',                'color' => '#DC2626', 'description' => 'Medical research, wellness, and public health news.',            'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800'],
            ['name' => 'Politics',              'color' => '#9F1239', 'description' => 'Global politics, policy, and government affairs.',               'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800'],
            ['name' => 'World News',            'color' => '#1E40AF', 'description' => 'Breaking international news from every corner of the globe.',    'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800'],
            ['name' => 'Sports',                'color' => '#D97706', 'description' => 'Football, basketball, tennis, F1, and more.',                    'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800'],
            ['name' => 'Startups',              'color' => '#059669', 'description' => 'Emerging companies, funding rounds, and founder stories.',       'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800'],
            ['name' => 'Space',                 'color' => '#6366F1', 'description' => 'Space exploration, satellites, and the cosmos.',                 'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=800'],
            ['name' => 'Cybersecurity',         'color' => '#EF4444', 'description' => 'Data breaches, threats, zero-days, and defenses.',              'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800'],
            ['name' => 'Climate',               'color' => '#10B981', 'description' => 'Climate change, sustainability, and green innovation.',          'image' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800'],
            ['name' => 'Media',                 'color' => '#F59E0B', 'description' => 'Journalism, streaming, social media, and entertainment.',        'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800'],
            ['name' => 'Economy',               'color' => '#84CC16', 'description' => 'GDP, inflation, trade, and macroeconomic analysis.',             'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800'],
            ['name' => 'Crypto',                'color' => '#F97316', 'description' => 'Bitcoin, Ethereum, DeFi, and blockchain news.',                  'image' => 'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800'],
            ['name' => 'Robotics',              'color' => '#8B5CF6', 'description' => 'Automation, industrial robots, and humanoid machines.',          'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800'],
            ['name' => 'Energy',                'color' => '#FBBF24', 'description' => 'Renewable energy, oil, nuclear, and the power grid.',           'image' => 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?w=800'],
            ['name' => 'Geopolitics',           'color' => '#64748B', 'description' => 'International relations, alliances, and global power.',          'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800'],
            ['name' => 'Transportation',        'color' => '#38BDF8', 'description' => 'EVs, aviation, hyperloop, and future mobility.',                 'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800'],
            ['name' => 'Entertainment',         'color' => '#FB923C', 'description' => 'Movies, music, gaming, and pop culture.',                       'image' => 'https://images.unsplash.com/photo-1478720568477-152d9b164e26?w=800'],
            ['name' => 'Law & Policy',          'color' => '#94A3B8', 'description' => 'Legislation, court rulings, and regulation.',                   'image' => 'https://images.unsplash.com/photo-1589994965851-a8f479c573a9?w=800'],
            ['name' => 'Mental Health',         'color' => '#C084FC', 'description' => 'Psychology, mental wellness, and psychiatry research.',         'image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=800'],
            ['name' => 'Education',             'color' => '#06B6D4', 'description' => 'EdTech, universities, research, and learning innovation.',      'image' => 'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=800'],
            ['name' => 'Food & Biotech',        'color' => '#A3E635', 'description' => 'Agriculture, food science, and biotech innovations.',           'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800'],
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
            // ── FREE (≈60%) ──────────────────────────────────────────────────
            ['title' => 'GPT-5 Arrives: What Businesses Need to Know Right Now', 'summary' => 'OpenAI\'s latest model delivers reasoning capabilities that challenge human experts across scientific and legal domains.', 'content' => "OpenAI has unveiled GPT-5, a model representing a fundamental leap in language understanding. Unlike its predecessor, GPT-5 exhibits persistent memory across sessions and performs multi-step scientific reasoning with citation-level accuracy.\n\nIn internal benchmarks, GPT-5 scored in the top 1% of the bar exam and outperformed specialist physicians on diagnostic accuracy for rare diseases.\n\nEarly adopters in the legal sector are already using GPT-5 to draft contracts, run due diligence, and produce first-draft litigation briefs. A San Francisco-based legal tech startup reported a 70% reduction in paralegal hours in a controlled trial.\n\nPricing starts at \$0.01 per 1K tokens, with enterprise agreements available for high-volume use cases.", 'image' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800', 'access' => 'free', 'categories' => ['Artificial Intelligence', 'Technology'], 'tags' => ['OpenAI', 'GPT-5', 'AI', 'LLM']],

            ['title' => 'Nvidia Surpasses $4 Trillion Market Cap — A New Record', 'summary' => 'The chip giant reaches unprecedented valuation as AI infrastructure demand shows no signs of slowing.', 'content' => "Nvidia has become the first company in history to sustain a market capitalization above \$4 trillion for 30 consecutive trading days.\n\nThe milestone comes on the back of record-breaking quarterly revenue driven by sales of H200 and Blackwell GPU clusters to hyperscalers including Microsoft Azure, Google Cloud, and Amazon Web Services.\n\nCEO Jensen Huang: \"Every major enterprise is now building its own AI factory. The demand is not a wave. It is a permanent shift in how the world computes.\"\n\nShares rose 3.2% on the announcement. Short sellers have lost over \$18 billion betting against the stock in the past 12 months.", 'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800', 'access' => 'free', 'categories' => ['Technology', 'Finance'], 'tags' => ['Nvidia', 'stocks', 'AI chips', 'market cap']],

            ['title' => 'Scientists Achieve Cold Fusion Breakthrough at MIT', 'summary' => 'MIT researchers claim to have sustained a net-energy fusion reaction for the first time in laboratory conditions.', 'content' => "Researchers at MIT's Plasma Science and Fusion Center announced a sustained net-energy fusion reaction lasting 47 seconds — longer than any previously recorded.\n\nThe experiment used a compact high-temperature superconducting magnet design that dramatically reduces the cost and size requirements for fusion reactors.\n\n\"We achieved Q > 1.4 — meaning we extracted 1.4 times more energy than we put in,\" said lead researcher Dr. Aiko Matsumoto. \"That changes everything.\"\n\nCommonwealth Fusion Systems has raised \$2.1 billion in private funding, targeting a demonstration plant by 2030 and commercial grid delivery by 2035.", 'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800', 'access' => 'free', 'categories' => ['Science', 'Energy'], 'tags' => ['fusion', 'clean energy', 'MIT', 'nuclear']],

            ['title' => 'Apple Vision Pro 2 Launches: The Spatial Computing Era Begins', 'summary' => 'Apple\'s second-generation spatial computer is thinner, 40% lighter, and finally has the app ecosystem to match its ambitions.', 'content' => "Apple unveiled the Vision Pro 2 at its Worldwide Developers Conference. The device is 40% lighter and introduces a new EyeSight display.\n\nThe redesigned R2X chip delivers twice the neural processing power, enabling real-time 3D scene reconstruction and persistent AR overlays.\n\nThe App Store now features over 1,400 native spatial apps, including a redesigned Final Cut Pro allowing editors to spread a 25-camera edit across their entire room.\n\nPricing starts at \$2,799 — down from the original \$3,499.", 'image' => 'https://images.unsplash.com/photo-1651341601634-9a9e0f2cb6b4?w=800', 'access' => 'free', 'categories' => ['Technology', 'Entertainment'], 'tags' => ['Apple', 'Vision Pro', 'AR', 'spatial computing']],

            ['title' => 'Global EV Sales Hit 22 Million in 2025, Led by BYD', 'summary' => 'Electric vehicle adoption accelerated sharply with China\'s BYD overtaking Tesla as the world\'s largest EV maker by volume.', 'content' => "Global electric vehicle sales reached 22.4 million units in 2025, representing a 31% year-over-year increase.\n\nChina's BYD surpassed Tesla for the third consecutive year, selling 4.2 million fully electric vehicles. Including its hybrid lineup, BYD's total electrified sales reached 7.1 million.\n\nEurope saw its fastest adoption rate since EV incentives were introduced, with Norway becoming the first country where new ICE vehicle sales fell below 3% of total auto registrations.\n\nCharging infrastructure remains the primary bottleneck in markets like the United States and Southeast Asia.", 'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800', 'access' => 'free', 'categories' => ['Transportation', 'Technology'], 'tags' => ['EV', 'BYD', 'Tesla', 'electric vehicles']],

            ['title' => 'World Leaders Sign Historic Ocean Protection Treaty', 'summary' => 'After four years of negotiations, 175 nations agreed to protect 30% of the world\'s oceans from industrial activity by 2030.', 'content' => "175 nations signed the Global Ocean Biodiversity Beyond National Jurisdiction Treaty in Geneva, committing to protect at least 30% of international waters by 2030.\n\nThe treaty establishes a new international body with authority to designate marine protected areas and regulate deep-sea mining.\n\nEnvironmentalists called it the most significant ocean conservation agreement since the establishment of UNCLOS in 1982.\n\n\"The paper is historic. The implementation will define the legacy,\" said Maria Kowalczyk of Oceana International.", 'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800', 'access' => 'free', 'categories' => ['Climate', 'World News', 'Politics'], 'tags' => ['ocean', 'treaty', 'climate', 'environment']],

            ['title' => 'SpaceX Starship Completes First Crewed Lunar Orbit', 'summary' => 'NASA and SpaceX mark a critical milestone in the Artemis program as four astronauts circle the Moon for six days.', 'content' => "SpaceX's Starship HLS completed its first crewed lunar orbit mission, carrying four astronauts on a six-day journey that brought them within 20 kilometers of the lunar surface.\n\nCommander Jamila Hassan: \"You see the Earth, this perfect blue sphere, and then you look the other way and there's nothing. Absolute nothing. It changes your perspective permanently.\"\n\nThe mission tested life support systems, communications latency, and cryogenic fuel transfer in deep space conditions.\n\nNASA administrator Bill Nelson called it \"the most complex human spaceflight mission since Apollo 17.\"", 'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=800', 'access' => 'free', 'categories' => ['Space', 'Technology'], 'tags' => ['SpaceX', 'Starship', 'NASA', 'Moon', 'Artemis']],

            ['title' => 'Crypto Market Rebounds: Bitcoin Crosses $120,000', 'summary' => 'Bitcoin breaks its all-time high as institutional demand surges following new US spot ETF inflows.', 'content' => "Bitcoin surpassed \$120,000 for the first time in history as institutional demand absorbs available supply at record rates.\n\nBlackRock's iShares Bitcoin Trust crossed \$60 billion in assets under management — a figure that took the firm's flagship gold ETF 19 years to reach.\n\n\"The math is simple,\" noted Galaxy Digital's Alex Thorn. \"Demand is growing. Supply is fixed. Price clears the market.\"\n\nEthereum and Solana followed Bitcoin higher, rising 18% and 24% respectively in the past week.", 'image' => 'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800', 'access' => 'free', 'categories' => ['Crypto', 'Finance'], 'tags' => ['Bitcoin', 'ETF', 'crypto', 'BlackRock']],

            ['title' => 'Real Madrid Wins Champions League for 16th Time', 'summary' => 'A clinical performance at Wembley against PSG extends the Spanish club\'s record European title count.', 'content' => "Real Madrid claimed their record-extending 16th UEFA Champions League title with a 2-1 victory over Paris Saint-Germain at Wembley Stadium.\n\nMbappé scored the opening goal in the 23rd minute. Endrick doubled the lead before halftime, before Dembélé pulled one back in the 67th minute.\n\nGoalkeeper Thibaut Courtois was the difference-maker, making five saves including a remarkable tip-over from a González free kick.\n\nManager Carlo Ancelotti becomes the most decorated manager in Champions League history with his fifth title.", 'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800', 'access' => 'free', 'categories' => ['Sports'], 'tags' => ['Champions League', 'Real Madrid', 'Mbappé', 'football']],

            ['title' => 'ChatGPT Reaches 500 Million Daily Active Users', 'summary' => 'OpenAI\'s flagship product surpasses social media giants in daily engagement, reshaping how the world writes and thinks.', 'content' => "OpenAI announced that ChatGPT has reached 500 million daily active users, placing it alongside Facebook and Instagram as one of the most-used consumer applications in history.\n\nThe figure represents a tenfold increase from the 50 million daily users reported in early 2024.\n\nOver 60% of active users are outside North America and Western Europe, with India, Brazil, and Southeast Asia representing the fastest-growing regions.\n\nOpenAI, now valued at \$340 billion, is reportedly preparing for an IPO in late 2026.", 'image' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800', 'access' => 'free', 'categories' => ['Artificial Intelligence', 'Technology'], 'tags' => ['ChatGPT', 'OpenAI', 'AI', 'users']],

            ['title' => 'Amazon Plans 100,000 Robot Warehouse Workforce by 2027', 'summary' => 'The retail giant accelerates automation as its next-generation humanoid robots pass quality tests.', 'content' => "Amazon has announced an ambitious deployment plan for its Digit humanoid robots, targeting a fleet of 100,000 units across North American fulfillment centers by 2027.\n\nThe Digit robots can pick, pack, and transport items through complex warehouse environments without modifications to existing infrastructure.\n\nAmazon's VP of Worldwide Operations emphasized robots would be deployed alongside human workers, not as replacements.\n\nThe announcement follows a difficult period of labor relations. Amazon settled a landmark union contract with workers at three New York facilities last year.", 'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800', 'access' => 'free', 'categories' => ['Robotics', 'Business', 'Technology'], 'tags' => ['Amazon', 'robots', 'automation', 'warehouse']],

            ['title' => 'DeepMind\'s AlphaFold 3 Predicts Entire Cellular Interaction Maps', 'summary' => 'The upgraded system models not just protein structure but protein-DNA, protein-RNA, and small molecule interactions.', 'content' => "Google DeepMind has released AlphaFold 3, expanding capabilities to cover the full range of biological molecular interactions.\n\nAlphaFold 3 outperforms all previous methods on protein-ligand docking by a margin of 50% on standard benchmarks.\n\nAstraZeneca reports a reduction in time from target identification to development candidate selection by approximately 18 months in two active programs.\n\nThe system enables virtual screening of millions of potential drug candidates in hours — transforming pharmaceutical development.", 'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800', 'access' => 'free', 'categories' => ['Science', 'Health', 'Artificial Intelligence'], 'tags' => ['AlphaFold', 'DeepMind', 'drug discovery', 'proteins']],

            ['title' => 'F1 2026: How New Regulations Are Reshaping the Championship', 'summary' => 'The most radical rule change in Formula 1 history takes effect — smaller cars, 50% electric power, new cost cap.', 'content' => "Formula 1's 2026 technical regulations represent the most fundamental rewrite since the turbo hybrid era began in 2014.\n\nFerrari enters 2026 as bookmakers' favorite, having designed their power unit from scratch for the new regulations. Red Bull face a reset that neutralizes their previous aerodynamic advantage.\n\n\"The 50% electric contribution changes everything about energy management strategy,\" said Mercedes technical chief James Allison.\n\nThe new \$180 million cost cap is already creating visible consequences across the grid.", 'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800', 'access' => 'free', 'categories' => ['Sports', 'Technology'], 'tags' => ['F1', 'Formula 1', '2026 regulations', 'Ferrari']],

            // ── PRO (≈25%) ───────────────────────────────────────────────────
            ['title' => 'Inside the $50 Billion AI Infrastructure Race: Who\'s Winning?', 'summary' => 'An exclusive analysis of capital allocation trends across hyperscalers reveals a dangerous concentration of AI compute.', 'content' => "Microsoft, Google, Amazon, and Meta have collectively committed \$214 billion in AI infrastructure capex for 2026 alone — a figure exceeding the GDP of Portugal.\n\nAll four hyperscalers remain overwhelmingly dependent on Nvidia GPUs, with custom silicon accounting for less than 15% of training workloads despite years of investment.\n\n62% of new AI datacenter capacity is being built in three US states: Virginia, Texas, and Arizona — creating cascading risk from power grid constraints and water scarcity.\n\nLess-visible winners include power utilities (Dominion Energy stock up 140%), fiber optic manufacturers (Corning at all-time highs), and cooling firms (Vertiv revenue up 95% YoY).", 'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800', 'access' => 'pro', 'categories' => ['Artificial Intelligence', 'Finance', 'Technology'], 'tags' => ['AI infrastructure', 'hyperscalers', 'capex', 'Nvidia']],

            ['title' => 'Federal Reserve Signals Three Rate Cuts in 2026', 'summary' => 'FOMC minutes reveal a decisively dovish pivot as inflation falls to 2.1% and unemployment edges toward 4.5%.', 'content' => "The Federal Reserve's FOMC voted 9-2 to maintain rates at 4.25-4.50% while inserting language favoring \"a measured reduction in policy restriction.\"\n\nMarket pricing implies a 78% probability of three 25-basis-point cuts before year-end.\n\nREITs and homebuilders stand to benefit most directly. Three cuts would bring 30-year fixed mortgage rates toward 5.9-6.1%, potentially unlocking the \"rate lock-in\" effect suppressing existing home sales.\n\nThe 10-year Treasury currently yields 4.1%; expect it to settle near 3.6-3.8% by year-end.", 'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800', 'access' => 'pro', 'categories' => ['Finance', 'Economy'], 'tags' => ['Fed', 'interest rates', 'portfolio', 'FOMC']],

            ['title' => 'Startup Funding Hits 18-Month High as VC Sentiment Shifts', 'summary' => 'Global venture capital deployment reached $89 billion in Q1 2026, signaling a clear recovery from the 2023-24 correction.', 'content' => "Global venture capital investment reached \$89.3 billion in Q1 2026, the highest quarterly total in 18 months and a 47% increase year-over-year.\n\nAI-related deals accounted for 41% of total deployment — stable for four consecutive quarters, suggesting the sector is maturing from speculation toward sustainable infrastructure investment.\n\nEuropean deep tech saw the most notable surge: London, Paris, and Berlin-based companies raised \$12.8 billion — up 68% from Q1 2025.\n\nNotable rounds: Mistral AI \$1.2B, xAI \$3B extension, Cohere \$500M, Cognition AI \$300M.", 'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800', 'access' => 'pro', 'categories' => ['Startups', 'Finance'], 'tags' => ['VC', 'funding', 'startups', 'venture capital']],

            ['title' => 'How Anthropic\'s Constitutional AI Is Reshaping Enterprise Compliance', 'summary' => 'Fortune 500 legal and compliance teams are turning to Claude for document review — with surprising results on accuracy and bias.', 'content' => "Anthropic's Constitutional AI framework has found an unexpected market in enterprise compliance, where its emphasis on value alignment addresses requirements that raw performance benchmarks cannot.\n\nA major US bank deployed Claude for sanctions screening alerts, reducing false-positive escalation rates by 34% in a 90-day pilot — without increasing false negatives.\n\n\"What surprised us was that Claude would flag its own uncertainty. That kind of epistemic transparency is rare.\"\n\nAn energy company used Claude to review 40,000 vendor contracts in six weeks — a task that had taken its legal team two years previously.", 'image' => 'https://images.unsplash.com/photo-1589994965851-a8f479c573a9?w=800', 'access' => 'pro', 'categories' => ['Artificial Intelligence', 'Business', 'Law & Policy'], 'tags' => ['Anthropic', 'Claude', 'compliance', 'enterprise AI']],

            ['title' => 'The Hidden Costs of AI Personalization: A Data Privacy Deep Dive', 'summary' => 'New research reveals most AI personalization engines retain behavioral data far longer than their privacy policies disclose.', 'content' => "A new EFF study audited 42 major AI recommendation systems and found 78% retained granular behavioral data — including scroll depth, reading time per paragraph, and cursor hover patterns — for up to 7 years.\n\nThe study catalogued 34 distinct behavioral signals collected by at least 10 of the 42 platforms.\n\nGDPR's purpose limitation principle should theoretically prevent this, but 31 platforms used training data under overly broad consent categories like \"service improvement.\"\n\nThe EFF's free browser extension blocks the majority of these tracking vectors without significantly degrading recommendation quality.", 'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800', 'access' => 'pro', 'categories' => ['Cybersecurity', 'Artificial Intelligence', 'Law & Policy'], 'tags' => ['privacy', 'AI', 'data', 'GDPR']],

            ['title' => 'The Global Water Crisis: 5 Billion People Face Scarcity by 2050', 'summary' => 'A UN assessment maps the accelerating depletion of aquifers and the geopolitical flashpoints it will create.', 'content' => "By 2050, five billion people will face serious water stress for at least one month per year, according to the UN FAO.\n\n21 of the world's 37 largest aquifers are being depleted faster than they are replenished. The Arabian Aquifer System and the Indus Basin face irreversible depletion within decades.\n\nGroundwater depletion in Punjab has caused well levels to drop 1-3 meters per year — threatening food production for 300 million people.\n\nThe report identifies 17 potential water conflict flashpoints in the Middle East, Central Asia, and Sub-Saharan Africa.", 'image' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800', 'access' => 'pro', 'categories' => ['Climate', 'World News', 'Geopolitics'], 'tags' => ['water', 'scarcity', 'climate', 'geopolitics']],

            ['title' => 'Inside Palantir\'s Government AI Business', 'summary' => 'Palantir\'s government division crossed $1B in quarterly revenue — a complete picture of what they\'re building.', 'content' => "Palantir Technologies crossed \$1 billion in US Government revenue for the first time in Q4 2025, driven by rapid expansion of its AI Platform into military, intelligence, and civilian agency use cases.\n\nPalantir's TITAN program, valued at \$480 million, integrates AI-assisted targeting recommendation into US Army operations, processing sensor data to identify threats in under 400 milliseconds.\n\nIts most controversial government relationship involves ICE, where FALCON and Investigative Case Management platforms have processed data on US citizens and permanent residents.\n\nCEO Alex Karp: \"We are building the operating system for the Western alliance.\"", 'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800', 'access' => 'pro', 'categories' => ['Technology', 'Politics', 'Business'], 'tags' => ['Palantir', 'government AI', 'Pentagon', 'surveillance']],

            // ── PREMIUM (≈15%) ───────────────────────────────────────────────
            ['title' => 'Exclusive: The Secret AI Arms Race Inside the Pentagon', 'summary' => 'A six-month investigation into DARPA\'s classified AI programs reveals capabilities not disclosed to Congress.', 'content' => "Over six months, Pulse360 spoke with 14 current and former DARPA contractors, three retired generals, and two members of the House Armed Services Committee.\n\nSources describe Project ATLAS — Autonomous Tactical Logistics and Situational Awareness — generating battlefield command recommendations in under 400 milliseconds.\n\n\"The question isn't whether the machine makes better targeting decisions on average. The question is who is morally responsible when it makes the wrong one.\"\n\nPentagon assessments indicate the PLA has deployed AI-assisted command systems in the South China Sea with fewer ethical constraints than US systems.", 'image' => 'https://images.unsplash.com/photo-1529107386315-e1a2ed48a620?w=800', 'access' => 'premium', 'categories' => ['Artificial Intelligence', 'Geopolitics', 'Politics'], 'tags' => ['Pentagon', 'DARPA', 'military AI', 'autonomous weapons']],

            ['title' => 'The Billionaire Playbook: Hedging Against AI Displacement', 'summary' => 'Pulse360 gained exclusive access to three family office memos outlining strategies for a post-AGI economy.', 'content' => "Investment strategy memos from three family offices managing a combined \$28 billion reveal a coherent playbook for the AGI era.\n\nAll three memos weight hard assets heavily — agricultural land in politically stable jurisdictions, water rights in water-stressed regions, and coastal real estate above projected flood lines.\n\n\"AI can produce infinite digital goods. It cannot produce an additional acre of fertile land in New Zealand.\"\n\nAll three independently identify small modular nuclear reactors as the highest-conviction infrastructure play for the AI era.", 'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=800', 'access' => 'premium', 'categories' => ['Finance', 'Artificial Intelligence', 'Economy'], 'tags' => ['billionaires', 'AGI', 'investment', 'family office']],

            ['title' => 'How Gene Editing Is Quietly Curing Diseases Doctors Couldn\'t Touch', 'summary' => 'Five years after the first approved CRISPR therapy, a new generation of base editing tools is transforming medicine.', 'content' => "Base editing and prime editing make surgical single-letter changes to DNA without cutting both strands — opening the door to treating conditions where CRISPR's risk-benefit calculation was borderline.\n\nA Phase 2 trial for Leber congenital amaurosis type 10 achieved functional vision restoration in 14 of 19 participants. Major wire services didn't cover it.\n\nA Phase 1/2 trial for transthyretin amyloid cardiomyopathy showed >85% reduction in the disease-causing protein after a single infusion.\n\nRetro Biosciences has initiated a Phase 1 safety trial of a partial reprogramming protocol in adult humans.", 'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800', 'access' => 'premium', 'categories' => ['Health', 'Science'], 'tags' => ['CRISPR', 'gene editing', 'medicine', 'biotech']],

            ['title' => 'China\'s Semiconductor Self-Sufficiency: A Classified Assessment', 'summary' => 'Based on export data, patents, and satellite imagery — how close is China to breaking free of Western chip dependencies?', 'content' => "Four months of open-source intelligence assessment reveals China's semiconductor progress is more nuanced than US officials publicly acknowledge.\n\nSMIC has achieved volume production of a \"7nm-equivalent\" process using DUV lithography that predates export controls. Yield rates are significantly lower than TSMC's but improving.\n\nSatellite analysis of SMIC's Shanghai facility shows three new cleanroom structures built since export controls took effect.\n\nChina's critical vulnerability remains EUV lithography manufactured exclusively by ASML. A DARPA-funded study suggests China may be pursuing nanoimprint lithography as an alternative path.", 'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800', 'access' => 'premium', 'categories' => ['Geopolitics', 'Technology', 'Economy'], 'tags' => ['China', 'semiconductors', 'SMIC', 'chip war']],

            ['title' => 'Quantum Computing\'s Commercial Moment: The Deals Defining the Decade', 'summary' => 'IBM, Google, and IonQ are signing the first commercial quantum contracts — here\'s what\'s actually deliverable today.', 'content' => "A European airline reported a 4.7% reduction in fuel costs — approximately €40 million annually — using a quantum-classical hybrid optimizer for flight route planning.\n\nRoche and Merck have active programs using IBM's 1,000+ qubit systems to model molecular interactions intractable for classical supercomputers.\n\nMost experts converge on 2028-2030 as the window for \"cryptographically relevant\" quantum computers — powerful enough to break RSA-2048 encryption.\n\nThis has triggered urgent migration projects to post-quantum cryptography standards across financial services, defense, and critical infrastructure.", 'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800', 'access' => 'premium', 'categories' => ['Technology', 'Science', 'Business'], 'tags' => ['quantum computing', 'IBM', 'Google', 'cryptography']],

            ['title' => 'Inside Mistral AI: The French Startup Taking On OpenAI With 40 People', 'summary' => 'How a 40-person Paris team built a model competing with GPT-4 — and why open weights could reshape the AI industry.', 'content' => "Three former DeepMind and Meta researchers founded Mistral in Paris with €11 million and a thesis: compute advantages of hyperscalers can be partially offset by superior architecture and training efficiency.\n\nTwo years later: 40 employees, \$1.2 billion raised, models benchmarking competitively with teams 100x their size.\n\nCEO Arthur Mensch: \"The insight is not that we are smarter than Google. 90% of what users need does not require a 1.7 trillion parameter model.\"\n\nLe Chat has reached 10 million monthly active users in Europe — growing at 30% month-over-month.", 'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800', 'access' => 'premium', 'categories' => ['Artificial Intelligence', 'Startups', 'Business'], 'tags' => ['Mistral', 'AI', 'France', 'LLM']],

            ['title' => 'The Longevity Boom: Biotech Bets on Extending Human Lifespan to 150', 'summary' => 'Billions in VC funding are flowing into companies targeting the biology of aging — from senolytics to epigenetic reprogramming.', 'content' => "Altos Labs, Calico, Retro Biosciences, and dozens of smaller companies have collectively raised over \$7 billion to pursue aging interventions.\n\nIn landmark studies, partial cellular reprogramming using Yamanaka factors has extended mouse lifespan by 25-30% while reversing functional decline in multiple organ systems.\n\nRetro Biosciences has initiated a Phase 1 safety trial of a partial reprogramming protocol in adult humans with premature aging conditions.\n\nKhosla Ventures' Vinod Khosla has repeatedly stated his belief that longevity is the \"largest economic opportunity in human history.\"", 'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800', 'access' => 'premium', 'categories' => ['Health', 'Science', 'Startups'], 'tags' => ['longevity', 'biotech', 'aging', 'reprogramming']],
        ];
    }

    // =========================================================================
    // 8. EVENTS
    // =========================================================================

    private function seedEvents(): void
    {
        foreach ($this->eventsData() as $event) {
            $slug = Str::slug($event['title']);

            $existing = DB::table('data_entries')
                ->where('project_id', $this->projectId)
                ->where('slug', $slug)
                ->first();

            if ($existing) {
                $this->eventEntries[] = ['id' => $existing->id, 'slug' => $slug, 'title' => $event['title']];
                continue;
            }

            $entryId = $this->createEntry(
                $this->eventTypeId,
                $slug,
                Carbon::parse($event['date'])->subDays(30)
            );

            $this->setValues($entryId, $this->eventTypeId, [
                'title'       => $event['title'],
                'slug'        => $slug,
                'description' => $event['description'],
                'content'     => $event['content'],
                'image'       => $event['image'],
                'date'        => $event['date'],
                'end_date'    => $event['end_date'],
                'location'    => $event['location'],
                'organizer'   => $event['organizer'],
                'access'      => $event['access'],
                'tags'        => json_encode($event['tags']),
            ]);

            $this->eventEntries[] = ['id' => $entryId, 'slug' => $slug, 'title' => $event['title']];
        }
    }

    private function eventsData(): array
    {
        return [
            ['title' => 'AI Future Summit 2026',               'description' => 'The world\'s leading AI conference featuring top researchers, policymakers, and industry pioneers.',              'content' => "AI Future Summit 2026 brings together 5,000 attendees from 80 countries for three days of keynotes, panels, and workshops.\n\nSpeakers include CEOs of OpenAI, Anthropic, DeepMind, and Mistral AI.\n\nThematic tracks: Foundation Models, AI Safety, Government Policy, Healthcare AI, and the Future of Work.",        'image' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800', 'date' => '2026-07-15', 'end_date' => '2026-07-17', 'location' => 'Dubai, UAE',            'organizer' => 'Pulse360 Events',               'access' => 'paid', 'tags' => ['AI', 'conference', 'technology', 'Dubai']],
            ['title' => 'Global Tech Innovators Conference',   'description' => 'Europe\'s premier technology conference connecting founders, investors, and enterprise decision-makers.',         'content' => "Tech Innovators Conference returns to Berlin for its 8th edition, gathering 3,000 technology leaders.\n\nFocus themes: EU AI Act, quantum computing readiness, defense tech, and European semiconductor manufacturing.\n\nNetworking includes a startup pitch competition with €500,000 in prizes.",                                           'image' => 'https://images.unsplash.com/photo-1515169067865-5387ec356754?w=800', 'date' => '2026-09-03', 'end_date' => '2026-09-04', 'location' => 'Berlin, Germany',        'organizer' => 'EuraTech Foundation',           'access' => 'paid', 'tags' => ['tech', 'conference', 'startups', 'Europe']],
            ['title' => 'Startup Pitch Night Series A',        'description' => 'Six selected startups pitch to 50 top-tier Series A investors with live Q&A and deal room access.',               'content' => "Pulse360's Startup Pitch Night curates compelling early-stage companies across AI, climate tech, biotech, and fintech.\n\nFormat: six 12-minute pitches, 8-minute Q&A, then structured deal room sessions.\n\nPrevious editions have announced over \$180 million in closed rounds within 6 months.",                                      'image' => 'https://images.unsplash.com/photo-1551836022-d5d88e9218df?w=800', 'date' => '2026-07-28', 'end_date' => '2026-07-28', 'location' => 'San Francisco, USA',    'organizer' => 'Pulse360 Events',               'access' => 'free', 'tags' => ['startups', 'investors', 'pitching', 'VC']],
            ['title' => 'Space Exploration Expo 2026',         'description' => 'Immersive three-day exposition of commercial space, satellite technology, and space tourism.',                     'content' => "Space Exploration Expo 2026 hosts representatives from SpaceX, Blue Origin, Rocket Lab, and 200+ exhibiting companies.\n\nFeatured: full-scale Starship engine display, live satellite tracking, and a New Space Investment Forum.\n\nFamily-friendly programming includes VR spacewalk experiences and astronaut Q&A sessions.",             'image' => 'https://images.unsplash.com/photo-1446776811953-b23d57bd21aa?w=800', 'date' => '2026-08-20', 'end_date' => '2026-08-22', 'location' => 'Tokyo, Japan',           'organizer' => 'Space Industry Consortium',     'access' => 'paid', 'tags' => ['space', 'expo', 'SpaceX', 'rockets']],
            ['title' => 'Cybersecurity World Summit',          'description' => 'Three days of intensive briefings on emerging threats, zero-days, and enterprise defense strategies.',             'content' => "Cybersecurity World Summit gathers CISOs, security researchers, and government representatives.\n\nAgenda: AI-assisted attacks, post-quantum cryptography migration, critical infrastructure security, and the evolving ransomware ecosystem.\n\nFeatures a live Red Team vs Blue Team competition and SANS Institute certification workshops.",  'image' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800', 'date' => '2026-10-06', 'end_date' => '2026-10-08', 'location' => 'Singapore',              'organizer' => 'CyberDefend Alliance',          'access' => 'paid', 'tags' => ['cybersecurity', 'infosec', 'CISO', 'threats']],
            ['title' => 'Future of Robotics Conference',       'description' => 'Annual gathering of robotics engineers, AI researchers, and industrial automation executives.',                    'content' => "The Future of Robotics Conference brings together 1,200 specialists for technical presentations, live demos, and workshops.\n\nKey themes: humanoid robot deployment, swarm robotics, brain-computer interfaces, and autonomous systems regulation.\n\nFeatured demos: Boston Dynamics Atlas performing construction tasks and a live Intuitive Surgical robot demonstration.", 'image' => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=800', 'date' => '2026-11-12', 'end_date' => '2026-11-13', 'location' => 'Oslo, Norway',           'organizer' => 'Nordic Robotics Institute',     'access' => 'paid', 'tags' => ['robotics', 'automation', 'engineering', 'humanoids']],
            ['title' => 'Climate Innovation Forum',            'description' => 'Where climate scientists, engineers, policymakers, and impact investors converge.',                                'content' => "Climate Innovation Forum convenes 800 participants at the intersection of climate science, clean technology, and policy.\n\nSessions: carbon capture, green hydrogen economics, climate finance, loss and damage funding, and agricultural adaptation.\n\nAnnouncements at last year's Forum led to \$4.2 billion in clean energy commitments.",                    'image' => 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=800', 'date' => '2026-06-22', 'end_date' => '2026-06-24', 'location' => 'Amsterdam, Netherlands', 'organizer' => 'Green Futures Foundation',      'access' => 'free', 'tags' => ['climate', 'sustainability', 'clean energy', 'innovation']],
            ['title' => 'HealthTech Summit 2026',              'description' => 'The intersection of medicine, AI, genomics, and digital health — where breakthroughs meet bedside.',              'content' => "HealthTech Summit 2026 convenes clinicians, tech entrepreneurs, pharma executives, and patient advocates.\n\nTopics: AI diagnostics in low-resource settings, CRISPR therapeutics pipeline, wearable biosensors, mental health technology, and drug pricing.\n\nIncludes a dedicated regulatory dialogue session with FDA and EMA representatives.",                          'image' => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=800', 'date' => '2026-09-18', 'end_date' => '2026-09-20', 'location' => 'London, UK',             'organizer' => 'MedTech Alliance',             'access' => 'paid', 'tags' => ['health', 'medtech', 'AI', 'genomics']],
            ['title' => 'Global Finance Leaders Summit',       'description' => 'Exclusive gathering of central bankers, institutional investors, and finance ministers.',                          'content' => "Global Finance Leaders Summit 2026 convenes 300 senior figures from central banking, sovereign wealth funds, and private finance.\n\nThemes: AI disruption of financial services, CBDC implementations, emerging market debt sustainability, and geopolitical fragmentation of capital flows.\n\nOperates under Chatham House rules for frank debate on topics rarely discussed publicly.",      'image' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800', 'date' => '2026-10-28', 'end_date' => '2026-10-29', 'location' => 'Zurich, Switzerland',    'organizer' => 'World Economic Institute',     'access' => 'paid', 'tags' => ['finance', 'banking', 'economy', 'investment']],
            ['title' => 'Quantum Computing Business Forum',    'description' => 'Bridging quantum research labs and enterprise adoption — case studies, vendor demos, and investment sessions.',    'content' => "Quantum Computing Business Forum is designed for enterprise technology leaders evaluating quantum adoption timelines.\n\nTracks: Near-Term Applications (optimization, simulation, cryptography migration), Infrastructure (hardware comparison, cloud quantum), and Investment & Policy.\n\nVendors including IBM, Google, IonQ, and Quantinuum will present live demonstrations and roadmaps.",        'image' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800', 'date' => '2026-12-04', 'end_date' => '2026-12-05', 'location' => 'New York, USA',          'organizer' => 'Quantum Industry Consortium',  'access' => 'paid', 'tags' => ['quantum computing', 'enterprise', 'IBM', 'technology']],
            ['title' => 'Media & Journalism Innovation Summit','description' => 'How newsrooms are adapting to AI-generated content, declining trust, and new business models.',                   'content' => "Media & Journalism Innovation Summit brings together 600 journalists, editors, media executives, and academics.\n\nTopics: AI content detection, synthetic media and deepfakes, journalist safety, subscription fatigue, and ethics of AI-assisted reporting.\n\nFeatured speaker: Reuters Editor-in-Chief on building trust in the age of synthetic media.",                          'image' => 'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=800', 'date' => '2026-08-05', 'end_date' => '2026-08-06', 'location' => 'New York, USA',          'organizer' => 'International Press Institute', 'access' => 'free', 'tags' => ['media', 'journalism', 'AI', 'news']],
            ['title' => 'Biotech Investment Congress',         'description' => 'Connecting biopharma executives, genomics researchers, and life science investors at the frontier of medicine.',  'content' => "Biotech Investment Congress 2026 is the primary deal-making forum for the life sciences industry, hosting 900 attendees.\n\nFocus areas: longevity and aging biology, gene therapy delivery, AI drug discovery platforms, cell therapy manufacturing, and regulatory strategy.\n\nThe Congress has historically generated over \$2 billion in partnership announcements annually.",    'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800', 'date' => '2026-11-24', 'end_date' => '2026-11-26', 'location' => 'Boston, USA',            'organizer' => 'BioPharma Connect',            'access' => 'paid', 'tags' => ['biotech', 'investment', 'pharma', 'genomics']],
            ['title' => 'Web3 & Decentralized Finance Forum',  'description' => 'The serious side of crypto and blockchain: enterprise adoption, regulatory frameworks, institutional DeFi.',      'content' => "Web3 & DeFi Forum 2026 focuses on institutional adoption, regulatory clarity, and real-world blockchain use cases.\n\nSessions: tokenized real-world assets, CBDC interoperability standards, DeFi risk management for institutional portfolios, and DAOs across jurisdictions.\n\nFeatured: Fidelity Digital Assets, BlackRock's tokenization team, and ECB digital euro representatives.",  'image' => 'https://images.unsplash.com/photo-1518546305927-5a555bb7020d?w=800', 'date' => '2026-07-10', 'end_date' => '2026-07-11', 'location' => 'Lisbon, Portugal',       'organizer' => 'CryptoFin Alliance',           'access' => 'paid', 'tags' => ['web3', 'DeFi', 'blockchain', 'crypto']],
            ['title' => 'Women in Tech Leadership Summit',     'description' => 'Celebrating and empowering women shaping the future of technology across all levels and disciplines.',            'content' => "Women in Tech Leadership Summit returns for its 9th year with 2,000 attendees.\n\nNew for 2026: a dedicated engineering leaders track, a startup pitch competition for female-founded companies, and 200 mentorship pairings.\n\nKeynote: GitHub CEO on building engineering cultures that retain diverse talent.",                                                             'image' => 'https://images.unsplash.com/photo-1559136555-9303baea8ebd?w=800', 'date' => '2026-06-11', 'end_date' => '2026-06-12', 'location' => 'London, UK',             'organizer' => 'TechDiversity Foundation',     'access' => 'free', 'tags' => ['women in tech', 'diversity', 'leadership', 'technology']],
            ['title' => 'Energy Transition Summit',            'description' => 'Flagship forum for utilities, energy ministers, and cleantech companies driving the global shift from fossil fuels.','content' => "Energy Transition Summit 2026 convenes 1,500 participants from 70 countries including G20 energy ministers.\n\nCritical discussions: offshore wind supply chain, nuclear renaissance policy, green hydrogen cost trajectory, grid stability with high renewables penetration, and energy poverty.\n\nPrevious Summits have generated over \$80 billion in cumulative investment commitments.",    'image' => 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?w=800', 'date' => '2026-09-29', 'end_date' => '2026-10-01', 'location' => 'Copenhagen, Denmark',   'organizer' => 'Clean Energy Council',         'access' => 'paid', 'tags' => ['energy', 'climate', 'renewables', 'transition']],
            ['title' => 'E-Sports World Championship 2026',   'description' => 'The largest competitive gaming event of the year with $10 million total prize pool.',                            'content' => "E-Sports World Championship 2026 features five titles across seven days: League of Legends, Valorant, CS2, Dota 2, and Rocket League.\n\nCombined prize pool: \$10 million, with the LoL championship carrying a \$4 million first prize.\n\nExpected: 65,000 in-venue attendees and 80 million online viewers — comparable to the Super Bowl.",                                  'image' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=800', 'date' => '2026-08-12', 'end_date' => '2026-08-18', 'location' => 'Seoul, South Korea',    'organizer' => 'Esports World Federation',    'access' => 'paid', 'tags' => ['esports', 'gaming', 'championship', 'Korea']],
            ['title' => 'Global Mental Health Conference',     'description' => 'Breaking the stigma, advancing treatment, and addressing the global mental health crisis.',                       'content' => "Global Mental Health Conference 2026 convenes psychiatrists, psychologists, patient advocates, and policymakers.\n\nKey themes: adolescent mental health and social media, AI-assisted therapy tools, ketamine and psychedelic-assisted therapies, and workplace mental health policy.\n\nFeatures 80 peer-reviewed paper presentations and 40 clinical skills workshops.",                  'image' => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=800', 'date' => '2026-10-14', 'end_date' => '2026-10-17', 'location' => 'Toronto, Canada',        'organizer' => 'World Psychiatric Association', 'access' => 'free', 'tags' => ['mental health', 'psychiatry', 'healthcare', 'wellbeing']],
            ['title' => 'Autonomous Vehicles Conference 2026', 'description' => 'The industry\'s definitive forum on self-driving technology, regulation, and the road to mass deployment.',       'content' => "AV Conference 2026 convenes automotive engineers, software teams, regulators, urban planners, and investors.\n\nFocused sessions: Level 4 robotaxi economics, LIDAR vs camera debates, insurance frameworks, cybersecurity of connected vehicles, and EVs intersecting with autonomy.\n\nVehicle demonstrations from Waymo, Cruise, and WeRide on a closed course throughout the event.", 'image' => 'https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=800', 'date' => '2026-08-26', 'end_date' => '2026-08-27', 'location' => 'Detroit, USA',           'organizer' => 'Autonomous Mobility Institute', 'access' => 'paid', 'tags' => ['autonomous vehicles', 'self-driving', 'Waymo', 'automotive']],
            ['title' => 'Digital Transformation Awards Gala',  'description' => 'Annual recognition of organizations and leaders driving meaningful digital transformation across industries.',     'content' => "The Digital Transformation Awards Gala celebrates exceptional results in applying digital technologies to real-world challenges.\n\nCategories: AI Deployment of the Year, Best Digital Health Initiative, Climate Tech Company of the Year, Most Innovative Government Digital Service, and Startup of the Year.\n\nThe Gala accommodates 800 guests with post-ceremony networking and winner case study presentations.", 'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=800', 'date' => '2026-11-05', 'end_date' => '2026-11-05', 'location' => 'Paris, France',          'organizer' => 'Digital Leaders Network',      'access' => 'paid', 'tags' => ['digital transformation', 'awards', 'innovation', 'technology']],
            ['title' => 'Genomics & Precision Medicine Summit', 'description' => 'From whole-genome sequencing to personalized drug dosing — the frontier of genomics in clinical medicine.',    'content' => "Genomics & Precision Medicine Summit 2026 bridges the laboratory and the clinic, bringing together geneticists, oncologists, pharmacogeneticists, and bioinformaticians.\n\nKey sessions: polygenic risk scores in clinical practice, pharmacogenomics in standard of care, liquid biopsy for early cancer detection, rare disease diagnosis timelines, and ethics of genetic data.\n\nPresents results from 50 ongoing clinical trials involving genomic medicine.", 'image' => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=800', 'date' => '2026-12-10', 'end_date' => '2026-12-12', 'location' => 'San Diego, USA',         'organizer' => 'Genome Medicine Association',  'access' => 'paid', 'tags' => ['genomics', 'precision medicine', 'oncology', 'genetics']],
        ];
    }

    // =========================================================================
    // 9. ACCESS RULES
    // =========================================================================

    private function seedAccessRules(): void
    {
        $accessRules = [
            ['event_key' => 'article.view.free',    'requires_subscription' => false, 'required_feature_key' => null],
            ['event_key' => 'article.view.pro',     'requires_subscription' => true,  'required_feature_key' => 'pro_articles'],
            ['event_key' => 'article.view.premium', 'requires_subscription' => true,  'required_feature_key' => 'premium_articles'],
            ['event_key' => 'event.book.free',      'requires_subscription' => false, 'required_feature_key' => null],
            ['event_key' => 'event.book.premium',   'requires_subscription' => true,  'required_feature_key' => 'premium_events'],
            ['event_key' => 'ai.chat',              'requires_subscription' => true,  'required_feature_key' => 'ai_chat'],
            ['event_key' => 'article.archive',      'requires_subscription' => true,  'required_feature_key' => 'pro_articles'],
        ];

        foreach ($accessRules as $rule) {
            $exists = DB::table('subscription_access_rules')
                ->where('project_id', $this->projectId)
                ->where('event_key', $rule['event_key'])
                ->exists();

            if (! $exists) {
                DB::table('subscription_access_rules')->insert(array_merge($rule, [
                    'project_id' => $this->projectId,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }

        $featureRules = [
            ['event_key' => 'article.view',         'feature_key' => 'articles_per_month', 'action' => 'both',  'reset_type' => 'monthly'],
            ['event_key' => 'ai.chat',              'feature_key' => 'ai_requests_daily',  'action' => 'both',  'reset_type' => 'daily'],
            ['event_key' => 'article.view.pro',     'feature_key' => 'pro_articles',       'action' => 'check', 'reset_type' => 'never'],
            ['event_key' => 'article.view.premium', 'feature_key' => 'premium_articles',   'action' => 'check', 'reset_type' => 'never'],
        ];

        foreach ($featureRules as $rule) {
            $exists = DB::table('subscription_feature_rules')
                ->where('project_id', $this->projectId)
                ->where('event_key', $rule['event_key'])
                ->where('feature_key', $rule['feature_key'])
                ->exists();

            if (! $exists) {
                DB::table('subscription_feature_rules')->insert(array_merge($rule, [
                    'project_id' => $this->projectId,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    // =========================================================================
    // 10. CONTENT ACCESS METADATA
    // =========================================================================

    private function seedContentAccessMetadata(): void
    {
        foreach ($this->articleEntries as $article) {
            $exists = DB::table('content_access_metadata')
                ->where('project_id', $this->projectId)
                ->where('content_type', 'article')
                ->where('content_id', $article['id'])
                ->exists();

            if ($exists) continue;

            $requiresSub     = in_array($article['access'], ['pro', 'premium']);
            $requiredFeature = match ($article['access']) {
                'pro'     => 'pro_articles',
                'premium' => 'premium_articles',
                default   => null,
            };

            $metaId = DB::table('content_access_metadata')->insertGetId([
                'project_id'            => $this->projectId,
                'content_type'          => 'article',
                'content_id'            => $article['id'],
                'requires_subscription' => $requiresSub,
                'is_active'             => true,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);

            if ($requiredFeature) {
                DB::table('content_access_features')->insert([
                    'content_access_metadata_id' => $metaId,
                    'feature_key'                => $requiredFeature,
                    'created_at'                 => now(),
                    'updated_at'                 => now(),
                ]);
            }
        }
    }

    // =========================================================================
    // 11. USER SUBSCRIPTIONS
    // =========================================================================

    private function seedUserSubscriptions(): void
    {
        foreach ($this->premiumUserIds as $userId) {
            $this->createSubscription($userId, $this->premiumPlanId, 'active', -30, 60);
        }

        foreach ($this->proUserIds as $idx => $userId) {
            if ($idx < 7) {
                $this->createSubscription($userId, $this->proPlanId, 'active', -15, 45);
            } elseif ($idx < 9) {
                $this->createSubscription($userId, $this->proPlanId, 'expired', -60, -5);
            } else {
                $this->createSubscription($userId, $this->proPlanId, 'cancelled', -45, -1, true);
            }
        }

        $freeUserIds = array_values(array_diff($this->userIds, $this->premiumUserIds, $this->proUserIds));
        foreach (array_slice($freeUserIds, 0, 15) as $userId) {
            $this->createSubscription($userId, $this->freePlanId, 'active', 0, 36500);
        }
    }

    private function createSubscription(
        int $userId,
        int $planId,
        string $status,
        int $startDaysFromNow,
        int $durationDays,
        bool $cancelled = false
    ): void {
        $exists = DB::table('subscriptions')
            ->where('user_id', $userId)
            ->where('plan_id', $planId)
            ->exists();

        if ($exists) return;

        $startsAt = now()->addDays($startDaysFromNow);
        $endsAt   = (clone $startsAt)->addDays($durationDays + abs($startDaysFromNow));

        DB::table('subscriptions')->insert([
            'user_id'              => $userId,
            'project_id'           => $this->projectId,
            'plan_id'              => $planId,
            'payment_id'           => null,
            'status'               => $status,
            'starts_at'            => $startsAt,
            'ends_at'              => $endsAt,
            'current_period_start' => $status === 'active' ? now()->startOfMonth() : null,
            'current_period_end'   => $status === 'active' ? now()->endOfMonth()   : null,
            'cancelled_at'         => $cancelled ? now()->subDays(rand(1, 10)) : null,
            'auto_renew'           => ! $cancelled,
            'metadata'             => json_encode(['source' => 'seeder']),
            'created_at'           => $startsAt,
            'updated_at'           => now(),
        ]);
    }

    // =========================================================================
    // 12. SEARCH INDICES
    // =========================================================================

    private function seedSearchIndices(): void
    {
        foreach ($this->articleEntries as $article) {
            $exists = DB::table('search_indices')
                ->where('entry_id', $article['id'])
                ->where('language', 'en')
                ->exists();

            if ($exists) continue;

            $title   = $this->getFieldValue($article['id'], $this->articleTypeId, 'title');
            $summary = $this->getFieldValue($article['id'], $this->articleTypeId, 'summary');
            $daysOld = rand(1, 180);

            DB::table('search_indices')->insert([
                'entry_id'          => $article['id'],
                'data_type_id'      => $this->articleTypeId,
                'project_id'        => $this->projectId,
                'language'          => 'en',
                'title'             => $title,
                'content'           => $summary,
                'meta'              => json_encode(['access' => $article['access']]),
                'status'            => 'published',
                'published_at'      => now()->subDays($daysOld),
                'click_count'       => rand(10, 2000),
                'view_count'        => rand(100, 20000),
                'popularity_score'  => round(rand(10, 999) / 10, 4),
                'title_has_numbers' => $title && preg_match('/[0-9]/', $title) ? 1 : 0,
                'title_word_count'  => $title ? str_word_count($title) : 0,
                'title_length'      => $title ? strlen($title) : 0,
                'primary_keyword'   => $title ? strtolower(strtok($title, ' ')) : '',
                'ctr_score'         => round(rand(5, 300) / 100, 4),
                'freshness_score'   => round(1.0 / ($daysOld + 1), 4),
                'data_type_slug'    => 'article',
                'created_at'        => now()->subDays($daysOld),
                'updated_at'        => now(),
            ]);
        }

        foreach ($this->eventEntries as $event) {
            $exists = DB::table('search_indices')
                ->where('entry_id', $event['id'])
                ->where('language', 'en')
                ->exists();

            if ($exists) continue;

            $title   = $this->getFieldValue($event['id'], $this->eventTypeId, 'title');
            $desc    = $this->getFieldValue($event['id'], $this->eventTypeId, 'description');
            $daysOld = rand(1, 30);

            DB::table('search_indices')->insert([
                'entry_id'          => $event['id'],
                'data_type_id'      => $this->eventTypeId,
                'project_id'        => $this->projectId,
                'language'          => 'en',
                'title'             => $title,
                'content'           => $desc,
                'meta'              => json_encode(['type' => 'event']),
                'status'            => 'published',
                'published_at'      => now()->subDays($daysOld),
                'click_count'       => rand(50, 5000),
                'view_count'        => rand(500, 50000),
                'popularity_score'  => round(rand(50, 999) / 10, 4),
                'title_has_numbers' => 0,
                'title_word_count'  => $title ? str_word_count($title) : 0,
                'title_length'      => $title ? strlen($title) : 0,
                'primary_keyword'   => $title ? strtolower(strtok($title, ' ')) : '',
                'ctr_score'         => round(rand(10, 500) / 100, 4),
                'freshness_score'   => round(1.0 / ($daysOld + 1), 4),
                'data_type_slug'    => 'event',
                'created_at'        => now()->subDays($daysOld),
                'updated_at'        => now(),
            ]);
        }
    }

    // =========================================================================
    // 13. POPULAR SEARCHES + SYNONYMS
    // =========================================================================

    private function seedPopularSearches(): void
    {
        $keywords = [
            'artificial intelligence', 'AI breakthrough', 'OpenAI GPT', 'Nvidia chips',
            'climate change', 'clean energy', 'electric vehicles', 'Tesla',
            'Bitcoin', 'crypto', 'blockchain', 'DeFi', 'cybersecurity',
            'data breach', 'ransomware', 'startup funding', 'venture capital',
            'Series A', 'space exploration', 'SpaceX', 'Starship',
            'gene therapy', 'CRISPR', 'drug discovery', 'quantum computing',
            'IBM quantum', 'geopolitics', 'China semiconductors', 'US economy',
            'Champions League', 'Formula 1', 'mental health', 'longevity research',
            'robotics', 'humanoid robots', 'automation', 'federal reserve',
            'interest rates', 'inflation',
        ];

        foreach ($keywords as $keyword) {
            $normalized = strtolower(trim($keyword));

            $exists = DB::table('popular_searches')
                ->where('project_id', $this->projectId)
                ->where('normalized_keyword', $normalized)
                ->where('language', 'en')
                ->exists();

            if ($exists) continue;

            DB::table('popular_searches')->insert([
                'project_id'         => $this->projectId,
                'keyword'            => $keyword,
                'normalized_keyword' => $normalized,
                'language'           => 'en',
                'count_24h'          => rand(20, 500),
                'count_7d'           => rand(100, 2000),
                'count_30d'          => rand(500, 8000),
                'count_all_time'     => rand(2000, 50000),
                'click_count'        => rand(100, 10000),
                'trending_score'     => round(rand(10, 5000) / 10, 4),
                'alltime_score'      => round(rand(100, 10000) / 10, 4),
                'last_searched_at'   => now()->subMinutes(rand(1, 10080)),
                'last_computed_at'   => now(),
                'created_at'         => now()->subDays(rand(1, 180)),
                'updated_at'         => now(),
            ]);
        }

        $synonymPairs = [
            ['artificial', 'intelligence'], ['machine', 'learning'],
            ['deep', 'learning'],           ['neural', 'network'],
            ['electric', 'vehicle'],        ['clean', 'energy'],
            ['bitcoin', 'ethereum'],        ['startup', 'funding'],
        ];

        foreach ($synonymPairs as $pair) {
            $exists = DB::table('synonym_suggestions')
                ->where('project_id', $this->projectId)
                ->where('word_a', $pair[0])
                ->where('word_b', $pair[1])
                ->exists();

            if ($exists) continue;

            DB::table('synonym_suggestions')->insert([
                'project_id'         => $this->projectId,
                'word_a'             => $pair[0],
                'word_b'             => $pair[1],
                'language'           => 'en',
                'jaccard_score'      => round(rand(30, 90) / 100, 6),
                'cooccurrence_count' => rand(50, 1000),
                'confidence_score'   => round(rand(40, 950) / 10, 4),
                'word_a_count'       => rand(100, 5000),
                'word_b_count'       => rand(100, 5000),
                'status'             => 'approved',
                'last_computed_at'   => now(),
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }

    // =========================================================================
    // 14. SEO ENTRIES
    // =========================================================================

    private function seedSeoEntries(): void
    {
        foreach ($this->articleEntries as $article) {
            $exists = DB::table('seo_entries')
                ->where('data_entry_id', $article['id'])
                ->whereNull('language')
                ->exists();

            if ($exists) continue;

            $title = $this->getFieldValue($article['id'], $this->articleTypeId, 'title');

            DB::table('seo_entries')->insert([
                'data_entry_id'    => $article['id'],
                'language'         => null,
                'meta_title'       => $title ? substr($title, 0, 55) . ' | Pulse360' : 'Pulse360',
                'meta_description' => $this->getFieldValue($article['id'], $this->articleTypeId, 'summary'),
                'slug'             => $article['slug'],
                'canonical_url'    => 'https://pulse360.com/article/' . $article['slug'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }

        foreach ($this->eventEntries as $event) {
            $exists = DB::table('seo_entries')
                ->where('data_entry_id', $event['id'])
                ->whereNull('language')
                ->exists();

            if ($exists) continue;

            $title = $this->getFieldValue($event['id'], $this->eventTypeId, 'title');

            DB::table('seo_entries')->insert([
                'data_entry_id'    => $event['id'],
                'language'         => null,
                'meta_title'       => $title ? substr($title, 0, 50) . ' | Pulse360 Events' : 'Pulse360 Events',
                'meta_description' => $this->getFieldValue($event['id'], $this->eventTypeId, 'description'),
                'slug'             => $event['slug'],
                'canonical_url'    => 'https://pulse360.com/event/' . $event['slug'],
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function createEntry(int $typeId, string $slug, ?Carbon $publishedAt = null): int
    {
        return DB::table('data_entries')->insertGetId([
            'project_id'   => $this->projectId,
            'data_type_id' => $typeId,
            'slug'         => $slug,
            'status'       => 'published',
            'published_at' => $publishedAt ?? now(),
            'created_at'   => $publishedAt ?? now(),
            'updated_at'   => now(),
        ]);
    }

    private function setValues(int $entryId, int $typeId, array $values): void
    {
        foreach ($values as $fieldName => $value) {
            $fieldId = $this->fields[$typeId][$fieldName]
                ?? DB::table('data_type_fields')
                    ->where('data_type_id', $typeId)
                    ->where('name', $fieldName)
                    ->value('id');

            if (! $fieldId) continue;

            $this->fields[$typeId][$fieldName] = $fieldId;

            $exists = DB::table('data_entry_values')
                ->where('data_entry_id', $entryId)
                ->where('data_type_field_id', $fieldId)
                ->whereNull('language')
                ->exists();

            if (! $exists) {
                DB::table('data_entry_values')->insert([
                    'data_entry_id'      => $entryId,
                    'data_type_field_id' => $fieldId,
                    'language'           => null,
                    'value'              => $value,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    private function getFieldValue(int $entryId, int $typeId, string $fieldName): ?string
    {
        $fieldId = $this->fields[$typeId][$fieldName]
            ?? DB::table('data_type_fields')
                ->where('data_type_id', $typeId)
                ->where('name', $fieldName)
                ->value('id');

        if (! $fieldId) return null;

        return DB::table('data_entry_values')
            ->where('data_entry_id', $entryId)
            ->where('data_type_field_id', $fieldId)
            ->value('value');
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    private function printSummary(): void
    {
        echo "\n";
        echo "╔══════════════════════════════════════════════╗\n";
        echo "║        PULSE360 CMS SEEDER COMPLETE          ║\n";
        echo "╠══════════════════════════════════════════════╣\n";
        echo "║ Service:        CMS\n";
        echo "║ Project ID:     {$this->projectId}\n";
        echo "║ Users:          " . count($this->userIds)         . "\n";
        echo "║ Categories:     " . count($this->categoryEntries) . "\n";
        echo "║ Articles:       " . count($this->articleEntries)  . "\n";
        echo "║ Events (CMS):   " . count($this->eventEntries)    . "\n";
        echo "║ Premium users:  " . count($this->premiumUserIds)  . "\n";
        echo "║ Pro users:      " . count($this->proUserIds)      . "\n";
        echo "╠══════════════════════════════════════════════╣\n";
        echo "║ TEST CREDENTIALS  (password: password)\n";
        echo "║  Admin:    admin@pulse360.com\n";
        echo "║  Premium:  premium@pulse360.com\n";
        echo "║  Pro:      pro@pulse360.com\n";
        echo "║  Free:     free@pulse360.com\n";
        echo "╠══════════════════════════════════════════════╣\n";
        echo "║ NEXT STEP:\n";
        echo "║  php artisan db:seed --class=Pulse360BookingSeeder\n";
        echo "╚══════════════════════════════════════════════╝\n\n";
    }
}