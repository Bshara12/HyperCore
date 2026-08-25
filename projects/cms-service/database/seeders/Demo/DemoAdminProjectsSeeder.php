<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The project group belonging to user1@example.com (admin).
 *
 * Three small projects, one per module combination, so each module can be
 * exercised on its own without wading through the comprehensive project:
 *
 *   Aurora Blog     [cms]
 *   Aurora Store    [cms, ecommerce]
 *   Aurora Clinic   [cms, booking]
 *
 * Run: php artisan db:seed --class="Database\Seeders\Demo\DemoAdminProjectsSeeder"
 *
 * Safe to re-run — each project is purged before it is rebuilt.
 */
class DemoAdminProjectsSeeder extends Seeder
{
    use DemoContentBuilder;

    public function run(): void
    {
        DB::transaction(function () {
            $this->mirrorDemoUsers();

            $this->seedBlog();
            $this->seedStore();
            $this->seedClinic();
        });

        $this->flushReadCaches();

        $this->command?->info('Admin (user1@example.com) projects seeded:');
        $this->command?->table(
            ['id', 'name', 'modules', 'X-Project-Id'],
            [
                [DemoIds::ADMIN_PROJECT_BLOG, 'Aurora Blog', 'cms', self::demoPublicId(DemoIds::ADMIN_PROJECT_BLOG)],
                [DemoIds::ADMIN_PROJECT_STORE, 'Aurora Store', 'cms, ecommerce', self::demoPublicId(DemoIds::ADMIN_PROJECT_STORE)],
                [DemoIds::ADMIN_PROJECT_CLINIC, 'Aurora Clinic', 'cms, booking', self::demoPublicId(DemoIds::ADMIN_PROJECT_CLINIC)],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────
    private function seedBlog(): void
    {
        $projectId = DemoIds::ADMIN_PROJECT_BLOG;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::ADMIN_USER_ID,
            name: 'Aurora Blog',
            slug: 'aurora-blog',
            description: 'A content-only project: posts, authors and nothing else.',
            languages: ['en', 'ar'],
            modules: ['cms'],
        );

        $fields = $this->createDataType(
            id: 9111,
            projectId: $projectId,
            name: 'Post',
            slug: 'posts',
            description: 'A published article.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'body', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string']],
                ['name' => 'reading_minutes', 'type' => 'number', 'rules' => ['numeric', 'min:1']],
            ],
        );

        $posts = [
            [9141, 'welcome-to-aurora', 'published', 'Welcome to Aurora', 'مرحباً بك في أورورا', 4],
            [9142, 'writing-good-headlines', 'published', 'Writing Good Headlines', 'كتابة عناوين جيدة', 6],
            [9143, 'draft-editorial-guide', 'draft', 'Editorial Guide (draft)', 'دليل التحرير (مسودة)', 9],
        ];

        foreach ($posts as $index => [$id, $slug, $status, $titleEn, $titleAr, $minutes]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: 9111,
                slug: $slug,
                status: $status,
                authorId: DemoIds::ADMIN_USER_ID,
                fieldIds: $fields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'body' => [
                        'en' => "Full text of {$titleEn}. ".str_repeat('Sample editorial content. ', 6),
                        'ar' => "النص الكامل لـ {$titleAr}. ".str_repeat('محتوى تحريري تجريبي. ', 6),
                    ],
                    'reading_minutes' => $minutes,
                ],
                createdAt: now()->subDays(30 - ($index * 7)),
            );

            if ($status === 'published') {
                $this->addSeo($id, 'en', $titleEn, "Read {$titleEn} on Aurora Blog.", $slug);
            }
        }

        $this->rate('data', 9141, [
            ['user' => DemoIds::CUSTOMER_ONE_ID, 'rating' => 5, 'review' => 'Great opening post.'],
            ['user' => DemoIds::CUSTOMER_TWO_ID, 'rating' => 4, 'review' => 'Clear and short.'],
        ]);
    }

    // ─────────────────────────────────────────────────────────
    private function seedStore(): void
    {
        $projectId = DemoIds::ADMIN_PROJECT_STORE;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::ADMIN_USER_ID,
            name: 'Aurora Store',
            slug: 'aurora-store',
            description: 'A small catalogue used to exercise the ecommerce module on its own.',
            languages: ['en'],
            modules: ['cms', 'ecommerce'],
        );

        $fields = $this->createDataType(
            id: 9112,
            projectId: $projectId,
            name: 'Product',
            slug: 'products',
            description: 'Something you can buy.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'price', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'count', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
            ],
        );

        $products = [
            [9151, 'aurora-mug', 'Aurora Mug', 12.50, 80],
            [9152, 'aurora-notebook', 'Aurora Notebook', 8.00, 150],
            [9153, 'aurora-tote', 'Aurora Tote Bag', 24.00, 40],
        ];

        foreach ($products as $index => [$id, $slug, $title, $price, $count]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: 9112,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::ADMIN_USER_ID,
                fieldIds: $fields,
                values: ['title' => $title, 'price' => $price, 'count' => $count],
                createdAt: now()->subDays(25 - ($index * 5)),
            );
        }
    }

    // ─────────────────────────────────────────────────────────
    private function seedClinic(): void
    {
        $projectId = DemoIds::ADMIN_PROJECT_CLINIC;

        $this->purgeProject($projectId);

        $this->createProject(
            id: $projectId,
            ownerId: DemoIds::ADMIN_USER_ID,
            name: 'Aurora Clinic',
            slug: 'aurora-clinic',
            description: 'Appointments only — used to exercise the booking module on its own.',
            languages: ['en', 'ar'],
            modules: ['cms', 'booking'],
        );

        $fields = $this->createDataType(
            id: 9113,
            projectId: $projectId,
            name: 'Treatment',
            slug: 'treatments',
            description: 'A bookable treatment.',
            fields: [
                ['name' => 'title', 'type' => 'text', 'required' => true, 'translatable' => true, 'rules' => ['string', 'max:255']],
                ['name' => 'price', 'type' => 'number', 'required' => true, 'rules' => ['numeric', 'min:0']],
                ['name' => 'duration_minutes', 'type' => 'number', 'rules' => ['numeric', 'min:5']],
            ],
        );

        $treatments = [
            [9161, 'general-checkup', 'General Checkup', 'فحص عام', 45.00, 30],
            [9162, 'dental-cleaning', 'Dental Cleaning', 'تنظيف الأسنان', 70.00, 45],
        ];

        foreach ($treatments as $index => [$id, $slug, $titleEn, $titleAr, $price, $duration]) {
            $this->createEntry(
                id: $id,
                projectId: $projectId,
                dataTypeId: 9113,
                slug: $slug,
                status: 'published',
                authorId: DemoIds::ADMIN_USER_ID,
                fieldIds: $fields,
                values: [
                    'title' => ['en' => $titleEn, 'ar' => $titleAr],
                    'price' => $price,
                    'duration_minutes' => $duration,
                ],
                createdAt: now()->subDays(20 - ($index * 5)),
            );
        }
    }
}
