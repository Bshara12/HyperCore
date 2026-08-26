<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * The CMS seed chain.
 *
 * Order matters and is not arbitrary — the later stages read what the earlier
 * ones wrote, by slug rather than by id (see Support\SeedContext):
 *
 *   1. Content   creates the projects, data types, fields and entries.
 *   2. Search    derives search_indices from those published entries.
 *   3. Behaviour derives search/click history from the index.
 *   4. Commerce  attaches subscription plans, gating and analytics.
 *
 * Every seeder here is idempotent: re-running `db:seed` finds its own records
 * by slug and leaves them alone rather than duplicating or crashing on a
 * unique index.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // ─── 1. Content: one realistic tenant per domain ─────────────────
        $this->call([
            // Healthcare clinic — replaces the old CoreDataSeeder, whose
            // content was placeholder text at project slug 'slug'.
            ClinicDataSeeder::class,

            // Media/publishing: categories, articles, events, plans.
            Pulse360CmsSeeder::class,

            // Retail: bilingual catalogue, categories, ratings, collections.
            EcommerceDataSeeder::class,

            // Events content on its own get-or-create project.
            CmsEventSeeder::class,

            // Analytics fixtures — its own three projects, with data types,
            // entries, collections and ratings. It belongs in this stage, not
            // after the index: it creates content, and anything seeded after
            // SearchIndexSeeder would never be indexed.
            CmsAnalyticsSeeder::class,
        ]);

        // ─── 1b. Ownership ───────────────────────────────────────────────
        // Re-points every demo project at its Auth account. Runs after the
        // content seeders because they skip projects that already exist —
        // including their stale owner_id — so this is the repair pass.
        $this->call(ProjectOwnershipSeeder::class);

        // ─── 2. Search index, built from the content above ───────────────
        // Runs the same ReindexSearchAction as `php artisan search:reindex`,
        // so every project is indexed with its own real project_id and
        // data_type_id instead of hardcoded ones.
        $this->call(SearchIndexSeeder::class);

        // ─── 3. Behaviour signals, built from the index ───────────────────
        // Search history, click logs and popular-search rollups per project.
        // Must follow the index: personalization joins clicks to
        // search_indices, so a click on an unindexed entry is invisible.
        $this->call(SearchBehaviorSeeder::class);

        // ─── 4. Monetisation ─────────────────────────────────────────────
        // Plans, feature rules, usage and content gating. Reads the clinic
        // project's real entries, so it only needs stage 1 — but it is kept
        // last because it gates content that the index should already cover.
        $this->call(SubscriptionDemoSeeder::class);
    }
}
