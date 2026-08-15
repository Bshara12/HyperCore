<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PersonalizationAffinityTestSeeder extends Seeder
{
    private const PROJECT_ID = 1;

    private const USER_NO_HISTORY_ID = 9001;
    private const USER_IPHONE_FAN_ID = 9002;
    private const USER_MACBOOK_FAN_ID = 9003;
    private const USER_SAMSUNG_FAN_ID = 9004; // ← جديد: لعرض تباين "smartphone"

    /*
     * entry_ids مخصصة لهذا الـ seeder (نطاق 91xx) لتجنب أي تعارض
     * مع SearchIndexSeeder (1-35) أو data_entries الحقيقية
     */
    private const ENTRY_IPHONE_15 = 9101;
    private const ENTRY_IPHONE_14 = 9102;
    private const ENTRY_SAMSUNG_S24 = 9103;
    private const ENTRY_SAMSUNG_A54 = 9104;

    public function run(): void
    {
        if (DB::table('search_indices')->where('project_id', self::PROJECT_ID)->count() === 0) {
            $this->command->error('✖ شغّل أولاً: php artisan db:seed --class=SearchIndexSeeder');

            return;
        }

        $this->seedUsers();
        $this->seedSmartphoneSearchIndexEntries();
        $this->seedClickLogs();

        $this->command->info('✅ Personalization test data seeded.');
        $this->command->table(
            ['User ID', 'Label', 'Expected behavior'],
            [
                [self::USER_NO_HISTORY_ID, 'No History', 'لا يوجد ترجيح شخصي — النتائج حسب FULLTEXT/popularity فقط'],
                [self::USER_IPHONE_FAN_ID, 'iPhone Fan', "بحث 'smartphone' → يتصدّر iPhone 15 Pro / iPhone 14"],
                [self::USER_MACBOOK_FAN_ID, 'MacBook Fan', "بحث 'laptop' → يتصدّر MacBook / Dell XPS"],
                [self::USER_SAMSUNG_FAN_ID, 'Samsung Fan', "بحث 'smartphone' → يتصدّر Galaxy S24 / Galaxy A54"],
            ]
        );

        $this->command->newLine();
        $this->command->info('🔎 اختبار العرض (tinker):');
        $this->command->line("   \$a = app(App\\Domains\\Search\\Support\\UserPreferenceAnalyzer::class)->analyzeForUser(1, 9002);");
        $this->command->line("   \$b = app(App\\Domains\\Search\\Support\\UserPreferenceAnalyzer::class)->analyzeForUser(1, 9004);");
        $this->command->line('   قارن الترتيب اللي بيرجع من endpoint البحث GET /search?q=smartphone مع كل user_id.');
    }

    // ─────────────────────────────────────────────────────────────
    // Users
    // ─────────────────────────────────────────────────────────────

    private function seedUsers(): void
    {
        $userIds = [
            self::USER_NO_HISTORY_ID,
            self::USER_IPHONE_FAN_ID,
            self::USER_MACBOOK_FAN_ID,
            self::USER_SAMSUNG_FAN_ID,
        ];

        DB::table('user_click_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();

        foreach ([
            [self::USER_NO_HISTORY_ID, 'No History'],
            [self::USER_IPHONE_FAN_ID, 'iPhone Fan'],
            [self::USER_MACBOOK_FAN_ID, 'MacBook Fan'],
            [self::USER_SAMSUNG_FAN_ID, 'Samsung Fan'],
        ] as [$id, $label]) {
            DB::table('users')->insert([
                'id' => $id,
                'name' => "Test - {$label}",
                'email' => "test-{$id}@personalization.test",
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Search Index: "smartphone" demo entries (معسكرين)
    // ─────────────────────────────────────────────────────────────

    /*
     * السبب في وجود هذه الـ entries:
     *
     * الأربعة كلهم بيحتوون كلمة "smartphone" بالعنوان/المحتوى، فكلهم
     * بيتطابقوا مع بحث "smartphone" على مستوى FULLTEXT بشكل متقارب.
     *
     * الفرق الحقيقي بيجي من Term Affinity: كل معسكر عنده كلمات مميزة
     * متكررة (iphone/apple/ios) مقابل (samsung/galaxy/android).
     * لما اليوزر ينقر على entries من معسكر معين، الـ term affinity
     * بيتعلم هالكلمات ويرجّح نفس المعسكر بالبحث القادم عن "smartphone".
     */
    private function seedSmartphoneSearchIndexEntries(): void
    {
        $now = now()->toDateTimeString();

        $rows = [
            // ─── Apple / iOS camp ──────────────────────────────────
            [
                'entry_id' => self::ENTRY_IPHONE_15,
                'data_type_id' => 1,
                'project_id' => self::PROJECT_ID,
                'language' => 'en',
                'title' => 'Apple iPhone 15 Pro - Premium Smartphone',
                'content' => 'The iPhone 15 Pro is a premium smartphone built by Apple with a titanium frame and A17 Pro chip. This iOS smartphone delivers flagship camera performance and seamless integration with the Apple ecosystem. A top choice for anyone searching for the best smartphone from Apple.',
                'status' => 'published',
                'meta' => json_encode(['tags' => 'apple, iphone, ios, smartphone, premium'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'entry_id' => self::ENTRY_IPHONE_14,
                'data_type_id' => 1,
                'project_id' => self::PROJECT_ID,
                'language' => 'en',
                'title' => 'Apple iPhone 14 - Reliable Smartphone Choice',
                'content' => 'iPhone 14 is a dependable Apple smartphone with excellent battery life and a smooth iOS experience. This smartphone integrates tightly with other Apple devices, making it a favorite smartphone for iOS users on a budget.',
                'status' => 'published',
                'meta' => json_encode(['tags' => 'apple, iphone, ios, smartphone, affordable'], JSON_UNESCAPED_UNICODE),
            ],

            // ─── Samsung / Android camp ─────────────────────────────
            [
                'entry_id' => self::ENTRY_SAMSUNG_S24,
                'data_type_id' => 1,
                'project_id' => self::PROJECT_ID,
                'language' => 'en',
                'title' => 'Samsung Galaxy S24 - Flagship Android Smartphone',
                'content' => 'The Samsung Galaxy S24 is a powerful Android smartphone with a stunning display and versatile camera system. This Samsung smartphone offers deep Android customization and best-in-class multitasking for Android smartphone enthusiasts.',
                'status' => 'published',
                'meta' => json_encode(['tags' => 'samsung, galaxy, android, smartphone, flagship'], JSON_UNESCAPED_UNICODE),
            ],
            [
                'entry_id' => self::ENTRY_SAMSUNG_A54,
                'data_type_id' => 1,
                'project_id' => self::PROJECT_ID,
                'language' => 'en',
                'title' => 'Samsung Galaxy A54 - Budget Android Smartphone',
                'content' => 'Samsung Galaxy A54 is an affordable Android smartphone with solid performance and a great camera for the price. This Samsung smartphone is ideal for Android users who want a reliable smartphone without the flagship price tag.',
                'status' => 'published',
                'meta' => json_encode(['tags' => 'samsung, galaxy, android, smartphone, budget'], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($rows as $row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
            $row['published_at'] = $now;

            DB::table('search_indices')->updateOrInsert(
                ['entry_id' => $row['entry_id'], 'language' => $row['language']],
                $row
            );
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Click Logs
    // ─────────────────────────────────────────────────────────────

    private function seedClickLogs(): void
    {
        $clicks = [];

        // ─── iPhone Fan: تعزيز إضافي + عينة smartphone ────────────
        // نستخدم entries من SearchIndexSeeder (1, 2) + entries هذا الـ seeder
        foreach ([1, 2, self::ENTRY_IPHONE_15, self::ENTRY_IPHONE_14] as $round) {
            for ($i = 0; $i < 3; $i++) {
                $clicks[] = $this->clickRow(self::USER_IPHONE_FAN_ID, $round, 1, $i);
            }
        }

        // ─── MacBook Fan: يبقى كما هو (يستخدم entries 4, 5) ───────
        foreach (array_fill(0, 4, 4) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_MACBOOK_FAN_ID, $entryId, 1, $i);
        }
        foreach (array_fill(0, 4, 5) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_MACBOOK_FAN_ID, $entryId, 1, $i + 4);
        }

        // ─── Samsung Fan: entries 3 (SearchIndexSeeder) + الجديدة ─
        foreach ([3, self::ENTRY_SAMSUNG_S24, self::ENTRY_SAMSUNG_A54] as $round) {
            for ($i = 0; $i < 3; $i++) {
                $clicks[] = $this->clickRow(self::USER_SAMSUNG_FAN_ID, $round, 1, $i);
            }
        }

        DB::table('user_click_logs')->insert($clicks);
    }

    private function clickRow(int $userId, int $entryId, int $dataTypeId, int $index): array
    {
        return [
            'user_id' => $userId,
            'project_id' => self::PROJECT_ID,
            'search_log_id' => null,
            'entry_id' => $entryId,
            'data_type_id' => $dataTypeId,
            'result_position' => ($index % 10) + 1,
            'session_id' => null,
            'clicked_at' => now()->subMinutes(rand(1, 60)),
        ];
    }
}