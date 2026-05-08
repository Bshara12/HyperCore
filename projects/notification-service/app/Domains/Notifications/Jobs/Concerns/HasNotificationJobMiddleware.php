<?php

namespace App\Domains\Notifications\Jobs\Concerns;

use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;

trait HasNotificationJobMiddleware
{
    protected function overlapKey(): string
    {
        return static::class;
    }

    protected function overlapReleaseAfter(): int
    {
        return 10;
    }

    protected function overlapExpireAfter(): int
    {
        return 120;
    }

    protected function throttleMaxExceptions(): int
    {
        return 5;
    }

    protected function throttleDecayMinutes(): int
    {
        return 5;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))
                ->releaseAfter($this->overlapReleaseAfter())
                ->expireAfter($this->overlapExpireAfter()),

            (new ThrottlesExceptions($this->throttleMaxExceptions(), $this->throttleDecayMinutes()))
                ->byJob(),
        ];
    }
}
