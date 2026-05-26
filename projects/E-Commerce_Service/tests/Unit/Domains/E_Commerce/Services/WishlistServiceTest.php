<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\WishlistService;
use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistRepositoryInterface;
use App\Models\Wishlist;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test;

class WishlistServiceTest extends TestCase
{
  private MockInterface $repositoryMock;
  private WishlistService $service;

  protected function setUp(): void
  {
    parent::setUp();
    $this->repositoryMock = $this->mock(WishlistRepositoryInterface::class);
    $this->service = new WishlistService($this->repositoryMock);
  }

  // ─── 1. اختبارات التحقق من الملكية (validateWishlistOwnership) ──────────

  #[Test]
  public function it_throws_exception_if_no_owner_is_provided()
  {
    // اختبار السطر 28-31: لا يوجد مستخدم ولا ضيف
    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('Wishlist must belong to either user or guest.');

    $reflection = new \ReflectionClass(WishlistService::class);
    $method = $reflection->getMethod('validateWishlistOwnership');
    $method->setAccessible(true);

    $method->invoke($this->service, null, null);
  }

  #[Test]
  public function it_throws_exception_if_both_user_and_guest_are_provided()
  {
    // اختبار السطر 21-25: وجود مستخدم وضيف معاً
    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('Wishlist cannot belong to both user and guest.');

    $reflection = new \ReflectionClass(WishlistService::class);
    $method = $reflection->getMethod('validateWishlistOwnership');
    $method->setAccessible(true);

    $method->invoke($this->service, 1, 'guest-token');
  }

  // ─── 2. اختبارات الإنشاء (Creation) ────────────────────────────────────────

  #[Test]
  public function it_can_create_wishlist_for_guest()
  {
    $guestToken = 'guest-123';
    $data = ['name' => 'Guest List'];
    $mockWishlist = new Wishlist();

    $this->repositoryMock->shouldReceive('getByGuestToken')
      ->once()->with($guestToken)->andReturn(new Collection());

    $this->repositoryMock->shouldReceive('create')
      ->once()->andReturn($mockWishlist);

    $result = $this->service->createForGuest($guestToken, $data);

    $this->assertSame($mockWishlist, $result);
  }

  // ─── 3. اختبارات الجلب (Retrieval) ──────────────────────────────────────────

  #[Test]
  public function it_can_get_user_wishlists()
  {
    $collection = new Collection([new Wishlist()]);
    $this->repositoryMock->shouldReceive('getByUserId')->with(1)->once()->andReturn($collection);

    $result = $this->service->getUserWishlists(1);
    $this->assertCount(1, $result);
  }

  #[Test]
  public function it_can_get_guest_wishlists()
  {
    $collection = new Collection([new Wishlist()]);
    $this->repositoryMock->shouldReceive('getByGuestToken')->with('token')->once()->andReturn($collection);

    $result = $this->service->getGuestWishlists('token');
    $this->assertCount(1, $result);
  }

  #[Test]
  public function it_returns_wishlist_object_from_get_user_wishlist_or_fail()
  {
    $wishlist = new Wishlist();
    $wishlist->id = 10; // تعيين المعرف يدوياً هنا 🔥

    $this->repositoryMock->shouldReceive('findByIdForUser')
      ->with(10, 1)
      ->once()
      ->andReturn($wishlist);

    $result = $this->service->getUserWishlistOrFail(10, 1);

    $this->assertInstanceOf(Wishlist::class, $result);
    $this->assertEquals(10, $result->id);
  }

  #[Test]
  public function it_can_get_guest_wishlist_or_fail()
  {
    $wishlist = new Wishlist();
    $wishlist->id = 20; // تعيين المعرف يدوياً هنا 🔥

    $this->repositoryMock->shouldReceive('findByIdForGuest')
      ->with(20, 'token')
      ->once()
      ->andReturn($wishlist);

    $result = $this->service->getGuestWishlistOrFail(20, 'token');

    $this->assertEquals(20, $result->id);
  }

  #[Test]
  public function it_can_get_public_wishlist_by_share_token()
  {
    $wishlist = new Wishlist(['share_token' => 'uuid-123']);
    $this->repositoryMock->shouldReceive('findByShareToken')->with('uuid-123')->once()->andReturn($wishlist);

    $result = $this->service->getPublicWishlist('uuid-123');
    $this->assertSame($wishlist, $result);
  }

  // ─── 4. اختبارات التحديث (Update Logic) ──────────────────────────────────

