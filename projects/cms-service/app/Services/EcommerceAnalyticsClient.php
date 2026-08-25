<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class EcommerceAnalyticsClient
{
  /**
   * Everything needed to issue the call, without issuing it.
   *
   * Lets a caller put this request into an Http::pool alongside another
   * service's instead of awaiting them one after the other.
   *
   * @return array{url: string, headers: array<string, string>, query: array<string, mixed>}
   */
  public function summaryRequest(string $token, $projectId, array $filters = []): array
  {
    return [
      'url' => config('services.ecommerce_service.url') . '/ecommerce/analytics/summary',
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'X-Project-Id' => (string) $projectId,
        'Accept' => 'application/json',
      ],
      'query' => $filters,
    ];
  }

  /**
   * دالة مساعدة لتنفيذ الطلبات لخدمة المتجر لتقليل تكرار الكود
   */
  private function fetchAnalytics(string $token, $projectId, string $endpoint, array $filters = []): array
  {
    $response = Http::withToken($token)
      // تأكد من اسم الهيدر الخاص بالمشروع بناءً على ما يقبله الـ Middleware لديك
      ->withHeaders(['X-Project-Id' => $projectId])
      // ltrim: some callers pass '/sales/trend', which otherwise produced a
      // double slash in the path.
      ->get(config('services.ecommerce_service.url') . '/ecommerce/analytics/' . ltrim($endpoint, '/'), $filters);
    if (! $response->successful()) {
      throw new Exception("Ecommerce Service Error: " . $response->body(), $response->status());
    }

    // افترضنا أن الـ API يرجع البيانات داخل مفتاح 'data' كما في خدمة المصادقة
    return $response->json()['data'] ?? $response->json();
  }

  public function getSummary(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, 'summary', $filters);
  }

  public function getSalesTrend(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/sales/trend', $filters);
  }

  public function getTopProducts(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/products/top', $filters);
  }

  public function getOffersAnalytics(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/offers', $filters);
  }

  public function getTopCustomers(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/customers/top', $filters);
  }

  public function getReturnsAnalytics(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/returns', $filters);
  }
}
