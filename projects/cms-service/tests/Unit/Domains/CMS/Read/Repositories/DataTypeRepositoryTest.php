<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\DataTypeRepository;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new DataTypeRepository();
});

test('it returns the correct id when slug and project id match', function () {
  // 1. إنشاء المشروع أولاً (لحل مشكلة الـ Foreign Key)
  $project = Project::factory()->create();

  // 2. إدخال الـ DataType المرتبط بهذا المشروع
  $id = DB::table('data_types')->insertGetId([
    'slug' => 'active-slug',
    'project_id' => $project->id, // استخدم الـ ID الخاص بالمشروع الذي أنشأناه
    'name' => 'Test Type',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // 3. التنفيذ
  $result = $this->repository->getIdBySlugAndProject('active-slug', $project->id);

  // 4. التأكيد
  expect($result)->toBe($id);
});

test('it returns null when slug does not exist', function () {
  $project = Project::factory()->create();

  DB::table('data_types')->insert([
    'slug' => 'exists',
    'project_id' => $project->id,
    'name' => 'Type',
  ]);

  $result = $this->repository->getIdBySlugAndProject('wrong-slug', $project->id);

  expect($result)->toBeNull();
});

test('it returns null when project id does not match', function () {
  // إنشاء مشروعين مختلفين
  $project1 = Project::factory()->create();
  $project2 = Project::factory()->create();

  // إدخال بيانات للمشروع الأول فقط
  DB::table('data_types')->insert([
    'slug' => 'shared-slug',
    'project_id' => $project1->id,
    'name' => 'Project 1 Type',
  ]);

  // البحث في المشروع الثاني (يجب أن يعيد null)
  $result = $this->repository->getIdBySlugAndProject('shared-slug', $project2->id);

  expect($result)->toBeNull();
});
