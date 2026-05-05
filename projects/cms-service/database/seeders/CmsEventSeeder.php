<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsEventSeeder extends Seeder
{
    public function run()
    {
        DB::beginTransaction();

        try {

            // 🟢 1. get or create project
            $project = DB::table('projects')
                ->where('slug', 'pulse360')
                ->first();

            if (!$project) {
                $projectId = DB::table('projects')->insertGetId([
                    'public_id' => Str::uuid(),
                    'slug' => 'pulse360',
                    'name' => 'Pulse360',
                    'owner_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $projectId = $project->id;
            }

            // 🟢 2. get or create event data type
            $eventType = DB::table('data_types')
                ->where('slug', 'event')
                ->value('id');

            if (!$eventType) {
                $eventType = DB::table('data_types')->insertGetId([
                    'project_id' => $projectId,
                    'name' => 'Event',
                    'slug' => 'event',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // fields
                $fields = ['title', 'description', 'image', 'date', 'location'];

                foreach ($fields as $i => $field) {
                    DB::table('data_type_fields')->insert([
                        'data_type_id' => $eventType,
                        'name' => $field,
                        'type' => 'text',
                        'sort_order' => $i,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 🟢 3. events
            $events = [
                ['title' => 'AI Conference 2026', 'location' => 'Dubai'],
                ['title' => 'Tech Meetup', 'location' => 'Riyadh'],
                ['title' => 'Startup Pitch Night', 'location' => 'Cairo'],
            ];

            foreach ($events as $i => $event) {

                $slug = Str::slug($event['title']);

                // prevent duplicate
                $exists = DB::table('data_entries')
                    ->where('slug', $slug)
                    ->exists();

                if ($exists) continue;

                $entryId = DB::table('data_entries')->insertGetId([
                    'project_id' => $projectId,
                    'data_type_id' => $eventType,
                    'slug' => $slug,
                    'status' => 'published',
                    'published_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->setValue($entryId, $eventType, 'title', $event['title']);
                $this->setValue($entryId, $eventType, 'description', 'Event description...');
                $this->setValue($entryId, $eventType, 'image', "https://picsum.photos/800/600?event=$i");
                $this->setValue($entryId, $eventType, 'date', now()->addDays($i * 3));
                $this->setValue($entryId, $eventType, 'location', $event['location']);
            }

            DB::commit();

            echo "\n✅ CMS Events Seeded\n";

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
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