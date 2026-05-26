<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\CreateBulkNotificationRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class CreateBulkNotificationRequestTest extends TestCase
{
  /**
   * ميثود مساعدة مطورة لتهيئة الـ Request وربط الهيدرز بشكل صحيح برمجياً
   */
  private function validate(array $data, array $headers = []): \Illuminate\Contracts\Validation\Validator
  {
    // إنشاء الـ Request باستخدام ميثود create الرسمية والاستفادة من دالة لارافيل الموروثة لتجهيز الهيدرز
    $request = CreateBulkNotificationRequest::create(
      '/api/v1/notifications/bulk',
      'POST',
      $data,
      [],
      [],
      $this->transformHeadersToServerVars($headers) // نستخدم الدالة الموروثة مباشرة من الـ TestCase
    );

    // ربط كائن الـ Request الحالي بـ Container التطبيق لكي تعمل دالة $this->header() بنجاح
    $this->app->instance('request', $request);

    // استدعاء الميثود المحمية prepareForValidation
    $reflector = new \ReflectionMethod($request, 'prepareForValidation');
    $reflector->setAccessible(true);
    $reflector->invoke($request);

    // تشغيل الـ Validator الفعلي للارافيل بناء على قواعد الـ Request
    return Validator::make($request->all(), $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_valid_minimum_data_via_headers()
  {
    $data = [
      'source' => [
        'service' => 'billing',
        'type' => 'invoice',
        'id' => 'inv-123'
      ],
      'audience' => [
        'type' => 'topic',
        'topic_key' => 'billing.invoice_paid',
        'recipients' => null
      ],
      'template_key' => 'welcome_template',
      'title' => null,
      'body' => 'محتوى الإشعار',
      'channel' => ['database', 'email'],
      'data' => [],
      'metadata' => []
    ];

    // تمرير الـ X-Project-Id عبر الـ Header
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
    $this->assertArrayHasKey('source', $errors);
    $this->assertArrayHasKey('audience', $errors);
    $this->assertArrayHasKey('channel', $errors);
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
  public function it_validates_audience_type_rule_in()
  {
    $data = [
      'project_id' => 'project-123',
      'audience' => [
        'type' => 'invalid-type-here',
      ],
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('audience.type', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_recipients_conditional_rules_successfully()
  {
    $data = [
      'project_id' => 'project-123',
      'audience' => [
        'type' => 'custom',
        'recipients' => [
          [
            'type' => 'ghost-role',
          ]
        ]
      ],
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('audience.recipients.0.type', $errors);
    $this->assertArrayHasKey('audience.recipients.0.id', $errors);
  }

  #[Test]
  public function it_validates_required_without_template_key_rule()
  {
    $data = [
      'project_id' => 'project-123',
      'template_key' => null,
      'title' => null,
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('title', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_channels_rule_in_and_minimum_size()
  {
    $data = [
      'project_id' => 'project-123',
      'channel' => ['fax', 'carrier-pigeon'],
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('channel.0', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_scheduled_at_is_not_an_after_now_date()
  {
    $data = [
      'project_id' => 'project-123',
      'scheduled_at' => now()->subDay()->toDateTimeString(),
    ];

    $validator = $this->validate($data);
    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('scheduled_at', $validator->errors()->toArray());
  }
}
