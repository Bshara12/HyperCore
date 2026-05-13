<?php

namespace Tests\Unit\Domains\E_Commerce\Analytics\DTOs;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use Illuminate\Http\Request;
use Tests\TestCase;

class AnalyticsFilterDTOTest extends TestCase
{
  /**
   * 1. Test: fromRequest with specific data
   */
  public function test_from_request_creates_dto_with_provided_data(): void
  {
    $request = new Request([
      'from'       => '2026-01-01',
      'to'         => '2026-01-31',
      'period'     => 'monthly',
      'project_id' => 5,
      'limit'      => 20,
    ]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    $this->assertEquals('2026-01-01', $dto->from);
    $this->assertEquals('2026-01-31', $dto->to);
    $this->assertEquals('monthly', $dto->period);
    $this->assertEquals(5, $dto->projectId);
    $this->assertEquals(20, $dto->limit);
  }

  /**
   * 2. Test: fromRequest with default values
   */
  public function test_from_request_applies_default_values(): void
  {
    // نرسل الـ project_id فقط لأنه ليس له قيمة افتراضية في الكود
    $request = new Request(['project_id' => 10]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    $this->assertEquals(now()->subMonth()->format('Y-m-d'), $dto->from);
    $this->assertEquals(now()->format('Y-m-d'), $dto->to);
    $this->assertEquals('daily', $dto->period); // القيمة الافتراضية للـ period
    $this->assertEquals(10, $dto->projectId);
    $this->assertEquals(10, $dto->limit); // القيمة الافتراضية للـ limit
  }

  /**
   * 3. Test: validation of period input
   */
  public function test_from_request_falls_back_to_daily_for_invalid_period(): void
  {
    $request = new Request([
      'period'     => 'yearly', // قيمة غير مدعومة في الـ in_array
      'project_id' => 1
    ]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    // يجب أن يعود لـ daily حسب المنطق المكتوب في الـ DTO
    $this->assertEquals('daily', $dto->period);
  }
}