  #[Test]
  public function it_generates_uuid_when_visibility_is_set_to_public()
  {
    // اختبار الأسطر 120-121
    $wishlist = Mockery::mock(new Wishlist(['share_token' => null]));
    $data = ['visibility' => 'public'];

    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($wishlist, Mockery::on(function ($arg) {
        // نتحقق أن الـ UUID تم توليده وأن الحالة أصبحت قابلة للمشاركة
        return isset($arg['share_token']) && strlen($arg['share_token']) === 36 && $arg['is_shareable'] === true;
      }));

    $wishlist->shouldReceive('refresh')->andReturn($wishlist);

    $result = $this->service->update($wishlist, $data);
    $this->assertSame($wishlist, $result);
  }

  // ─── 5. اختبارات الحذف (Delete) ───────────────────────────────────────────

  #[Test]
  public function it_can_delete_wishlist()
  {
    $wishlist = new Wishlist();
    $this->repositoryMock->shouldReceive('delete')->with($wishlist)->once()->andReturn(true);

    $result = $this->service->delete($wishlist);
    $this->assertTrue($result);
  }

  #[Test]
  public function it_creates_first_user_wishlist_as_default()
  {
    $userId = 1;
    $data = ['name' => 'First List', 'visibility' => 'private'];

    // محاكاة أن قاعدة البيانات فارغة لهذا المستخدم
    $this->repositoryMock->shouldReceive('getByUserId')
      ->once()
      ->with($userId)
      ->andReturn(new Collection());

    $this->repositoryMock->shouldReceive('create')
      ->once()
      ->with(Mockery::on(fn($arg) => $arg['is_default'] === true)) // السطر المستهدف
      ->andReturn(new Wishlist());

    $this->service->createForUser($userId, $data);
  }

  #[Test]
  public function it_triggers_unset_default_wishlist_when_updating_to_default()
  {
    $userId = 1;

    // 1. ننشئ الموك للموديل ونحدد الخصائص التي يحتاجها المنطق
    $wishlistMock = Mockery::mock(Wishlist::class)->makePartial();
    $wishlistMock->user_id = $userId;

    $data = ['is_default' => true];
    $oldDefault = new Wishlist();

    // 2. التوقعات (Expectations)

    // البحث عن القائمة القديمة
    $this->repositoryMock->shouldReceive('getDefaultByUserId')
      ->once()
      ->with($userId)
      ->andReturn($oldDefault);

    // تحديث القديمة لتصبح false
    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($oldDefault, ['is_default' => false]);

    // تحديث القائمة الحالية (استخدام نفس الـ $wishlistMock هنا هو السر 🔥)
    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($wishlistMock, $data);

    // محاكاة الـ refresh
    $wishlistMock->shouldReceive('refresh')->once()->andReturn($wishlistMock);

    // 3. التنفيذ
    $result = $this->service->update($wishlistMock, $data);

    $this->assertSame($wishlistMock, $result);
  }

  #[Test]
  public function it_can_generate_share_token_successfully()
  {
    // إنشاء موك للموديل
    $wishlistMock = Mockery::mock(Wishlist::class)->makePartial();

    // توقع استدعاء التحديث بالبيانات المطلوبة
    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($wishlistMock, Mockery::on(function ($data) {
        return $data['visibility'] === 'public' &&
          $data['is_shareable'] === true &&
          strlen($data['share_token']) === 36; // التحقق من أنه UUID
      }));

    // توقع استدعاء refresh
    $wishlistMock->shouldReceive('refresh')->once()->andReturn($wishlistMock);

    $result = $this->service->generateShareToken($wishlistMock);

    $this->assertSame($wishlistMock, $result);
  }

  #[Test]
  public function it_unsets_current_default_wishlist_for_guest_specifically()
  {
    // 1. إعداد قائمة ضيف حالية
    $guestToken = 'guest-uuid-123';
    $wishlistMock = Mockery::mock(Wishlist::class)->makePartial();
    $wishlistMock->guest_token = $guestToken;
    $wishlistMock->user_id = null; // للتأكد من أنه ضيف فقط

    // 2. إعداد القائمة الافتراضية القديمة التي يجب إلغاؤها
    $oldDefaultWishlist = new Wishlist();
    $oldDefaultWishlist->id = 500;

    // 3. التوقعات
    // البحث عن الافتراضية القديمة للضيف (السطر 170-171)
    $this->repositoryMock->shouldReceive('getDefaultByGuestToken')
      ->once()
      ->with($guestToken)
      ->andReturn($oldDefaultWishlist);

    // إلغاء الحالة الافتراضية للقديمة (السطر 174-176)
    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($oldDefaultWishlist, ['is_default' => false]);

    // تحديث القائمة الحالية (التي استدعت التحديث)
    $this->repositoryMock->shouldReceive('update')
      ->once()
      ->with($wishlistMock, ['is_default' => true]);

    $wishlistMock->shouldReceive('refresh')->once()->andReturn($wishlistMock);

    // 4. التنفيذ (تمرير is_default ليدخل في الشرط)
    $this->service->update($wishlistMock, ['is_default' => true]);
  }
}
