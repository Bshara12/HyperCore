<?php

namespace Tests\Feature\Domains\Notifications\Jobs;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\MaterializeNotificationBatchJob;
use App\Domains\Notifications\Services\NotificationBatchService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Mockery;
use Exception;

class MaterializeNotificationBatchJobTest extends TestCase
{
  private $batchServiceMock;
  private $writeServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    // بناء جدول الـ Batch لبيئة معزولة بالكامل تتوافق مع بيانات الـ Job
    Schema::create('notification_batches', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('created_by_type')->nullable();
      $table->string('created_by_id')->nullable();
      $table->string('project_id')->nullable();
      $table->string('request_id')->nullable();
      $table->string('correlation_id')->nullable();
      $table->string('causation_id')->nullable();
      $table->json('actor_snapshot')->nullable();
      $table->json('payload')->nullable();
      $table->string('source_service')->nullable();
      $table->string('source_event_type')->nullable();
      $table->string('status')->default('pending');
      $table->integer('total_targets')->default(0);
      $table->integer('processed_targets')->default(0);
      $table->timestamp('started_at')->nullable();
      $table->timestamp('completed_at')->nullable();
      $table->timestamps();
    });

    $this->batchServiceMock = Mockery::mock(NotificationBatchService::class);
    $this->writeServiceMock = Mockery::mock(NotificationWriteService::class);
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notification_batches');
    Mockery::close();

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. مسار النجاح الكامل وتوليد الإشعارات وتحديث العدادات
  // --------------------------------------------------------------------
  public function test_materializes_notification_batch_successfully_for_all_recipients()
  {
    // إنشاء Batch تجريبي ببيانات تتبع واضحة
    $batch = NotificationBatch::create([
      'created_by_type' => 'user',
      'created_by_id' => 'usr_feras_123',
      'project_id' => 'proj_cms_core',
      'request_id' => 'req_999',
      'correlation_id' => 'corr_999',
      'causation_id' => 'caus_999',
      'actor_snapshot' => ['display_name' => 'Feras Hatem'],
      'payload' => ['title' => 'Batch Alert', 'body' => 'Hello team'],
      'source_service' => 'billing',
      'source_event_type' => 'invoice.paid',
      'status' => 'pending'
    ]);

    $recipients = [
      ['type' => 'user', 'id' => 'usr_01'],
      ['type' => 'user', 'id' => 'usr_02'],
    ];

    // 1. محاكاة فك المتلقين عبر السيرفيس
    $this->batchServiceMock->shouldReceive('resolveRecipients')
      ->once()
      ->with(Mockery::on(function ($arg) use ($batch) {
        return $arg->id === $batch->id;
      }))
      ->andReturn($recipients);

    // 2. محاكاة سرفيس الكتابة وإنشاء الإشعار مرتين (لكل متلقي مرة)
    $this->writeServiceMock->shouldReceive('create')
      ->times(2)
      ->with(
        Mockery::type(NotificationActor::class),
        Mockery::on(function ($payload) use ($batch) {
          // التحقق من صحة بناء الـ Payload وحساب الـ dedupe_key الفريد بدقة
          $hasProject = $payload['project_id'] === $batch->project_id;
          $hasDedupe = isset($payload['dedupe_key']) && strlen($payload['dedupe_key']) === 64; // SHA-256
          $hasSource = $payload['source']['id'] === $batch->id;
          return $hasProject && $hasDedupe && $hasSource;
        })
      );

    $job = new MaterializeNotificationBatchJob($batch->id);
    $job->handle($this->batchServiceMock, $this->writeServiceMock);

    // فحص تحديث حالة الـ Batch النهائي بعد النجاح
    $batch->refresh();
    $this->assertEquals('completed', $batch->status);
    $this->assertEquals(2, $batch->total_targets);
    $this->assertEquals(2, $batch->processed_targets);
    $this->assertNotNull($batch->started_at);
    $this->assertNotNull($batch->completed_at);
  }

  // --------------------------------------------------------------------
  // 2. مسار الفشل ودخول الـ Catch لتسجيل حالة الفشل وإعادة رمي الخطأ
  // --------------------------------------------------------------------
  public function test_marks_batch_as_failed_and_rethrows_exception_on_failure()
  {
    $batch = NotificationBatch::create([
      'project_id' => 'proj_failed_case',
      'status' => 'pending'
    ]);

    $recipients = [
      ['type' => 'user', 'id' => 'usr_01']
    ];

    $this->batchServiceMock->shouldReceive('resolveRecipients')->once()->andReturn($recipients);

    // إجبار سرفيس الكتابة على رمي استثناء لضرب الـ Try block
    $this->writeServiceMock->shouldReceive('create')
      ->once()
      ->andThrow(new Exception('Database connection lost during writing.'));

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Database connection lost during writing.');

    $job = new MaterializeNotificationBatchJob($batch->id);

    try {
      $job->handle($this->batchServiceMock, $this->writeServiceMock);
    } finally {
      // التحقق من أن حالة الـ Batch تحولت لـ failed حتى مع الـ Rethrow
      $batch->refresh();
      $this->assertEquals('failed', $batch->status);
      $this->assertNotNull($batch->completed_at);
    }
  }

  // --------------------------------------------------------------------
  // 3. فحص الـ Overlap والـ Throttle Configurations الخاصة بالـ Middleware
  // --------------------------------------------------------------------
  public function test_it_defines_correct_overlap_and_throttle_configurations()
  {
    $job = new MaterializeNotificationBatchJob('batch-id-xyz');
    $reflection = new \ReflectionClass(MaterializeNotificationBatchJob::class);

    // فحص الـ overlapKey
    $overlapKey = $reflection->getMethod('overlapKey');
    $overlapKey->setAccessible(true);
    $this->assertEquals('batch:batch-id-xyz', $overlapKey->invoke($job));

    // فحص الـ overlapReleaseAfter
    $release = $reflection->getMethod('overlapReleaseAfter');
    $release->setAccessible(true);
    $this->assertEquals(30, $release->invoke($job));

    // فحص الـ overlapExpireAfter
    $expire = $reflection->getMethod('overlapExpireAfter');
    $expire->setAccessible(true);
    $this->assertEquals(600, $expire->invoke($job));

    // فحص الـ throttleMaxExceptions
    $maxExceptions = $reflection->getMethod('throttleMaxExceptions');
    $maxExceptions->setAccessible(true);
    $this->assertEquals(3, $maxExceptions->invoke($job));

    // فحص الـ throttleDecayMinutes
    $decay = $reflection->getMethod('throttleDecayMinutes');
    $decay->setAccessible(true);
    $this->assertEquals(10, $decay->invoke($job));
  }
}
