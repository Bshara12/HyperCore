<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\WishlistService;
use App\Domains\E_Commerce\Services\WishlistItemService;
use App\Http\Controllers\WishlistItemController;
use App\Services\Auth\AuthApiClient;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Domains\E_Commerce\Requests\AddWishlistItemRequest;
use App\Domains\E_Commerce\Requests\ReorderWishlistItemsRequest;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class WishlistItemControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $wishlistServiceMock;
  private MockInterface $wishlistItemServiceMock;
  private MockInterface $authApiClientMock;
  private array $mockUser = ['id' => 1];

  protected function setUp(): void
  {
    parent::setUp();
    $this->wishlistServiceMock = $this->mock(WishlistService::class);
    $this->wishlistItemServiceMock = $this->mock(WishlistItemService::class);
    $this->authApiClientMock = $this->mock(AuthApiClient::class);
  }

  private function createMockWishlist(array $data = [])
  {
    $wishlist = new Wishlist();
    $wishlist->forceFill(array_merge([
      'id' => 1,
      'name' => 'Tech List',
      'is_default' => true,
      'visibility' => 'public',
      'is_shareable' => true,
      'share_token' => 'abc',
      'user_id' => 1,
      'guest_token' => null,
      'created_at' => now(),
      'updated_at' => now(),
    ], $data));

    // ربط علاقة وهمية للـ items لتجنب أخطاء الـ DTO
    $wishlist->setRelation('items', new EloquentCollection([]));
    return $wishlist;
  }

  private function createMockItem(array $data = [])
  {
    $item = new WishlistItem();
    $item->forceFill(array_merge([
      'id' => 101,
      'product_id' => 50,
      'variant_id' => null,
      'sort_order' => 1,
      'product_snapshot' => ['name' => 'Laptop'],
    ], $data));
    return $item;
  }

  #[Test]
  public function it_can_add_item_to_wishlist()
  {
    $wishlistId = 1;
    $payload = ['product_id' => 50];
    $mockWishlist = $this->createMockWishlist();
    $mockItem = $this->createMockItem();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);

    // يتم استدعاء getUserWishlistOrFail مرتين في الـ store
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')->twice()->andReturn($mockWishlist);

    $this->wishlistItemServiceMock->shouldReceive('addItem')->once()->andReturn($mockItem);

    $request = AddWishlistItemRequest::create("/api/wishlists/{$wishlistId}/items", 'POST', $payload);
    $request->headers->set('Authorization', 'Bearer token');
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $controller = new WishlistItemController(
      $this->wishlistServiceMock,
      $this->wishlistItemServiceMock,
      $this->authApiClientMock
    );

    $response = $controller->store($request, $wishlistId);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertStringContainsString('Item added to wishlist successfully', $response->getContent());
  }

  #[Test]
  public function it_can_remove_item_from_wishlist()
  {
    $wishlistId = 1;
    $itemId = 101;
    $mockWishlist = $this->createMockWishlist();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')->andReturn($mockWishlist);
    $this->wishlistItemServiceMock->shouldReceive('removeItem')->once();

    $request = Request::create("/api/wishlists/{$wishlistId}/items/{$itemId}", 'DELETE');
    $request->headers->set('Authorization', 'Bearer token');

    $controller = new WishlistItemController(
      $this->wishlistServiceMock,
      $this->wishlistItemServiceMock,
      $this->authApiClientMock
    );

    $response = $controller->destroy($request, $wishlistId, $itemId);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_can_reorder_items()
  {
    $wishlistId = 1;
    // تعديل الـ payload ليتضمن مفتاح id لكل عنصر كما يطلب الـ Validation
    $payload = [
      'items' => [
        ['id' => 101, 'sort_order' => 1],
        ['id' => 102, 'sort_order' => 2]
      ]
    ];

    $mockWishlist = $this->createMockWishlist();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);

    // الـ Controller يستدعي getUserWishlistOrFail مرتين
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')
      ->times(2)
      ->andReturn($mockWishlist);

    $this->wishlistItemServiceMock->shouldReceive('reorderItems')->once();

    $request = ReorderWishlistItemsRequest::create("/api/wishlists/{$wishlistId}/reorder", 'POST', $payload);
    $request->headers->set('Authorization', 'Bearer token');
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $controller = new WishlistItemController(
      $this->wishlistServiceMock,
      $this->wishlistItemServiceMock,
      $this->authApiClientMock
    );

    $response = $controller->reorder($request, $wishlistId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Wishlist items reordered successfully', $response->getContent());
  }

  #[Test]
  public function it_can_move_item_to_cart()
  {
    $wishlistId = 1;
    $itemId = 101;
    $projectId = 7;

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')->andReturn($this->createMockWishlist());

    $this->wishlistItemServiceMock->shouldReceive('moveToCart')
      ->once()
      ->withAnyArgs();

    $request = Request::create("/api/wishlists/{$wishlistId}/items/{$itemId}/move-to-cart", 'POST');
    $request->headers->set('Authorization', 'Bearer token');
    $request->headers->set('X-Project-Id', $projectId);

    $controller = new WishlistItemController(
      $this->wishlistServiceMock,
      $this->wishlistItemServiceMock,
      $this->authApiClientMock
    );

    $response = $controller->moveToCart($request, $wishlistId, $itemId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Item moved to cart successfully', $response->getContent());
  }
}
