<?php

namespace Database\Seeders\Demo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The write helpers the demo project seeders share.
 *
 * Everything here is idempotent and works on fixed ids so the seeders can be
 * run one at a time, repeatedly, without duplicating rows.
 */
trait DemoContentBuilder
{
    /**
     * Drop the read caches after seeding.
     *
     * These seeders write with DB::table(), which is deliberate — they set
     * explicit ids the other services reference — but it means they bypass the
     * actions that normally invalidate. projects:all alone is cached for a full
     * day, so without this the newly seeded projects simply do not appear in
     * GET /api/projects until tomorrow.
     *
     * A flush rather than a key list: a seeder touches projects, data types,
     * fields, entries, collections and ratings at once, so essentially every
     * derived cache is stale. Enumerating keys here would silently rot the
     * first time a new cache is added.
     */
    protected function flushReadCaches(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            // A missing cache backend must not fail the seed.
            $this->command?->warn('Could not flush the cache: '.$e->getMessage());
        }
    }

    /**
     * Mirror the Auth users into the local users table.
     *
     * data_entries.created_by carries a foreign key to this table even though
     * users actually live in the Auth service, so an entry cannot be written
     * for an author CMS has never heard of.
     */
    protected function mirrorDemoUsers(): void
    {
        $users = [
            [DemoIds::ADMIN_USER_ID, 'Admin User', DemoIds::ADMIN_EMAIL],
            [DemoIds::OWNER_USER_ID, 'Project Owner', DemoIds::OWNER_EMAIL],
            [DemoIds::CUSTOMER_ONE_ID, 'Sara Haddad', 'customer1@example.com'],
            [DemoIds::CUSTOMER_TWO_ID, 'Omar Nasser', 'customer2@example.com'],
            [DemoIds::CUSTOMER_THREE_ID, 'Lina Khoury', 'customer3@example.com'],
        ];

        foreach ($users as [$id, $name, $email]) {
            DB::table('users')->where('email', $email)->where('id', '!=', $id)->delete();

            DB::table('users')->updateOrInsert(
                ['id' => $id],
                [
                    'name' => $name,
                    'email' => $email,
                    'password' => bcrypt(DemoIds::DEMO_PASSWORD),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Remove a project and everything hanging off it, so a re-run starts clean.
     *
     * Order matters: children before parents, because the schema cascades on
     * delete only in some directions.
     */
    protected function purgeProject(int $projectId): void
    {
        $entryIds = DB::table('data_entries')->where('project_id', $projectId)->pluck('id');
        $collectionIds = DB::table('data_collections')->where('project_id', $projectId)->pluck('id');
        $dataTypeIds = DB::table('data_types')->where('project_id', $projectId)->pluck('id');

        if ($entryIds->isNotEmpty()) {
            DB::table('data_entry_values')->whereIn('data_entry_id', $entryIds)->delete();
            DB::table('data_entry_relations')->whereIn('data_entry_id', $entryIds)->delete();
            DB::table('data_entry_relations')->whereIn('related_entry_id', $entryIds)->delete();
            DB::table('seo_entries')->whereIn('data_entry_id', $entryIds)->delete();
            DB::table('ratings')->where('rateable_type', 'data')->whereIn('rateable_id', $entryIds)->delete();
            DB::table('search_indices')->whereIn('entry_id', $entryIds)->delete();
        }

        if ($collectionIds->isNotEmpty()) {
            DB::table('data_collection_items')->whereIn('collection_id', $collectionIds)->delete();
        }

        DB::table('data_collections')->where('project_id', $projectId)->delete();
        DB::table('data_entries')->where('project_id', $projectId)->delete();

        if ($dataTypeIds->isNotEmpty()) {
            DB::table('data_type_relations')->whereIn('data_type_id', $dataTypeIds)->delete();
            DB::table('data_type_relations')->whereIn('related_data_type_id', $dataTypeIds)->delete();
            DB::table('data_type_fields')->whereIn('data_type_id', $dataTypeIds)->delete();
        }

        DB::table('data_types')->where('project_id', $projectId)->delete();
        DB::table('ratings')->where('rateable_type', 'project')->where('rateable_id', $projectId)->delete();
        DB::table('project_user')->where('project_id', $projectId)->delete();
        DB::table('projects')->where('id', $projectId)->delete();
    }

    /**
     * @param  string[]  $languages
     * @param  string[]  $modules
     */
    protected function createProject(
        int $id,
        int $ownerId,
        string $name,
        string $slug,
        string $description,
        array $languages,
        array $modules,
    ): void {
        DB::table('projects')->insert([
            'id' => $id,
            // Deterministic public_id: the header every other service addresses
            // this project by, so it has to survive a re-seed unchanged.
            'public_id' => self::demoPublicId($id),
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'owner_id' => $ownerId,
            'supported_languages' => json_encode($languages),
            'enabled_modules' => json_encode($modules),
            'created_at' => now()->subMonths(2),
            'updated_at' => now(),
        ]);

        DB::table('project_user')->insert([
            'project_id' => $id,
            'user_id' => $ownerId,
        ]);
    }

    /**
     * A stable, readable public_id derived from the project id.
     *
     * This is the value callers put in X-Project-Id, so it must not change
     * between seeds — a random UUID would invalidate every saved request the
     * moment the seeder is re-run.
     */
    public static function demoPublicId(int $projectId): string
    {
        $hash = md5("hypercore-demo-project-{$projectId}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12)
        );
    }

    /**
     * @param  array<int, array{name: string, type: string, required?: bool, translatable?: bool, settings?: array, rules?: array}>  $fields
     * @return array<string, int> field name => id
     */
    protected function createDataType(
        int $id,
        int $projectId,
        string $name,
        string $slug,
        string $description,
        array $fields,
    ): array {
        DB::table('data_types')->insert([
            'id' => $id,
            'project_id' => $projectId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'is_active' => true,
            'settings' => json_encode([]),
            'created_at' => now()->subMonths(2),
            'updated_at' => now(),
        ]);

        $fieldIds = [];
        $sortOrder = 1;

        foreach ($fields as $field) {
            $fieldId = DB::table('data_type_fields')->insertGetId([
                'data_type_id' => $id,
                'name' => $field['name'],
                'type' => $field['type'],
                'required' => $field['required'] ?? false,
                'translatable' => $field['translatable'] ?? false,
                'validation_rules' => json_encode($field['rules'] ?? []),
                'settings' => json_encode($field['settings'] ?? []),
                'sort_order' => $sortOrder++,
                'created_at' => now()->subMonths(2),
                'updated_at' => now(),
            ]);

            $fieldIds[$field['name']] = $fieldId;
        }

        return $fieldIds;
    }

    /**
     * @param  array<string, int>  $fieldIds  field name => id
     * @param  array<string, mixed>  $values   field name => scalar, or ['en' => .., 'ar' => ..]
     */
    protected function createEntry(
        int $id,
        int $projectId,
        int $dataTypeId,
        string $slug,
        string $status,
        int $authorId,
        array $fieldIds,
        array $values,
        ?\DateTimeInterface $createdAt = null,
        ?\DateTimeInterface $scheduledAt = null,
    ): void {
        $createdAt ??= now()->subWeeks(4);

        DB::table('data_entries')->insert([
            'id' => $id,
            'slug' => $slug,
            'data_type_id' => $dataTypeId,
            'project_id' => $projectId,
            'status' => $status,
            'scheduled_at' => $scheduledAt,
            'created_by' => $authorId,
            'updated_by' => $authorId,
            'published_at' => $status === 'published' ? $createdAt : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        $rows = [];

        foreach ($values as $fieldName => $value) {
            if (! isset($fieldIds[$fieldName])) {
                continue;
            }

            // A scalar is a single untranslated value (language NULL); an array
            // is one row per language, which is how the read side expects
            // translatable fields to be stored.
            $perLanguage = is_array($value) ? $value : [null => $value];

            foreach ($perLanguage as $language => $languageValue) {
                if ($languageValue === null) {
                    continue;
                }

                $rows[] = [
                    'data_entry_id' => $id,
                    'data_type_field_id' => $fieldIds[$fieldName],
                    'language' => $language === '' ? null : $language,
                    'value' => (string) $languageValue,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('data_entry_values')->insert($rows);
        }
    }

    protected function addSeo(int $entryId, string $language, string $title, string $description, string $slug): void
    {
        DB::table('seo_entries')->insert([
            'data_entry_id' => $entryId,
            'language' => $language,
            'meta_title' => $title,
            'meta_description' => $description,
            'slug' => $slug,
            'canonical_url' => "https://demo.hypercore.test/{$slug}",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<int, array{user: int, rating: int, review: string}>  $ratings
     */
    protected function rate(string $rateableType, int $rateableId, array $ratings): void
    {
        $rows = [];

        foreach ($ratings as $index => $rating) {
            $rows[] = [
                'user_id' => $rating['user'],
                'rateable_type' => $rateableType,
                'rateable_id' => $rateableId,
                'rating' => $rating['rating'],
                'review' => $rating['review'],
                'created_at' => now()->subDays(20 - $index),
                'updated_at' => now()->subDays(20 - $index),
            ];
        }

        DB::table('ratings')->insert($rows);

        $count = count($rows);
        $average = round(array_sum(array_column($ratings, 'rating')) / max($count, 1), 2);

        // The read side serves these counters straight off the parent row, so
        // they have to be written here rather than derived on the fly.
        $table = $rateableType === 'project' ? 'projects' : 'data_entries';

        DB::table($table)->where('id', $rateableId)->update([
            'ratings_count' => $count,
            'ratings_avg' => $average,
        ]);
    }
}
