<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSearchLogsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('user_search_logs')->delete();

        $now = now();

        $records = [];

        // ─────────────────────────────────────────────
        // User 1 → يحب المنتجات (product heavy)
        // ─────────────────────────────────────────────
        foreach ([
            'iphone',
            'iphone price',
            'buy iphone',
            'cheap iphone',
            'best phone price',
        ] as $keyword) {
            $records[] = $this->makeRecord(
                userId: 1,
                projectId: 1,
                keyword: $keyword,
                intent: 'transactional',
                confidence: 0.9,
                results: rand(5, 15),
                time: $now->copy()->subMinutes(rand(10, 500))
            );
        }

        // ─────────────────────────────────────────────
        // User 2 → يحب المقالات (article heavy)
        // ─────────────────────────────────────────────
        foreach ([
            'iphone review',
            'laravel tutorial',
            'php guide',
            'how to build api',
            'docker tutorial',
        ] as $keyword) {
            $records[] = $this->makeRecord(
                userId: 2,
                projectId: 1,
                keyword: $keyword,
                intent: 'informational',
                confidence: 0.85,
                results: rand(5, 20),
                time: $now->copy()->subMinutes(rand(10, 500))
            );
        }

        // ─────────────────────────────────────────────
        // User 3 → خدمات (service heavy)
        // ─────────────────────────────────────────────
        foreach ([
            'iphone repair',
            'book doctor appointment',
            'laptop repair service',
            'cleaning service',
        ] as $keyword) {
            $records[] = $this->makeRecord(
                userId: 3,
                projectId: 1,
                keyword: $keyword,
                intent: 'service',
                confidence: 0.8,
                results: rand(3, 10),
                time: $now->copy()->subMinutes(rand(10, 500))
            );
        }

        // ─────────────────────────────────────────────
        // Guest Users (session-based)
        // ─────────────────────────────────────────────
        foreach ([
            'iphone',
            'samsung phone',
            'buy laptop',
        ] as $keyword) {
            $records[] = $this->makeRecord(
                userId: null,
                projectId: 1,
                keyword: $keyword,
                intent: 'transactional',
                confidence: 0.7,
                results: rand(5, 15),
                sessionId: 'guest_session_1',
                time: $now->copy()->subMinutes(rand(10, 500))
            );
        }

        // Bulk insert
        foreach (array_chunk($records, 20) as $chunk) {
            DB::table('user_search_logs')->insert($chunk);
        }

        $this->command->info('UserSearchLogs seeded: ' . count($records));
    }

    // ─────────────────────────────────────────────

    private function makeRecord(
        ?int $userId,
        int $projectId,
        string $keyword,
        string $intent,
        float $confidence,
        int $results,
        $time,
        ?string $sessionId = null
    ): array {
        return [
            'user_id'           => $userId,
            'project_id'        => $projectId,
            'keyword'           => $keyword,
            'language'          => 'en',
            'detected_intent'   => $intent,
            'intent_confidence' => $confidence,
            'results_count'     => $results,
            'session_id'        => $sessionId,
            'searched_at'       => $time,
        ];
    }
}