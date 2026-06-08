<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Models\Project;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EntryRelationRepository();

    // إنشاء مشروعين لتأكيد فصل البيانات بين المشاريع
    $this->projectA = Project::factory()->create();
    $this->projectB = Project::factory()->create();

    $this->dataTypeA = DataType::factory()->create(['project_id' => $this->projectA->id]);
    $this->dataTypeB = DataType::factory()->create(['project_id' => $this->projectB->id]);

    // 🔥 التعديل هنا: إضافة حقل relation_type لتلبية قيد الـ NOT NULL الاخير
    $this->relationIdA = DB::table('data_type_relations')->insertGetId([
        'data_type_id' => $this->dataTypeA->id,
        'related_data_type_id' => $this->dataTypeA->id,
        'relation_type' => 'manyToMany', 
    ]);

    $this->relationIdB = DB::table('data_type_relations')->insertGetId([
        'data_type_id' => $this->dataTypeB->id,
        'related_data_type_id' => $this->dataTypeB->id,
        'relation_type' => 'manyToMany',
    ]);
});


## 1. اختبار جلب المعرفات الأب (getParentIds)

test('it returns parent ids for a given entry id', function () {
    // 1. إنشاء السجل الابن
    $entryId = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'child-entry',
        'status' => 'published',
    ]);

    // 2. إنشاء سجلات الآباء حقيقية لتفادي قيد المفتاح الأجنبي
    $parentId1 = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'parent-1',
        'status' => 'published',
    ]);

    $parentId2 = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'parent-2',
        'status' => 'published',
    ]);

    // 3. ربط العلاقات بهويات حقيقية موجودة في قاعدة البيانات
    DB::table('data_entry_relations')->insert([
        ['data_entry_id' => $entryId, 'related_entry_id' => $parentId1, 'data_type_relation_id' => $this->relationIdA],
        ['data_entry_id' => $entryId, 'related_entry_id' => $parentId2, 'data_type_relation_id' => $this->relationIdA],
    ]);

    $result = $this->repository->getParentIds($entryId);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toContain($parentId1, $parentId2);
});

test('it returns an empty array if entry has no parents', function () {
    $result = $this->repository->getParentIds(9999);
    
    expect($result)->toBeEmpty();
});


## 2. اختبار جلب المعرفات الأبناء (getChildIds)

test('it returns child ids for a given related entry id', function () {
    // 1. إنشاء سجل الأب حقيقي
    $relatedEntryId = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'parent-entry',
        'status' => 'published',
    ]);

    // 2. إنشاء سجلات الأبناء حقيقية
    $childId1 = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'child-1',
        'status' => 'published',
    ]);

    $childId2 = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'child-2',
        'status' => 'published',
    ]);

    // 3. ربط العلاقات بالمعرفات الحقيقية
    DB::table('data_entry_relations')->insert([
        ['data_entry_id' => $childId1, 'related_entry_id' => $relatedEntryId, 'data_type_relation_id' => $this->relationIdA],
        ['data_entry_id' => $childId2, 'related_entry_id' => $relatedEntryId, 'data_type_relation_id' => $this->relationIdA],
    ]);

    $result = $this->repository->getChildIds($relatedEntryId);

    expect($result)->toBeArray()
        ->toHaveCount(2)
        ->toContain($childId1, $childId2);
});

test('it returns an empty array if entry has no children', function () {
    $result = $this->repository->getChildIds(9999);
    
    expect($result)->toBeEmpty();
});


## 3. اختبار جلب جميع العلاقات الخاصة بمشروع محدد (getAllByProject)

test('it returns all relations mapped correctly for a specific project', function () {
    // 1. إعداد بيانات المشروع الأول (Project A) بسجلات حقيقية ومترابطة
    $entryA = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'entry-a',
        'status' => 'published',
    ]);

    $relatedA = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectA->id,
        'data_type_id' => $this->dataTypeA->id,
        'slug' => 'related-a',
        'status' => 'published',
    ]);
    
    DB::table('data_entry_relations')->insert([
        'data_entry_id' => $entryA,
        'related_entry_id' => $relatedA,
        'data_type_relation_id' => $this->relationIdA,
    ]);

    // 2. إعداد بيانات المشروع الثاني (Project B) للتأكد من العزل
    $entryB = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectB->id,
        'data_type_id' => $this->dataTypeB->id,
        'slug' => 'entry-b',
        'status' => 'published',
    ]);

    $relatedB = DB::table('data_entries')->insertGetId([
        'project_id' => $this->projectB->id,
        'data_type_id' => $this->dataTypeB->id,
        'slug' => 'related-b',
        'status' => 'published',
    ]);
    
    DB::table('data_entry_relations')->insert([
        'data_entry_id' => $entryB,
        'related_entry_id' => $relatedB,
        'data_type_relation_id' => $this->relationIdB,
    ]);

    // التنفيذ والتأكيد
    $result = $this->repository->getAllByProject($this->projectA->id);

    expect($result)->toBeArray()->toHaveCount(1)
        ->and($result[0])->toBe([
            'parent_id' => $entryA,
            'child_id' => $relatedA,
        ]);
});

test('it returns an empty array if project has no relations', function () {
    $result = $this->repository->getAllByProject($this->projectA->id);
    
    expect($result)->toBeEmpty();
});