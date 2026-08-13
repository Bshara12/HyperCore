<?php

use App\Services\BookingAnalyticsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
  Config::set('services.booking_service.url', 'https://api.booking-service.test');
  $this->client = new BookingAnalyticsClient();
});

afterEach(function () {
  Http::preventStrayRequests(false);
});

test('it fetches overview analytics successfully', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/overview*' => Http::response([
      'data' => ['total_bookings' => 150]
    ], 200),
  ]);

  $result = $this->client->getOverview('fake-token', 'project-1', ['period' => 'monthly']);
  expect($result)->toBe(['total_bookings' => 150]);
});

test('it fetches trend analytics successfully without data key', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/trend*' => Http::response([
      'status' => 'success',
      'values' => [10, 20]
    ], 200),
  ]);

  $result = $this->client->getTrend('fake-token', 'project-1');
  expect($result)->toBe(['status' => 'success', 'values' => [10, 20]]);
});

test('it fetches resource performance analytics successfully', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/resources*' => Http::response([
      'data' => ['resource' => 'room-A']
    ], 200),
  ]);

  $result = $this->client->getResourcePerformance('fake-token', 'project-1');
  expect($result)->toBe(['resource' => 'room-A']);
});

test('it fetches cancellations analytics successfully', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/cancellations*' => Http::response([
      'data' => ['cancellations_count' => 5]
    ], 200),
  ]);

  $result = $this->client->getCancellations('fake-token', 'project-1');
  expect($result)->toBe(['cancellations_count' => 5]);
});

test('it fetches peak times analytics successfully', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/peak-times*' => Http::response([
      'data' => ['peak_hour' => '14:00']
    ], 200),
  ]);

  $result = $this->client->getPeakTimes('fake-token', 'project-1');
  expect($result)->toBe(['peak_hour' => '14:00']);
});

test('it throws an exception when the booking service returns an error status', function () {
  Http::fake([
    'api.booking-service.test/booking/analytics/overview*' => Http::response('Service Unavailable', 503),
  ]);

  expect(fn() => $this->client->getOverview('fake-token', 'project-1'))
    ->toThrow(Exception::class, 'Booking Service Error: Service Unavailable');
});
