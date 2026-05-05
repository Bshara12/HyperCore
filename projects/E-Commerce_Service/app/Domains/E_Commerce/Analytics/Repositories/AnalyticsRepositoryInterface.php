<?php

namespace App\Domains\E_Commerce\Analytics\Repositories;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;

interface AnalyticsRepositoryInterface
{
  public function getSalesSummary(AnalyticsFilterDTO $dto): array;

  public function getSalesTrend(AnalyticsFilterDTO $dto): array;

  public function getTopProducts(AnalyticsFilterDTO $dto): array;

  public function getOffersAnalytics(AnalyticsFilterDTO $dto): array;

  public function getTopCustomers(AnalyticsFilterDTO $dto): array;

  public function getReturnsAnalytics(AnalyticsFilterDTO $dto): array;

  public function resolveGroupBy(string $period, string $column): string;
}
