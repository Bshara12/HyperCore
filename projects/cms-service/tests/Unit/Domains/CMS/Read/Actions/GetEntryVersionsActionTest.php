<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetEntryVersionsAction;
use App\Domains\CMS\Read\DTOs\EntryVersionDTO;
use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO;
use App\Domains\CMS\Read\Repositories\EntryVersionReadRepositoryInterface;

beforeEach(function () {
  $this->repo = mock(EntryVersionReadRepositoryInterface::class);
  $this->action = new GetEntryVersionsAction($this->repo);
});

test('it maps repository data to EntryVersionsListDTO and handles snapshots correctly', function () {
  // 1. بيانات تجريبية تحاكي استجابة الـ Repository
  $mockData = [
    'total' => 2,
    'page' => 1,
    'per_page' => 10,
    'items' => [
      [
        'id' => '1', // نص ليتم تحويله
        'data_entry_id' => '100',
        'version_number' => '5',
        'created_by' => '2',
        'created_at' => '2026-05-28 12:00:00',
        'snapshot' => '{"key": "value"}' // نص JSON (ليتم تحويله)
      ],
      [
        'id' => 2,
        'data_entry_id' => 101,
        'version_number' => 6,
        'created_by' => null,
        'created_at' => '2026-05-28 13:00:00',
        'snapshot' => ['existing' => 'array'] // مصفوفة جاهزة
      ]
    ]
  ];

  // 2. إعداد الـ Mock
  $this->repo->shouldReceive('listForEntrySlug')
    ->once()
    ->with(1, 'test-slug', 1, 10, true)
    ->andReturn($mockData);

  // 3. التنفيذ
  $result = $this->action->execute(1, 'test-slug', 1, 10, true);

  // 4. التأكيد
  expect($result)->toBeInstanceOf(EntryVersionsListDTO::class)
    ->and($result->total)->toBe(2)
    ->and($result->items)->toHaveCount(2);

  // التحقق من الـ DTO الأول (حالة الـ JSON String)
  expect($result->items[0])->toBeInstanceOf(EntryVersionDTO::class)
    ->and($result->items[0]->id)->toBe(1) // التأكد من الـ Casting لـ int
    ->and($result->items[0]->snapshot)->toBe(['key' => 'value']); // التأكد من تحويل JSON لـ array

  // التحقق من الـ DTO الثاني (حالة الـ Array الجاهزة و null)
  expect($result->items[1]->snapshot)->toBe(['existing' => 'array'])
    ->and($result->items[1]->created_by)->toBeNull();
});

test('it sets snapshot to null if the snapshot data is not a valid array', function () {
  // 1. إعداد بيانات تحتوي على snapshot غير صالح (رقم أو نص بسيط ليس JSON Array)
  // عند استخدام json_decode('123', true) النتيجة تكون int(123)، وهي ليست array
  $mockData = [
    'total' => 1,
    'page' => 1,
    'per_page' => 10,
    'items' => [
      [
        'id' => 3,
        'data_entry_id' => 10,
        'version_number' => 1,
        'created_by' => null,
        'created_at' => '2026-05-28 12:00:00',
        'snapshot' => '123'
      ]
    ]
  ];

  $this->repo->shouldReceive('listForEntrySlug')
    ->once()
    ->andReturn($mockData);

  // 2. التنفيذ
  $result = $this->action->execute(1, 'test-slug', 1, 10, true);

  // 3. التأكيد
  // هنا يجب أن نتحقق أن الـ snapshot أصبح null
  expect($result->items[0]->snapshot)->toBeNull();
});
