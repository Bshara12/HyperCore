<?php

namespace Tests\Feature\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationTemplateRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class UpdateNotificationTemplateRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new UpdateNotificationTemplateRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_empty_payload_due_to_sometimes_rule()
  {
    // قاعدة sometimes تسمح بتمرير مصفوفة فارغة تماماً عند التحديث
    $validator = $this->validate([]);

    $this->assertFalse($validator->fails(), 'Validation should pass with empty data on update.');
  }

  #[Test]
  public function it_passes_validation_with_valid_provided_data()
  {
    $data = [
      'key' => 'order_dispatched',
      'channel' => 'broadcast',
      'locale' => 'en',
      'version' => 2,
      'subject_template' => 'Your order #{id} has been shipped!',
      'body_template' => 'Hello, your order is on the way.',
      'variables_schema' => ['id' => 'integer'],
      'defaults' => ['id' => 0],
      'is_active' => false,
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails());
  }

  #[Test]
  public function it_validates_channel_rule_in_with_invalid_channel()
  {
    $data = [
      'channel' => 'sms_gateway', // قناة غير مدعومة خارج القائمة البيضاء
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('channel', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_version_is_below_minimum_boundary()
  {
    $data = [
      'version' => 0, // أقل من الحد الأدنى المسموح min:1
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('version', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_is_active_field_is_not_a_boolean()
  {
    $data = [
      'is_active' => 'true-as-string', // قيمة نصية غير صحيحة لحقل الـ boolean
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('is_active', $validator->errors()->toArray());
  }
}
