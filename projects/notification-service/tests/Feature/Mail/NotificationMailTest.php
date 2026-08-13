<?php

namespace Tests\Feature\Mail;

use App\Mail\NotificationMail;
use App\Models\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
  #[Test]
  public function it_has_correct_envelope_subject()
  {
    // 1. Arrange
    $notificationMock = Mockery::mock(Notification::class);
    $notificationMock->shouldReceive('getAttribute')
      ->with('title')
      ->andReturn('Important Notification Title');

    $mailable = new NotificationMail($notificationMock);

    // 2. Act
    $envelope = $mailable->envelope();

    // 3. Assert
    $this->assertEquals('Important Notification Title', $envelope->subject);
  }

  #[Test]
  public function it_has_correct_content_view()
  {
    // 1. Arrange
    $notificationMock = Mockery::mock(Notification::class);
    $mailable = new NotificationMail($notificationMock);

    // 2. Act
    $content = $mailable->content();

    // 3. Assert
    $this->assertEquals('emails.notification', $content->view);
  }

  #[Test]
  public function it_passes_notification_data_to_mailable()
  {
    // 1. Arrange
    $notificationMock = Mockery::mock(Notification::class);

    // 2. Act
    $mailable = new NotificationMail($notificationMock);

    // 3. Assert
    $this->assertSame($notificationMock, $mailable->notification);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
