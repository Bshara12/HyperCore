<?php

namespace Database\Seeders\Support;

use App\Domains\Auth\Service\AuthServiceClient;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resolves seeded records by their stable business keys (slug / email) instead
 * of by auto-increment id.
 *
 * Why this exists: the dependent seeders used to hardcode `project_id => 1` and
 * `data_type_id => 1|2|3`. Those ids only lined up when the whole database was
 * seeded from empty in one exact order — so on a database that already had
 * rows, the search index, click logs and affinity data all attached to the
 * wrong project (or to a project that did not exist), and the personalization
 * and search features silently had nothing coherent to work with.
 *
 * Every lookup is memoised per run, so repeated resolution inside one seeder
 * does not re-query.
 */
class SeedContext
{
    /** @var array<string, int> */
    private array $projects = [];

    /** @var array<string, int> */
    private array $dataTypes = [];

    /** @var array<string, int> */
    private array $fields = [];

    /**
     * Email addresses of the demo owners, mirroring the Auth service's
     * DemoProjectOwnersSeeder. Kept as one list so ownership resolves in a
     * single batched call.
     */
    public const DEMO_OWNER_EMAILS = [
        'clinic-owner@hypercore.test',
        'pulse360-owner@hypercore.test',
        'shop-owner@hypercore.test',
        'analytics-owner@hypercore.test',
    ];

    /** @var array<string, int>|null resolved lazily, then reused */
    private ?array $owners = null;

    /** @var array<int, int> Auth ids already mirrored into the local table */
    private array $mirrored = [];

    // ─── Projects ────────────────────────────────────────────────────────

    public function findProjectId(string $slug): ?int
    {
        if (isset($this->projects[$slug])) {
            return $this->projects[$slug];
        }

        $id = DB::table('projects')
            ->where('slug', $slug)
            ->value('id');

        if ($id === null) {
            return null;
        }

        return $this->projects[$slug] = (int) $id;
    }

    public function projectId(string $slug): int
    {
        $id = $this->findProjectId($slug);

        if ($id === null) {
            throw new RuntimeException(
                "Project [{$slug}] has not been seeded. Its content seeder must run first."
            );
        }

        return $id;
    }

