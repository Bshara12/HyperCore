<?php

use App\Models\CircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can be created with fillable attributes', function () {
  $data = [
    'service_name' => 'auth-service',
    'state' => 'closed',
    'failure_count' => 0,
    'failure_threshold' => 5,
  ];

  $cb = CircuitBreaker::create($data);

  expect($cb->service_name)->toBe('auth-service')
    ->and($cb->failure_threshold)->toBe(5)
    ->and($cb->state)->toBe('closed');
});

test('it can update state and failure count', function () {
  $cb = CircuitBreaker::create([
    'service_name' => 'payment-service',
    'state' => 'closed',
    'failure_count' => 0,
    'failure_threshold' => 3,
  ]);

  $cb->update([
    'state' => 'open',
    'failure_count' => 3,
    'opened_at' => now(),
  ]);

  $cb->refresh();

  expect($cb->state)->toBe('open')
    ->and($cb->failure_count)->toBe(3);
});
