<?php

namespace App\Domains\Subscription\Repositories\Interface;

use App\Exceptions\ContentEntryNotFoundException;

interface ContentTypeResolverInterface
{
    /**
     * Resolve the content_type slug from a given content_id (DataEntry).
     *
     * @throws ContentEntryNotFoundException
     */
    public function resolve(int $contentId): string;
}
