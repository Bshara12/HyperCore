<?php

namespace Tests\Feature\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationSubscriptionRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class UpdateNotificationSubscriptionRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new UpdateNotificationSubscriptionRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_empty_payload_due_to_sometimes_rule()
  {
    // بما أن كل الحقول عليها sometimes، إرسال مصفوفة فارغة تماماً يعتبر ناجحاً ومقبولاً في التحديث
    $validator = $this->validate([]);

    $this->assertFalse($validator->fails(), 'Validation should pass with empty data on update.');
  }

  #[Test]
  public function it_passes_validation_with_valid_provided_data()
  {
    $data = [
      'topic_key' => 'billing.subscription_renewed',
      'channel_mask' => ['database', 'webhook'],
      'filters' => ['event_type' => 'success'],
      'active' => false,
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails());
  }

  #[Test]
  public function it_validates_channel_mask_rule_in_with_invalid_channels()
  {
    $data = [
      'channel_mask' => ['push', 'slack'], // قنوات غير مدعومة في القائمة البيضاء
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('channel_mask.0', $errors);
    $this->assertArrayHasKey('channel_mask.1', $errors);
  }

  #[Test]
  public function it_fails_if_active_field_is_not_a_boolean()
  {
    $data = [
      'active' => 'active-string-value', // قيمة نصية غير صحيحة لحقل الـ boolean
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('active', $validator->errors()->toArray());
  }
}
