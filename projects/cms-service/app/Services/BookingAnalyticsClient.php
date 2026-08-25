<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class BookingAnalyticsClient
{
  /**
   * Everything needed to issue the call, without issuing it.
   *
   * Lets a caller put this request into an Http::pool alongside another
   * service's instead of awaiting them one after the other.
   *
   * @return array{url: string, headers: array<string, string>, query: array<string, mixed>}
   */
  public function overviewRequest(string $token, $projectId, array $filters = []): array
  {
    return [
      'url' => config('services.booking_service.url') . '/booking/analytics/overview',
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'X-Project-Id' => (string) $projectId,
        'Accept' => 'application/json',
      ],
      'query' => $filters,
    ];
  }

  /**
   * دالة مساعدة لتنفيذ الطلبات لخدمة الحجوزات
   */
  private function fetchAnalytics(string $token, $projectId, string $endpoint, array $filters = []): array
  {
    $response = Http::withToken($token)
      ->withHeaders(['X-Project-Id' => $projectId])
      // Endpoints are passed without a leading slash so the path stays
      // /booking/analytics/<endpoint> rather than gaining a double slash.
      ->get(config('services.booking_service.url') . '/booking/analytics/' . ltrim($endpoint, '/'), $filters);

    if (! $response->successful()) {
      throw new Exception("Booking Service Error: " . $response->body(), $response->status());
    }

    return $response->json()['data'] ?? $response->json();
  }

  public function getOverview(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/overview', $filters);
  }

  public function getTrend(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/trend', $filters);
  }

  public function getResourcePerformance(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/resources', $filters);
  }

  public function getCancellations(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/cancellations', $filters);
  }

  public function getPeakTimes(string $token, $projectId, array $filters = []): array
  {
    return $this->fetchAnalytics($token, $projectId, '/peak-times', $filters);
  }
}
