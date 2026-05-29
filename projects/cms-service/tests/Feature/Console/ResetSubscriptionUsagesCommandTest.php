<?php

use App\Domains\Subscription\Services\UsageResetService;

test('it resets subscription usages and shows success message', function () {
  // 1. عمل Mock للـ Service
  $serviceMock = Mockery::mock(UsageResetService::class);

  // 2. التأكد من استدعاء دالة handle مرة واحدة
  $serviceMock->shouldReceive('handle')
    ->once();

  // 3. حقن الـ Mock في حاوية لارافيل
  $this->app->instance(UsageResetService::class, $serviceMock);

  // 4. تنفيذ الأمر والتحقق من المخرجات
  $this->artisan('subscriptions:reset-usages')
    ->expectsOutput('Subscription usages reset successfully.')
    ->assertExitCode(0);
});
