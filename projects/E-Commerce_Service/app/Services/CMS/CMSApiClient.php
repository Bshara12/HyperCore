<?php

namespace App\Services\CMS;

use App\Domains\Core\Traits\HasProjectHeaders;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use Illuminate\Support\Facades\Http;

class CMSApiClient
{
  use HasProjectHeaders;

  protected string $baseUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.cms.url'), '/');
  }
  /**
   * @return array<string, mixed>
   */
  public function resolveProject(): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/projects/resolve");
    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to resolve project in CMS: ' . $error);
    }

    return $response->json()['original'];
  }
  /**
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  public function createCollection(array $data): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/cms/collections", $data);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to create collection in CMS: ' . $error);
    }

    return $response->json();
  }
  /**
   * @return array<string, mixed>
   */
  public function getCollectionBySlug(string $collectionSlug): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/cms/collections/{$collectionSlug}");

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to fetch collection in CMS: ' . $error);
    }

    return $response->json()['data'];
  }
  /**
   * @return array<string, mixed>
   */
  public function getCollectionById(int $collectionId): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/cms/collections/id/{$collectionId}");

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to fetch collection in CMS: ' . $error);
    }

    return $response->json('data');
  }
  /**
   * @return array<string, mixed>
   */
  public function getDynamicEntries(string $collectionSlug): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/cms/collections/{$collectionSlug}/entries");

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to fetch dynamic entries in CMS: ' . $error);
    }

    return $response->json();
  }
  /**
   * @param array<string, mixed> $data
   * @return array<string, mixed>
   */
  public function updateCollection(string $collectionSlug, array $data)
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->patch("{$this->baseUrl}/api/cms/collections/{$collectionSlug}", $data);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to update collection in CMS: ' . $error);
    }

    return $response->json();
  }
  /**
   * @param array<mixed> $data
   * @return string
   */
  public function addCollectionItems(string $collectionSlug, array $data)
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/cms/collections/{$collectionSlug}/insert", [
      'items' => $data,
    ]);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to update collection in CMS: ' . $error);
    }

    return $response->json('message');
  }
  /**
   * @param array<mixed> $data
   * @return string
   */
  public function removeCollectionItems(string $collectionSlug, array $data)
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->delete("{$this->baseUrl}/api/cms/collections/{$collectionSlug}/items", [
      'items' => $data,
    ]);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to update collection in CMS: ' . $error);
    }

    return $response->json('message');
  }

  /**
   * @return string
   */
  public function deactivationCollection(string $collectionSlug, bool $is_active): string
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->patch("{$this->baseUrl}/api/cms/collections/{$collectionSlug}/deactivate", [
      'is_active' => $is_active,
    ]);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to update collection in CMS: ' . $error);
    }

    return $response->json('message');
  }

  /**
   * @param array<string, mixed> $params
   * @return array<string, mixed>
   */
  public function getEntries(string $collection, array $params = []): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/cms/collections/{$collection}/entries", $params);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to fetch entries from CMS: ' . $error);
    }

    return $response->json();
  }
  /**
   * @return array<string, mixed>
   */
  public function getEntryValuesById(string $slug): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/cms/entries/$slug");
    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to fetch entries from CMS: ' . $error);
    }

    return $response->json();
  }
  /**
   * @param array<int, int|string> $ids
   * @return array<string, mixed>
   */
  public function getEntriesByIds(array $ids): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/cms/entries/bulk", [
      'ids' => $ids,
    ]);

    if ($response->failed()) {
      throw new \Exception('Failed to fetch entries by ids');
    }

    return $response->json();
  }
  /**
   * @return array<string, mixed>
   */
  public function getEntriesByDataType(string $dataTypeSlug): array
  {
    $project = $this->resolveProject();

    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/projects/{$project['id']}/data-types/{$dataTypeSlug}/entries");

    if ($response->failed()) {
      throw new \Exception('Failed to fetch entries by data type');
    }

    return $response->json()['entries']; // 🔥 مهم
  }
  /**
   * @return array<string, mixed>
   */
  public function processPayment(PaymentDTO $dto): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/payments/pay", json_decode(json_encode($dto), true));

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception('Failed to process payment in CMS: ' . $error);
    }

    return $response->json();
  }

  /**
   * @return array<string, mixed>
   */
  public function payInstallment(PayInstallmentDTO $data): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/payments/installment", json_decode(json_encode($data), true));

    if ($response->failed()) {
      throw new \Exception(
        $response->json('message') ?? 'Installment payment failed.'
      );
    }

    return $response->json();
  }
  /**
   * @param array<int, int|string> $entryIds
   * @return array<string, mixed>
   */
  public function getStockStatus(array $entryIds): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/stock/status", [
      'entry_ids' => $entryIds,
    ]);

    if ($response->failed()) {
      throw new \Exception(
        $response->json('message') ?? 'Failed to fetch stock status.'
      );
    }

    return $response->json('data');
  }

  // public function updateEntry(string $slug, array $data)
  // {

  //   $response = Http::withHeaders(
  //     $this->projectHeaders()
  //   )->patch("{$this->baseUrl}/api/data-entries/{$slug}", $data);
  //   if ($response->failed()) {
  //     throw new \Exception("Failed to update entry stock");
  //   }

  //   return $response->json();
  // }



  /**
   * @param array<int, mixed> $items
   */
  public function decrementStock(array $items): void
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/stock/decrement", [
      'items' => $items,
    ]);

    if ($response->failed()) {
      throw new \Exception(
        $response->json('message') ?? 'Stock update failed'
      );
    }
  }
}
