<?php

namespace Tests\Unit\Domains\CMS\Repositories;

use App\Domains\CMS\Repositories\Eloquent\EloquentSeoEntryRepository;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentSeoEntryRepository();
});

test('insertForEntry inserts multiple language seo data correctly', function () {
  $entry = DataEntry::factory()->create();

  $seoData = [
    'en' => [
      'meta_title' => 'English Title',
      'meta_description' => 'English Desc',
      'slug' => 'en-slug',
      'canonical_url' => 'https://en.example.com'
    ],
    'ar' => [
      'meta_title' => 'عنوان عربي',
      'meta_description' => 'وصف عربي',
      'slug' => 'ar-slug',
      'canonical_url' => 'https://ar.example.com'
    ]
  ];

  $this->repository->insertForEntry($entry->id, $seoData);

  $this->assertDatabaseHas('seo_entries', [
    'data_entry_id' => $entry->id,
    'language' => 'en',
    'meta_title' => 'English Title'
  ]);

  $this->assertDatabaseHas('seo_entries', [
    'data_entry_id' => $entry->id,
    'language' => 'ar',
    'meta_title' => 'عنوان عربي'
  ]);
});

test('getForEntry retrieves all seo entries for a given entry', function () {
  $entry = DataEntry::factory()->create();

  \Illuminate\Support\Facades\DB::table('seo_entries')->insert([
    ['data_entry_id' => $entry->id, 'language' => 'en', 'meta_title' => 'T1', 'created_at' => now(), 'updated_at' => now()],
    ['data_entry_id' => $entry->id, 'language' => 'ar', 'meta_title' => 'T2', 'created_at' => now(), 'updated_at' => now()],
  ]);

  $results = $this->repository->getForEntry($entry->id);

  expect($results)->toHaveCount(2)
    ->and($results[0]['language'])->toBe('ar') // 'ar' تأتي أولاً أبجدياً
    ->and($results[1]['language'])->toBe('en'); // 'en' تأتي ثانياً
});

test('deleteForEntry removes all seo entries for a given entry', function () {
  $entry = DataEntry::factory()->create();

  \Illuminate\Support\Facades\DB::table('seo_entries')->insert([
    ['data_entry_id' => $entry->id, 'language' => 'en', 'meta_title' => 'T1', 'created_at' => now(), 'updated_at' => now()],
  ]);

  $this->repository->deleteForEntry($entry->id);

  $this->assertDatabaseMissing('seo_entries', ['data_entry_id' => $entry->id]);
});
