<?php

namespace App\Domains\Subscription\Repositories\Interface;

interface ContentTypeResolverInterface
{
    /**
     * Resolve the content_type slug from a given content_id (DataEntry).
     *
     * @throws \App\Exceptions\ContentEntryNotFoundException
     */
    public function resolve(int $contentId): string;
}