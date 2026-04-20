<?php

namespace App\Domains\CMS\Support;

class LanguageResolver
{
    public function resolve(?string $requested): string
    {
        return $requested ?? $this->fallback();
    }

    public function fallback(): string
    {
        return config('app.fallback_locale', 'en');
    }
}
