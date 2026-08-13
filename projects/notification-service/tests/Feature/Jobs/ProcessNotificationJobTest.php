<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\NotificationDispatcherService;
use Exception;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessNotificationJobTest extends TestCase
{
  #[Test]
  public function it_dispatches_notification_successfully()
  {
    // 1. Arrange
    $notificationMock = Mockery::mock(Notification::class);
    $notificationMock->shouldReceive('getAttribute')
      ->andReturnUsing(function ($key) {
        return match ($key) {
          'id'      => 1,
          'channel' => (object) ['value' => 'in_app'],
          'user_id' => 'user-123',
          default   => null,
        };
      });

    // محاكاة خدمة التوزيع NotificationDispatcherService والتوقع باستدعاء dispatch مرة واحدة
    $dispatcherMock = Mockery::mock(NotificationDispatcherService::class);
    $dispatcherMock->shouldReceive('dispatch')
      ->once()
      ->with($notificationMock);

    // مراقبة الـ Logs للتأكد من تسجيل معلومات التشغيل
    Log::shouldReceive('info')
      ->once()
      ->with('Processing notification', Mockery::any());

    $job = new ProcessNotificationJob($notificationMock);

    // 2. Act
    $job->handle($dispatcherMock);

    // 3. Assert (تم التحقق عبر Mockery expectations)
    $this->assertTrue(true);
  }

  #[Test]
  public function it_marks_notification_as_failed_when_job_fails_permanently()
  {
    // 1. Arrange
    $exceptionMessage = 'Network connection timeout';
    $exception = new Exception($exceptionMessage);

    $notificationMock = Mockery::mock(Notification::class);
    $notificationMock->shouldReceive('getAttribute')
      ->andReturnUsing(function ($key) {
        return match ($key) {
          'id'    => 1,
          default => null,
        };
      });

    // التوقع بأن دالة markAsFailed سيتم استدعاؤها مع رسالة الخطأ
    $notificationMock->shouldReceive('markAsFailed')
      ->once()
      ->with($exceptionMessage);

    // مراقبة الـ Logs للتأكد من تسجيل خطأ الفشل النهائي
    Log::shouldReceive('error')
      ->once()
      ->with('Notification job permanently failed', Mockery::any());

    $job = new ProcessNotificationJob($notificationMock);

    // 2. Act
    $job->failed($exception);

    // 3. Assert (تم التحقق عبر Mockery expectations)
    $this->assertTrue(true);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
