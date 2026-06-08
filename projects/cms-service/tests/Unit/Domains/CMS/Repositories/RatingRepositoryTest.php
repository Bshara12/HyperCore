<?php

namespace Tests\Unit\Domains\CMS\Repositories;

use App\Domains\CMS\DTOs\Rate\RateDTO;
use App\Domains\CMS\Repositories\Eloquent\RatingRepository;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new RatingRepository();
  $this->user = User::factory()->create();
});

test('findUserRating returns rating when it exists', function () {
  $rating = Rating::factory()->create([
    'user_id' => $this->user->id,
    'rateable_type' => 'App\Models\Post',
    'rateable_id' => 1
  ]);

  $found = $this->repository->findUserRating($this->user->id, 'App\Models\Post', 1);

  expect($found->id)->toBe($rating->id);
});

test('findUserRating returns null when it does not exist', function () {
  $found = $this->repository->findUserRating($this->user->id, 'App\Models\Post', 999);

  expect($found)->toBeNull();
});

test('create persists a new rating in database', function () {
  $dto = new RateDTO(
    userId: $this->user->id,
    rateableType: 'App\Models\Post',
    rateableId: 1,
    rating: 5,
    review: 'Excellent!'
  );

  $rating = $this->repository->create($dto);

  $this->assertDatabaseHas('ratings', [
    'user_id' => $this->user->id,
    'rating' => 5,
    'review' => 'Excellent!'
  ]);
  expect($rating->id)->toBeGreaterThan(0);
});

test('update modifies existing rating', function () {
  // إضافة القيم الناقصة هنا
  $rating = Rating::factory()->create([
    'rating' => 1,
    'rateable_type' => 'App\Models\Post',
    'rateable_id' => 1
  ]);

  $dto = new RateDTO(
    userId: $rating->user_id,
    rateableType: $rating->rateable_type,
    rateableId: $rating->rateable_id,
    rating: 5,
    review: 'Updated review'
  );

  $updated = $this->repository->update($rating, $dto);

  expect($updated->rating)->toBe(5)
    ->and($updated->review)->toBe('Updated review');
});

test('getStats calculates count and average correctly', function () {
  Rating::factory()->create(['rateable_type' => 'Post', 'rateable_id' => 1, 'rating' => 4]);
  Rating::factory()->create(['rateable_type' => 'Post', 'rateable_id' => 1, 'rating' => 2]);

  $stats = $this->repository->getStats('Post', 1);

  expect((int)$stats->count)->toBe(2)
    ->and((float)$stats->avg)->toBe(3.0);
});

test('paginateByRateable returns paginated results', function () {
  Rating::factory()->count(15)->create(['rateable_type' => 'Post', 'rateable_id' => 1]);

  $results = $this->repository->paginateByRateable('Post', 1, 10);

  expect($results)->toBeInstanceOf(LengthAwarePaginator::class)
    ->and($results->total())->toBe(15)
    ->and($results->count())->toBe(10);
});
