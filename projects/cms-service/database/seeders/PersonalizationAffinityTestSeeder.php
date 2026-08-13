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

    public function run(): void
    {
        if (DB::table('search_indices')->where('project_id', self::PROJECT_ID)->count() === 0) {
            $this->command->error('✖ شغّل أولاً: php artisan db:seed --class=SearchIndexSeeder');
            return;
        }

        $userIds = [self::USER_NO_HISTORY_ID, self::USER_IPHONE_FAN_ID, self::USER_MACBOOK_FAN_ID];

        DB::table('user_click_logs')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();

        foreach ([
            [self::USER_NO_HISTORY_ID, 'No History'],
            [self::USER_IPHONE_FAN_ID, 'iPhone Fan'],
            [self::USER_MACBOOK_FAN_ID, 'MacBook Fan'],
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

        $clicks = [];

        foreach (array_fill(0, 4, 1) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_IPHONE_FAN_ID, $entryId, 1, $i);
        }
        foreach (array_fill(0, 4, 2) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_IPHONE_FAN_ID, $entryId, 1, $i + 4);
        }

        foreach (array_fill(0, 4, 4) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_MACBOOK_FAN_ID, $entryId, 1, $i);
        }
        foreach (array_fill(0, 4, 5) as $i => $entryId) {
            $clicks[] = $this->clickRow(self::USER_MACBOOK_FAN_ID, $entryId, 1, $i + 4);
        }

        DB::table('user_click_logs')->insert($clicks);

        $this->command->info('✅ Personalization test data seeded.');
        $this->command->table(
            ['User ID', 'Label', 'Expected term affinity signal'],
            [
                [self::USER_NO_HISTORY_ID, 'No History', 'affinities=[] termAffinities=[]'],
                [self::USER_IPHONE_FAN_ID, 'iPhone Fan', "top terms ≈ 'iphone', '15', 'pro', 'max', '14'"],
                [self::USER_MACBOOK_FAN_ID, 'MacBook Fan', "top terms ≈ 'macbook', 'pro', 'dell', 'xps'"],
            ]
        );
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