<?php

use App\Console\Commands\AutoRenewSubscriptionsCommand;
use App\Domains\Subscription\Actions\Subscription\AutoRenewSubscriptionsAction;

test('it executes auto renew subscriptions action and displays success message', function () {
  // 1. عمل Mock للـ Action
  $actionMock = Mockery::mock(AutoRenewSubscriptionsAction::class);

  // 2. التوقع بأن دالة execute سيتم استدعاؤها مرة واحدة
  $actionMock->shouldReceive('execute')
    ->once()
    ->andReturn(null);

  // 3. حقن الـ Mock في الحاوية
  $this->app->instance(AutoRenewSubscriptionsAction::class, $actionMock);

  // 4. تنفيذ الأمر والتحقق من المخرجات
  $this->artisan('subscriptions:auto-renew')
    ->expectsOutput('Subscriptions processed successfully.')
    ->assertExitCode(0);
});
