<?php

namespace App\Services\CMS;

use App\Domains\Core\Traits\HasProjectHeaders;
use Illuminate\Support\Facades\Http;

class CMSApiClient
{
  use HasProjectHeaders;

  protected string $baseUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.cms_service.url'), '/');
  }

  public function resolveProject()
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->get("{$this->baseUrl}/api/projects/resolve");
    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception("Failed to resolve project in CMS: " . $error);
    }

    return $response->json()['original'];
  }

  public function createCollection(array $data): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/cms/collections", $data);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception("Failed to create collection in CMS: " . $error);
    }

    return $response->json();
  }

  public function chargeBooking(array $data): array
  {
    // dd($data);
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/payments/pay", [
      'userId'      => $data['user_id'],
      'userName' => $data['user_name'],
      'projectId'   => $data['project_id'],
      'amount'       => $data['amount'],
      'currency'     => $data['currency'],
      'gateway'      => $data['gateway'],
      'paymentType' => 'full',
      'token'        => $data['token'] ?? null,
    ]);

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception("Payment failed: " . $error);
    }

    return $response->json();
  }

  public function refundBooking(array $data): array
  {
    $response = Http::withHeaders(
      $this->projectHeaders()
    )->post("{$this->baseUrl}/api/payments/refund", [
      'paymentId' => $data['payment_id'],
      'amount'    => $data['amount'],
    ]);

    if ($response->failed()) {
      dd($response);
      throw new \Exception("Refund failed");
    }

    return $response->json();
  }
}
