<?php

namespace App\Domains\CMS\Actions\Rate;

use App\Domains\Auth\Service\AuthServiceClient;
use App\Domains\CMS\DTOs\Rate\GetRatingsDTO;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;

// class GetRatingsAction
// {
//     public function __construct(
//         private RatingRepositoryInterface $ratings
//     ) {}

//     public function execute(GetRatingsDTO $dto)
//     {
//         return $this->ratings->paginateByRateable(
//             $dto->rateableType,
//             $dto->rateableId,
//             $dto->perPage
//         );
//     }
// }

class GetRatingsAction
{
  public function __construct(
    private RatingRepositoryInterface $ratings,
    private AuthServiceClient $authClient // ⭐ جديد
  ) {}

  // public function execute(GetRatingsDTO $dto)
  // {
  //     $ratings = $this->ratings->paginateByRateable(
  //         $dto->rateableType,
  //         $dto->rateableId,
  //         $dto->perPage
  //     );

  //     // ⭐ collect user_ids
  //     $userIds = collect($ratings->items())
  //         ->pluck('user_id')
  //         ->filter()
  //         ->unique()
  //         ->values()
  //         ->toArray();

  //     // ⭐ نجيب المستخدمين دفعة وحدة
  //     $users = $this->authClient->getUsersByIds($userIds);

  //     // ⭐ نحولهم map
  //     $usersMap = collect($users)->keyBy('id');

  //     // ⭐ نربطهم مع ratings
  //     foreach ($ratings as $rating) {
  //         $rating->user = $usersMap[$rating->user_id] ?? null;
  //     }

  //     return $ratings;
  // }
  public function execute(GetRatingsDTO $dto)
  {
    $ratings = $this->ratings->paginateByRateable(
      $dto->rateableType,
      $dto->rateableId,
      $dto->perPage
    );

    // ⭐ جمع user_ids
    $userIds = collect($ratings->items())
      ->pluck('user_id')
      ->filter()
      ->unique()
      ->values()
      ->toArray();

    if (empty($userIds)) {
      return $ratings;
    }

    // ⭐ جلب المستخدمين
    $users = $this->authClient->getUsersByIds($userIds);

    // ⭐ تحويلهم map
    $usersMap = collect($users)->keyBy('id');

    // ⭐ ربطهم مع ratings
    foreach ($ratings as $rating) {
      $rating->user = $usersMap[$rating->user_id] ?? null;
    }

    return $ratings;
  }
}
