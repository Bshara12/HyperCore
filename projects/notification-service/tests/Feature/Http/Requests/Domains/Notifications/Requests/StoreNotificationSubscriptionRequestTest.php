<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\StoreNotificationSubscriptionRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class StoreNotificationSubscriptionRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتهيئة الـ Request وتمرير الهيدرز والبيانات برمجياً
   */
  private function validate(array $data, array $headers = []): \Illuminate\Contracts\Validation\Validator
  {
    // استخدام الدالة الموروثة تلقائياً transformHeadersToServerVars من TestCase
    $request = StoreNotificationSubscriptionRequest::create(
      '/api/v1/subscriptions',
      'POST',
      $data,
      [],
      [],
      $this->transformHeadersToServerVars($headers)
    );

    // ربط الـ Request بحاوية التطبيق لتفعيل دالة الـ Header
    $this->app->instance('request', $request);

    // تشغيل ميثود التجهيز prepareForValidation
    $reflector = new \ReflectionMethod($request, 'prepareForValidation');
    $reflector->setAccessible(true);
    $reflector->invoke($request);

    // تطبيق الـ Validator الفعلي للارافيل
    return Validator::make($request->all(), $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_valid_minimum_data_via_headers()
  {
    $data = [
      'topic_key' => 'billing.invoice_created',
      'channel_mask' => ['email', 'database'],
      'filters' => ['importance' => 'high'],
      'active' => true,
    ];

    // التحقق من جلب الـ project_id من الـ Header بنجاح
    $validator = $this->validate($data, ['X-Project-Id' => 'project-omega']);

    if ($validator->fails()) {
      $this->fail('Validation failed with errors: ' . json_encode($validator->errors()->toArray(), JSON_UNESCAPED_UNICODE));
    }

    $this->assertFalse($validator->fails());
  }

  #[Test]
  public function it_fails_if_required_fields_are_missing()
  {
    $validator = $this->validate([]);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('project_id', $errors);
    $this->assertArrayHasKey('topic_key', $errors);
  }

  #[Test]
  public function it_validates_channel_mask_rule_in_with_invalid_channels()
  {
    $data = [
      'project_id' => 'project-123',
      'topic_key' => 'notifications.orders',
      'channel_mask' => ['sms', 'slack'], // قنوات غير مدعومة خارج القائمة البيضاء
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
      'project_id' => 'project-123',
      'topic_key' => 'notifications.orders',
      'active' => 'not-a-boolean-value', // قيمة نصية خاطئة لحقل البوليان
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('active', $validator->errors()->toArray());
  }
}
