<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserBehaviorSeeder extends Seeder
{
    // ─── إعدادات ثابتة ───────────────────────────────────────────────
    private const PROJECT_ID = 1;

    private const DATA_TYPES = [
        1 => 'product',
        2 => 'article',
        3 => 'service',
    ];

    // entry_ids من كل data_type (يجب أن تتطابق مع SearchIndexSeeder)
    private const ENTRIES_BY_TYPE = [
        1 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 29],        // products
        2 => [11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 30, 35], // articles
        3 => [21, 22, 23, 24, 25, 26, 27, 28, 31],         // services
    ];

    // keywords شائعة لكل نوع
    private const KEYWORDS_BY_TYPE = [
        1 => ['iphone price', 'samsung buy', 'laptop deal', 'cheap phone',
            'apple watch price', 'ipad offer', 'macbook cost', 'tv sale'],
        2 => ['laravel tutorial', 'php guide', 'how to code', 'docker learn',
            'mysql fulltext', 'api documentation', 'framework review', 'iphone review'],
        3 => ['phone repair', 'laptop fix', 'book appointment', 'doctor booking',
            'iphone screen repair', 'car maintenance', 'hotel reservation', 'cleaning service'],
    ];

    // ─────────────────────────────────────────────────────────────────

    public function run(): void
    {
        DB::table('user_click_logs')->delete();
        DB::table('user_search_logs')->delete();

        $searchLogs = [];
        $clickLogs = [];
        $now = now();

        // ─── User 1: Product Hunter (يبحث عن منتجات بشكل مكثف) ───────
        [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
            userId: 1,
            sessionId: null,
            primaryType: 1,
            primaryWeight: 0.80,   // 80% نقرات على products
            secondaryType: 2,
            secondaryWeight: 0.15,   // 15% على articles
            tertiaryType: 3,
            tertiaryWeight: 0.05,   // 5% على services
            totalClicks: 60,
            searchLogIdOffset: 0,
        );
        $searchLogs = array_merge($searchLogs, $userSearchLogs);
        $clickLogs = array_merge($clickLogs, $userClickLogs);

        // ─── User 2: Knowledge Seeker (يقرأ مقالات ودلائل) ───────────
        [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
            userId: 2,
            sessionId: null,
            primaryType: 2,
            primaryWeight: 0.75,   // 75% على articles
            secondaryType: 1,
            secondaryWeight: 0.20,   // 20% على products
            tertiaryType: 3,
            tertiaryWeight: 0.05,   // 5% على services
            totalClicks: 50,
            searchLogIdOffset: count($searchLogs),
        );
        $searchLogs = array_merge($searchLogs, $userSearchLogs);
        $clickLogs = array_merge($clickLogs, $userClickLogs);

        // ─── User 3: Service Booker (يحجز خدمات) ─────────────────────
        [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
            userId: 3,
            sessionId: null,
            primaryType: 3,
            primaryWeight: 0.70,   // 70% على services
            secondaryType: 2,
            secondaryWeight: 0.20,   // 20% على articles
            tertiaryType: 1,
            tertiaryWeight: 0.10,   // 10% على products
            totalClicks: 45,
            searchLogIdOffset: count($searchLogs),
        );
        $searchLogs = array_merge($searchLogs, $userSearchLogs);
        $clickLogs = array_merge($clickLogs, $userClickLogs);

        // ─── User 4: Mixed Behavior (سلوك متوازن - لاختبار general) ──
        [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
            userId: 4,
            sessionId: null,
            primaryType: 1,
            primaryWeight: 0.38,   // تقريباً متساوي
            secondaryType: 2,
            secondaryWeight: 0.35,
            tertiaryType: 3,
            tertiaryWeight: 0.27,
            totalClicks: 30,
            searchLogIdOffset: count($searchLogs),
        );
        $searchLogs = array_merge($searchLogs, $userSearchLogs);
        $clickLogs = array_merge($clickLogs, $userClickLogs);

        // ─── User 5: New User - إشارات ضعيفة (cold start scenario) ───
        [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
            userId: 5,
            sessionId: null,
            primaryType: 1,
            primaryWeight: 0.60,
            secondaryType: 2,
            secondaryWeight: 0.40,
            tertiaryType: 3,
            tertiaryWeight: 0.0,
            totalClicks: 5,    // نقرات قليلة جداً
            searchLogIdOffset: count($searchLogs),
        );
        $searchLogs = array_merge($searchLogs, $userSearchLogs);
        $clickLogs = array_merge($clickLogs, $userClickLogs);

        // ─── Guest Users عبر session_id ───────────────────────────────
        $guestSessions = [
            'sess_abc123def456' => ['type' => 1, 'weight' => 0.85, 'clicks' => 20],
            'sess_xyz789uvw012' => ['type' => 2, 'weight' => 0.80, 'clicks' => 15],
            'sess_ghi345jkl678' => ['type' => 3, 'weight' => 0.75, 'clicks' => 18],
        ];

        foreach ($guestSessions as $sessionId => $config) {
            $secondaryType = $config['type'] === 1 ? 2 : ($config['type'] === 2 ? 1 : 2);
            $tertiaryType = $config['type'] === 1 ? 3 : ($config['type'] === 2 ? 3 : 1);
            $secondaryWeight = (1 - $config['weight']) * 0.7;
            $tertiaryWeight = (1 - $config['weight']) * 0.3;

            [$userSearchLogs, $userClickLogs] = $this->generateUserBehavior(
                userId: null,
                sessionId: $sessionId,
                primaryType: $config['type'],
                primaryWeight: $config['weight'],
                secondaryType: $secondaryType,
                secondaryWeight: $secondaryWeight,
                tertiaryType: $tertiaryType,
                tertiaryWeight: $tertiaryWeight,
                totalClicks: $config['clicks'],
                searchLogIdOffset: count($searchLogs),
            );
            $searchLogs = array_merge($searchLogs, $userSearchLogs);
            $clickLogs = array_merge($clickLogs, $userClickLogs);
        }

        // ─── Bulk Insert ──────────────────────────────────────────────
        foreach (array_chunk($searchLogs, 50) as $chunk) {
            DB::table('user_search_logs')->insert($chunk);
        }

        foreach (array_chunk($clickLogs, 100) as $chunk) {
            DB::table('user_click_logs')->insert($chunk);
        }

        // ─── Summary ──────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('✓ UserBehavior seeded successfully.');
        $this->command->info('');

        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Search Logs', DB::table('user_search_logs')->count()],
                ['Click Logs',  DB::table('user_click_logs')->count()],
            ]
        );

        $this->command->info('');
        $this->command->info('Expected Preferences:');
        $this->command->table(
            ['User', 'Expected Type', 'Confidence', 'Total Clicks'],
            [
                ['User 1',       'product', '~0.80', '60'],
                ['User 2',       'article', '~0.75', '50'],
                ['User 3',       'service', '~0.70', '45'],
                ['User 4',       'general', '~0.38', '30'],
                ['User 5',       'general (weak)', '<0.35', '5'],
                ['Guest abc123', 'product', '~0.85', '20'],
                ['Guest xyz789', 'article', '~0.80', '15'],
                ['Guest ghi345', 'service', '~0.75', '18'],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * توليد search logs و click logs لـ user واحد
     *
     * @return array{0: array[], 1: array[]} [searchLogs, clickLogs]
     */
    private function generateUserBehavior(
        ?int $userId,
        ?string $sessionId,
        int $primaryType,
        float $primaryWeight,
        int $secondaryType,
        float $secondaryWeight,
        int $tertiaryType,
        float $tertiaryWeight,
        int $totalClicks,
        int $searchLogIdOffset,
    ): array {
        $searchLogs = [];
        $clickLogs = [];

        // توزيع الـ clicks حسب الأوزان
        $distribution = $this->calculateDistribution(
            $totalClicks,
            $primaryType, $primaryWeight,
            $secondaryType, $secondaryWeight,
            $tertiaryType, $tertiaryWeight,
        );

        // إنشاء search logs أولاً
        $searchLogCount = max(5, (int) ($totalClicks * 0.6));

        for ($i = 0; $i < $searchLogCount; $i++) {
            // اختر keyword بناءً على النوع الأكثر بحثاً
            $randomType = $this->weightedRandom($distribution);
            $keywords = self::KEYWORDS_BY_TYPE[$randomType] ?? self::KEYWORDS_BY_TYPE[1];
            $keyword = $keywords[array_rand($keywords)];
            $intentMap = [1 => 'product', 2 => 'article', 3 => 'service'];

            $searchLogs[] = [
                'user_id' => $userId,
                'project_id' => self::PROJECT_ID,
                'keyword' => $keyword,
                'language' => 'en',
                'detected_intent' => $intentMap[$randomType],
                'intent_confidence' => round(0.5 + lcg_value() * 0.5, 3),
                'results_count' => rand(3, 15),
                'session_id' => $sessionId,
                'searched_at' => $this->randomTimestamp(30),
            ];
        }

        // إنشاء click logs مرتبطة بـ search logs
        $localSearchLogIndex = 0;

        foreach ($distribution as $dataTypeId => $clickCount) {
            $entries = self::ENTRIES_BY_TYPE[$dataTypeId] ?? [];

            if (empty($entries)) {
                continue;
            }

            for ($i = 0; $i < $clickCount; $i++) {
                $entryId = $entries[array_rand($entries)];
                $position = $this->weightedPosition();

                // ربط بـ search log إذا وُجد
                $searchLogId = null;
                if (! empty($searchLogs)) {
                    $searchLogId = $searchLogIdOffset + ($localSearchLogIndex % count($searchLogs)) + 1;
                    $localSearchLogIndex++;
                }

                $clickLogs[] = [
                    'user_id' => $userId,
                    'project_id' => self::PROJECT_ID,
                    'search_log_id' => $searchLogId,
                    'entry_id' => $entryId,
                    'data_type_id' => $dataTypeId,
                    'result_position' => $position,
                    'session_id' => $sessionId,
                    'clicked_at' => $this->randomTimestamp(30),
                ];
            }
        }

        return [$searchLogs, $clickLogs];
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * حساب توزيع الـ clicks الفعلي بناءً على الأوزان
     *
     * @return array<int, int> [data_type_id => click_count]
     */
    private function calculateDistribution(
        int $total,
        int $primaryType, float $primaryWeight,
        int $secondaryType, float $secondaryWeight,
        int $tertiaryType, float $tertiaryWeight,
    ): array {
        $primary = (int) round($total * $primaryWeight);
        $secondary = (int) round($total * $secondaryWeight);
        $tertiary = $total - $primary - $secondary;  // الباقي للثالث

        $dist = [];

        if ($primary > 0) {
            $dist[$primaryType] = $primary;
        }
        if ($secondary > 0) {
            $dist[$secondaryType] = $secondary;
        }
        if ($tertiary > 0 && $tertiaryType !== $primaryType && $tertiaryType !== $secondaryType) {
            $dist[$tertiaryType] = $tertiary;
        }

        return $dist;
    }

    /**
     * اختيار عشوائي موزون (weighted random)
     *
     * @param  array<int, int>  $distribution
     */
    private function weightedRandom(array $distribution): int
    {
        $total = array_sum($distribution);

        if ($total === 0) {
            return array_key_first($distribution);
        }

        $rand = rand(1, $total);
        $cumulative = 0;

        foreach ($distribution as $type => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $type;
            }
        }

        return array_key_last($distribution);
    }

    /**
     * موقع النتيجة المنقور عليها
     * المنطق: المستخدمون ينقرون على النتائج الأولى أكثر (مثل الواقع)
     *
     *   position 1 → 45% من الوقت
     *   position 2 → 25%
     *   position 3 → 15%
     *   position 4-6 → 10%
     *   position 7+ → 5%
     */
    private function weightedPosition(): int
    {
        $rand = rand(1, 100);

        return match (true) {
            $rand <= 45 => 1,
            $rand <= 70 => 2,
            $rand <= 85 => 3,
            $rand <= 95 => rand(4, 6),
            default => rand(7, 10),
        };
    }

    /**
     * توليد timestamp عشوائي في آخر N يوم
     * مع توزيع أكثر واقعية (أحدث الأحداث أكثر)
     */
    private function randomTimestamp(int $days): string
    {
        // التوزيع: الأيام الأخيرة أكثر نشاطاً (مثل الواقع)
        $weight = lcg_value();  // 0.0 → 1.0
        $daysAgo = (int) ($days * (1 - sqrt($weight)));  // أكثر تركيزاً على الأيام الأخيرة

        $secondsAgo = rand(0, 86400) + ($daysAgo * 86400);

        return now()->subSeconds($secondsAgo)->format('Y-m-d H:i:s');
    }
}
