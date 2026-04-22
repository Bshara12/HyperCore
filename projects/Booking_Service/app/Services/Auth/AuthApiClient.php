<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

class AuthApiClient
{
  protected string $baseUrl;

  public function __construct()
  {
    $this->baseUrl = rtrim(config('services.auth_service.url'), '/');
  }

  public function getUserFromToken(string $token): array
  {
    $response = Http::withToken($token)
      ->get("{$this->baseUrl}/api/my-profile");

    if (!$response->successful()) {
      dd($response->status(), $response->body());
    }

    if ($response->failed()) {
      $error = $response->json('message')
        ?? substr($response->body(), 0, 200);

      throw new \Exception(
        "Failed to fetch user from auth service: " . $error
      );
    }

    $user = $response->json()['data'];

    $permissions = collect($user['roles'])
      ->flatMap(fn($role) => $role['permessions'])
      ->pluck('name')
      ->unique()
      ->values()
      ->toArray();

    $user['permissions'] = $permissions;

    return $user;
  }
}
