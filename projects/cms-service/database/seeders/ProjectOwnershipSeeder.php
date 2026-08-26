<?php

namespace Database\Seeders;

use App\Domains\CMS\Support\CacheKeys;
use Database\Seeders\Support\SeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Points every demo project at the Auth account that owns it.
 *
 * The content seeders set `owner_id` correctly when they *create* a project,
 * but they are idempotent: on a database seeded before ownership was resolved
 * from the Auth service, they find the project already present and leave it
 * alone — including its stale owner. This is the repair pass.
 *
 * It also backfills `project_user`, so the owner is a member of their own
 * project and the dashboard's project list resolves for them either way.
 *
 * Runs before the content seeders: once ownership is correct, everything
 * downstream (project scoping, the owner's project list) lines up.
 */
class ProjectOwnershipSeeder extends Seeder
{
    /**
     * project slug => owning Auth account.
     *
     * The single place that records who owns which demo tenant. Slugs absent
     * from the database are skipped, so this stays valid on a partial seed.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        return [
            'nour-medical-clinic' => 'clinic-owner@hypercore.test',
            'pulse360' => 'pulse360-owner@hypercore.test',
            'ecommerce-demo' => 'shop-owner@hypercore.test',

            // CmsAnalyticsSeeder's three fixtures, plus the CmsEventSeeder one.
            'e-commerce-store' => 'analytics-owner@hypercore.test',
            'service-booking-platform' => 'analytics-owner@hypercore.test',
            'online-shop' => 'analytics-owner@hypercore.test',
            'test-pro' => 'analytics-owner@hypercore.test',
        ];
    }

    public function run(): void
    {
        $ctx = new SeedContext;
        $rows = [];

        foreach (self::map() as $projectSlug => $ownerEmail) {

            $projectId = $ctx->findProjectId($projectSlug);

            if ($projectId === null) {
                continue;
            }

            // Resolves the Auth id and mirrors it into the local users table,
            // which the project_user / created_by foreign keys require.
            $ownerId = $ctx->ownerId($ownerEmail);

            $currentOwnerId = (int) DB::table('projects')
                ->where('id', $projectId)
                ->value('owner_id');

            if ($currentOwnerId !== $ownerId) {
                DB::table('projects')
                    ->where('id', $projectId)
                    ->update([
                        'owner_id' => $ownerId,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('project_user')->insertOrIgnore([
                'project_id' => $projectId,
                'user_id' => $ownerId,
            ]);

            $rows[] = [
                $projectSlug,
                $ownerEmail,
                $ownerId,
                $currentOwnerId === $ownerId ? 'already correct' : "was {$currentOwnerId}",
            ];
        }

        if ($rows === []) {
            $this->command?->warn('ProjectOwnershipSeeder: no demo projects present yet.');

            return;
        }

        // The project list is cached per user, and ownership just changed.
        CacheKeys::bumpProjectListVersion();

        $this->command?->info('Project ownership resolved against the Auth service.');
        $this->command?->table(
            ['Project', 'Owner', 'Auth id', 'Previous'],
            $rows
        );
    }
}
