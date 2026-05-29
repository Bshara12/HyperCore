<?php

namespace Tests\Unit\Domains\CMS\Read\Repositories;

use App\Domains\CMS\Read\Repositories\EntryProjectReadRepository;
use App\Models\Project;
use App\Models\DataType;
use App\Models\DataTypeField; // استيراد المودل
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EntryProjectReadRepository();
    
    // إنشاء المشروع
    $this->project = Project::factory()->create();
    
    // إنشاء النوع المرتبط بالمشروع
    $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);
    
    // إنشاء حقل (Field) مرتبط بالنوع (لحله مشكلة data_type_field_id)
    $this->field = DataTypeField::factory()->create(['data_type_id' => $this->dataType->id]);
});

test('it filters entries by project and scheduling', function () {
    $commonData = [
        'project_id' => $this->project->id,
        'data_type_id' => $this->dataType->id,
        'slug' => Str::random(10),
    ];

    $validEntry = DB::table('data_entries')->insertGetId(array_merge($commonData, [
        'scheduled_at' => null,
        'deleted_at' => null,
    ]));

    $pastEntry = DB::table('data_entries')->insertGetId(array_merge($commonData, [
        'slug' => Str::random(10),
        'scheduled_at' => now()->subDay(),
        'deleted_at' => null,
    ]));

    DB::table('data_entries')->insert(array_merge($commonData, [
        'slug' => Str::random(10),
        'scheduled_at' => now()->addDay(),
    ]));

    DB::table('data_entries')->insert(array_merge($commonData, [
        'slug' => Str::random(10),
        'deleted_at' => now(),
    ]));

    $results = $this->repository->queryByProject($this->project->id, [])->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($validEntry, $pastEntry);
});

test('it searches entries by value', function () {
    $entry = DB::table('data_entries')->insertGetId([
        'project_id' => $this->project->id,
        'data_type_id' => $this->dataType->id,
        'slug' => 'test-entry',
    ]);

    // إضافة الـ data_type_field_id هنا لحل مشكلة الإدخال
    DB::table('data_entry_values')->insert([
        'data_entry_id' => $entry,
        'data_type_field_id' => $this->field->id, 
        'value' => 'Hello World',
    ]);

    $results = $this->repository->queryByProject($this->project->id, ['search' => 'Hello'])->get();
    expect($results)->toHaveCount(1);
});

test('it filters entries by published date range', function () {
    $entry1 = DB::table('data_entries')->insertGetId([
        'project_id' => $this->project->id,
        'data_type_id' => $this->dataType->id,
        'slug' => 'entry-one',
        'published_at' => '2026-05-20',
    ]);

    DB::table('data_entries')->insert([
        'project_id' => $this->project->id,
        'data_type_id' => $this->dataType->id,
        'slug' => 'entry-two',
        'published_at' => '2026-05-10',
    ]);

    $filters = ['date_from' => '2026-05-15', 'date_to' => '2026-05-25'];
    
    $results = $this->repository->queryByProject($this->project->id, $filters)->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($entry1);
});