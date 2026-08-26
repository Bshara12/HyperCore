<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        $ctx = new SeedContext;

        /*
        |----------------------------------------------------------------------
        | Attach to a real project instead of the null tenant this used to use.
        |
        | With project_id = null the plans belonged to no project at all, so the
        | dashboard's Subscriptions section — which scopes every query by the
        | current project — listed nothing, and the feature/access rules could
        | never match an event dispatched from a real project.
        |
        | The clinic tenant is the target because it has articles worth gating
        | and does not already seed its own plans (Pulse360 does).
        |----------------------------------------------------------------------
        */
        $projectId = $ctx->findProjectId(ClinicDataSeeder::PROJECT_SLUG);

        if ($projectId === null) {
            $this->command?->warn(
                'SubscriptionDemoSeeder: clinic project missing — run ClinicDataSeeder first.'
            );

            return;
        }

        // subscription_plans is unique on (project_id, slug).
        $alreadySeeded = DB::table('subscription_plans')
            ->where('project_id', $projectId)
            ->where('slug', 'free-plan')
            ->exists();

        if ($alreadySeeded) {
            $this->command?->warn('SubscriptionDemoSeeder: already seeded — skipping.');

            return;
        }

        // Gate a real entry: content_id used to be the literal 1, which the
        // seeder's own comment flagged as unusable — AuthorizeContentAccessAction
        // resolves the content type from the entry, so a missing entry throws
        // ContentEntryNotFoundException.
        $postsTypeId = $ctx->dataTypeId($projectId, 'health-posts');
        $gatedEntryIds = $ctx->entryIds($postsTypeId, 2);

        DB::transaction(function () use ($projectId, $gatedEntryIds) {

            /*
            |--------------------------------------------------------------------------
            | 1️⃣ Plans
            |--------------------------------------------------------------------------
            */

            $freePlanId = DB::table('subscription_plans')->insertGetId([
                'project_id' => $projectId,
                'name' => 'Free Plan',
                'slug' => 'free-plan',
                'description' => 'خطة مجانية بحد أقصى محدود من المقالات.',
                'price' => 0,
                'currency' => 'USD',
                'duration_days' => 30,
                'is_active' => true,
                'metadata' => json_encode(['tier' => 'free']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $premiumPlanId = DB::table('subscription_plans')->insertGetId([
                'project_id' => $projectId,
                'name' => 'Premium Plan',
                'slug' => 'premium-plan',
                'description' => 'خطة مدفوعة بميزات موسّعة ووصول للمحتوى المميز.',
                'price' => 9.99,
                'currency' => 'USD',
                'duration_days' => 30,
                'is_active' => true,
                'metadata' => json_encode(['tier' => 'premium']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $legacyPlanId = DB::table('subscription_plans')->insertGetId([
                'project_id' => $projectId,
                'name' => 'Legacy Yearly Plan (Discontinued)',
                'slug' => 'legacy-yearly-plan',
                'description' => 'خطة قديمة موقوفة — لاختبار محاولة الاشتراك بخطة غير فعّالة.',
                'price' => 79.00,
                'currency' => 'USD',
                'duration_days' => 365,
                'is_active' => false,
                'metadata' => json_encode(['tier' => 'legacy']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 2️⃣ Plan Features
            |--------------------------------------------------------------------------
            */

            DB::table('subscription_features')->insert([
                [
                    'plan_id' => $freePlanId,
                    'feature_key' => 'articles_per_month',
                    'feature_type' => 'number',
                    'feature_value' => json_encode(5),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plan_id' => $freePlanId,
                    'feature_key' => 'premium_articles',
                    'feature_type' => 'boolean',
                    'feature_value' => json_encode(false),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],

                [
                    'plan_id' => $premiumPlanId,
                    'feature_key' => 'articles_per_month',
                    'feature_type' => 'number',
                    'feature_value' => json_encode(100),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plan_id' => $premiumPlanId,
                    'feature_key' => 'premium_articles',
                    'feature_type' => 'boolean',
                    'feature_value' => json_encode(true),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'plan_id' => $premiumPlanId,
                    'feature_key' => 'ai_requests_daily',
                    'feature_type' => 'number',
                    'feature_value' => json_encode(20),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],

                [
                    'plan_id' => $legacyPlanId,
                    'feature_key' => 'articles_per_month',
                    'feature_type' => 'number',
                    'feature_value' => json_encode(1000),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 3️⃣ Subscriptions
            |--------------------------------------------------------------------------
            | userId 101 → active (Premium)
            | userId 102 → expired (Free)
            | userId 103 → بدون اشتراك إطلاقًا (لاختبار SUBSCRIPTION_REQUIRED)
            | userId 104 → cancelled (Premium)
            */

            $activeSubscriptionId = DB::table('subscriptions')->insertGetId([
                'user_id' => 101,
                'project_id' => $projectId,
                'plan_id' => $premiumPlanId,
                'payment_id' => null,
                'status' => 'active',
                'starts_at' => now()->subDays(5),
                'ends_at' => now()->addDays(25),
                'current_period_start' => now()->subDays(5),
                'current_period_end' => now()->addDays(25),
                'cancelled_at' => null,
                'auto_renew' => true,
                'metadata' => json_encode(['source' => 'seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('subscriptions')->insert([
                'user_id' => 102,
                'project_id' => $projectId,
                'plan_id' => $freePlanId,
                'payment_id' => null,
                'status' => 'expired',
                'starts_at' => now()->subDays(60),
                'ends_at' => now()->subDays(30),
                'current_period_start' => now()->subDays(60),
                'current_period_end' => now()->subDays(30),
                'cancelled_at' => null,
                'auto_renew' => false,
                'metadata' => json_encode(['source' => 'seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('subscriptions')->insert([
                'user_id' => 104,
                'project_id' => $projectId,
                'plan_id' => $premiumPlanId,
                'payment_id' => null,
                'status' => 'cancelled',
                'starts_at' => now()->subDays(20),
                'ends_at' => now()->addDays(10),
                'current_period_start' => now()->subDays(20),
                'current_period_end' => now()->addDays(10),
                'cancelled_at' => now()->subDays(2),
                'auto_renew' => false,
                'metadata' => json_encode([
                    'source' => 'seeder',
                    'cancel_reason' => 'انتهى الاحتياج للخدمة',
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 4️⃣ Usage — استهلاك جزئي على اشتراك المستخدم 101 الفعّال
            |--------------------------------------------------------------------------
            */

            DB::table('subscription_usages')->insert([
                [
                    'subscription_id' => $activeSubscriptionId,
                    'feature_key' => 'articles_per_month',
                    'used_value' => 3, // من أصل 100 المسموحة بخطة Premium
                    'reset_at' => now()->startOfMonth()->addMonth(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'subscription_id' => $activeSubscriptionId,
                    'feature_key' => 'ai_requests_daily',
                    'used_value' => 20, // === الحد بالضبط — لاختبار UsageLimitExceededException
                    'reset_at' => now()->startOfDay()->addDay(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 5️⃣ Feature Rules — تربط event_key بـ feature_key
            |--------------------------------------------------------------------------
            */

            DB::table('subscription_feature_rules')->insert([
                [
                    'project_id' => $projectId,
                    'event_key' => 'health-posts.create',
                    'feature_key' => 'articles_per_month',
                    'action' => 'both',
                    'reset_type' => 'monthly',
                    'is_active' => true,
                    'metadata' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'project_id' => $projectId,
                    'event_key' => 'ai.generate',
                    'feature_key' => 'ai_requests_daily',
                    'action' => 'both',
                    'reset_type' => 'daily',
                    'is_active' => true,
                    'metadata' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            /*
            |--------------------------------------------------------------------------
            | 6️⃣ Access Rules — تربط event_key بشرط اشتراك/فيتشر مباشر
            |--------------------------------------------------------------------------
            */

            DB::table('subscription_access_rules')->insert([
                'project_id' => $projectId,
                'event_key' => 'health-posts.view.premium',
                'requires_subscription' => true,
                'required_feature' => 'premium_articles',
                'is_active' => true,
                'metadata' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | 7️⃣ Content Access — حماية محتوى معيّن
            |--------------------------------------------------------------------------
            | ⚠️ content_id هون افتراضي (id = 1) فقط. لو بدك تختبر AuthorizeContentAccessAction
            | فعليًا عن طريق الـ API (مو مباشرة بالداتابيز)، لازم يكون فيه data_entry
            | حقيقي بنفس الـ content_id، وإلا رح ترمي ContentEntryNotFoundException.
            */

            if ($gatedEntryIds === []) {
                echo "
No published entries to gate — skipped content access.
";

                return;
            }

            $contentAccessMetadataId = DB::table('content_access_metadata')->insertGetId([
                'project_id' => $projectId,
                // Resolved from the project's own schema and a real entry, so
                // the gate is exercisable through the API.
                'content_type' => 'health-posts',
                'content_id' => $gatedEntryIds[0],
                'requires_subscription' => true,
                'metadata' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('content_access_features')->insert([
                [
                    'content_access_metadata_id' => $contentAccessMetadataId,
                    'feature_key' => 'premium_articles',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'content_access_metadata_id' => $contentAccessMetadataId,
                    'feature_key' => 'ai_requests_daily',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            echo "\nSubscription Demo Seed Completed Successfully:\n";
            echo "--------------------------------\n";
            echo "Free Plan ID: $freePlanId\n";
            echo "Premium Plan ID: $premiumPlanId\n";
            echo "Legacy (inactive) Plan ID: $legacyPlanId\n";
            echo "Active Subscription ID (user_id=101): $activeSubscriptionId\n";
            echo "Content Access Metadata ID: $contentAccessMetadataId\n";
            echo "--------------------------------\n";
        });
    }
}