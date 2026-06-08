<?php

use App\Console\Commands\AnalyzeSynonymsCommand;
use App\Domains\Search\Services\SynonymAnalysisService;
use App\Domains\Search\DTOs\SynonymAnalysisResultDTO;
use Illuminate\Support\Facades\Artisan;

test('command fails when project option is missing', function () {
  $this->artisan('search:analyze-synonyms')
    ->expectsOutput('--project is required')
    ->assertExitCode(1);
});

test('command executes successfully and displays table data', function () {
  // 1. تعريف بيانات اختبارية (Suggestion mock)
  $suggestion = new class {
    public string $wordA = 'a';
    public string $wordB = 'b';
    public float $jaccardScore = 0.55555;
    public int $cooccurrenceCount = 5;
    public float $confidenceScore = 0.88888;
  };

  // 2. إنشاء الـ DTO مع بيانات كاملة
  $dto = new SynonymAnalysisResultDTO(
    projectId: 1,
    keywordsAnalyzed: 10,
    uniqueWordsFound: 5,
    pairsEvaluated: 2,
    suggestionsGenerated: 1,
    suggestions: [$suggestion],
    durationMs: 12.345
  );

  // 3. عمل Mock للخدمة
  $serviceMock = Mockery::mock(SynonymAnalysisService::class);
  $serviceMock->shouldReceive('analyze')->once()->andReturn($dto);
  $this->app->instance(SynonymAnalysisService::class, $serviceMock);

  // 4. تنفيذ الأمر والتحقق من الجدول
  $this->artisan('search:analyze-synonyms --project=1')
    ->expectsOutput('Analyzing synonyms for project 1 (en)...')
    ->expectsTable(['Metric', 'Value'], [
      ['Keywords Analyzed', 10],
      ['Unique Words Found', 5],
      ['Pairs Evaluated', 2],
      ['Suggestions Generated', 1],
      ['Duration', '12.35ms'],
    ])
    ->expectsOutput("\nTop suggestions:")
    ->expectsTable(['Word A', 'Word B', 'Jaccard', 'Co-occur', 'Confidence'], [
      ['a', 'b', 0.5556, 5, 0.8889]
    ])
    ->assertExitCode(0);
});

test('command skips suggestions table when empty', function () {
  $dto = new SynonymAnalysisResultDTO(
    projectId: 1,
    keywordsAnalyzed: 10,
    uniqueWordsFound: 5,
    pairsEvaluated: 2,
    suggestionsGenerated: 0,
    suggestions: [],
    durationMs: 12.345
  );

  $serviceMock = Mockery::mock(SynonymAnalysisService::class);
  $serviceMock->shouldReceive('analyze')->once()->andReturn($dto);
  $this->app->instance(SynonymAnalysisService::class, $serviceMock);

  // التقاط المخرجات للتحقق من عدم وجود نص معين
  $this->artisan('search:analyze-synonyms --project=1');

  // التحقق من أن النص لا يظهر في مخرجات الأمر بدون استخدام دوال Macro غير معروفة
  $this->assertFalse(str_contains(Artisan::output(), 'Top suggestions:'));
});
