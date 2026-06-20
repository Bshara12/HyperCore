<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class BookingAnalyticsClient
{
  /**
   * دالة مساعدة لتنفيذ الطلبات لخدمة الحجوزات
   */
  private function fetchAnalytics(string $token, $projectId, string $endpoint, array $filters = []): array
  {
    $response = Http::withToken($token)
      ->withHeaders(['X-Project-Id' => $projectId])
      ->get(config('services.booking_service.url') . '/booking/analytics' . $endpoint, $filters);

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
