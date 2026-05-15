<?php

namespace App\Services\CMS;

use Illuminate\Support\Facades\Http;

class CMSApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.cms.url'), '/');
    }

    public function resolveProject(): array
    {
        $response = Http::acceptJson()
            ->withHeaders($this->projectHeaders())
            ->timeout(10)
            ->retry(2, 200)
            ->get("{$this->baseUrl}/api/projects/resolve");

        if ($response->failed()) {
            $error = $response->json('message')
                ?? substr($response->body(), 0, 200);

            throw new \Exception('Failed to resolve project in CMS: '.$error);
        }

        return $response->json('original')
            ?? $response->json('data')
            ?? $response->json();
    }

    protected function projectHeaders(): array
    {
        return [
            'X-Project-Id' => request()->header('X-Project-Id'),
            'Accept' => 'application/json',
        ];
    }
}
