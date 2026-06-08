<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories\DataType;

use App\Domains\CMS\Read\Repositories\DataType\DataTypeRepositoryRead;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new DataTypeRepositoryRead();
    // إنشاء مشروع واحد لاستخدامه في جميع الاختبارات لضمان سلامة الـ Foreign Key
    $this->project = Project::factory()->create();
});

test('it lists data types belonging to a specific project', function () {
    // إنشاء بيانات مرتبطة بالمشروع الذي أنشأناه
    DataType::factory()->create(['project_id' => $this->project->id, 'name' => 'B']);
    DataType::factory()->create(['project_id' => $this->project->id, 'name' => 'A']);
    
    // إنشاء مشروع آخر لضمان العزل
    $otherProject = Project::factory()->create();
    DataType::factory()->create(['project_id' => $otherProject->id, 'name' => 'C']);

    $results = $this->repository->list($this->project->id);

    expect($results)->toHaveCount(2)
        ->and($results->first()->name)->toBe('A') // التأكد من الترتيب الأبجدي
        ->and($results->last()->name)->toBe('B');
});

test('it retrieves only trashed data types for a project', function () {
    // إنشاء سجل وحذفه (Soft Delete)
    $trashedType = DataType::factory()->create(['project_id' => $this->project->id]);
    $trashedType->delete();

    // إنشاء سجل نشط
    DataType::factory()->create(['project_id' => $this->project->id]);

    $results = $this->repository->trashed($this->project->id);

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($trashedType->id);
});

test('it finds a data type by slug and project id', function () {
    $slug = 'test-slug';
    $type = DataType::factory()->create([
        'project_id' => $this->project->id, 
        'slug' => $slug
    ]);

    // 1. البحث الناجح
    $found = $this->repository->findBySlug($slug, $this->project->id);
    expect($found)->not->toBeNull()->and($found->id)->toBe($type->id);

    // 2. البحث عن Slug غير موجود
    $notFound = $this->repository->findBySlug('wrong-slug', $this->project->id);
    expect($notFound)->toBeNull();

    // 3. البحث في مشروع آخر (حتى لو الـ Slug موجود)
    $otherProject = Project::factory()->create();
    $wrongProject = $this->repository->findBySlug($slug, $otherProject->id);
    expect($wrongProject)->toBeNull();
});