<?php

namespace Tests\Feature\Domains\E_Commerce\Actions\Stock;

use App\Domains\E_Commerce\Actions\Stock\UpdateStockInCMSAction;
use App\Services\CMS\CMSApiClient;
use Mockery;

beforeEach(function () {
  $this->cms = Mockery::mock(CMSApiClient::class);
  $this->action = new UpdateStockInCMSAction($this->cms);
});

it('decrements stock successfully when enough stock is available', function () {
  // 1. البيانات القادمة من السلة (Cart)
  $items = [
    [
      'slug' => 'iphone-15',
      'title' => 'iPhone 15',
      'count' => 10, // هذا الحقل موجود للتأكد من تخطي الـ skip
      'quantity' => 2
    ]
  ];

  // 2. محاكاة جلب القيم الحالية من الـ CMS
  $this->cms->shouldReceive('getEntryValuesById')
    ->once()
    ->with('iphone-15')
    ->andReturn([
      'values' => ['count' => 10]
    ]);

  // 3. التوقع: استدعاء decrementStock بالمصفوفة الصحيحة
  $this->cms->shouldReceive('decrementStock')
    ->once()
    ->with(Mockery::on(function ($argument) {
      return count($argument) === 1 && $argument[0]['slug'] === 'iphone-15';
    }));

  $this->action->execute($items);
});

it('throws an exception if requested quantity exceeds available stock', function () {
  $items = [
    [
      'slug' => 'macbook-pro',
      'title' => 'MacBook Pro',
      'count' => 1,
      'quantity' => 5 // يطلب 5 والمخزون 3
    ]
  ];

  $this->cms->shouldReceive('getEntryValuesById')
    ->andReturn([
      'values' => ['count' => 3]
    ]);

  // لا ينبغي الوصول لمرحلة الـ decrement في حال وجود خطأ
  $this->cms->shouldNotReceive('decrementStock');

  expect(fn() => $this->action->execute($items))
    ->toThrow(\Exception::class, 'Stock changed! Product MacBook Pro now has only 3');
});

it('skips items that do not have a stock count', function () {
  $items = [
    [
      'slug' => 'service-item',
      'title' => 'Consultation',
      'count' => null, // منتج ليس له مخزون (خدمة مثلاً)
      'quantity' => 1
    ]
  ];

  // لا يجب استدعاء getEntryValuesById لأننا سنعمل continue
  $this->cms->shouldNotReceive('getEntryValuesById');

  // سيتم استدعاء decrementStock بمصفوفة فارغة لأن الـ item سقط بالـ skip
  $this->cms->shouldReceive('decrementStock')->with([]);

  $this->action->execute($items);
});
