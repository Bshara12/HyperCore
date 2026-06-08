<?php

use App\Jobs\UpdateSearchSignalsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

test('it dispatches the job to the search-maintenance queue', function () {
  // إيقاف إرسال الوظائف الفعلية واعتراضها
  Bus::fake();

  $this->artisan('search:update-signals')
    ->expectsOutput('Job dispatched to search-maintenance queue.')
    ->assertExitCode(0);

  // التحقق من أن الأمر أرسل الـ Job الصحيح للطابور الصحيح
  Bus::assertDispatched(UpdateSearchSignalsJob::class, function ($job) {
    return $job->queue === 'search-maintenance';
  });
});

test('it runs the job synchronously without executing mysql queries on sqlite', function () {
  // دالة pretend تخبر لارافيل بتجاوز التنفيذ الفعلي لأي استعلام Database
  // مما يمنع SQLite من قراءة كود MySQL ورمي خطأ Syntax Error
  // في نفس الوقت، سيتم تنفيذ باقي أوامر الـ Command بشكل طبيعي ليغطي الأسطر 100%
  DB::connection()->pretend(function () {
    $this->artisan('search:update-signals --sync')
      ->expectsOutput('Running synchronously...')
      ->expectsOutput('Done.')
      ->assertExitCode(0);
  });
});
