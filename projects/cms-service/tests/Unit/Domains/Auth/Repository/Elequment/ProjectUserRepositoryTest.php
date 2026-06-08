<?php

namespace Tests\Unit\Domains\Auth\Repository\Elequment;

use App\Domains\Auth\Repository\Elequment\ProjectUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new ProjectUserRepository();
});

test('exists returns true when user is associated with project', function () {
  $user = User::factory()->create();

  // إنشاء المشروع باستخدام الـ Factory (سيتكفل بالقيم الافتراضية للـ name و slug)
  $project = \App\Models\Project::factory()->create(['public_id' => 'project_abc_123']);

  DB::table('project_user')->insert([
    'user_id' => $user->id,
    'project_id' => $project->id,
  ]);

  $result = $this->repository->exists($user->id, 'project_abc_123');
  expect($result)->toBeTrue();
});

test('exists returns false when user is not associated with project', function () {
  $user = User::factory()->create();

  // هكذا، أنت لا تهتم بما هي الحقول الإجبارية، الـ Factory يتولى الأمر
  \App\Models\Project::factory()->create([
    'public_id' => 'project_abc_123'
  ]);

  $result = $this->repository->exists($user->id, 'project_abc_123');

  expect($result)->toBeFalse();
});

test('exists returns false when project does not exist', function () {
  $user = User::factory()->create();

  $result = $this->repository->exists($user->id, 'non_existent_key');

  expect($result)->toBeFalse();
});
