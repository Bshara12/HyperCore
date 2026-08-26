<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Http;

class AuthApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.auth.url'), '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserFromToken(string $token): array
    {
        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/api/my-profile");

        if ($response->failed()) {
            $error = $response->json('message')
                ?? substr($response->body(), 0, 200);

            throw new \Exception(
                'Failed to fetch user from auth service: ' . $error
            );
        }

        /** 
         * @var array{
         *   roles?: array<int, array{permessions?: array<int, array{name: string}>}>
         * } & array<string, mixed> $user 
         */
        $user = $response->json()['data'];

        /** @var array<int, array{permessions?: array<int, array{name: string}>}> $roles */
        $roles = $user['roles'] ?? [];

        /** @var array<int, string> $permissions */
        $permissions = collect($roles)
            ->flatMap(fn(array $role): array => $role['permessions'] ?? [])
            ->pluck('name')
            ->unique()
            ->values()
            ->toArray();

        $user['permissions'] = $permissions;

        return $user;
    }
}