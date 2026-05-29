<?php

use App\Console\Commands\SearchReindexCommand;
use App\Domains\Search\Actions\ReindexSearchAction;
use Illuminate\Support\Facades\DB;

/**
 * دالة مساعدة لتجهيز المحاكاة الافتراضية لقاعدة البيانات
 */
function setupDatabaseExpectations(bool $shouldConnect = true, int $indexCount = 100)
{
  if (! $shouldConnect) {
    DB::shouldReceive('connection')->andThrow(new Exception('Database connection lost'));
    return;
  }

  // محاكاة الاتصال الناجح والـ PDO
  $pdoMock = Mockery::mock(PDO::class);
  $connectionMock = Mockery::mock();
  $connectionMock->shouldReceive('getPdo')->andReturn($pdoMock);

  DB::shouldReceive('connection')->andReturn($connectionMock);

  // محاكاة استعلام الفوتر وعرض النتائج
  DB::shouldReceive('table')->with('search_indices')->andReturnSelf();
  DB::shouldReceive('count')->andReturn($indexCount);
}

test('it fails when database connection cannot be established', function () {
  setupDatabaseExpectations(shouldConnect: false);

  $this->artisan('search:reindex')
    ->assertExitCode(1);
});

test('it cancels reindex when confirmation prompt is declined', function () {
  setupDatabaseExpectations(shouldConnect: true);

  $this->artisan('search:reindex')
    ->expectsConfirmation('Are you sure you want to continue?', 'no')
    ->assertExitCode(0);
});

test('it executes reindex successfully and finishes progress bar', function () {
  setupDatabaseExpectations(shouldConnect: true);

  $actionMock = Mockery::mock(ReindexSearchAction::class);
  $actionMock->shouldReceive('execute')
    ->once()
    ->andReturnUsing(function ($onProgress) {
      $onProgress(1, 10); // هذا ينشئ الـ progressBar
      return ['total' => 10, 'indexed' => 10, 'skipped' => 0];
    });
  $this->app->instance(ReindexSearchAction::class, $actionMock);

  // هذا الاختبار سيغطي الأسطر 79-82 (الإنهاء الناجح)
  $this->artisan('search:reindex --force')
    ->assertExitCode(0);
});

test('it finishes progress bar when exception occurs', function () {
  setupDatabaseExpectations(shouldConnect: true);

  $actionMock = Mockery::mock(ReindexSearchAction::class);
  // نجعل الـ callback ينشئ الـ progress bar ثم نرمي استثناء
  $actionMock->shouldReceive('execute')
    ->once()
    ->andReturnUsing(function ($onProgress) {
      $onProgress(1, 10); // إنشاء الـ progressBar (سطر 62)
      throw new Exception('Triggering catch block');
    });
  $this->app->instance(ReindexSearchAction::class, $actionMock);

  // هذا الاختبار سيغطي الأسطر 89-92 (الإنهاء داخل catch)
  $command = new SearchReindexCommand($actionMock);
  $command->setLaravel($this->app);

  $input = new \Symfony\Component\Console\Input\ArrayInput(['--force' => true], $command->getDefinition());
  $output = new \Symfony\Component\Console\Output\BufferedOutput();

  $command->run($input, $output);

  // التحقق من أن الخطأ طُبع
  $this->assertStringContainsString('Reindex failed: Triggering catch block', $output->fetch());
});

test('it handles exceptions during reindex and prints trace in verbose mode', function () {
  setupDatabaseExpectations(shouldConnect: true);

  $actionMock = Mockery::mock(ReindexSearchAction::class);
  $actionMock->shouldReceive('execute')->andThrow(new Exception('Unexpected failure'));
  $this->app->instance(ReindexSearchAction::class, $actionMock);

  // نستخدم الـ try/catch اليدوي هنا لتجنب مشاكل التقاط المخرجات في الـ Artisan Buffer
  // ونقوم بتشغيل الأمر مباشرة عبر الحاوية لاختبار الـ Verbose ومسار getTraceAsString
  $command = new SearchReindexCommand($actionMock);
  $command->setLaravel($this->app);

  // محاكاة الـ Input والـ Output المناسبين للـ Verbose Mode
  $input = new \Symfony\Component\Console\Input\ArrayInput(['--force' => true], $command->getDefinition());
  $output = new \Symfony\Component\Console\Output\BufferedOutput();
  $output->setVerbosity(\Symfony\Component\Console\Output\BufferedOutput::VERBOSITY_VERBOSE);

  $command->run($input, $output);
  $textOutput = $output->fetch();

  // التحقق الصافي المضمون باستخدام PHPUnit Native Assertions
  $this->assertStringContainsString('Reindex failed: Unexpected failure', $textOutput);
  $this->assertStringContainsString('SearchReindexCommand', $textOutput);
});