    /**
     * Every seeded project id, oldest first.
     *
     * @return array<int, int>
     */
    public function allProjectIds(): array
    {
        return DB::table('projects')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ─── Data types ──────────────────────────────────────────────────────

    public function findDataTypeId(int $projectId, string $slug): ?int
    {
        $key = "{$projectId}:{$slug}";

        if (isset($this->dataTypes[$key])) {
            return $this->dataTypes[$key];
        }

        // data_types.slug is unique per project, never globally — the project
        // scope is what makes this lookup correct.
        $id = DB::table('data_types')
            ->where('project_id', $projectId)
            ->where('slug', $slug)
            ->value('id');

        if ($id === null) {
            return null;
        }

        return $this->dataTypes[$key] = (int) $id;
    }

    public function dataTypeId(int $projectId, string $slug): int
    {
        $id = $this->findDataTypeId($projectId, $slug);

        if ($id === null) {
            throw new RuntimeException(
                "Data type [{$slug}] is missing from project #{$projectId}."
            );
        }

        return $id;
    }

    /**
     * Data type ids of one project keyed by slug.
     *
     * @return array<string, int>
     */
    public function dataTypesOf(int $projectId): array
    {
        return DB::table('data_types')
            ->where('project_id', $projectId)
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    // ─── Fields ──────────────────────────────────────────────────────────

    public function findFieldId(int $dataTypeId, string $name): ?int
    {
        $key = "{$dataTypeId}:{$name}";

        if (isset($this->fields[$key])) {
            return $this->fields[$key];
        }

        $id = DB::table('data_type_fields')
            ->where('data_type_id', $dataTypeId)
            ->where('name', $name)
            ->value('id');

        if ($id === null) {
            return null;
        }

        return $this->fields[$key] = (int) $id;
    }

    // ─── Entries ─────────────────────────────────────────────────────────

    /**
     * Published entry ids for one data type.
     *
     * @return array<int, int>
     */
    public function entryIds(int $dataTypeId, ?int $limit = null): array
    {
        $query = DB::table('data_entries')
            ->where('data_type_id', $dataTypeId)
            ->whereNull('deleted_at')
            ->where('status', 'published')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Entry ids keyed by slug, for a data type.
     *
     * @return array<string, int>
     */
    public function entryIdsBySlug(int $dataTypeId): array
    {
        return DB::table('data_entries')
            ->where('data_type_id', $dataTypeId)
            ->whereNull('deleted_at')
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * The stored value of one field on one entry — used to build search logs
     * and click logs whose keywords match content that actually exists.
     */
    public function entryValue(int $entryId, int $fieldId, string $language = 'en'): ?string
    {
        $value = DB::table('data_entry_values')
            ->where('data_entry_id', $entryId)
            ->where('data_type_field_id', $fieldId)
            ->where('language', $language)
            ->value('value');

        return $value !== null ? (string) $value : null;
    }

    // ─── Owners (Auth service identities) ────────────────────────────────

    /**
     * Resolve a project owner to its Auth account id.
     *
     * `projects.owner_id` holds an **Auth** user id — CreateProjectDTO takes it
     * straight from auth_user['id']. The seeders used to insert a row into this
     * service's own `users` table and use that id instead, which put a value
     * from an unrelated sequence into the column: none of the seeded owners
     * could sign in, and their ids matched real accounts only by accident.
     *
     * Resolved in one batched call and memoised, so a chain of seeders costs a
     * single request.
     */
    public function ownerId(string $email): int
    {
        if ($this->owners === null) {
            $this->owners = app(AuthServiceClient::class)
                ->getUserIdsByEmails(self::DEMO_OWNER_EMAILS);
        }

        if (! isset($this->owners[$email])) {
            throw new RuntimeException(
                "Auth account [{$email}] does not exist. Run the Auth service's "
                .'DemoProjectOwnersSeeder first: php artisan db:seed --class=DemoProjectOwnersSeeder'
            );
        }

        $authId = $this->owners[$email];

        $this->mirrorAuthUser($authId, $email);

        return $authId;
    }

    /**
     * Ensure a local `users` row exists carrying the Auth account's own id.
     *
     * `projects.owner_id` is a plain integer, but four other columns are real
     * foreign keys into this service's `users` table:
     *
     *   data_entries.created_by, data_entries.updated_by,
     *   data_entry_versions.created_by, project_user.user_id, ratings.user_id
     *
     * All of them are written with the Auth user id at runtime, so the row has
     * to exist under that id or the insert fails on the constraint. Mirroring
     * the identity — same id, no password — satisfies the schema and keeps the
     * seeded data shaped like data the running application would produce.
     */
    private function mirrorAuthUser(int $authId, string $email): void
    {
        if (in_array($authId, $this->mirrored, true)) {
            return;
        }

        $this->mirrored[] = $authId;

        if (DB::table('users')->where('id', $authId)->exists()) {
            return;
        }

        // An address already taken by a legacy local row would collide on the
        // unique email index, so the mirror is suffixed rather than skipped —
        // the id is what the foreign keys care about.
        $emailTaken = DB::table('users')->where('email', $email)->exists();

        DB::table('users')->insert([
            'id' => $authId,
            'name' => 'Auth #'.$authId,
            'email' => $emailTaken ? "auth-{$authId}+{$email}" : $email,
            // Not a credential: authentication happens in the Auth service.
            // This row exists only so the foreign keys above resolve.
            'password' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ─── Users ───────────────────────────────────────────────────────────

    /**
     * Find a user by email, or create them. Returns the id.
     *
     * Seeders are re-run constantly during development, so every user write
     * has to tolerate the row already existing — a bare insert throws on the
     * unique email index the second time around.
     */
    public function userId(string $email, string $name, string $passwordHash): int
    {
        $existing = DB::table('users')->where('email', $email)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => $passwordHash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    public function userIdsLike(string $emailPattern, int $limit = 50): array
    {
        return DB::table('users')
            ->where('email', 'like', $emailPattern)
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
