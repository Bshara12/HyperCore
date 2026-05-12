<?php

namespace App\Domains\Subscription\DTOs\ContentAccess;

use App\Models\ContentAccessMetadata;

class ActivateContentAccessDTO
{
    public function __construct(

        public readonly ContentAccessMetadata
        $contentAccessMetadata
    ) {}
}