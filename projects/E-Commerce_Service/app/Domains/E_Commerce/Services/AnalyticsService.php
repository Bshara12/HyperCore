<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Analytics\Actions\GetOffersAnalyticsAction;
use App\Domains\E_Commerce\Analytics\Actions\GetReturnsAnalyticsAction;
use App\Domains\E_Commerce\Analytics\Actions\GetSalesSummaryAction;
use App\Domains\E_Commerce\Analytics\Actions\GetSalesTrendAction;
use App\Domains\E_Commerce\Analytics\Actions\GetTopCustomersAction;
use App\Domains\E_Commerce\Analytics\Actions\GetTopProductsAction;


class AnalyticsService
{
  public function __construct(
    protected GetOffersAnalyticsAction $offersAnalyticsAction,
    protected GetReturnsAnalyticsAction $returnsAnalyticsAction,
    protected GetSalesSummaryAction $salesSummaryAction,
    protected GetSalesTrendAction $salesTrendAction,
    protected GetTopCustomersAction $topCustomersAction,
    protected GetTopProductsAction $topProductsAction
  ) {}

  public function getSalesSummary($dto)
  {
    return $this->salesSummaryAction->execute($dto);
  }

  public function getSalesTrend($dto)
  {
    return $this->salesTrendAction->execute($dto);
  }

  public function getTopCustomers($dto)
  {
    return $this->topCustomersAction->execute($dto);
  }

  public function getTopProducts($dto)
  {
    return $this->topProductsAction->execute($dto);
  }
  public function getReturnsAnalytics($dto)
  {
    return $this->returnsAnalyticsAction->execute($dto);
  }
  public function getOffersAnalytics($dto)
  {
    return $this->offersAnalyticsAction->execute($dto);
  }
}
