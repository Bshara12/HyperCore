<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\CreateNotificationRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class CreateNotificationRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتهيئة الـ Request وتمرير الهيدرز والبيانات برمجياً
   */
  private function validate(array $data, array $headers = []): \Illuminate\Contracts\Validation\Validator
  {
    // استخدام الدالة الموروثة تلقائياً transformHeadersToServerVars من TestCase
    $request = CreateNotificationRequest::create(
      '/api/v1/notifications',
      'POST',
      $data,
      [],
      [],
      $this->transformHeadersToServerVars($headers)
    );

    // ربط الـ Request الحالي بحاوية التطبيق لتفعيل دالة $this->header()
    $this->app->instance('request', $request);

    // تشغيل ميثود التجهيز prepareForValidation
    $reflector = new \ReflectionMethod($request, 'prepareForValidation');
    $reflector->setAccessible(true);
    $reflector->invoke($request);

    // تطبيق الـ Validator الفعلي للارافيل بقواعد الـ FormRequest
    return Validator::make($request->all(), $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_valid_minimum_data_via_headers()
  {
    $data = [
      'recipient' => [
        'type' => 'user',
        'id' => 'user-789'
      ],
      'source' => [
        'service' => 'auth',
        'type' => 'password_reset',
      ],
      'template_key' => 'reset_password_tpl',
      'channel' => ['email'],
    ];

    // فحص دمج الـ project_id من الـ Header بنجاح
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
    $this->assertArrayHasKey('recipient', $errors);
    $this->assertArrayHasKey('source', $errors);
    $this->assertArrayHasKey('channel', $errors);
  }

  #[Test]
  public function it_fails_if_recipient_sub_fields_are_invalid_or_missing()
  {
    $data = [
      'project_id' => 'project-123',
      'recipient' => [
        'type' => 'ghost-type', // قيمة غير مدعومة خارج الـ Rule::in
        // غياب حقل الـ id الإجباري
      ],
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('recipient.type', $errors);
    $this->assertArrayHasKey('recipient.id', $errors);
  }

  #[Test]
  public function it_fails_if_source_sub_fields_are_invalid_or_missing()
  {
    $data = [
      'project_id' => 'project-123',
      'source' => [
        'id' => 'some-id'
      ],
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('source.service', $errors);
    $this->assertArrayHasKey('source.type', $errors);
  }

  #[Test]
  public function it_validates_required_without_template_key_rule()
  {
    $data = [
      'project_id' => 'project-123',
      'template_key' => null, // طالما فارغ، يصبح الـ title إلزامياً فوراً
      'title' => null,
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('title', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_priority_min_and_max_boundaries()
  {
    $data = [
      'project_id' => 'project-123',
      'priority' => 300, // أعلى من الحد الأقصى المسموح 255
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('priority', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_scheduled_at_is_not_an_after_now_date()
  {
    $data = [
      'project_id' => 'project-123',
      'scheduled_at' => now()->subMinutes(10)->toDateTimeString(), // تاريخ قديم بالخطأ
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('scheduled_at', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_channels_rule_in_and_minimum_size()
  {
    $data = [
      'project_id' => 'project-123',
      'channel' => ['sms', 'push-notification'], // قنوات غير مدعومة خارج القائمة البيضاء
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('channel.0', $validator->errors()->toArray());
  }
}
