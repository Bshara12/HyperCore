<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * EntryRemovedFromSearch
 *
 * يُطلَق عندما يجب حذف entry من search_indices:
 *   1. عند حذف الـ entry نهائياً (destroy)
 *   2. عند تغيير status من published → draft/scheduled
 *
 * لماذا event منفصل وليس داخل DataEntrySavedEvent؟
 *   DataEntrySavedEvent يحمل DataEntry object كاملاً.
 *   عند الحذف، الـ entry قد لا يكون موجوداً بعد الآن في DB.
 *   لذلك نحمل فقط entry_id اللازمة للحذف من الفهرس.
 */
class EntryRemovedFromSearch
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int $entryId,
        public readonly string $reason, // 'deleted' | 'unpublished' | 'archived'
    ) {}
}