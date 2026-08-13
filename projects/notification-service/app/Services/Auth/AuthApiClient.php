<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

class AuthApiClient
{
  protected string $baseUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.auth.url'), '/');
  }

  /**
   * توكن مستخدم حقيقي — يبقى كما هو، يُستخدم فقط لـ UserAuthMiddleware
   */
  public function getUserFromToken(string $token): array
  {
    $response = Http::acceptJson()
      ->withToken($token)
      ->timeout(10)
      ->retry(2, 200, throw: false)
      // ->retry(2, 200)
      ->get("{$this->baseUrl}/api/my-profile");

    if ($response->failed()) {
      $error = $response->json('message')
        // @codeCoverageIgnoreStart
        ?? substr($response->body(), 0, 200);
      // @codeCoverageIgnoreEnd

      throw new \Exception(
        'Failed to fetch user from auth service: ' . $error
      );
    }

    $user = $response->json('data') ?? [];

    $permessions = collect(data_get($user, 'roles', []))
      ->flatMap(fn($role) => data_get($role, 'permessions', []))
      ->pluck('name')
      ->filter()
      ->unique()
      ->values()
      ->toArray();

    $user['permessions'] = $permessions;

    return $user;
  }

  /**
   * توكن خدمة — يبقى كما هو، يُستخدم فقط لـ ServiceAuthMiddleware
   */
  public function getServiceFromToken(string $token): array
  {
    $response = Http::acceptJson()
      ->withToken($token)
      ->timeout(10)
      ->retry(2, 200, throw: false)
      // ->retry(2, 200)
      ->get("{$this->baseUrl}/api/get-service");

    if ($response->failed()) {
      $error = $response->json('message')
        // @codeCoverageIgnoreStart
        ?? substr($response->body(), 0, 200);
        // @codeCoverageIgnoreEnd

      throw new \Exception(
        'Failed to fetch user from auth service: ' . $error
      );
    }

    return $response->json('data') ?? [];
  }

  /**
   * جلب مستخدم بالـ ID لأغراض التواصل بين الخدمات
   *
   * مُصحَّح: نستخدم الآن endpoint داخلي مخصص (/api/internal/users/{id})
   * محمي بمفتاح مشترك (X-Internal-Api-Key) بدلاً من Bearer token
   * هذا يتجنب نهائياً مشكلة "Invalid token" لأن هذا المسار
   * لا علاقة له بنظام مصادقة المستخدمين في Auth Service إطلاقاً
   */
  public function getUserById(string $userId): array
  {
    $internalApiKey = config('services.auth.internal_api_key');

    if (empty($internalApiKey)) {
      throw new \Exception(
        'INTERNAL_SERVICES_API_KEY is not configured in .env'
      );
    }

    $response = Http::acceptJson()
      ->withHeaders([
        'X-Internal-Api-Key' => $internalApiKey,
      ])
      ->timeout(10)
      ->retry(2, 200, throw: false)
      // ->retry(2, 200)
      ->get("{$this->baseUrl}/api/internal/users/{$userId}");

    if ($response->status() === 404) {
      return [];
    }

    if ($response->failed()) {
      throw new \Exception(
        'Failed to verify user: ' . ($response->json('message') ?? substr($response->body(), 0, 200))
      );
    }

    return $response->json('data') ?? [];
  }
}
