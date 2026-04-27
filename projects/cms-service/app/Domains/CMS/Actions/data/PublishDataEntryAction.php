<?php

namespace App\Domains\CMS\Actions\Data;

use App\Domains\CMS\Support\CacheKeys;
use App\Events\SystemLogEvent;
use App\Models\DataEntry;
use App\Support\CurrentProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PublishDataEntryAction
{
  public function execute(string $entrySlug, ?int $userId)
  {
    $projectId = CurrentProject::id();

    $entry = DataEntry::query()
      ->where('project_id', $projectId)
      ->where('slug', $entrySlug)
      ->firstOrFail();

    $now = now();
    $publishedAt = $entry->published_at ? Carbon::parse($entry->published_at) : null;

    $entry->status = 'published';

    if ($publishedAt === null || $publishedAt->greaterThan($now)) {
      $entry->published_at = $now;
    }

    if ($userId !== null) {
      // if (
      //   in_array('updated_by', $entry->getFillable(), true) ||
      //   array_key_exists('updated_by', $entry->getAttributes())
      // ) {
      if (in_array('updated_by', $entry->getFillable(), true) || array_key_exists('updated_by', $entry->getAttributes())) {
        $entry->updated_by = $userId;
      }
    // }

    $entry->save();

// <<<<<<< HEAD
//     // ✅ Status تغير — امسح كل اللغات
//     foreach (['default', 'ar', 'en', 'fr'] as $lang) {
//       Cache::forget(CacheKeys::entry($entry->id, $lang));
//       Cache::forget(CacheKeys::entryBySlug($entrySlug, $lang));
//     }
// =======

    event(new SystemLogEvent(
      module: 'cms',
      eventType: 'publish_data',
      userId: $userId,
      entityType: 'data',
      entityId: $entrySlug
    ));

    return $entry;
  }
}}
