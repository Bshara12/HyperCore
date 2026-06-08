<?php

use App\Domains\CMS\Support\CacheKeys;

test('it generates correct cache keys', function (callable $method, array $args, string $expected) {
  expect(call_user_func_array($method, $args))->toBe($expected);
})->with([
  // Projects
  'all projects'        => [[CacheKeys::class, 'allProjects'], [], 'projects:all'],
  'project by id'       => [[CacheKeys::class, 'project'], [10], 'projects:10'],

  // DataTypes
  'data types'          => [[CacheKeys::class, 'dataTypes'], [1], 'project:1:data_types'],
  'data type by id'     => [[CacheKeys::class, 'dataType'], [2], 'data_types:2'],
  'data type by slug'   => [[CacheKeys::class, 'dataTypeBySlug'], ['my-slug', 5], 'project:5:data_types:slug:my-slug'],

  // Fields
  'fields'              => [[CacheKeys::class, 'fields'], [3], 'data_type:3:fields'],

  // Collections
  'collections'         => [[CacheKeys::class, 'collections'], [1], 'project:1:collections'],
  'collection by slug'  => [[CacheKeys::class, 'collection'], [1, 'books'], 'project:1:collections:books'],
  'collection by id'    => [[CacheKeys::class, 'collectionById'], [5], 'collections:5'],
  'collection items'    => [[CacheKeys::class, 'collectionItems'], [5], 'collections:5:items'],
  'collection entries'  => [[CacheKeys::class, 'collectionEntries'], [5], 'collections:5:entries'],

  // Entries
  'entry default'       => [[CacheKeys::class, 'entry'], [100], 'entries:100:lang:default'],
  'entry with lang'     => [[CacheKeys::class, 'entry'], [100, 'ar'], 'entries:100:lang:ar'],
  'entry by slug'       => [[CacheKeys::class, 'entryBySlug'], ['post-1', 'en'], 'entries:slug:post-1:lang:en'],

  // Ratings
  'rating stats'        => [[CacheKeys::class, 'ratingStats'], ['product', 50], 'ratings:product:50:stats'],
]);

test('it has correct TTL constants', function () {
  expect(CacheKeys::TTL_SHORT)->toBe(300)
    ->and(CacheKeys::TTL_MEDIUM)->toBe(3600)
    ->and(CacheKeys::TTL_LONG)->toBe(86400);
});
