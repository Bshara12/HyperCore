<?php

namespace App\Domains\CMS\Actions\Rate;

use App\Domains\CMS\DTOs\Rate\RateDTO;
use App\Domains\CMS\Repositories\Interface\DataEntryRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\RatingRepositoryInterface;
use App\Domains\CMS\Support\CacheKeys;
use App\Support\CurrentProject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RateAction
{
  public function __construct(
    private RatingRepositoryInterface $ratings,
    private ProjectRepositoryInterface $projects,
    private DataEntryRepositoryInterface $dataEntries
  ) {}

  // public function execute(RateDTO $dto)
  // {
  //   DB::beginTransaction();

  //   try {
  //     $existing = $this->ratings->findUserRating(
  //       $dto->userId,
  //       $dto->rateableType,
  //       $dto->rateableId
  //     );

  //     if ($existing) {
  //       $this->ratings->update($existing, $dto);
  //     } else {
  //       $this->ratings->create($dto);
  //     }

  //     $this->updateStats($dto);

  //     DB::commit();

  //     return true;
  //   } catch (\Exception $e) {
  //     DB::rollBack();
  //     throw $e;
  //   }
  // }


  public function execute(RateDTO $dto)
  {
    DB::beginTransaction();

    try {

      // ⭐ جيب المشروع الحالي
      $project = CurrentProject::get();
      if (!$project) {
        throw new \Exception('Project not resolved');
      }

      // ⭐ تحقق العلاقة
      $this->validateRelation($dto, $project->id);

      // ⭐ create/update rating
      $existing = $this->ratings->findUserRating(
        $dto->userId,
        $dto->rateableType,
        $dto->rateableId
      );

      if ($existing) {
        $this->ratings->update($existing, $dto);
      } else {
        $this->ratings->create($dto);
      }

      $this->updateStats($dto);

      DB::commit();

      return true;
    } catch (\Exception $e) {
      DB::rollBack();
      throw $e;
    }
  }



  // private function validateRelation(RateDTO $dto, int $projectId): void
  // {
  //   // فقط إذا التقييم على data
  //   if ($dto->rateableType !== 'data') {
  //     return;
  //   }

  //   // ⭐ جيب data entry
  //   $data = $this->dataEntries->findOrFail($dto->rateableId);

  //   // ⭐ تحقق إنها لنفس المشروع
  //   if ($data->project_id !== $projectId) {
  //     throw new \Exception('This data does not belong to this project');
  //   }
  // }
  private function validateRelation(RateDTO $dto, int $projectId): void
  {
    // 🟢 الحالة 1: تقييم Project
    if ($dto->rateableType === 'project') {

      if ($dto->rateableId !== $projectId) {
        throw new \Exception('You cannot rate a project outside current context');
      }

      return;
    }

    // 🟡 الحالة 2: تقييم Data
    if ($dto->rateableType === 'data') {

      $data = $this->dataEntries->findOrFail($dto->rateableId);

      if ($data->project_id !== $projectId) {
        throw new \Exception('This data does not belong to this project');
      }

      return;
    }

    // 🔴 fallback (optional safety)
    throw new \Exception('Invalid rateable type');
  }




  private function updateStats(RateDTO $dto)
  {
    $stats = $this->ratings->getStats(
      $dto->rateableType,
      $dto->rateableId
    );

    $data = [
      'ratings_count' => $stats->count,
      'ratings_avg'   => round($stats->avg, 2)
    ];

    match ($dto->rateableType) {
      'project' => $this->projects->updateRatingStats(
        $this->projects->findById($dto->rateableId)->id,
        $data
      ),
      'data' => $this->dataEntries->updateRatingStats(
        $dto->rateableId,
        $data
      ),
    };

    Cache::forget(CacheKeys::ratingStats($dto->rateableType, $dto->rateableId));
  }
}
