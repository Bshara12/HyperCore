<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\EntryVersionReadRepository;
use App\Models\Project;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EntryVersionReadRepository();

    // إنشاء مشروعين ونوعين من البيانات لتأكيد عزل البيانات بين المشاريع
    $this->projectA = Project::factory()->create();
    $this->projectB = Project::factory()->create();

    $this->dataTypeA = DataType::factory()->create(['project_id' => $this->projectA->id]);
    $this->dataTypeB = DataType::factory()->create(['project_id' => $this->projectB->id]);
});


## 1. اختبار الجلب الأساسي وحالة حقل الـ Snapshot

test('it lists versions for a given entry slug and includes snapshot when requested', function () {
    // 1. إنشاء Entry للمشروع A
    $entryId = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'article-1',
        'status' => 'published',
    ]);

    // 2. إدخال نسختين للـ Entry
    DB::table('data_entry_versions')->insert([
        [
            'data_entry_id' => $entryId,
            'version_number' => 1,
            'created_by' => 1,
            'snapshot' => json_encode(['title' => 'Version 1']),
            'created_at' => now()->subDays(2),
        ],
        [
            'data_entry_id' => $entryId,
            'version_number' => 2,
            'created_by' => 1,
            'snapshot' => json_encode(['title' => 'Version 2']),
            'created_at' => now()->subDay(),
        ]
    ]);

    // الفحص الأول: استدعاء الدالة مع تفعيل $withSnapshot = true
    $resultWithSnapshot = $this->repository->listForEntrySlug(
        $this->projectA->id,
        'article-1',
        1,
        10,
        true
    );

    expect($resultWithSnapshot['total'])->toBe(2);
    expect($resultWithSnapshot['items'])->toHaveCount(2);
    expect($resultWithSnapshot['items'][0])->toHaveKey('snapshot'); // يجب أن يحتوي على السناب شوت
    expect($resultWithSnapshot['items'][0]['version_number'])->toBe(2); // الترتيب التنازلي بناءً على رقم النسخة

    // الفحص الثاني: استدعاء الدالة مع تعطيل $withSnapshot = false
    $resultWithoutSnapshot = $this->repository->listForEntrySlug(
        $this->projectA->id,
        'article-1',
        1,
        10,
        false
    );

    expect($resultWithoutSnapshot['items'][0])->not->toHaveKey('snapshot'); // يجب ألا يظهر حقل السناب شوت لحفظ الذاكرة
});


## 2. اختبار الصفحات (Pagination) والترتيب التنازلي

test('it paginates results and orders them by version number descending', function () {
    $entryId = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'paginated-entry',
        'status' => 'published',
    ]);

    // إدخال 3 نسخ
    foreach ([1, 2, 3] as $vNum) {
        DB::table('data_entry_versions')->insert([
            'data_entry_id' => $entryId,
            'version_number' => $vNum,
            'created_by' => 1,
            'snapshot' => json_encode(['v' => $vNum]),
            'created_at' => now(),
        ]);
    }

    // جلب الصفحة الأولى بمعدل عنصر واحد لكل صفحة
    $page1 = $this->repository->listForEntrySlug($this->projectA->id, 'paginated-entry', 1, 1, false);
    // جلب الصفحة الثانية بمعدل عنصر واحد لكل صفحة
    $page2 = $this->repository->listForEntrySlug($this->projectA->id, 'paginated-entry', 2, 1, false);

    expect($page1['total'])->toBe(3);
    expect($page1['items'])->toHaveCount(1);
    expect($page1['items'][0]['version_number'])->toBe(3); // الصفحة الأولى يجب أن تعود بأحدث نسخة (3)

    expect($page2['items'])->toHaveCount(1);
    expect($page2['items'][0]['version_number'])->toBe(2); // الصفحة الثانية تعود بالنسخة التي تليها (2)
});


## 3. اختبار قيود حماية المدخلات المتطرفة (Sanitization Boundaries)

test('it enforces minimum and maximum boundaries for page and per page parameters', function () {
    $entryId = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'bounded-entry',
        'status' => 'published',
    ]);

    // إرسال قيم صفرية وسالبة للتأكد من رفعها إلى الحد الأدنى (1)
    $badMinResult = $this->repository->listForEntrySlug($this->projectA->id, 'bounded-entry', 0, 0, false);
    expect($badMinResult['page'])->toBe(1);
    expect($badMinResult['per_page'])->toBe(1);

    // إرسال قيمة perPage ضخمة جداً للتأكد من كبحها عند الحد الأقصى (200)
    $badMaxResult = $this->repository->listForEntrySlug($this->projectA->id, 'bounded-entry', 1, 500, false);
    expect($badMaxResult['per_page'])->toBe(200);
});


## 4. اختبار عزل البيانات الصارم بين المشاريع والـ Slugs

test('it isolates versions by project id and entry slug', function () {
    // 1. سجل في المشروع A مع Slug معين
    $entryA = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'shared-slug',
        'status' => 'published',
    ]);
    DB::table('data_entry_versions')->insert([
        'data_entry_id' => $entryA, 'version_number' => 1, 'created_by' => 1, 'snapshot' => '{}'
    ]);

    // 2. سجل في المشروع B بنفس الـ Slug (لتأكيد الفصل الفعلي حسب المشروع)
    $entryB = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectB->id,
        'data_type_id' => $this->dataTypeB->id,
        'slug' => 'shared-slug',
        'status' => 'published',
    ]);
    DB::table('data_entry_versions')->insert([
        'data_entry_id' => $entryB, 'version_number' => 5, 'created_by' => 1, 'snapshot' => '{}'
    ]);

    // التنفيذ بالنسبة للمشروع A فقط
    $result = $this->repository->listForEntrySlug($this->projectA->id, 'shared-slug', 1, 10, false);

    // التأكيد: يجب أن يعود بنسخة المشروع A فقط ولا يرى بيانات المشروع B
    expect($result['total'])->toBe(1);
    expect($result['items'][0]['data_entry_id'])->toBe($entryA);
    expect($result['items'][0]['version_number'])->toBe(1);
});