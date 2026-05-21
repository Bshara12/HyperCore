<?php

namespace Tests\Http\Requests\Domains\Notifications\Requests;

use Tests\TestCase;
use App\Http\Requests\Domains\Notifications\Requests\SyncNotificationSubscriptionsRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;

class SyncNotificationSubscriptionsRequestTest extends TestCase
{
  /**
   * ميثود مساعدة لتشغيل الـ Validator بناءً على قواعد الـ Request
   */
  private function validate(array $data): \Illuminate\Contracts\Validation\Validator
  {
    $request = new SyncNotificationSubscriptionsRequest();

    return Validator::make($data, $request->rules());
  }

  #[Test]
  public function it_passes_validation_with_valid_subscriptions_array()
  {
    $data = [
      'subscriptions' => [
        [
          'subscriber_type' => 'user',
          'subscriber_id' => 'user-101',
          'topic_key' => 'billing.invoice_paid',
          'channel_mask' => ['email', 'database'],
          'filters' => ['tier' => 'premium'],
          'active' => true,
        ],
        [
          'subscriber_type' => 'team',
          'subscriber_id' => 'team-202',
          'topic_key' => 'orders.created',
          'channel_mask' => null,
          'filters' => null,
          'active' => false,
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertFalse($validator->fails(), 'Validation should have passed with correct nested data.');
  }

  #[Test]
  public function it_fails_if_subscriptions_field_is_missing_or_empty()
  {
    // 1. مصفوفة فارغة تماماً
    $validator1 = $this->validate([]);
    $this->assertTrue($validator1->fails());
    $this->assertArrayHasKey('subscriptions', $validator1->errors()->toArray());

    // 2. حقل الـ subscriptions ممرر كمصفوفة فارغة أقل من الحد الأدنى min:1
    $validator2 = $this->validate(['subscriptions' => []]);
    $this->assertTrue($validator2->fails());
    $this->assertArrayHasKey('subscriptions', $validator2->errors()->toArray());
  }

  #[Test]
  public function it_fails_if_nested_required_fields_are_missing()
  {
    $data = [
      'subscriptions' => [
        [
          // مصفوفة فارغة للعنصر الأول للتأكد من إلزامية الحقول الفرعية
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('subscriptions.0.subscriber_type', $errors);
    $this->assertArrayHasKey('subscriptions.0.subscriber_id', $errors);
    $this->assertArrayHasKey('subscriptions.0.topic_key', $errors);
  }

  #[Test]
  public function it_validates_nested_subscriber_type_rule_in()
  {
    $data = [
      'subscriptions' => [
        [
          'subscriber_type' => 'invalid-role', // قيمة مرفوضة خارج الـ Rule::in
          'subscriber_id' => 'id-123',
          'topic_key' => 'test.topic',
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('subscriptions.0.subscriber_type', $validator->errors()->toArray());
  }

  #[Test]
  public function it_validates_nested_channel_mask_rule_in_with_invalid_channels()
  {
    $data = [
      'subscriptions' => [
        [
          'subscriber_type' => 'user',
          'subscriber_id' => 'id-123',
          'topic_key' => 'test.topic',
          'channel_mask' => ['slack', 'sms'] // قنوات غير مدعومة
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());

    $errors = $validator->errors()->toArray();
    $this->assertArrayHasKey('subscriptions.0.channel_mask.0', $errors);
    $this->assertArrayHasKey('subscriptions.0.channel_mask.1', $errors);
  }

  #[Test]
  public function it_fails_if_nested_active_field_is_not_a_boolean()
  {
    $data = [
      'subscriptions' => [
        [
          'subscriber_type' => 'admin',
          'subscriber_id' => 'id-555',
          'topic_key' => 'test.topic',
          'active' => 'string-value' // قيمة غير منطقية لحقل الـ boolean
        ]
      ]
    ];

    $validator = $this->validate($data);

    $this->assertTrue($validator->fails());
    $this->assertArrayHasKey('subscriptions.0.active', $validator->errors()->toArray());
  }
}
