<?php

namespace App\Domains\CMS\Analytics\Repositories;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;

interface AnalyticsRepositoryInterface
{
  public function getAdminOverview(string $from, string $to): array;

  public function getProjectsGrowth(AdminOverviewDTO $dto): array;

  public function getContentSummary(AnalyticsFilterDTO $dto): array;

  public function getContentGrowth(AnalyticsFilterDTO $dto): array;

  public function getTopRatedEntries(AnalyticsFilterDTO $dto): array;

  public function getRatingsReport(AnalyticsFilterDTO $dto): array;

  public function resolveGroupBy(string $period): string;

  public function emptyRatingsReport(AnalyticsFilterDTO $dto): array;
  }
