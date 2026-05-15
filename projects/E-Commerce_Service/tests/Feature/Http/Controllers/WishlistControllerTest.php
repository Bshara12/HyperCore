<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\WishlistService;
use App\Http\Controllers\WishlistController;
use App\Services\Auth\AuthApiClient;
use App\Models\Wishlist;
use App\Domains\E_Commerce\Requests\StoreWishlistRequest;
use App\Domains\E_Commerce\Requests\UpdateWishlistRequest;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class WishlistControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $wishlistServiceMock;
  private MockInterface $authApiClientMock;
  private array $mockUser = ['id' => 1, 'name' => 'Test User'];

  protected function setUp(): void
  {
    parent::setUp();
    $this->wishlistServiceMock = $this->mock(WishlistService::class);
    $this->authApiClientMock = $this->mock(AuthApiClient::class);
  }

  /**
   * ننشئ Model وهمي يحتوي على كافة الحقول التي يتوقعها WishlistDetailsDTO
   */
  private function createMockWishlist(array $data = [])
  {
    $wishlist = new Wishlist();

    // نستخدم forceFill لملء كافة الحقول بما فيها created_at
    $wishlist->forceFill(array_merge([
      'id' => 1,
      'name' => 'My Wishlist',
      'is_default' => false,
      'visibility' => 'private',
      'share_token' => 'token_123',
      'user_id' => 1,
      'description' => 'Test Description', // أضف الحقول المفقودة هنا
      'created_at' => now()->toDateTimeString(), // الوسيط رقم 10 الذي سبب الخطأ
      'updated_at' => now()->toDateTimeString(),
      'items' => new EloquentCollection([]),
    ], $data));

    return $wishlist;
  }

  private function addAuthHeader(Request $request)
  {
    $request->headers->set('Authorization', 'Bearer fake-token');
    return $request;
  }

  #[Test]
  public function it_returns_user_wishlists_successfully()
  {
    $mockWishlists = new EloquentCollection([$this->createMockWishlist()]);

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlists')->andReturn($mockWishlists);

    $request = $this->addAuthHeader(Request::create('/api/wishlists', 'GET'));

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->index($request);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_creates_wishlist_successfully()
  {
    $payload = ['name' => 'New Wishlist'];
    $mockWishlist = $this->createMockWishlist($payload);

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('createForUser')->andReturn($mockWishlist);

    $request = StoreWishlistRequest::create('/api/wishlists', 'POST', $payload);
    $this->addAuthHeader($request);
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->store($request);

    $this->assertEquals(201, $response->getStatusCode());
  }

  #[Test]
  public function it_updates_wishlist_successfully()
  {
    $wishlistId = 1;
    $payload = ['name' => 'Updated Name'];
    $mockWishlist = $this->createMockWishlist($payload);

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')->andReturn($mockWishlist);
    $this->wishlistServiceMock->shouldReceive('update')->andReturn($mockWishlist);

    $request = UpdateWishlistRequest::create("/api/wishlists/{$wishlistId}", 'PUT', $payload);
    $this->addAuthHeader($request);
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->update($request, $wishlistId);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_generates_share_link_successfully()
  {
    $wishlistId = 1;
    $mockWishlist = $this->createMockWishlist();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')->andReturn($mockWishlist);
    $this->wishlistServiceMock->shouldReceive('generateShareToken')->andReturn($mockWishlist);

    $request = $this->addAuthHeader(Request::create("/api/wishlists/{$wishlistId}/share", 'POST'));

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->generateShareLink($request, $wishlistId);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_shows_shared_wishlist_successfully()
  {
    $token = 'token_123';
    $mockWishlist = $this->createMockWishlist();

    $this->wishlistServiceMock->shouldReceive('getPublicWishlist')->with($token)->andReturn($mockWishlist);

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->showSharedWishlist($token);

    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_wishlist_details_successfully()
  {
    $wishlistId = 1;
    $mockWishlist = $this->createMockWishlist();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')
      ->with($wishlistId, $this->mockUser['id'])
      ->andReturn($mockWishlist);

    $request = $this->addAuthHeader(Request::create("/api/wishlists/{$wishlistId}", 'GET'));

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->show($request, $wishlistId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Wishlist fetched successfully', $response->getContent());
  }

  #[Test]
  public function it_deletes_wishlist_successfully()
  {
    $wishlistId = 1;
    $mockWishlist = $this->createMockWishlist();

    $this->authApiClientMock->shouldReceive('getUserFromToken')->andReturn($this->mockUser);
    $this->wishlistServiceMock->shouldReceive('getUserWishlistOrFail')
      ->with($wishlistId, $this->mockUser['id'])
      ->andReturn($mockWishlist);

    // نتوقع استدعاء دالة الحذف مرة واحدة
    $this->wishlistServiceMock->shouldReceive('delete')->once()->with($mockWishlist);

    $request = $this->addAuthHeader(Request::create("/api/wishlists/{$wishlistId}", 'DELETE'));

    $controller = new WishlistController($this->wishlistServiceMock, $this->authApiClientMock);
    $response = $controller->destroy($request, $wishlistId);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Wishlist deleted successfully', $response->getContent());
  }
}
