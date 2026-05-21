<?php

namespace Tests\Feature\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\WebhookDeliveryCallbackRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class WebhookDeliveryCallbackRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new WebhookDeliveryCallbackRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_complete_valid_data()
  {
    $data = [
      'delivery_id' => 'dlv-abc-12345',
      'provider_message_id' => 'msg-998877',
      'status' => 'delivered', // حالة صحيحة متواجدة داخل الـ Rule::in
      'error_code' => null,
      'error_message' => null,
      'payload' => ['attempt' => 1, 'response_time_ms' => 142]
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails(), 'Validation should pass with valid provider callback data.');
  }

  #[Test]
  public function it_passes_validation_with_failed_status_and_error_details()
  {
    $data = [
      'delivery_id' => 'dlv-xyz-67890',
      'status' => 'failed', // حالة صحيحة أخرى
      'error_code' => 'CONN_TIMEOUT',
      'error_message' => 'The destination server did not respond in time.',
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails());
  }

  #[Test]
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = $this->validate([]);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('delivery_id', $errors);
    $this->assertArrayHasKey('status', $errors);
  }

  #[Test]
  public function it_validates_status_rule_in_with_invalid_status()
  {
    $data = [
      'delivery_id' => 'dlv-123',
      'status' => 'unknown-status', // حالة غير مدعومة خارج القائمة البيضاء
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('status', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_payload_is_not_an_array()
  {
    $data = [
      'delivery_id' => 'dlv-123',
      'status' => 'sent',
      'payload' => 'not-an-array-string' // تمرير نص بدلاً من مصفوفة
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('payload', $validator->errors()->toArray());
  }
}
