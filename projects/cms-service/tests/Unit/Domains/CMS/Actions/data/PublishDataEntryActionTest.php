<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\PublishDataEntryAction;
use App\Models\DataEntry;
use App\Support\CurrentProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Event; // <-- استدعاء الـ Facade الخاص بالأحداث

uses(RefreshDatabase::class);

beforeEach(function () {
    Schema::disableForeignKeyConstraints();

    // 🔥 منع كل الأحداث والمستمعين (مثل RabbitMQ) من التشغيل في هذا الملف
    Event::fake();

    // إنشاء مستخدمين بمعرفات 1 و 99 لتغطية كلي الاختبارين
    \App\Models\User::factory()->create(['id' => 1]); 
    \App\Models\User::factory()->create(['id' => 99]); 

    // إنشاء المشروع الحقيقي
    $project = \App\Models\Project::create([
        'id' => 1,
        'slug' => 'test-project',
        'public_id' => 'proj-123',
        'name' => 'Test Project',
        'owner_id' => 1
    ]);

    // ربط كائن المشروع الحقيقي داخل الحاوية 
    app()->instance('currentProject', $project);

    // إنشاء النوع
    \App\Models\DataType::create([
        'id' => 1,
        'name' => 'test-type',
        'slug' => 'test-type-slug',
        'project_id' => 1
    ]);
});

test('it publishes a data entry correctly', function () {
    $projectId = 1;
    $userId = 99;
    $slug = 'test-entry';

    // الآن سيتم إنشاء السجل بنجاح لأن المشروع و الـ DataType موجودان
    $entry = DataEntry::create([
        'project_id'   => $projectId,
        'data_type_id' => 1,
        'slug'         => $slug,
        'status'       => 'draft',
    ]);

    $action = new PublishDataEntryAction();
    $result = $action->execute($slug, $userId);

    expect($result->status)->toBe('published')
        ->and($result->updated_by)->toBe($userId);
});

test('it does not update published_at if it is already in the past', function () {
    $projectId = 1;
    $pastDate = now()->subDays(5);

    // إنشاء السجل مع وجود الأب (Project & DataType)
    $entry = DataEntry::create([
        'project_id'   => $projectId,
        'data_type_id' => 1,
        'slug'         => 'old-entry',
        'status'       => 'draft',
        'published_at' => $pastDate,
    ]);

    $action = new PublishDataEntryAction();
    $result = $action->execute('old-entry', 1);

    expect($result->published_at->format('Y-m-d H:i:s'))
        ->toBe($pastDate->format('Y-m-d H:i:s'));
});