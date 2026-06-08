<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\EntryTypeReadRepository;
use App\Models\Project;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EntryTypeReadRepository();

    // إنشاء مشروع ونوع بيانات أساسي للاستخدام في الاختبارات
    $this->project = Project::factory()->create();
    $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);
});


## 1. اختبار دالة getDataTypeId

test('it returns data type id for a given entry id', function () {
    $entryId = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'test-entry',
        'status' => 'published',
    ]);

    $result = $this->repository->getDataTypeId($entryId);

    expect($result)->toBe($this->dataType->id);
});

test('it returns null if entry does not exist', function () {
    $result = $this->repository->getDataTypeId(9999);
    
    expect($result)->toBeNull();
});


## 2. اختبار دالة queryPublishedByType (شروط النشر والجدولة والتعديل)

test('it queries only published entries handling schedules and soft deletes', function () {
    // 1. سجل صالح: موعد الجدولة فارغ (ينشر فوراً) وغير محذوف
    $validNullSchedule = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'valid-now',
        'status' => 'published',
        'scheduled_at' => null,
        'deleted_at' => null,
    ]);

    // 2. سجل صالح: موعد الجدولة في الماضي (حان وقت نشره)
    $validPastSchedule = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'valid-past',
        'status' => 'published',
        'scheduled_at' => now()->subDay(), // أمس
        'deleted_at' => null,
    ]);

    // 3. سجل غير صالح: مجدول في المستقبل (لم يحن وقته بعد)
    $invalidFutureSchedule = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'invalid-future',
        'status' => 'published',
        'scheduled_at' => now()->addDay(), // غداً
        'deleted_at' => null,
    ]);

    // 4. سجل غير صالح: محذوف (soft deleted)
    $invalidDeleted = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'invalid-deleted',
        'status' => 'published',
        'scheduled_at' => null,
        'deleted_at' => now(),
    ]);

    // التنفيذ
    $results = $this->repository->queryPublishedByType($this->dataType->id)->pluck('id');

    // التأكيد
    expect($results)
        ->toContain($validNullSchedule, $validPastSchedule)
        ->not->toContain($invalidFutureSchedule, $invalidDeleted);
});


## 3. اختبار دالة filterPublishedByType (الفلترة المتقدمة والبحث)

test('it filters published entries by date range', function () {
    $entryOld = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'old-entry',
        'status' => 'published',
        'published_at' => '2026-01-01',
    ]);

    $entryNew = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'new-entry',
        'status' => 'published',
        'published_at' => '2026-05-01',
    ]);

    // فحص الفلترة من تاريخ محدد (dateFrom)
    $resFrom = $this->repository->filterPublishedByType($this->dataType->id, '2026-04-01', null, null, null)->pluck('id');
    expect($resFrom)->toContain($entryNew)->not->toContain($entryOld);

    // فحص الفلترة حتى تاريخ محدد (dateTo)
    $resTo = $this->repository->filterPublishedByType($this->dataType->id, null, '2026-02-01', null, null)->pluck('id');
    expect($resTo)->toContain($entryOld)->not->toContain($entryNew);
});

test('it filters entries by searching within dynamic values table', function () {
    // إنشاء حقل (Field) لتفادي أي قيود مفاتيح أجنبية لجدول قيم الحقول
    $fieldId1 = DB::table('data_type_fields')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'name' => 'title',
        'type' => 'text',
    ]);
    
    $fieldId2 = DB::table('data_type_fields')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'name' => 'description',
        'type' => 'text',
    ]);

    // إنشاء المقالات
    $entryLaravel = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'laravel-news',
        'status' => 'published',
    ]);

    $entrySymfony = DB::table('data_entries')->insertGetId([
        'data_type_id' => $this->dataType->id,
        'project_id' => $this->project->id,
        'slug' => 'symfony-news',
        'status' => 'published',
    ]);

    // ربط القيم في جدول data_entry_values
    DB::table('data_entry_values')->insert([
        ['data_entry_id' => $entryLaravel, 'data_type_field_id' => $fieldId1, 'value' => 'Mastering Laravel Framework'],
        ['data_entry_id' => $entrySymfony, 'data_type_field_id' => $fieldId2, 'value' => 'Introduction to Symfony Components'],
    ]);

    // 1. فحص البحث العام بقيمة نصية (Search Value) فقط
    $searchResult = $this->repository->filterPublishedByType($this->dataType->id, null, null, null, 'Laravel')->pluck('id');
    expect($searchResult)->toContain($entryLaravel)->not->toContain($entrySymfony);

    // 2. فحص البحث المخصص بقيمة نصية داخل حقل محدد (Search Value + Field ID)
    $fieldSearchResult = $this->repository->filterPublishedByType($this->dataType->id, null, null, $fieldId1, 'Laravel')->pluck('id');
    expect($fieldSearchResult)->toContain($entryLaravel);

    // 3. فحص البحث عن كلمة صحيحة ولكن في الحقل الخطأ (يجب ألا يرجع أي نتيجة)
    $wrongFieldResult = $this->repository->filterPublishedByType($this->dataType->id, null, null, $fieldId2, 'Laravel')->pluck('id');
    expect($wrongFieldResult)->toBeEmpty();
});