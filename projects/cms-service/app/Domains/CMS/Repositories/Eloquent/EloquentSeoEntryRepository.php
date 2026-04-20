<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\Repositories\Interface\SeoEntryRepository;
use Illuminate\Support\Facades\DB;


class EloquentSeoEntryRepository implements SeoEntryRepository
{
  public function insertForEntry(
    int $entryId,
    array $seoData
  ): void {
    $rows = [];

    foreach ($seoData as $language => $data) {
      $rows[] = [
        'data_entry_id' => $entryId,
        'language' => $language,
        'meta_title' => $data['meta_title'] ?? null,
        'meta_description' => $data['meta_description'] ?? null,
        'slug' => $data['slug'] ?? null,
        'canonical_url' => $data['canonical_url'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
      ];
    }

    DB::table('seo_entries')->insert($rows);
  }

  public function getForEntry(int $entryId): array
  {
    return DB::table('seo_entries')
      ->where('data_entry_id', $entryId)
      ->get()
      ->map(fn($r) => (array) $r)
      ->toArray();
  }
  public function deleteForEntry(int $entryId): void
  {
    DB::table('seo_entries')
      ->where('data_entry_id', $entryId)
      ->delete();
  }
}
