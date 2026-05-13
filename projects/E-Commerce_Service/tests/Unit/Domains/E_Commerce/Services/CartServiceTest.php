<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\CartService;
use App\Domains\E_Commerce\Actions\Cart\AddCartItemAction;
use App\Domains\E_Commerce\Actions\Cart\ClearCartAction;
use App\Domains\E_Commerce\Actions\Cart\GetCartAction;
use App\Domains\E_Commerce\Actions\Cart\RemoveCartItemsAction;
use App\Domains\E_Commerce\Actions\Cart\UpdateCartItemsAction;
use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Services\CMS\CMSApiClient;
use DomainException;
use Mockery;

beforeEach(function () {
  $this->addItemAction = Mockery::mock(AddCartItemAction::class);
  $this->getCartAction = Mockery::mock(GetCartAction::class);
  $this->cms = Mockery::mock(CMSApiClient::class);
  $this->updateCartItemsAction = Mockery::mock(UpdateCartItemsAction::class);
  $this->removeCartItemsAction = Mockery::mock(RemoveCartItemsAction::class);
  $this->clearCartAction = Mockery::mock(ClearCartAction::class);

  $this->service = new CartService(
    $this->addItemAction,
    $this->getCartAction,
    $this->cms,
    $this->updateCartItemsAction,
    $this->removeCartItemsAction,
    $this->clearCartAction
  );
});

afterEach(function () {
  Mockery::close();
});

it('adds items to cart', function () {
  $dto = new AddCartItemsDTO(1, 100, [['item_id' => 1, 'quantity' => 2]]);
  // نفترض أن addItems قد ترجع مصفوفة السلة أو نجاح العملية كـ array
  $this->addItemAction->shouldReceive('execute')->once()->with($dto)->andReturn(['status' => 'success']);

  expect($this->service->addItems($dto))->toBe(['status' => 'success']);
});

it('updates cart items', function () {
  $dto = new UpdateCartItemsDTO(1, 100, [['item_id' => 1, 'quantity' => 5]]);
  // تصحيح: إرجاع مصفوفة بدلاً من true
  $this->updateCartItemsAction->shouldReceive('execute')->once()->with($dto)->andReturn(['items' => []]);

  expect($this->service->updateItems($dto))->toBeArray();
});

it('removes cart items', function () {
  $dto = new RemoveCartItemsDTO(1, 100, [1, 2]);
  // تصحيح: إرجاع مصفوفة بدلاً من true
  $this->removeCartItemsAction->shouldReceive('execute')->once()->with($dto)->andReturn(['items' => []]);

  expect($this->service->removeItems($dto))->toBeArray();
});

it('clears the cart', function () {
  // إذا كان clearCart يرجع مصفوفة أيضاً، غيرها هنا. سأبقيها true إذا كان تعريف الـ Action يسمح.
  // لكن بناءً على الأخطاء السابقة، يفضل إرجاع مصفوفة فارغة للامتثال للعقد.
  $this->clearCartAction->shouldReceive('execute')->once()->with(1, 100)->andReturn([]);

  expect($this->service->clearCart(1, 100))->toBeArray();
});

it('throws exception if cart not found', function () {
    $this->getCartAction->shouldReceive('execute')
        ->once()
        ->with(1, 100)
        ->andReturn(null); // سيعمل الآن لأننا أضفنا ? قبل array

    $this->service->getCart(1, 100);
})->throws(DomainException::class, "You don't have a cart yet");

// بقية الاختبارات (it returns cart directly, it merges CMS) ستبقى كما هي لأنها كانت ناجحة (✓)

it('returns cart directly if it has no items', function () {
  $cartData = ['id' => 1, 'items' => []];
  $this->getCartAction->shouldReceive('execute')->once()->with(1, 100)->andReturn($cartData);

  // لا يجب استدعاء الـ CMS هنا
  $this->cms->shouldNotReceive('getEntriesByIds');

  expect($this->service->getCart(1, 100))->toBe($cartData);
});

it('merges CMS entries with cart items correctly', function () {
  $cartData = [
    'id' => 1,
    'items' => [
      ['item_id' => 10, 'quantity' => 1],
      ['item_id' => 20, 'quantity' => 2],
    ]
  ];

  $cmsEntries = [
    ['id' => 10, 'title' => 'Product A'],
    ['id' => 20, 'title' => 'Product B'],
  ];

  $this->getCartAction->shouldReceive('execute')->once()->with(1, 100)->andReturn($cartData);

  $this->cms->shouldReceive('getEntriesByIds')
    ->once()
    ->with([10, 20])
    ->andReturn($cmsEntries);

  $result = $this->service->getCart(1, 100);

  expect($result['items'])->toHaveCount(2);
  expect($result['items'][0]['entry']['title'])->toBe('Product A');
  expect($result['items'][1]['entry']['title'])->toBe('Product B');
});

it('handles missing CMS entries gracefully', function () {
  $cartData = [
    'id' => 1,
    'items' => [['item_id' => 99, 'quantity' => 1]]
  ];

  $this->getCartAction->shouldReceive('execute')->once()->with(1, 100)->andReturn($cartData);

  // محاكاة استجابة فارغة من الـ CMS لمنتج غير موجود
  $this->cms->shouldReceive('getEntriesByIds')->once()->andReturn([]);

  $result = $this->service->getCart(1, 100);

  expect($result['items'][0]['entry'])->toBeNull();
});
