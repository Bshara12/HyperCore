<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class UpdateNotificationPreferencesRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new UpdateNotificationPreferencesRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_valid_preferences_data()
  {
    $data = [
      'preferences' => [
        [
          'topic_key' => 'billing.invoices',
          'channel' => 'email',
          'enabled' => true,
          'mute_until' => now()->addDays(5)->toDateTimeString(),
          'quiet_hours' => ['start' => '22:00', 'end' => '08:00'],
          'delivery_mode' => 'digest',
          'locale' => 'ar',
          'metadata' => ['device' => 'mobile']
        ],
        [
          'topic_key' => null, // حقل اختياري
          'channel' => 'database',
          'enabled' => false,
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails(), 'Validation should have passed with complete valid payload.');
  }

  #[Test]
  public function it_fails_if_preferences_field_is_missing_or_empty()
  {
    // 1. مصفوفة فارغة تماماً
    $validator1 = $this->validate([]);
    $this->assertTrue($validator1->fails());
    $this->assertArrayHasKey('preferences', $validator1->errors()->toArray());

    // 2. تمرير حقل الـ preferences كمصفوفة فارغة أقل من الحد الأدنى min:1
    $validator2 = $this->validate(['preferences' => []]);
    $this->assertTrue($validator2->fails());
    $this->assertArrayHasKey('preferences', $validator2->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_nested_required_fields_are_missing()
  {
    $data = [
      'preferences' => [
        [
          'topic_key' => 'marketing.news'
          // غياب الـ channel والـ enabled الإجباريين
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('preferences.0.channel', $errors);
    $this->assertArrayHasKey('preferences.0.enabled', $errors);
  }

  #[Test]
  public function it_validates_nested_channel_rule_in_with_invalid_channels()
  {
    $data = [
      'preferences' => [
        [
          'channel' => 'slack', // قناة غير مدعومة خارج القائمة البيضاء
          'enabled' => true
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('preferences.0.channel', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_enabled_field_is_not_a_boolean()
  {
    $data = [
      'preferences' => [
        [
          'channel' => 'email',
          'enabled' => 'yes_enabled' // قيمة نصية غير صحيحة لحقل البوليان
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('preferences.0.enabled', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_nested_delivery_mode_rule_in()
  {
    $data = [
      'preferences' => [
        [
          'channel' => 'email',
          'enabled' => true,
          'delivery_mode' => 'delayed' // قيمة مرفوضة خارج الـ Rule::in المسموح (instant, digest, muted)
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('preferences.0.delivery_mode', $validator->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_mute_until_is_not_a_valid_date()
  {
    $data = [
      'preferences' => [
        [
          'channel' => 'broadcast',
          'enabled' => true,
          'mute_until' => 'invalid-date-string' // نص عشوائي وليس تاريخاً صالحاً
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('preferences.0.mute_until', $validator->errors()->toArray());
  }
}
