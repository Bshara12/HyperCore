<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsAnalyticsSeeder extends Seeder
{
  // =========================================================
  // هذا الـ Seeder يشتغل على DB خاصة بـ CMS Service
  // بعد تشغيله، خذ الـ IDs وحدّث config/seed_config.php
  // في كل الخدمات الثانية
  // =========================================================

  public function run(): void
  {
    $this->command->info('🌱 [CMS] Starting Seeder...');

    // ─── 1. Users ──────────────────────────────────────────────
    $userIds = [];

    for ($i = 1; $i <= 10; $i++) {
      $userId = DB::table('users')->insertGetId([
        'name'       => "User {$i}",
        'email'      => "user{$i}@test.com",
        'password'   => bcrypt('password'),
        'created_at' => now()->subMonths(rand(3, 12)),
        'updated_at' => now(),
      ]);
      $userIds[] = $userId;
    }
    $this->command->info('✅ Users: ' . implode(', ', $userIds));

    // ─── 2. Projects ───────────────────────────────────────────
    $projectsData = [
      [
        'name'            => 'E-Commerce Store',
        'enabled_modules' => json_encode(['ecommerce', 'booking']),
        'months_ago'      => 6,
      ],
      [
        'name'            => 'Service Booking Platform',
        'enabled_modules' => json_encode(['booking']),
        'months_ago'      => 4,
      ],
      [
        'name'            => 'Online Shop',
        'enabled_modules' => json_encode(['ecommerce']),
        'months_ago'      => 2,
      ],
    ];

    $projectIds = [];
    foreach ($projectsData as $p) {
      $pid = DB::table('projects')->insertGetId([
        'public_id'           => Str::uuid()->toString(),
        'slug'                => Str::slug($p['name']),
        'name'                => $p['name'],
        'owner_id'            => $userIds[0],
        'supported_languages' => json_encode(['en', 'ar']),
        'enabled_modules'     => $p['enabled_modules'],
        'ratings_count'       => 0,
        'ratings_avg'         => 0,
        'created_at'          => now()->subMonths($p['months_ago']),
        'updated_at'          => now()->subMonths($p['months_ago']),
      ]);
      $projectIds[] = $pid;

      // ربط المستخدمين بالمشروع
      foreach (array_slice($userIds, 0, 5) as $uid) {
        DB::table('project_user')->insertOrIgnore([
          'project_id' => $pid,
          'user_id'    => $uid,
        ]);
      }
    }
    $this->command->info('✅ Projects: ' . implode(', ', $projectIds));

    // ─── 3. Data Types ─────────────────────────────────────────
    $dataTypeDefinitions = [
      ['name' => 'Products',   'slug' => 'products'],
      ['name' => 'Articles',   'slug' => 'articles'],
      ['name' => 'Categories', 'slug' => 'categories'],
      ['name' => 'Services',   'slug' => 'services'],
    ];

    $dataTypeIds = [];
    foreach ($projectIds as $pid) {
      $dataTypeIds[$pid] = [];
      foreach ($dataTypeDefinitions as $dt) {
        $dtId = DB::table('data_types')->insertGetId([
          'project_id'  => $pid,
          'name'        => $dt['name'],
          'slug'        => $dt['slug'],
          'description' => "Auto-generated {$dt['name']}",
          'is_active'   => true,
          'settings'    => json_encode([]),
          'created_at'  => now()->subMonths(3),
          'updated_at'  => now()->subMonths(3),
        ]);
        $dataTypeIds[$pid][] = $dtId;

        // Data Type Fields
        DB::table('data_type_fields')->insert([
          [
            'data_type_id'     => $dtId,
            'name'             => 'title',
            'type'             => 'text',
            'required'         => true,
            'translatable'     => true,
            'validation_rules' => json_encode(['string', 'max:255']),
            'settings'         => json_encode(['placeholder' => 'Enter title']),
            'sort_order'       => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
          ],
          [
            'data_type_id'     => $dtId,
            'name'             => 'price',
            'type'             => 'number',
            'required'         => false,
            'translatable'     => false,
            'validation_rules' => json_encode(['numeric', 'min:0']),
            'settings'         => json_encode(['default' => 0, 'step' => 1]),
            'sort_order'       => 2,
            'created_at'       => now(),
            'updated_at'       => now(),
          ],
          [
            'data_type_id'     => $dtId,
            'name'             => 'count',
            'type'             => 'number',
            'required'         => false,
            'translatable'     => false,
            'validation_rules' => json_encode(['integer', 'min:0']),
            'settings'         => json_encode(['default' => 0, 'step' => 1]),
            'sort_order'       => 3,
            'created_at'       => now(),
            'updated_at'       => now(),
          ],
        ]);
      }
    }

    // ─── 4. Data Entries ───────────────────────────────────────
    $statuses = [
      'published',
      'published',
      'published',
      'draft',
      'scheduled',
      'archived',
    ];

    $entryIds = [];
    foreach ($projectIds as $pid) {
      $entryIds[$pid] = [];

      foreach ($dataTypeIds[$pid] as $dtId) {
        for ($e = 1; $e <= 20; $e++) {
          $status      = $statuses[array_rand($statuses)];
          $createdAt   = now()->subDays(rand(1, 180));
          $publishedAt = $status === 'published'
            ? $createdAt->copy()->addHours(rand(1, 24))
            : null;

          $entryId = DB::table('data_entries')->insertGetId([
            'slug'         => Str::slug("entry-{$pid}-{$dtId}-{$e}-" . Str::random(4)),
            'data_type_id' => $dtId,
            'project_id'   => $pid,
            'status'       => $status,
            'created_by'   => $userIds[array_rand($userIds)],
            'ratings_count' => 0,
            'ratings_avg'  => 0,
            'published_at' => $publishedAt,
            'created_at'   => $createdAt,
            'updated_at'   => $createdAt,
          ]);

          // Entry Values
          $fields = DB::table('data_type_fields')
            ->where('data_type_id', $dtId)
            ->get();

          foreach ($fields as $field) {
            $value = match ($field->name) {
              'title' => "Item {$e} title",
              'price' => rand(10, 500),
              'count' => rand(0, 100),
              default => null,
            };

            if ($value !== null) {
              DB::table('data_entry_values')->insert([
                'data_entry_id'     => $entryId,
                'data_type_field_id' => $field->id,
                'language'          => null,
                'value'             => (string) $value,
                'created_at'        => now(),
                'updated_at'        => now(),
              ]);
            }
          }

          $entryIds[$pid][] = $entryId;
        }
      }
    }
    $this->command->info('✅ Data Entries created per project');

    // ─── 5. Data Collections ───────────────────────────────────
    $collectionIds = [];
    foreach ($projectIds as $pid) {
      $dtId = $dataTypeIds[$pid][0]; // Products data type

      $col1 = DB::table('data_collections')->insertGetId([
        'project_id'       => $pid,
        'data_type_id'     => $dtId,
        'name'             => 'Featured Products',
        'slug'             => "featured-products-{$pid}",
        'type'             => 'manual',
        'conditions_logic' => 'and',
        'is_active'        => true,
        'is_offer'         => true,
        'created_at'       => now()->subDays(60),
        'updated_at'       => now()->subDays(60),
      ]);

      $col2 = DB::table('data_collections')->insertGetId([
        'project_id'       => $pid,
        'data_type_id'     => $dtId,
        'name'             => 'New Arrivals',
        'slug'             => "new-arrivals-{$pid}",
        'type'             => 'dynamic',
        'conditions_logic' => 'and',
        'is_active'        => true,
        'is_offer'         => false,
        'created_at'       => now()->subDays(30),
        'updated_at'       => now()->subDays(30),
      ]);

      $collectionIds[$pid] = [$col1, $col2];

      // Collection Items
      $sampleEntries = array_slice($entryIds[$pid], 0, 8);
      foreach ($sampleEntries as $index => $eid) {
        DB::table('data_collection_items')->insert([
          'collection_id' => $col1,
          'item_id'       => $eid,
          'sort_order'    => $index + 1,
          'created_at'    => now(),
          'updated_at'    => now(),
        ]);
      }
    }
    $this->command->info('✅ Collections created');

    // ─── 6. Ratings ────────────────────────────────────────────
    $ratingValues = [1, 2, 3, 3, 4, 4, 4, 5, 5, 5];

    foreach ($projectIds as $pid) {
      // تقييمات المشروع
      foreach (array_slice($userIds, 0, 6) as $uid) {
        $rating = $ratingValues[array_rand($ratingValues)];
        DB::table('ratings')->insertOrIgnore([
          'user_id'       => $uid,
          'rateable_type' => 'project',
          'rateable_id'   => $pid,
          'rating'        => $rating,
          'review'        => $rating >= 4 ? 'Great platform!' : null,
          'created_at'    => now()->subDays(rand(1, 90)),
          'updated_at'    => now(),
        ]);
      }

      // تقييمات الـ Entries
      foreach (array_slice($entryIds[$pid], 0, 15) as $eid) {
        foreach (array_slice($userIds, 0, rand(2, 5)) as $uid) {
          $rating = $ratingValues[array_rand($ratingValues)];
          DB::table('ratings')->insertOrIgnore([
            'user_id'       => $uid,
            'rateable_type' => 'data',
            'rateable_id'   => $eid,
            'rating'        => $rating,
            'review'        => $rating >= 4 ? 'Excellent!' : null,
            'created_at'    => now()->subDays(rand(1, 60)),
            'updated_at'    => now(),
          ]);
        }

        // تحديث ratings stats على الـ entry
        $stats = DB::table('ratings')
          ->where('rateable_type', 'data')
          ->where('rateable_id', $eid)
          ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
          ->first();

        DB::table('data_entries')->where('id', $eid)->update([
          'ratings_count' => $stats->cnt,
          'ratings_avg'   => round($stats->avg, 2),
        ]);
      }

      // تحديث ratings stats على المشروع
      $pStats = DB::table('ratings')
        ->where('rateable_type', 'project')
        ->where('rateable_id', $pid)
        ->selectRaw('COUNT(*) as cnt, AVG(rating) as avg')
        ->first();

      DB::table('projects')->where('id', $pid)->update([
        'ratings_count' => $pStats->cnt,
        'ratings_avg'   => round($pStats->avg, 2),
      ]);
    }
    $this->command->info('✅ Ratings created and stats updated');

    // ─── 7. طباعة الـ IDs المهمة ───────────────────────────────
    $this->command->newLine();
    $this->command->info('📋 Copy these IDs to config/seed_config.php in ALL services:');
    $this->command->table(
      ['Config Key', 'Value'],
      [
        ['project_id (first)',  $projectIds[0]],
        ['project_ids',         implode(', ', $projectIds)],
        ['user_ids',            implode(', ', $userIds)],
        ['owner_id',            $userIds[0]],
        ['collection_id (first)', $collectionIds[$projectIds[0]][0]],
        ['entry_ids (sample)',  implode(', ', array_slice($entryIds[$projectIds[0]], 0, 5))],
      ]
    );

    $this->command->info('🎉 [CMS] Seeder completed!');
    $this->command->table(
      ['Entity', 'Count'],
      [
        ['Users',        count($userIds)],
        ['Projects',     count($projectIds)],
        ['Data Types',   count($projectIds) * count($dataTypeDefinitions)],
        ['Data Entries', count($projectIds) * count($dataTypeDefinitions) * 20],
        ['Collections',  count($projectIds) * 2],
      ]
    );
  }
}
