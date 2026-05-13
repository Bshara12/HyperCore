<?php

namespace App\Domains\CMS\Read\DTOs;

class GetEntryDetailDTO
{
    public function __construct(

        public readonly int $entryId,

        public readonly ?string $language,

        public readonly ?int $userId
    ) {}
}