<?php

namespace Tests\Feature\Console\Commands;

use App\Domains\E_Commerce\Actions\Offers\ReEvaluateEntryPricesAction;
use Mockery;

// لا نحتاج لـ RefreshDatabase هنا إلا إذا كان الأكشن يلمس القاعدة مباشرة 
// ولكن بما أننا سنقوم بعمل Mock للأكشن، فالاختبار سيكون سريعاً ومعزولاً.

afterEach(function () {
  Mockery::close();
});

it('executes the re-evaluate action with formatted entries', function () {
  // 1. إعداد البيانات المدخلة (Arguments)
  $entriesIds = ['10', '20', '30'];

  // التنسيق المتوقع الذي يخرجه array_map
  $expectedPayload = [
    ['entry_id' => '10'],
    ['entry_id' => '20'],
    ['entry_id' => '30'],
  ];

  // 2. محاكاة الـ Action
  $mockAction = Mockery::mock(ReEvaluateEntryPricesAction::class);

  // نتوقع أن يستدعي الأمر تابع execute مرة واحدة مع المصفوفة المنسقة
  $mockAction->shouldReceive('execute')
    ->once()
    ->with($expectedPayload);

  // حقن الـ Mock في حاوية التطبيق
  $this->app->instance(ReEvaluateEntryPricesAction::class, $mockAction);

  // 3. تنفيذ الأمر والتحقق من المخرجات
  $this->artisan('offers:re-evaluate', ['entries' => $entriesIds])
    ->expectsOutput('Entries sent for re-evaluation: 10, 20, 30')
    ->expectsOutput('Re-evaluated prices for 3 entries.')
    ->assertExitCode(0);
});

it('handles a single entry correctly', function () {
  $mockAction = Mockery::mock(ReEvaluateEntryPricesAction::class);
  $mockAction->shouldReceive('execute')
    ->once()
    ->with([['entry_id' => '99']]);

  $this->app->instance(ReEvaluateEntryPricesAction::class, $mockAction);

  $this->artisan('offers:re-evaluate', ['entries' => ['99']])
    ->expectsOutput('Re-evaluated prices for 1 entries.')
    ->assertExitCode(0);
});
