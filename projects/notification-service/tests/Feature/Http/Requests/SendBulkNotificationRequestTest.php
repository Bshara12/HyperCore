<?php

namespace Tests\Feature\Http\Requests;

use App\Enums\NotificationChannel;
use App\Http\Requests\SendBulkNotificationRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendBulkNotificationRequestTest extends TestCase
{
  /**
   * دالة مساعدة لتمرير البيانات وتطبيق قواعد التحقق عليها
   */
  private function validate(array $data)
  {
    $request = new SendBulkNotificationRequest();
    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_authorizes_all_requests()
  {
    $request = new SendBulkNotificationRequest();

    // التحقق من أن الدالة ترجع true دائماً
    $this->assertTrue($request->authorize());
  }

  #[Test]
  public function it_passes_with_valid_data()
  {
    $data = [
      'notifications' => [
        [
          'user_id'    => 'user-123',
          'user_email' => 'user@example.com',
          'channel'    => NotificationChannel::EMAIL->value,
          'title'      => 'Test Title 1',
          'body'       => 'Test Body 1',
          'data'       => ['link' => 'https://example.com'],
        ],
        [
          'user_id' => 'user-456',
          'channel' => NotificationChannel::IN_APP->value,
          'title'   => 'Test Title 2',
          'body'    => 'Test Body 2',
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->passes());
  }

  #[Test]
  public function it_fails_if_notifications_array_is_missing_or_empty()
  {
    // غير موجود
    $this->assertFalse($this->validate([])->passes());

    // ليس مصفوفة
    $this->assertFalse($this->validate(['notifications' => 'string'])->passes());

    // مصفوفة فارغة (يجب أن تحتوي على عنصر واحد كحد أدنى min:1)
    $this->assertFalse($this->validate(['notifications' => []])->passes());
  }

  #[Test]
  public function it_fails_if_notifications_exceed_max_limit()
  {
    // إنشاء 101 إشعار لتجاوز الحد الأقصى (max:100)
    $notifications = array_fill(0, 101, [
      'user_id' => 'user-1',
      'channel' => NotificationChannel::IN_APP->value,
      'title'   => 'Title',
      'body'    => 'Body'
    ]);

    $validator = $this->validate(['notifications' => $notifications]);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('notifications', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_required_fields_are_missing_in_notification_item()
  {
    $data = [
      'notifications' => [
        [
          // تم تجاهل الحقول المطلوبة: user_id, channel, title, body
          'user_email' => 'test@example.com',
        ]
      ]
    ];

    $validator = $this->validate($data);
    $errors = $validator->errors()->toArray();

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('notifications.0.user_id', $errors);
    $this->assertArrayHasKey('notifications.0.channel', $errors);
    $this->assertArrayHasKey('notifications.0.title', $errors);
    $this->assertArrayHasKey('notifications.0.body', $errors);
  }

  #[Test]
  public function it_fails_if_email_is_invalid()
  {
    $data = [
      'notifications' => [
        [
          'user_id'    => 'user-123',
          'user_email' => 'not-an-email-address', // إيميل غير صالح
          'channel'    => NotificationChannel::EMAIL->value,
          'title'      => 'Title',
          'body'       => 'Body',
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('notifications.0.user_email', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_channel_is_not_in_enum()
  {
    $data = [
      'notifications' => [
        [
          'user_id'    => 'user-123',
          'channel'    => 'unsupported_channel', // قناة غير موجودة في الـ Enum
          'title'      => 'Title',
          'body'       => 'Body',
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('notifications.0.channel', $validator->errors()->toArray());
  }
}
