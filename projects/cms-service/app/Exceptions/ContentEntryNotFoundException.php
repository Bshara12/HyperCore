<?php

namespace App\Exceptions;

use RuntimeException;

class ContentEntryNotFoundException extends RuntimeException
{
    public function __construct(int $contentId)
    {
        parent::__construct(
            sprintf(
                'Data entry [%d] not found or has no associated data type.',
                $contentId
            )
        );
    }
}