<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\ProcessRefundAction;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\CMS\CMSApiClient;
use Mockery;

test('it returns early if booking has no payment id', function () {
  $booking = (object) ['payment_id' => null];
  $cmsClient = Mockery::mock(CMSApiClient::class);

  $cmsClient->shouldNotReceive('refundBooking');

  $action = new ProcessRefundAction($cmsClient);
  $action->execute($booking, 100.0);

  expect(true)->toBeTrue();
});

test('it returns early if resource type is free', function () {
  $resource = (new Resource())->forceFill(['payment_type' => 'free']);
  $booking = (object) [
    'payment_id' => 'PAY-123',
    'resource' => $resource
  ];

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $cmsClient->shouldNotReceive('refundBooking');

  $action = new ProcessRefundAction($cmsClient);
  $action->execute($booking, 100.0);

  expect(true)->toBeTrue();
});

test('it returns early if refund amount is zero or negative', function () {
  $resource = (new Resource())->forceFill(['payment_type' => 'paid']);
  $booking = (object) [
    'payment_id' => 'PAY-123',
    'resource' => $resource
  ];

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $cmsClient->shouldNotReceive('refundBooking');

  $action = new ProcessRefundAction($cmsClient);

  // تجربة مبلغ صفر
  $action->execute($booking, 0);
  // تجربة مبلغ سالب
  $action->execute($booking, -10.5);

  expect(true)->toBeTrue();
});

test('it calls cms client refund when all conditions are met', function () {
  $paymentId = 'PAY-999';
  $amount = 50.0;

  $resource = (new Resource())->forceFill(['payment_type' => 'paid']);
  $booking = (object) [
    'payment_id' => $paymentId,
    'resource' => $resource
  ];

  $cmsClient = Mockery::mock(CMSApiClient::class);

  // التأكد من استدعاء الـ API بالبارامترات الصحيحة
  $cmsClient->shouldReceive('refundBooking')
    ->once()
    ->with([
      'payment_id' => $paymentId,
      'amount' => $amount,
    ]);

  $action = new ProcessRefundAction($cmsClient);
  $action->execute($booking, $amount);

  expect(true)->toBeTrue();
});
