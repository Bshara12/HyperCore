<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\StoreNotificationTemplateRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class StoreNotificationTemplateRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتهيئة الـ Request وتمرير الهيدرز والبيانات برمجياً
   */
  private function validate(array $data, array $headers = []): \Illuminate\Contracts\Validation\Validator
  {
    // استخدام الدالة الموروثة تلقائياً transformHeadersToServerVars من TestCase
    $request = StoreNotificationTemplateRequest::create(
      '/api/v1/templates',
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
      'key' => 'welcome_user',
      'channel' => 'email',
      'locale' => 'ar',
      'version' => 1,
      'subject_template' => 'أهلاً بك يا {name}',
      'body_template' => 'محتوى قالب الترحيب هنا بالكامل وهو إجباري',
      'variables_schema' => ['name' => 'string'],
      'defaults' => ['name' => 'المستخدم'],
      'is_active' => true,
    ];

    // التحقق من جلب الـ project_id من الـ Header بنجاح دمجه
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
    $this->assertArrayHasKey('key', $errors);
    $this->assertArrayHasKey('body_template', $errors);
  }

  #[Test]
  public function it_validates_channel_rule_in_with_invalid_channel()
  {
    $data = [
      'project_id' => 'project-123',
      'key' => 'order_shipped',
      'body_template' => 'Your order is shipped',
      'channel' => 'invalid-channel-name', // قناة خارج القائمة البيضاء المدعومة
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('channel', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_version_is_below_minimum_boundary()
  {
    $data = [
      'project_id' => 'project-123',
      'key' => 'order_shipped',
      'body_template' => 'Your order is shipped',
      'version' => 0, // أقل من القيمة المسموحة min:1
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('version', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_is_active_field_is_not_a_boolean()
  {
    $data = [
      'project_id' => 'project-123',
      'key' => 'order_shipped',
      'body_template' => 'Your order is shipped',
      'is_active' => 'not-a-boolean-value', // قيمة نصية غير صحيحة لحقل الـ boolean
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('is_active', $validator->errors()->toArray());
  }
}
