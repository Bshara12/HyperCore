<?php

use App\Domains\Search\Repositories\Eloquent\EloquentSynonymSuggestionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\SynonymSuggestion;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSynonymSuggestionRepository();
});

test('fetchKeywordsForAnalysis returns keywords correctly', function () {
  // بناء Mock لسلسلة DB::table
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');
  $builder->shouldReceive('select')->andReturnSelf();
  $builder->shouldReceive('selectRaw')->andReturnSelf();
  $builder->shouldReceive('where')->times(3)->andReturnSelf(); // project_id, language, searched_at
  $builder->shouldReceive('whereNotNull')->andReturnSelf();
  $builder->shouldReceive('whereRaw')->andReturnSelf();
  $builder->shouldReceive('groupBy')->andReturnSelf();
  $builder->shouldReceive('having')->andReturnSelf();
  $builder->shouldReceive('orderByDesc')->andReturnSelf();
  $builder->shouldReceive('limit')->andReturnSelf();
  $builder->shouldReceive('pluck')->once()->with('keyword')->andReturn(collect(['iphone', 'samsung']));

  DB::shouldReceive('table')->once()->with('user_search_logs')->andReturn($builder);

  $results = $this->repository->fetchKeywordsForAnalysis(1, 'en');

  expect($results)->toBe(['iphone', 'samsung']);
});

test('saveSuggestions executes upsert correctly', function () {
  $projectId = 1;

  // إنشاء كائن بيانات بسيط
  $suggestion = new \stdClass();
  $suggestion->wordA = 'iphone';
  $suggestion->wordB = 'apple phone';
  $suggestion->language = 'en';
  $suggestion->jaccardScore = 0.8;
  $suggestion->cooccurrenceCount = 5;
  $suggestion->confidenceScore = 0.9;
  $suggestion->wordACount = 10;
  $suggestion->wordBCount = 10;

  $count = $this->repository->saveSuggestions($projectId, [$suggestion]);

  expect($count)->toBe(1);

  // تأكد فعلياً أن البيانات دخلت قاعدة البيانات
  $this->assertDatabaseHas('synonym_suggestions', [
    'word_a' => 'iphone',
    'word_b' => 'apple phone',
    'project_id' => $projectId
  ]);
});

test('getPendingSuggestions returns filtered list', function () {
  $builder = Mockery::mock('Illuminate\Database\Query\Builder');
  $builder->shouldReceive('where')->times(4)->andReturnSelf();
  $builder->shouldReceive('orderByDesc')->andReturnSelf();
  $builder->shouldReceive('limit')->andReturnSelf();
  $builder->shouldReceive('get')->once()->andReturn(collect([['id' => 1]]));

  DB::shouldReceive('table')->once()->with('synonym_suggestions')->andReturn($builder);

  $results = $this->repository->getPendingSuggestions(1);

  expect($results)->toHaveCount(1);
});

test('updateStatus updates the database record', function () {
  // 1. إنشاء سجل حقيقي في قاعدة البيانات
  $suggestion = SynonymSuggestion::factory()->create([
    'status' => 'pending'
  ]);

  // 2. استدعاء التابع
  $this->repository->updateStatus($suggestion->id, 'approved', 'Looks good', 1);

  // 3. التحقق من التغيير في قاعدة البيانات
  $this->assertDatabaseHas('synonym_suggestions', [
    'id' => $suggestion->id,
    'status' => 'approved',
    'reviewer_notes' => 'Looks good'
  ]);
});
