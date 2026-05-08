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

    public function getUserFromToken(string $token): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(10)
            ->retry(2, 200)
            ->get("{$this->baseUrl}/api/my-profile");

        // $response = Http::withToken($token)
        //     ->get("{$this->baseUrl}/api/my-profile");

        if ($response->failed()) {
            $error = $response->json('message')
                ?? substr($response->body(), 0, 200);

            throw new \Exception(
                'Failed to fetch user from auth service: ' . $error
            );
        }

        $user = $response->json('data') ?? [];

        $permessions = collect(data_get($user, 'roles', []))
            ->flatMap(fn ($role) => data_get($role, 'permessions', []))
            ->pluck('name')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $user['permessions'] = $permessions;

        return $user;
    }

    public function getServiceFromToken(string $token): array
    {
        $response = Http::acceptJson()
            ->withToken($token)
            ->timeout(10)
            ->retry(2, 200)
            ->get("{$this->baseUrl}/api/get-service");

        // $response = Http::withToken($token)
        //     ->get("{$this->baseUrl}/api/my-profile");

        if ($response->failed()) {
            $error = $response->json('message')
                ?? substr($response->body(), 0, 200);

            throw new \Exception(
                'Failed to fetch user from auth service: ' . $error
            );
        }

        $service = $response->json('data') ?? [];

        // $permessions = collect(data_get($user, 'roles', []))
        //     ->flatMap(fn ($role) => data_get($role, 'permessions', []))
        //     ->pluck('name')
        //     ->filter()
        //     ->unique()
        //     ->values()
        //     ->toArray();

        // $user['permessions'] = $permessions;

        return $service;
    }
}
