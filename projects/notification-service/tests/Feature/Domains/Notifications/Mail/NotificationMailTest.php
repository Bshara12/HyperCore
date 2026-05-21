<?php

namespace Tests\Feature\Domains\Notifications\Mail;

use Tests\TestCase;
use App\Domains\Notifications\Mail\NotificationMail;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class NotificationMailTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // بناء جدول ميموري مؤقت لتأمين حقول كائن الـ Notification
    Schema::create('notifications', function (Blueprint $table) {
      $table->ulid('id')->primary();
      $table->string('title')->nullable();
      $table->text('body')->nullable();
      $table->json('data')->nullable();
      $table->timestamps();
    });
  }

  protected function tearDown(): void
  {
    Schema::dropIfExists('notifications');

    parent::tearDown();
  }

  // --------------------------------------------------------------------
  // 1. اختبار الـ Envelope والعنوان الديناميكي (Subject)
  // --------------------------------------------------------------------
  public function test_it_builds_envelope_with_notification_title_as_subject()
  {
    $notification = Notification::create([
      'title' => 'Invoice Paid Successfully',
    ]);

    $mailable = new NotificationMail($notification);
    $envelope = $mailable->envelope();

    $this->assertEquals('Invoice Paid Successfully', $envelope->subject);
  }

  public function test_it_builds_envelope_with_default_subject_if_title_is_missing()
  {
    // إنشاء إشعار بدون عنوان لفحص المعامل الشرطي والاختيار الافتراضي
    $notification = Notification::create([
      'title' => null,
    ]);

    $mailable = new NotificationMail($notification);
    $envelope = $mailable->envelope();

    $this->assertEquals('Notification', $envelope->subject);
  }

  // --------------------------------------------------------------------
  // 2. اختبار الـ Content والـ View والمصفوفة الممررة (With Data)
  // --------------------------------------------------------------------
  public function test_it_builds_content_with_correct_view_and_mapped_data()
  {
    $notification = Notification::create([
      'title' => 'Security Alert',
      'body' => 'New login detected from unusual location.',
      'data' => ['ip' => '192.168.1.1', 'device' => 'S24 Ultra'],
    ]);

    $mailable = new NotificationMail($notification);
    $content = $mailable->content();

    // فحص الـ View المستخدمة
    $this->assertEquals('emails.notifications.generic', $content->view);

    // فحص مصفوفة البيانات الممررة للقالب
    $data = $content->with;

    $this->assertArrayHasKey('notification', $data);
    $this->assertEquals($notification->id, $data['notification']->id);
    $this->assertEquals('Security Alert', $data['title']);
    $this->assertEquals('New login detected from unusual location.', $data['body']);
    $this->assertEquals(['ip' => '192.168.1.1', 'device' => 'S24 Ultra'], $data['data']);
  }

  public function test_it_builds_content_with_empty_array_for_data_if_null()
  {
    // في حال كان حقل الـ data فارغاً في قاعدة البيانات
    $notification = Notification::create([
      'title' => 'Simple Alert',
      'body' => 'Just a text notice.',
      'data' => null,
    ]);

    $mailable = new NotificationMail($notification);
    $content = $mailable->content();

    $this->assertIsArray($content->with['data']);
    $this->assertEmpty($content->with['data']);
  }
}
