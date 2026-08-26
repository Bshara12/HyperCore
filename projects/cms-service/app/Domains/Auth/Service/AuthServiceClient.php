<?php

namespace App\Domains\Auth\Service;

use Illuminate\Support\Facades\Http;

class AuthServiceClient
{
  public function getUserFromToken(string $token): array
  {
    $response = Http::withToken($token)
      ->get(config('services.auth_service.url') . '/my-profile');

    if (! $response->successful()) {
      throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
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

  /**
   * Resolve Auth account ids from email addresses.
   *
   * Identity lives in the Auth service: `projects.owner_id` holds an Auth user
   * id, not a row from this service's own `users` table. Anything that knows a
   * user only by address — the demo seeders wiring up ownership — has to ask
   * for the id rather than invent one.
   *
   * @param  array<int, string>  $emails
   * @return array<string, int>  email => id, missing addresses omitted
   */
  public function getUserIdsByEmails(array $emails): array
  {
    if ($emails === []) {
      return [];
    }

    $response = Http::withHeaders([
      'X-Internal-Api-Key' => config('services.auth_service.internal_api_key'),
    ])->post(
      config('services.auth_service.url') . '/internal/users/by-emails',
      ['emails' => array_values($emails)]
    );

    if (! $response->successful()) {
      throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
    }

    $resolved = [];

    foreach ($response->json()['data'] ?? [] as $user) {
      if (isset($user['email'], $user['id'])) {
        $resolved[$user['email']] = (int) $user['id'];
      }
    }

    return $resolved;
  }

  public function getUsersByIds(array $ids)
  {
    $response = Http::post(
      config('services.auth_service.url') . '/users/by-ids',
      [
        'ids' => $ids,
      ]
    );
    // $response = Http::get(
    //   config('services.auth_service.url') . `/profile/$ids`
    // );
    if (! $response->successful()) {
      throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
    }

    return $response->json()['data'];
  }


    /**
     * تسجيل/دخول مستخدم ضمن مشروع محدد — بدون توكن شخصي
     * الأمان هنا عبر X-Internal-Api-Key (خدمة لخدمة) لأن المستخدم
     * قد لا يملك أي توكن بعد (أول مرة يدخل مشروعاً)
     *
     * نفس أسلوب الكلاس: بدون acceptJson()، رمي Exception بنفس الصيغة
     */
    public function joinProject(int $projectId, array $data): array
    {
        $response = Http::withHeaders([
            'X-Internal-Api-Key' => config('services.auth_service.internal_api_key'),
        ])->post(
            config('services.auth_service.url') . "/internal/projects/{$projectId}/join",
            $data
        );

        if (! $response->successful()) {
            throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
        }

        return $response->json();
    }

    public function getProjectMembers(int $projectId): array
    {
        $response = Http::withHeaders([
            'X-Internal-Api-Key' => config('services.auth_service.internal_api_key'),
        ])->get(
            config('services.auth_service.url') . "/internal/projects/{$projectId}/members"
        );

        if (! $response->successful()) {
            throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
        }

        return $response->json()['data'];
    }

    public function leaveProject(int $projectId, int $userId): array
    {
        $response = Http::withHeaders([
            'X-Internal-Api-Key' => config('services.auth_service.internal_api_key'),
        ])->post(
            config('services.auth_service.url') . "/internal/projects/{$projectId}/leave",
            ['user_id' => $userId]
        );

        if (! $response->successful()) {
            throw new \Exception("Auth Service Error: " . $response->body(), $response->status());
        }

        return $response->json();
    }
}
