<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Analytics\Actions\GetAdminOverviewAction;
use App\Domains\CMS\Analytics\Actions\GetContentGrowthAction;
use App\Domains\CMS\Analytics\Actions\GetContentSummaryAction;
use App\Domains\CMS\Analytics\Actions\GetProjectsGrowthAction;
use App\Domains\CMS\Analytics\Actions\GetRatingsReportAction;
use App\Domains\CMS\Analytics\Actions\GetTopRatedEntriesAction;
use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;

class AnalyticsService
{
  public function __construct(
    protected GetAdminOverviewAction $adminOverview,
    protected GetProjectsGrowthAction $projectsGrowth,
    protected GetContentSummaryAction $contentSummary,
    protected GetContentGrowthAction $contentGrowth,
    protected GetTopRatedEntriesAction $topRatedEntries,
    protected GetRatingsReportAction $ratingsReport
  ) {}

  public function adminOverview(AdminOverviewDTO $dto)
  {
    return $this->adminOverview->execute($dto);
  }

  public function projectsGrowth(AdminOverviewDTO $dto)
  {
    return $this->projectsGrowth->execute($dto);
  }

  public function contentSummary(AnalyticsFilterDTO $dto)
  {
    return $this->contentSummary->execute($dto);
  }

  public function contentGrowth(AnalyticsFilterDTO $dto)
  {
    return $this->contentGrowth->execute($dto);
  }

  public function topRatedEntries(AnalyticsFilterDTO $dto)
  {
    return $this->topRatedEntries->execute($dto);
  }

  public function ratingsReport(AnalyticsFilterDTO $dto)
  {
    return $this->ratingsReport->execute($dto);
  }

}
