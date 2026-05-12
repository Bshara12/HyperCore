<?php

namespace Tests\Unit\App\Models;

use Tests\TestCase;
use App\Models\BookingCancellationPolicy;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class BookingCancellationPolicyTest extends TestCase
{
  use RefreshDatabase;
  #[Test]
  public function it_has_correct_fillable_and_casts()
  {
    $policy = new BookingCancellationPolicy([
      'hours_before' => '24',
      'refund_percentage' => '50',
      'description' => 'Partial refund'
    ]);

    // التحقق من الـ Casts (تحويل النصوص إلى أرقام صحيحة)
    $this->assertIsInt($policy->hours_before);
    $this->assertIsInt($policy->refund_percentage);
    $this->assertEquals(24, $policy->hours_before);
    $this->assertEquals(50, $policy->refund_percentage);
  }
  #[Test]
  public function it_belongs_to_a_resource()
  {
    // إنشاء المورد أولاً
    $resource = Resource::factory()->create();

    // إنشاء السياسة يدوياً بدون استخدام Factory
    $policy = BookingCancellationPolicy::create([
      'resource_id' => $resource->id,
      'hours_before' => 24,
      'refund_percentage' => 100,
      'description' => 'Test Policy'
    ]);

    $this->assertInstanceOf(Resource::class, $policy->resource);
    $this->assertEquals($resource->id, $policy->resource->id);
  }
  #[Test]
  public function it_calculates_refund_amount_correctly()
  {
    $policy = new BookingCancellationPolicy(['refund_percentage' => 75]);

    $amount = 100.00;
    $expectedRefund = 75.00;

    // التحقق من الحساب (75% من 100)
    $this->assertEquals($expectedRefund, $policy->calculateRefund($amount));

    // تجربة مبلغ مع كسور لضمان عمل التقريب (round)
    $policy->refund_percentage = 33;
    $amount = 150.75;
    // الحساب: 150.75 * 0.33 = 49.7475 -> تقريب لـ 49.75
    $this->assertEquals(49.75, $policy->calculateRefund($amount));
  }
}
