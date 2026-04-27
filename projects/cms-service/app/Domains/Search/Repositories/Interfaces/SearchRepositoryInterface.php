<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Support\ProcessedKeyword;

interface SearchRepositoryInterface
{
  /**
   * البحث الفعلي - يرجع array خام من DB
   * الـ Action هو من يحوّله إلى DTOs
   *
   * @return array{items: array, total: int}
   */


  // public function search(SearchQueryDTO $dto): array;
  public function search(
    SearchQueryDTO $dto,
    ProcessedKeyword $processed,
    UserPreferenceDTO $preference   // ← إضافة
  ): array;
}
