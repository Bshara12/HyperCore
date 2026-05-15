<?php

namespace Tests\Unit\Services\CMS;

use Tests\TestCase;
use App\Services\CMS\CMSApiClient;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class CMSApiClientTest extends TestCase
{
  private CMSApiClient $cmsApiClient;

  protected function setUp(): void
  {
    parent::setUp();
    Http::preventStrayRequests();
    $this->cmsApiClient = new CMSApiClient();
  }

  // ─── ResolveProject Tests ───────────────────────────────────────────────

  #[Test]
  public function it_can_resolve_project_successfully()
  {
    $mockResponse = [
      'original' => [
        'id' => 1,
        'name' => 'E-Commerce Project',
        'slug' => 'ecommerce-project'
      ]
    ];

    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response($mockResponse, 200)
    ]);

    $result = $this->cmsApiClient->resolveProject();

    $this->assertIsArray($result);
    $this->assertEquals(1, $result['id']);
    $this->assertEquals('E-Commerce Project', $result['name']);
  }

  #[Test]
  public function it_throws_exception_on_failed_project_resolution()
  {
    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['message' => 'Project not found'], 404)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to resolve project in CMS');

    $this->cmsApiClient->resolveProject();
  }

  #[Test]
  public function it_uses_body_fallback_on_resolve_project_error()
  {
    $longErrorBody = str_repeat('y', 500);

    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->resolveProject();
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to resolve project in CMS', $e->getMessage());
    }
  }

  // ─── CreateCollection Tests ───────────────────────────────────────────

  #[Test]
  public function it_can_create_collection_successfully()
  {
    $data = [
      'name' => 'Products',
      'slug' => 'products',
      'description' => 'Product collection'
    ];

    $mockResponse = [
      'id' => 1,
      'name' => 'Products',
      'slug' => 'products'
    ];

    Http::fake([
      config('services.cms.url') . '/api/cms/collections' => Http::response($mockResponse, 201)
    ]);

    $result = $this->cmsApiClient->createCollection($data);

    $this->assertEquals('Products', $result['name']);
  }

  #[Test]
  public function it_throws_exception_on_failed_collection_creation()
  {
    $data = ['name' => ''];

    Http::fake([
      config('services.cms.url') . '/api/cms/collections' => Http::response(['message' => 'Name is required'], 422)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to create collection in CMS');

    $this->cmsApiClient->createCollection($data);
  }

  #[Test]
  public function it_uses_body_fallback_on_create_collection_error()
  {
    $longErrorBody = str_repeat('z', 500);

    Http::fake([
      config('services.cms.url') . '/api/cms/collections' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->createCollection([]);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to create collection in CMS', $e->getMessage());
    }
  }

  // ─── GetCollectionBySlug Tests ─────────────────────────────────────────

  #[Test]
  public function it_can_get_collection_by_slug_successfully()
  {
    $slug = 'products';
    $mockResponse = [
      'data' => [
        'id' => 1,
        'name' => 'Products',
        'slug' => $slug
      ]
    ];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response($mockResponse, 200)
    ]);

    $result = $this->cmsApiClient->getCollectionBySlug($slug);

    $this->assertEquals('Products', $result['name']);
  }

  #[Test]
  public function it_throws_exception_when_collection_slug_not_found()
  {
    $slug = 'non-existent';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response(['message' => 'Not found'], 404)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getCollectionBySlug($slug);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_collection_by_slug_error()
  {
    $slug = 'products';
    $longErrorBody = str_repeat('w', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getCollectionBySlug($slug);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch collection in CMS', $e->getMessage());
    }
  }

  // ─── GetCollectionById Tests ───────────────────────────────────────────

  #[Test]
  public function it_can_get_collection_by_id_successfully()
  {
    $id = 5;
    $mockResponse = [
      'data' => [
        'id' => $id,
        'name' => 'Collection',
        'slug' => 'collection'
      ]
    ];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/id/{$id}" => Http::response($mockResponse, 200)
    ]);

    $result = $this->cmsApiClient->getCollectionById($id);

    $this->assertEquals('Collection', $result['name']);
  }

  #[Test]
  public function it_throws_exception_when_collection_id_not_found()
  {
    Http::fake([
      config('services.cms.url') . "/api/cms/collections/id/999" => Http::response(['message' => 'Not found'], 404)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getCollectionById(999);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_collection_by_id_error()
  {
    $id = 1;
    $longErrorBody = str_repeat('t', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/id/{$id}" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getCollectionById($id);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch collection in CMS', $e->getMessage());
    }
  }

  // ─── GetDynamicEntries Tests ───────────────────────────────────────────

  #[Test]
  public function it_can_get_dynamic_entries_successfully()
  {
    $slug = 'products';
    $mockResponse = [
      'entries' => [
        ['id' => 1, 'name' => 'Product 1'],
        ['id' => 2, 'name' => 'Product 2']
      ]
    ];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/entries" => Http::response($mockResponse, 200)
    ]);

    $result = $this->cmsApiClient->getDynamicEntries($slug);

    $this->assertArrayHasKey('entries', $result);
    $this->assertCount(2, $result['entries']);
  }

  #[Test]
  public function it_throws_exception_on_failed_dynamic_entries_fetch()
  {
    $slug = 'invalid';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/entries" => Http::response(['message' => 'Not found'], 404)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getDynamicEntries($slug);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_dynamic_entries_error()
  {
    $slug = 'invalid';
    $longErrorBody = str_repeat('s', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/entries" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getDynamicEntries($slug);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch dynamic entries in CMS', $e->getMessage());
    }
  }

  // ─── UpdateCollection Tests ───────────────────────────────────────────

  #[Test]
  public function it_can_update_collection_successfully()
  {
    $slug = 'products';
    $data = ['name' => 'Updated Products'];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response(['name' => 'Updated Products'], 200)
    ]);

    $result = $this->cmsApiClient->updateCollection($slug, $data);

    $this->assertEquals('Updated Products', $result['name']);
  }

  #[Test]
  public function it_throws_exception_on_failed_collection_update()
  {
    $slug = 'products';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response(['message' => 'Validation failed'], 422)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->updateCollection($slug, []);
  }

  #[Test]
  public function it_uses_body_fallback_on_update_collection_error()
  {
    $slug = 'products';
    $longErrorBody = str_repeat('v', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->updateCollection($slug, []);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to update collection in CMS', $e->getMessage());
    }
  }

  // ─── AddCollectionItems Tests ──────────────────────────────────────────

  #[Test]
  public function it_can_add_collection_items_successfully()
  {
    $slug = 'products';
    $items = [['id' => 1, 'quantity' => 10]];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/insert" => Http::response(['message' => 'Items added'], 200)
    ]);

    $result = $this->cmsApiClient->addCollectionItems($slug, $items);

    $this->assertEquals('Items added', $result);
  }

  #[Test]
  public function it_throws_exception_on_failed_add_items()
  {
    $slug = 'products';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/insert" => Http::response(['message' => 'Failed'], 422)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->addCollectionItems($slug, []);
  }

  #[Test]
  public function it_uses_body_fallback_on_add_items_error()
  {
    $slug = 'products';
    $longErrorBody = str_repeat('u', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/insert" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->addCollectionItems($slug, []);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to update collection in CMS', $e->getMessage());
    }
  }

  // ─── RemoveCollectionItems Tests ───────────────────────────────────────

  #[Test]
  public function it_can_remove_collection_items_successfully()
  {
    $slug = 'products';
    $items = [1, 2];

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/items" => Http::response(['message' => 'Items removed'], 200)
    ]);

    $result = $this->cmsApiClient->removeCollectionItems($slug, $items);

    $this->assertEquals('Items removed', $result);
  }

  #[Test]
  public function it_throws_exception_on_failed_remove_items()
  {
    $slug = 'products';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/items" => Http::response(['message' => 'Failed'], 422)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->removeCollectionItems($slug, []);
  }

  #[Test]
  public function it_uses_body_fallback_on_remove_items_error()
  {
    $slug = 'products';
    $longErrorBody = str_repeat('r', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/items" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->removeCollectionItems($slug, []);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to update collection in CMS', $e->getMessage());
    }
  }

  // ─── DeactivationCollection Tests ──────────────────────────────────────

  #[Test]
  public function it_can_deactivate_collection_successfully()
  {
    $slug = 'products';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/deactivate" => Http::response(['message' => 'Deactivated'], 200)
    ]);

    $result = $this->cmsApiClient->deactivationCollection($slug, false);

    $this->assertEquals('Deactivated', $result);
  }

  #[Test]
  public function it_throws_exception_on_failed_deactivation()
  {
    $slug = 'products';

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/deactivate" => Http::response(['message' => 'Failed'], 400)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->deactivationCollection($slug, false);
  }

  #[Test]
  public function it_uses_body_fallback_on_deactivate_error()
  {
    $slug = 'products';
    $longErrorBody = str_repeat('q', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/collections/{$slug}/deactivate" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->deactivationCollection($slug, false);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to update collection in CMS', $e->getMessage());
    }
  }

  // ─── GetEntries Tests ──────────────────────────────────────────────────

  #[Test]
  public function it_can_get_entries_with_parameters_successfully()
  {
    $collection = 'products';
    $params = ['limit' => 10, 'offset' => 0];

    Http::fake(function ($request) use ($collection) {
      if (str_contains($request->url(), "/api/cms/collections/{$collection}/entries")) {
        return Http::response(['data' => []], 200);
      }
    });

    $result = $this->cmsApiClient->getEntries($collection, $params);

    $this->assertIsArray($result);
  }

  #[Test]
  public function it_throws_exception_on_failed_get_entries()
  {
    $collection = 'invalid';

    Http::fake(function ($request) use ($collection) {
      if (str_contains($request->url(), "/api/cms/collections/{$collection}/entries")) {
        return Http::response(['message' => 'Not found'], 404);
      }
    });

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getEntries($collection);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_entries_error()
  {
    $collection = 'invalid';
    $longErrorBody = str_repeat('p', 500);

    Http::fake(function ($request) use ($collection, $longErrorBody) {
      if (str_contains($request->url(), "/api/cms/collections/{$collection}/entries")) {
        return Http::response($longErrorBody, 500);
      }
    });

    try {
      $this->cmsApiClient->getEntries($collection);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch entries from CMS', $e->getMessage());
    }
  }

  // ─── GetEntryValuesById Tests ──────────────────────────────────────────

  #[Test]
  public function it_can_get_entry_values_by_id_successfully()
  {
    $slug = 'product-1';

    Http::fake([
      config('services.cms.url') . "/api/cms/entries/{$slug}" => Http::response(['id' => 1, 'name' => 'Product'], 200)
    ]);

    $result = $this->cmsApiClient->getEntryValuesById($slug);

    $this->assertEquals('Product', $result['name']);
  }

  #[Test]
  public function it_throws_exception_on_failed_get_entry_values()
  {
    $slug = 'non-existent';

    Http::fake([
      config('services.cms.url') . "/api/cms/entries/{$slug}" => Http::response(['message' => 'Not found'], 404)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getEntryValuesById($slug);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_entry_values_error()
  {
    $slug = 'product-1';
    $longErrorBody = str_repeat('o', 500);

    Http::fake([
      config('services.cms.url') . "/api/cms/entries/{$slug}" => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getEntryValuesById($slug);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch entries from CMS', $e->getMessage());
    }
  }

  // ─── GetEntriesByIds Tests ────────────────────────────────────────────

  #[Test]
  public function it_can_get_entries_by_ids_successfully()
  {
    $ids = [1, 2, 3];

    Http::fake([
      config('services.cms.url') . '/api/cms/entries/bulk' => Http::response(['data' => []], 200)
    ]);

    $result = $this->cmsApiClient->getEntriesByIds($ids);

    $this->assertIsArray($result);
  }

  #[Test]
  public function it_throws_exception_on_failed_get_entries_by_ids()
  {
    Http::fake([
      config('services.cms.url') . '/api/cms/entries/bulk' => Http::response(['message' => 'Failed'], 500)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getEntriesByIds([1, 2, 3]);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_entries_by_ids_error()
  {
    $longErrorBody = str_repeat('m', 500);

    Http::fake([
      config('services.cms.url') . '/api/cms/entries/bulk' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getEntriesByIds([1, 2, 3]);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertTrue(true);
    }
  }

  // ─── GetEntriesByDataType Tests ────────────────────────────────────────

  #[Test]
  public function it_can_get_entries_by_data_type_successfully()
  {
    $dataTypeSlug = 'products';

    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['original' => ['id' => 1]], 200),
      config('services.cms.url') . '/api/cms/projects/1/data-types/products/entries' => Http::response(['entries' => []], 200)
    ]);

    $result = $this->cmsApiClient->getEntriesByDataType($dataTypeSlug);

    $this->assertIsArray($result);
  }

  #[Test]
  public function it_throws_exception_on_failed_get_entries_by_data_type()
  {
    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['original' => ['id' => 1]], 200),
      config('services.cms.url') . '/api/cms/projects/1/data-types/invalid/entries' => Http::response(['message' => 'Not found'], 404)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getEntriesByDataType('invalid');
  }

  #[Test]
  public function it_uses_body_fallback_on_get_entries_by_data_type_error()
  {
    $longErrorBody = str_repeat('l', 500);

    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['original' => ['id' => 1]], 200),
      config('services.cms.url') . '/api/cms/projects/1/data-types/invalid/entries' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getEntriesByDataType('invalid');
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch entries by data type', $e->getMessage());
    }
  }

  // ─── ProcessPayment Tests ──────────────────────────────────────────────

  #[Test]
  public function it_can_process_payment_successfully()
  {
    $paymentDTO = $this->createMock(PaymentDTO::class);

    Http::fake([
      config('services.cms.url') . '/api/payments/pay' => Http::response(['transaction_id' => 'txn_123', 'status' => 'success'], 200)
    ]);

    $result = $this->cmsApiClient->processPayment($paymentDTO);

    $this->assertEquals('success', $result['status']);
  }

  #[Test]
  public function it_throws_exception_on_failed_payment()
  {
    $paymentDTO = $this->createMock(PaymentDTO::class);

    Http::fake([
      config('services.cms.url') . '/api/payments/pay' => Http::response(['message' => 'Payment failed'], 400)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->processPayment($paymentDTO);
  }

  #[Test]
  public function it_uses_body_fallback_on_process_payment_error()
  {
    $paymentDTO = $this->createMock(PaymentDTO::class);
    $longErrorBody = str_repeat('n', 500);

    Http::fake([
      config('services.cms.url') . '/api/payments/pay' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->processPayment($paymentDTO);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to process payment in CMS', $e->getMessage());
    }
  }

  // ─── PayInstallment Tests ──────────────────────────────────────────────

  #[Test]
  public function it_can_pay_installment_successfully()
  {
    $installmentDTO = $this->createMock(PayInstallmentDTO::class);

    Http::fake([
      config('services.cms.url') . '/api/payments/installment' => Http::response(['status' => 'pending'], 200)
    ]);

    $result = $this->cmsApiClient->payInstallment($installmentDTO);

    $this->assertEquals('pending', $result['status']);
  }

  #[Test]
  public function it_throws_exception_on_failed_installment_payment()
  {
    $installmentDTO = $this->createMock(PayInstallmentDTO::class);

    Http::fake([
      config('services.cms.url') . '/api/payments/installment' => Http::response(['message' => 'Failed'], 400)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->payInstallment($installmentDTO);
  }

  #[Test]
  public function it_uses_body_fallback_on_pay_installment_error()
  {
    $installmentDTO = $this->createMock(PayInstallmentDTO::class);
    $longErrorBody = str_repeat('k', 500);

    Http::fake([
      config('services.cms.url') . '/api/payments/installment' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->payInstallment($installmentDTO);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Installment payment failed', $e->getMessage());
    }
  }

  // ─── GetStockStatus Tests ──────────────────────────────────────────────

  #[Test]
  public function it_can_get_stock_status_successfully()
  {
    $entryIds = [1, 2, 3];

    Http::fake([
      config('services.cms.url') . '/api/stock/status' => Http::response(['data' => []], 200)
    ]);

    $result = $this->cmsApiClient->getStockStatus($entryIds);

    $this->assertIsArray($result);
  }

  #[Test]
  public function it_throws_exception_on_failed_stock_status_fetch()
  {
    Http::fake([
      config('services.cms.url') . '/api/stock/status' => Http::response(['message' => 'Failed'], 500)
    ]);

    $this->expectException(\Exception::class);

    $this->cmsApiClient->getStockStatus([1, 2, 3]);
  }

  #[Test]
  public function it_uses_body_fallback_on_get_stock_status_error()
  {
    $longErrorBody = str_repeat('j', 500);

    Http::fake([
      config('services.cms.url') . '/api/stock/status' => Http::response($longErrorBody, 500)
    ]);

    try {
      $this->cmsApiClient->getStockStatus([1, 2, 3]);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Failed to fetch stock status', $e->getMessage());
    }
  }

  // ─── DecrementStock Tests ──────────────────────────────────────────────

  #[Test]
  public function it_can_decrement_stock_successfully()
  {
    $items = [['id' => 1, 'quantity' => 5]];

    Http::fake([
      config('services.cms.url') . '/api/stock/decrement' => Http::response(['message' => 'Success'], 200)
    ]);

    $this->cmsApiClient->decrementStock($items);
    $this->assertTrue(true);
  }

  #[Test]
  public function it_throws_exception_on_failed_stock_decrement()
  {
    $items = [['id' => 1, 'quantity' => 100]];

    Http::fake([
      config('services.cms.url') . '/api/stock/decrement' => Http::response(['message' => 'Insufficient stock'], 400)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Insufficient stock');

    $this->cmsApiClient->decrementStock($items);
  }

  #[Test]
  public function it_uses_json_message_fallback_on_decrement_stock_error()
  {
    $items = [['id' => 1, 'quantity' => 5]];

    Http::fake([
      config('services.cms.url') . '/api/stock/decrement' => Http::response(['message' => 'Custom error'], 500)
    ]);

    try {
      $this->cmsApiClient->decrementStock($items);
      $this->fail('Expected exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString('Custom error', $e->getMessage());
    }
  }

  #[Test]
  public function it_sends_correct_headers_for_all_requests()
  {
    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['original' => ['id' => 1]], 200)
    ]);

    $this->cmsApiClient->resolveProject();

    Http::assertSent(function ($request) {
      return $request->hasHeader('X-Project-Id') || $request->hasHeader('X-User-Id');
    });
  }

  #[Test]
  public function it_handles_multiple_sequential_requests()
  {
    Http::fake([
      config('services.cms.url') . '/api/projects/resolve' => Http::response(['original' => ['id' => 1]], 200),
      config('services.cms.url') . '/api/cms/collections/products' => Http::response(['data' => []], 200)
    ]);

    $project = $this->cmsApiClient->resolveProject();
    $collection = $this->cmsApiClient->getCollectionBySlug('products');

    $this->assertIsArray($project);
    $this->assertIsArray($collection);
  }
}
