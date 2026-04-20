<?php

namespace App\Domains\Auth\Service;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class AuthServiceClient
{
  public function getUserFromToken(string $token): array
  {
    $response = Http::withToken($token)
      ->get(config('services.auth_service.url') . '/my-profile');

    if (!$response->successful()) {
      dd($response->status(), $response->body());
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

  public function getUsersByIds(array $ids)
  {
    $response = Http::post(
      config('services.auth_service.url') . '/users/by-ids',
      [
        'ids' => $ids
      ]
    );
    // $response = Http::get(
    //   config('services.auth_service.url') . `/profile/$ids`
    // );
    if (!$response->successful()) {
      dd($response->status(), $response->body());
    }

    return $response->json()['data'];
  }
}
