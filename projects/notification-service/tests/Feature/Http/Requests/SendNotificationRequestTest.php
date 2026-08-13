<?php

namespace Tests\Feature\Http\Requests;

use App\Enums\NotificationChannel;
use App\Http\Requests\SendNotificationRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendNotificationRequestTest extends TestCase
{
  /**
   * دالة مساعدة لإنشاء الطلب بالبيانات وتطبيق قواعد التحقق ورسائل الخطأ عليه
   */
  private function validate(array $data)
  {
    // إنشاء الطلب مع دمج البيانات ليتمكن $this->input() من قراءتها
    $request = SendNotificationRequest::create('/', 'POST', $data);

    return Validator::make($data, $request->rules(), $request->messages());
  }

  #[Test]
  public function it_authorizes_all_requests()
  {
    $request = new SendNotificationRequest();

    // التحقق من أن دالة الـ authorize ترجع true دائماً
    $this->assertTrue($request->authorize());
  }

  #[Test]
  public function it_passes_with_valid_data_for_non_email_channel()
  {
    $data = [
      'user_id' => 'user-123',
      'channel' => NotificationChannel::IN_APP->value,
      'title'   => 'Test Title',
      'body'    => 'Test Body Content',
      'data'    => ['key' => 'value'],
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->passes());
  }

  #[Test]
  public function it_passes_when_email_is_provided_for_email_channel()
  {
    $data = [
      'user_id'    => 'user-123',
      'channel'    => NotificationChannel::EMAIL->value,
      'user_email' => 'user@example.com',
      'title'      => 'Email Title',
      'body'       => 'Email Body Content',
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->passes());
  }

  #[Test]
  public function it_fails_when_email_is_missing_for_email_channel()
  {
    $data = [
      'user_id'    => 'user-123',
      'channel'    => NotificationChannel::EMAIL->value,
      'user_email' => null, // مفقود رغم أن القناة بريد إلكتروني
      'title'      => 'Email Title',
      'body'       => 'Email Body Content',
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('user_email', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = $this->validate([]);
    $errors = $validator->errors()->toArray();

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('user_id', $errors);
    $this->assertArrayHasKey('channel', $errors);
    $this->assertArrayHasKey('title', $errors);
    $this->assertArrayHasKey('body', $errors);
  }

  #[Test]
  public function it_fails_if_email_format_is_invalid()
  {
    $data = [
      'user_id'    => 'user-123',
      'channel'    => NotificationChannel::EMAIL->value,
      'user_email' => 'not-a-valid-email',
      'title'      => 'Title',
      'body'       => 'Body',
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('user_email', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_channel_is_not_in_enum()
  {
    $data = [
      'user_id' => 'user-123',
      'channel' => 'invalid_channel',
      'title'   => 'Title',
      'body'    => 'Body',
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->passes());
    $this->assertArrayHasKey('channel', $validator->errors()->toArray());
  }
}
