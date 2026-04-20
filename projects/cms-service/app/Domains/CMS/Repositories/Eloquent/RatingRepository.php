<?php

namespace App\Domains\CMS\Repositories\Eloquent;

use App\Domains\CMS\DTOs\Rate\RateDTO;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;
use App\Models\Rating;

class RatingRepository implements RatingRepositoryInterface
{
  public function findUserRating($userId, $type, $id)
  {
    return Rating::where('user_id', $userId)
      ->where('rateable_type', $type)
      ->where('rateable_id', $id)
      ->first();
  }

  public function create(RateDTO $dto)
  {
    return Rating::create([
      'user_id' => $dto->userId,
      'rateable_type' => $dto->rateableType,
      'rateable_id' => $dto->rateableId,
      'rating' => $dto->rating,
      'review' => $dto->review,
    ]);
  }

  public function update($rating, RateDTO $dto)
  {
    $rating->update([
      'rating' => $dto->rating,
      'review' => $dto->review,
    ]);

    return $rating;
  }

  public function getStats($type, $id)
  {
    return Rating::where('rateable_type', $type)
      ->where('rateable_id', $id)
      ->selectRaw('COUNT(*) as count, AVG(rating) as avg')
      ->first();
  }

  // public function paginateByRateable(string $type, int $id, int $perPage)
  // {
  //   return Rating::find() // ⭐ user info
  //     ->where('rateable_type', $type)
  //     ->where('rateable_id', $id)
  //     ->latest()
  //     ->paginate($perPage);
  // }

  public function paginateByRateable(string $type, int $id, int $perPage)
  {
    return Rating::query()
      ->where('rateable_type', $type)
      ->where('rateable_id', $id)
      ->latest()
      ->paginate($perPage);
  }
}
