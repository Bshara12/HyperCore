<?php

namespace App\Domains\CMS\Actions\data;

use App\Domains\CMS\States\DataEntryStateResolver;

class ResolveStateAction
{
    public function __construct(
        private DataEntryStateResolver $resolver
    ) {}

    public function execute(object $entry, string $status, ?string $scheduledAt): void
    {
        $state = $this->resolver->resolve($entry);

        if ($status === 'published') {
            $state->publish($entry);
        }

        if ($status === 'scheduled') {
            $state->schedule($entry, $scheduledAt);
        }
    }
}
