<?php

namespace App\Domains\CMS\Services\Rate;

use App\Domains\CMS\Actions\Rate\GetRatingsAction;
use App\Domains\CMS\Actions\Rate\GetRatingStatsAction;
use App\Domains\CMS\Actions\Rate\RateAction;
use App\Domains\CMS\DTOs\Rate\GetRatingsDTO;
use App\Domains\CMS\DTOs\Rate\GetRatingStatsDTO;
use App\Domains\CMS\DTOs\Rate\RateDTO;

class RatingService
{
  public function __construct(
    private RateAction $action,
    private GetRatingsAction $getRatingsAction,
    private GetRatingStatsAction $statsAction
  ) {}

  public function rate(RateDTO $dto)
  {
    return $this->action->execute($dto);
  }
  public function getRatings(GetRatingsDTO $dto)
  {
    return $this->getRatingsAction->execute($dto);
  }


  public function getStats(GetRatingStatsDTO $dto): array
  {
    return $this->statsAction->execute($dto);
  }
}
