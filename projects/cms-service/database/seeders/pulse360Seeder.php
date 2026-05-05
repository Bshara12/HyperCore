<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class pulse360Seeder extends Seeder
{
  public function run()
  {
    DB::beginTransaction();

    try {

      // 🟢 1. Project
      $projectId = DB::table('projects')->insertGetId([
        'public_id' => Str::uuid(),
        'slug' => 'pulse360',
        'name' => 'Pulse360',
        'owner_id' => 1,
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      // 🟢 2. Data Types
      $articleType = $this->createDataType($projectId, 'article');
      $categoryType = $this->createDataType($projectId, 'category');
      $eventType = $this->createDataType($projectId, 'event');

      // 🟢 3. Fields
      $this->createFields($articleType, ['title', 'content', 'image']);
      $this->createFields($categoryType, ['name']);
      $this->createFields($eventType, ['title', 'description', 'image', 'date', 'location']);

      // 🟢 4. Relation
      $articleCategoryRelationId = DB::table('data_type_relations')->insertGetId([
        'data_type_id' => $articleType,
        'related_data_type_id' => $categoryType,
        'relation_type' => 'many_to_many',
        'relation_name' => 'categories',
        'created_at' => now(),
        'updated_at' => now(),
      ]);

      // 🟢 5. Categories
      $categories = ['Technology', 'AI', 'Business', 'Science'];

      $categoryEntries = [];
      foreach ($categories as $cat) {
        $entryId = $this->createEntry($projectId, $categoryType, Str::slug($cat));
        $this->setValue($entryId, $categoryType, 'name', $cat);

        $categoryEntries[] = [
          'id' => $entryId,
          'name' => $cat,
        ];
      }

      // 🟢 6. Articles
      for ($i = 1; $i <= 6; $i++) {

        $entryId = $this->createEntry($projectId, $articleType, "article-$i");

        $this->setValue($entryId, $articleType, 'title', "AI Article $i");
        $this->setValue($entryId, $articleType, 'content', "This is content for article $i...");
        $this->setValue($entryId, $articleType, 'image', "https://picsum.photos/800/600?random=$i");

        $randomCategory = $categoryEntries[array_rand($categoryEntries)];

        // Relation
        DB::table('data_entry_relations')->insert([
          'data_entry_id' => $entryId,
          'related_entry_id' => $randomCategory['id'],
          'data_type_relation_id' => $articleCategoryRelationId,
          'created_at' => now(),
          'updated_at' => now(),
        ]);
      }

      // 🟢 7. 🔥 POPULAR SEARCHES (Trending System)
      // foreach ($categories as $cat) {

      //     DB::table('popular_searches')->insert([
      //         'project_id' => $projectId,
      //         'keyword' => $cat,
      //         'normalized_keyword' => Str::lower($cat),
      //         'language' => 'en',

      //         // fake stats
      //         'count_24h' => rand(10, 100),
      //         'count_7d' => rand(50, 300),
      //         'count_30d' => rand(100, 800),
      //         'count_all_time' => rand(500, 2000),

      //         'click_count' => rand(50, 500),

      //         // scores
      //         'trending_score' => rand(100, 1000) / 10,
      //         'alltime_score' => rand(500, 2000) / 10,

      //         'last_searched_at' => now()->subMinutes(rand(1, 500)),
      //         'last_computed_at' => now(),

      //         'created_at' => now(),
      //         'updated_at' => now(),
      //     ]);
      // }
      // 🟢 7. 🔥 POPULAR SEARCHES (Articles-based Trending)

      $articles = DB::table('data_entries')
        ->where('data_type_id', $articleType)
        ->get();

      foreach ($articles as $article) {

        // نجيب العنوان
        $title = DB::table('data_entry_values')
          ->join('data_type_fields', 'data_entry_values.data_type_field_id', '=', 'data_type_fields.id')
          ->where('data_entry_values.data_entry_id', $article->id)
          ->where('data_type_fields.name', 'title')
          ->value('value');

        DB::table('popular_searches')->insert([
          'project_id' => $projectId,

          // 🔥 الآن المقال نفسه
          'keyword' => $title,
          'normalized_keyword' => $article->slug,

          'language' => 'en',

          // fake stats
          'count_24h' => rand(10, 100),
          'count_7d' => rand(50, 300),
          'count_30d' => rand(100, 800),
          'count_all_time' => rand(500, 2000),

          'click_count' => rand(50, 500),

          // scores
          'trending_score' => rand(100, 1000) / 10,
          'alltime_score' => rand(500, 2000) / 10,

          'last_searched_at' => now()->subMinutes(rand(1, 500)),
          'last_computed_at' => now(),

          'created_at' => now(),
          'updated_at' => now(),
        ]);
      }

      // 🟢 8. Events
      for ($i = 1; $i <= 3; $i++) {

        $entryId = $this->createEntry($projectId, $eventType, "event-$i");

        $this->setValue($entryId, $eventType, 'title', "AI Event $i");
        $this->setValue($entryId, $eventType, 'description', "Join event $i with top speakers.");
        $this->setValue($entryId, $eventType, 'image', "https://picsum.photos/800/600?event=$i");
        $this->setValue($entryId, $eventType, 'date', now()->addDays($i * 5));
        $this->setValue($entryId, $eventType, 'location', "Dubai");
      }

      DB::commit();

      // 🟢 9. PRINT RESULTS
      echo "\n✅ Seeder Completed\n";

      $popular = DB::table('popular_searches')->get();

      echo "\n🔥 Trending Keywords:\n";
      foreach ($popular as $p) {
        echo "Keyword: {$p->keyword} | Score: {$p->trending_score}\n";
      }
    } catch (\Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }

  // Helpers

  private function createDataType($projectId, $slug)
  {
    return DB::table('data_types')->insertGetId([
      'project_id' => $projectId,
      'name' => ucfirst($slug),
      'slug' => $slug,
      'is_active' => true,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  private function createFields($dataTypeId, $fields)
  {
    foreach ($fields as $index => $field) {
      DB::table('data_type_fields')->insert([
        'data_type_id' => $dataTypeId,
        'name' => $field,
        'type' => 'text',
        'sort_order' => $index,
        'created_at' => now(),
        'updated_at' => now(),
      ]);
    }
  }

  private function createEntry($projectId, $typeId, $slug)
  {
    return DB::table('data_entries')->insertGetId([
      'project_id' => $projectId,
      'data_type_id' => $typeId,
      'slug' => $slug,
      'status' => 'published',
      'published_at' => now(),
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }

  private function setValue($entryId, $typeId, $fieldName, $value)
  {
    $fieldId = DB::table('data_type_fields')
      ->where('data_type_id', $typeId)
      ->where('name', $fieldName)
      ->value('id');

    DB::table('data_entry_values')->insert([
      'data_entry_id' => $entryId,
      'data_type_field_id' => $fieldId,
      'value' => $value,
      'created_at' => now(),
      'updated_at' => now(),
    ]);
  }
}
