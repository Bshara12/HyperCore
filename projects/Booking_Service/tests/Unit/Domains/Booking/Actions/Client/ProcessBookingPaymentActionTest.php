<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\ProcessBookingPaymentAction;
use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Models\Booking;
use App\Models\Resource;
use App\Services\CMS\CMSApiClient;
use Mockery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it confirms booking immediately if resource is free', function () {
  // 1. التجهيز
  $resource = (new Resource())->forceFill(['payment_type' => 'free']);
  $booking = Mockery::mock(Booking::class)->makePartial();
  $booking->resource = $resource;

  $dto = Mockery::mock(CreateBookingDTO::class);

  // نتوقع تحديث الحالة إلى CONFIRMED
  $booking->shouldReceive('update')
    ->once()
    ->with([
      'status' => Booking::STATUS_CONFIRMED,
      'payment_id' => null,
    ])
    ->andReturn(true);

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $cmsClient->shouldNotReceive('chargeBooking'); // لا يجب استدعاء الدفع

  $action = new ProcessBookingPaymentAction($cmsClient);

  // 2. التنفيذ
  $result = $action->execute($booking, $dto);

  // 3. التحقق
  expect($result)->toBe($booking);
});

test('it confirms booking after successful payment charge', function () {
  // 1. التجهيز
  $resource = (new Resource())->forceFill(['payment_type' => 'paid']);
  $booking = Mockery::mock(Booking::class)->makePartial();
  $booking->resource = $resource;

  $dto = new CreateBookingDTO(
    resourceId: 1,
    userId: 10,
    userName: 'Ahmed',
    startAt: '2024-01-01 10:00:00',
    endAt: '2024-01-01 12:00:00',
    projectId: 1,
    amount: 200,
    currency: 'USD',
    gateway: 'stripe',
    gatewayToken: 'tok_123'
  );

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $cmsClient->shouldReceive('chargeBooking')
    ->once()
    ->with([
      'user_id' => 10,
      'user_name' => 'Ahmed',
      'project_id' => 1,
      'amount' => 200,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'token' => 'tok_123',
    ])
    ->andReturn(['payment_id' => 'PAY-999']);

  $booking->shouldReceive('update')
    ->once()
    ->with([
      'status' => Booking::STATUS_CONFIRMED,
      'payment_id' => 'PAY-999',
    ]);

  $action = new ProcessBookingPaymentAction($cmsClient);

  // 2. التنفيذ
  $action->execute($booking, $dto);

  expect(true)->toBeTrue();
});

test('it cancels booking and rethrows exception if payment fails', function () {
  // 1. التجهيز
  $resource = (new Resource())->forceFill(['payment_type' => 'paid']);
  $booking = Mockery::mock(Booking::class)->makePartial();
  $booking->resource = $resource;

  $dto = Mockery::mock(CreateBookingDTO::class);
  $dto->userId = 1; // تعيين القيم التي يحتاجها الـ Action من الـ DTO
  $dto->userName = 'Ahmed';
  $dto->projectId = 1;
  $dto->amount = 100;
  $dto->currency = 'USD';
  $dto->gateway = 'stripe';
  $dto->gatewayToken = 'invalid_token';

  $cmsClient = Mockery::mock(CMSApiClient::class);
  $cmsClient->shouldReceive('chargeBooking')
    ->andThrow(new \Exception('Payment Declined'));

  // نتوقع تحديث الحالة إلى CANCELLED عند فشل الدفع
  $booking->shouldReceive('update')
    ->once()
    ->with([
      'status' => Booking::STATUS_CANCELLED,
    ]);

  $action = new ProcessBookingPaymentAction($cmsClient);

  // 2. التحقق من رمي الاستثناء وتغيير الحالة
  expect(fn() => $action->execute($booking, $dto))
    ->toThrow(\Exception::class, 'Payment Declined');
});
