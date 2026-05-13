<?php

namespace Tests\Unit\Domains\E_Commerce\Support;

use App\Domains\E_Commerce\Support\CacheKeys;

it('has correct TTL constants', function () {
  expect(CacheKeys::TTL_SHORT)->toBe(300)
    ->and(CacheKeys::TTL_MEDIUM)->toBe(3600)
    ->and(CacheKeys::TTL_LONG)->toBe(86400);
});

describe('Offer Cache Keys', function () {
  it('generates correct offers key', function () {
    expect(CacheKeys::offers(123))->toBe('project:123:offers');
  });

  it('generates correct offer by collection id key', function () {
    expect(CacheKeys::offer(456))->toBe('offers:collection:456');
  });

  it('generates correct offer by slug key', function () {
    expect(CacheKeys::offerBySlug('summer-sale'))->toBe('offers:slug:summer-sale');
  });
});

describe('Cart Cache Keys', function () {
  it('generates correct cart key', function () {
    expect(CacheKeys::cart(1, 10))->toBe('user:1:project:10:cart');
  });
});

describe('Order Cache Keys', function () {
  it('generates correct user orders key', function () {
    expect(CacheKeys::userOrders(1, 10))->toBe('user:1:project:10:orders');
  });

  it('generates correct single order key', function () {
    expect(CacheKeys::order(500, 1))->toBe('user:1:orders:500');
  });

  it('generates correct admin orders key with filters hash', function () {
    $hash = md5('status=completed');
    expect(CacheKeys::adminOrders(10, $hash))->toBe("project:10:admin:orders:{$hash}");
  });
});
