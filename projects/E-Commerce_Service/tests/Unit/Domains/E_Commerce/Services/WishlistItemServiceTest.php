<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\WishlistItemService;
use App\Domains\E_Commerce\Services\CartService;
use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistItemRepositoryInterface;
use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Services\CMS\CMSApiClient;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
  $this->wishlistItemRepository = Mockery::mock(WishlistItemRepositoryInterface::class);
  $this->cmsApiClient = Mockery::mock(CMSApiClient::class);
  $this->cartService = Mockery::mock(CartService::class);

  $this->service = new WishlistItemService(
    $this->wishlistItemRepository,
    $this->cmsApiClient,
    $this->cartService
  );
});

afterEach(function () {
  Mockery::close();
});

/**
 * دالة مساعدة لإنشاء موديل Wishlist بـ ID محدد
 * لأن الموديلات الجديدة في الـ Unit Tests لا تملك ID تلقائياً
 */
function createWishlistWithId(int $id): Wishlist
{
  $wishlist = new Wishlist();
  $wishlist->id = $id;
  return $wishlist;
}

it('returns wishlist items for a given wishlist', function () {
  $wishlist = createWishlistWithId(1);
  $collection = new Collection([new WishlistItem()]);

  $this->wishlistItemRepository->shouldReceive('getByWishlistId')
    ->once()
    ->with(1)
    ->andReturn($collection);

  expect($this->service->getWishlistItems($wishlist))->toBe($collection);
});

it('throws 422 error if product already exists in wishlist', function () {
  $wishlist = createWishlistWithId(1);
  $data = ['product_id' => 10, 'variant_id' => null];

  $this->wishlistItemRepository->shouldReceive('existsInWishlist')
    ->once()
    ->with(1, 10, null)
    ->andReturn(true);

  expect(fn() => $this->service->addItem($wishlist, $data))
    ->toThrow(HttpException::class, 'Product already exists in wishlist.');
});

it('throws 404 if product not found in CMS', function () {
  $wishlist = createWishlistWithId(1);
  $data = ['product_id' => 10];

  $this->wishlistItemRepository->shouldReceive('existsInWishlist')->andReturn(false);
  $this->cmsApiClient->shouldReceive('getEntriesByDataType')->andReturn([]);

  expect(fn() => $this->service->addItem($wishlist, $data))
    ->toThrow(HttpException::class, 'Product not found.');
});

it('adds product to wishlist successfully with snapshot', function () {
  $wishlist = createWishlistWithId(1);
  $data = ['product_id' => 10, 'notify_on_price_drop' => true];
  $productFromCMS = [
    ['id' => 10, 'values' => ['title' => 'iPhone', 'price' => 1000]]
  ];

  $this->wishlistItemRepository->shouldReceive('existsInWishlist')->andReturn(false);
  $this->cmsApiClient->shouldReceive('getEntriesByDataType')->with('product')->andReturn($productFromCMS);
  $this->wishlistItemRepository->shouldReceive('getHighestSortOrder')->andReturn(5);

  $this->wishlistItemRepository->shouldReceive('create')->once()->andReturn(new WishlistItem());

  $result = $this->service->addItem($wishlist, $data);
  expect($result)->toBeInstanceOf(WishlistItem::class);
});

it('removes item from wishlist', function () {
  $wishlist = createWishlistWithId(1);
  $item = new WishlistItem();

  $this->wishlistItemRepository->shouldReceive('findByIdInWishlist')
    ->once()
    ->with(100, 1)
    ->andReturn($item);

  $this->wishlistItemRepository->shouldReceive('delete')->once()->with($item)->andReturn(true);

  expect($this->service->removeItem($wishlist, 100))->toBeTrue();
});

it('moves item to cart and deletes it from wishlist using transaction', function () {
  // استخدام andReturnUsing بدلاً من andImplementation الخاطئة
  DB::shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());

  $wishlist = createWishlistWithId(1);
  $item = new WishlistItem();
  $item->product_id = 50;
  $item->variant_id = null;

  $this->wishlistItemRepository->shouldReceive('findByIdInWishlist')->andReturn($item);
  $this->cartService->shouldReceive('addItems')->once();
  $this->wishlistItemRepository->shouldReceive('delete')->once()->with($item);

  $this->service->moveToCart($wishlist, 100, 1, 1);
});

it('reorders items successfully', function () {
  $wishlist = createWishlistWithId(1);
  $itemsData = [['item_id' => 1, 'sort_order' => 2]];
  $itemModel = new WishlistItem();

  $this->wishlistItemRepository->shouldReceive('findByIdInWishlist')->andReturn($itemModel);
  $this->wishlistItemRepository->shouldReceive('update')->once();

  $this->service->reorderItems($wishlist, $itemsData);
});

it('checks if product exists in wishlist', function () {
  $wishlist = createWishlistWithId(1);
  $this->wishlistItemRepository->shouldReceive('existsInWishlist')
    ->once()
    ->with(1, 10, null)
    ->andReturn(true);

  expect($this->service->exists($wishlist, 10))->toBeTrue();
});

it('skips non-existent items when reordering', function () {
  $wishlist = createWishlistWithId(1);

  // سنرسل عنصرين: واحد موجود وواحد غير موجود
  $itemsData = [
    ['item_id' => 99, 'sort_order' => 1], // نفترض أن هذا غير موجود
    ['item_id' => 1, 'sort_order' => 2],  // وهذا موجود
  ];

  $itemModel = new WishlistItem();

  // التوقعات (Expectations):
  $this->wishlistItemRepository->shouldReceive('findByIdInWishlist')
    ->with(99, 1)
    ->once()
    ->andReturn(null); // هنا سيحدث الـ continue

  $this->wishlistItemRepository->shouldReceive('findByIdInWishlist')
    ->with(1, 1)
    ->once()
    ->andReturn($itemModel);

  // التأكد أن التحديث تم فقط للعنصر الموجود
  $this->wishlistItemRepository->shouldReceive('update')
    ->once()
    ->with($itemModel, ['sort_order' => 2]);

  // التنفيذ
  $this->service->reorderItems($wishlist, $itemsData);

  // إذا لم يرمِ الكود أي خطأ واستمر، فهذا يعني أن الـ continue عملت بنجاح
});
