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

  /*
  |--------------------------------------------------------------------------
  | Regressions
  |--------------------------------------------------------------------------
  */

  public function test_it_prefers_the_resolved_project_over_a_client_supplied_id(): void
  {
    // ResolveProject merges the resolved project into the request; a caller
    // passing their own project_id must not win.
    $request = new Request(['project_id' => 999]);
    $request->merge(['project' => ['id' => 7]]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    $this->assertEquals(7, $dto->projectId);
  }

  public function test_it_refuses_to_run_when_no_project_was_resolved(): void
  {
    $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    AnalyticsFilterDTO::fromRequest(new Request());
  }

  public function test_it_caps_the_limit(): void
  {
    // limit reaches both ->limit() and the cache key, so it has to be bounded.
    $request = new Request(['project_id' => 1, 'limit' => 999999999]);

    $dto = AnalyticsFilterDTO::fromRequest($request);

    $this->assertEquals(AnalyticsFilterDTO::MAX_LIMIT, $dto->limit);
  }

  /**
   * @dataProvider invalidFilterProvider
   */
  public function test_the_filter_request_rejects_invalid_filters(array $payload, string $invalidKey): void
  {
    $rules = (new \App\Domains\E_Commerce\Analytics\Requests\AnalyticsFilterRequest)->rules();

    $validator = \Illuminate\Support\Facades\Validator::make($payload, $rules);

    $this->assertTrue($validator->fails());
    $this->assertTrue($validator->errors()->has($invalidKey));
  }

  public static function invalidFilterProvider(): array
  {
    return [
      'garbage from' => [['from' => 'NOT-A-DATE'], 'from'],
      'inverted range' => [['from' => '2030-01-01', 'to' => '2020-01-01'], 'to'],
      'oversized limit' => [['limit' => 999999999], 'limit'],
      'unknown period' => [['period' => 'hourly'], 'period'],
    ];
  }
}
