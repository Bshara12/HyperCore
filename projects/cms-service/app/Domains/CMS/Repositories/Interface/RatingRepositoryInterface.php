<?php

namespace App\Domains\CMS\Repositories\Interface;

use App\Domains\CMS\DTOs\Rate\RateDTO;

interface RatingRepositoryInterface
{
  public function findUserRating($userId, $type, $id);
  public function create(RateDTO $dto);
  public function update($rating, RateDTO $dto);
  public function getStats($type, $id);

  
  public function paginateByRateable(string $type, int $id, int $perPage);
}
