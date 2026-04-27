<?php

namespace App\Domains\CMS\Analytics\Repositories;

use App\Domains\CMS\Analytics\DTOs\AdminOverviewDTO;
use App\Domains\CMS\Analytics\DTOs\AnalyticsFilterDTO;
use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataType;
use App\Models\Project;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;

class EloquentCmsAnalyticsRepository implements AnalyticsRepositoryInterface
{
  // =========================================================
  // ADMIN — Platform Level
  // =========================================================

  public function getAdminOverview(string $from, string $to): array
  {
    // إجمالي المشاريع
    $projectStats = Project::query()->whereNull('deleted_at')->selectRaw("
                COUNT(*) as total_projects,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_projects
            ", [$from . ' 00:00:00', $to . ' 23:59:59'])
      ->first();

    // استخدام الـ modules
    $modulesStats = Project::query()
      ->whereNull('deleted_at')
      ->selectRaw("
                SUM(JSON_CONTAINS(COALESCE(enabled_modules, '[]'), '\"ecommerce\"')) as ecommerce_enabled,
                SUM(JSON_CONTAINS(COALESCE(enabled_modules, '[]'), '\"booking\"'))   as booking_enabled
            ")
      ->first();

    // إجمالي الـ Data Types
    $dataTypesCount = DataType::query()
      ->whereNull('deleted_at')
      ->count();

    // إجمالي الـ Entries
    $entriesStats = DataEntry::query()
      ->whereNull('deleted_at')
      ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'published'  THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'draft'      THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN status = 'scheduled'  THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN status = 'archived'   THEN 1 ELSE 0 END) as archived
            ")
      ->first();

    // إجمالي التقييمات
    $ratingsStats = Rating::query()
      ->selectRaw("COUNT(*) as total, ROUND(AVG(rating), 2) as avg_rating")
      ->first();

    // إجمالي الـ Collections
    $collectionsCount = DataCollection::query()->count();

    return [
      'projects' => [
        'total'     => (int) $projectStats->total_projects,
        'new'       => (int) $projectStats->new_projects,
      ],
      'modules_usage' => [
        'ecommerce_enabled' => (int) ($modulesStats->ecommerce_enabled ?? 0),
        'booking_enabled'   => (int) ($modulesStats->booking_enabled   ?? 0),
      ],
      'content' => [
        'total_data_types'   => $dataTypesCount,
        'total_collections'  => $collectionsCount,
        'total_entries'      => (int) $entriesStats->total,
        'published_entries'  => (int) $entriesStats->published,
        'draft_entries'      => (int) $entriesStats->drafts,
        'scheduled_entries'  => (int) $entriesStats->scheduled,
        'archived_entries'   => (int) $entriesStats->archived,
        'publish_rate'       => $entriesStats->total > 0
          ? round(($entriesStats->published / $entriesStats->total) * 100, 2)
          : 0,
      ],
      'ratings' => [
        'total'      => (int) $ratingsStats->total,
        'avg_rating' => (float) $ratingsStats->avg_rating,
      ],
    ];
  }

  public function getProjectsGrowth(AdminOverviewDTO $dto): array
  {
    $groupBy = $this->resolveGroupBy($dto->period);

    $rows = Project::query()
      ->whereNull('deleted_at')
      ->whereBetween('created_at', [
        $dto->from . ' 00:00:00',
        $dto->to   . ' 23:59:59',
      ])
      ->selectRaw("{$groupBy} as label, COUNT(*) as count")
      ->groupByRaw($groupBy)
      ->orderBy('label')
      ->get();

    return [
      'period' => $dto->period,
      'from'   => $dto->from,
      'to'     => $dto->to,
      'data'   => $rows->map(fn($r) => [
        'label' => $r->label,
        'count' => (int) $r->count,
      ])->toArray(),
    ];
  }

    // =========================================================
    // PROJECT OWNER — Per Project
    // =========================================================

  /**
   * ملخص المحتوى لكل data type في المشروع
   */
  public function getContentSummary(AnalyticsFilterDTO $dto): array
  {
    $dataTypes = DB::table('data_types as dt')
      ->leftJoin('data_entries as de', function ($join) {
        $join->on('de.data_type_id', '=', 'dt.id')
          ->whereNull('de.deleted_at');
      })
      ->where('dt.project_id', $dto->projectId)
      ->whereNull('dt.deleted_at')
      ->selectRaw("
                dt.id            as data_type_id,
                dt.name          as data_type_name,
                dt.slug          as data_type_slug,
                dt.is_active,
                COUNT(de.id)     as total_entries,
                SUM(CASE WHEN de.status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN de.status = 'draft'     THEN 1 ELSE 0 END) as drafts,
                SUM(CASE WHEN de.status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
                SUM(CASE WHEN de.status = 'archived'  THEN 1 ELSE 0 END) as archived,
                ROUND(AVG(de.ratings_avg), 2)  as avg_rating,
                SUM(de.ratings_count)           as total_ratings
            ")
      ->groupBy('dt.id', 'dt.name', 'dt.slug', 'dt.is_active')
      ->orderByRaw('total_entries DESC')
      ->get();

    // إجمالي الـ collections للمشروع
    $collectionsStats = DataCollection::query()
      ->where('project_id', $dto->projectId)
      ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN type = 'manual'  THEN 1 ELSE 0 END) as manual,
                SUM(CASE WHEN type = 'dynamic' THEN 1 ELSE 0 END) as dynamic,
                SUM(CASE WHEN is_offer = 1     THEN 1 ELSE 0 END) as offer_collections,
                SUM(CASE WHEN is_active = 1    THEN 1 ELSE 0 END) as active
            ")
      ->first();

    return [
      'project_id'  => $dto->projectId,
      'data_types'  => $dataTypes->map(fn($r) => [
        'data_type_id'   => $r->data_type_id,
        'name'           => $r->data_type_name,
        'slug'           => $r->data_type_slug,
        'is_active'      => (bool) $r->is_active,
        'total_entries'  => (int) $r->total_entries,
        'published'      => (int) $r->published,
        'drafts'         => (int) $r->drafts,
        'scheduled'      => (int) $r->scheduled,
        'archived'       => (int) $r->archived,
        'publish_rate'   => $r->total_entries > 0
          ? round(($r->published / $r->total_entries) * 100, 2)
          : 0,
        'avg_rating'     => (float) $r->avg_rating,
        'total_ratings'  => (int) $r->total_ratings,
      ])->toArray(),
      'collections' => [
        'total'              => (int) $collectionsStats->total,
        'manual'             => (int) $collectionsStats->manual,
        'dynamic'            => (int) $collectionsStats->dynamic,
        'offer_collections'  => (int) $collectionsStats->offer_collections,
        'active'             => (int) $collectionsStats->active,
      ],
    ];
  }

  /**
   * نمو المحتوى المنشور عبر الزمن
   */
  public function getContentGrowth(AnalyticsFilterDTO $dto): array
  {
    $groupBy = $this->resolveGroupBy($dto->period);

    $rows = DataEntry::query()
      ->where('project_id', $dto->projectId)
      ->where('status', 'published')
      ->whereNotNull('published_at')
      ->whereBetween('published_at', [
        $dto->from . ' 00:00:00',
        $dto->to   . ' 23:59:59',
      ])
      ->whereNull('deleted_at')
      ->selectRaw(str_replace('created_at', 'published_at', $groupBy) . " as label, COUNT(*) as count")
      ->groupByRaw(str_replace('created_at', 'published_at', $groupBy))
      ->orderBy('label')
      ->get();

    return [
      'period' => $dto->period,
      'from'   => $dto->from,
      'to'     => $dto->to,
      'data'   => $rows->map(fn($r) => [
        'label' => $r->label,
        'count' => (int) $r->count,
      ])->toArray(),
    ];
  }

  /**
   * أعلى المحتوى تقييماً
   */
  public function getTopRatedEntries(AnalyticsFilterDTO $dto): array
  {
    $entries = DB::table('data_entries as de')
      ->join('data_types as dt', 'dt.id', '=', 'de.data_type_id')
      ->where('de.project_id', $dto->projectId)
      ->where('de.ratings_count', '>', 0)
      ->whereNull('de.deleted_at')
      ->selectRaw("
                de.id,
                de.slug,
                de.status,
                de.ratings_count,
                de.ratings_avg,
                dt.name  as data_type_name,
                dt.slug  as data_type_slug
            ")
      ->orderByRaw('de.ratings_avg DESC, de.ratings_count DESC')
      ->limit($dto->limit)
      ->get();

    return [
      'project_id' => $dto->projectId,
      'limit'      => $dto->limit,
      'entries'    => $entries->map(fn($r) => [
        'id'             => $r->id,
        'slug'           => $r->slug,
        'status'         => $r->status,
        'ratings_count'  => (int) $r->ratings_count,
        'ratings_avg'    => (float) $r->ratings_avg,
        'data_type'      => [
          'name' => $r->data_type_name,
          'slug' => $r->data_type_slug,
        ],
      ])->toArray(),
    ];
  }

  /**
   * تقرير التقييمات للمشروع
   */
  public function getRatingsReport(AnalyticsFilterDTO $dto): array
  {
    // IDs الـ entries للمشروع
    $entryIds = DataEntry::query()
      ->where('project_id', $dto->projectId)
      ->whereNull('deleted_at')
      ->pluck('id');

    if ($entryIds->isEmpty()) {
      return $this->emptyRatingsReport($dto);
    }

    // ملخص عام
    $summary = Rating::query()
      ->whereIn('rateable_id', $entryIds)
      ->where('rateable_type', 'data')
      ->whereBetween('created_at', [
        $dto->from . ' 00:00:00',
        $dto->to   . ' 23:59:59',
      ])
      ->selectRaw("
                COUNT(*)                         as total_ratings,
                ROUND(AVG(rating), 2)            as avg_rating,
                COUNT(CASE WHEN review IS NOT NULL AND review != '' THEN 1 END) as with_review,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
            ")
      ->first();

    // توزيع التقييمات عبر الزمن
    $groupBy = $this->resolveGroupBy($dto->period);
    $trend = DB::table('ratings')
      ->whereIn('rateable_id', $entryIds)
      ->where('rateable_type', 'data')
      ->whereBetween('created_at', [
        $dto->from . ' 00:00:00',
        $dto->to   . ' 23:59:59',
      ])
      ->selectRaw("{$groupBy} as label, COUNT(*) as count, ROUND(AVG(rating),2) as avg")
      ->groupByRaw($groupBy)
      ->orderBy('label')
      ->get();

    // توزيع على Project نفسه
    $projectRatings = Rating::query()
      ->where('rateable_type', 'project')
      ->where('rateable_id', $dto->projectId)
      ->whereBetween('created_at', [
        $dto->from . ' 00:00:00',
        $dto->to   . ' 23:59:59',
      ])
      ->selectRaw("
                COUNT(*) as total,
                ROUND(AVG(rating), 2) as avg_rating
            ")
      ->first();

    $total = (int) $summary->total_ratings;

    return [
      'period'          => ['from' => $dto->from, 'to' => $dto->to],
      'content_ratings' => [
        'total'        => $total,
        'avg_rating'   => (float) $summary->avg_rating,
        'with_review'  => (int)   $summary->with_review,
        'distribution' => [
          5 => ['count' => (int)$summary->five_star,  'percentage' => $total > 0 ? round(($summary->five_star  / $total) * 100, 2) : 0],
          4 => ['count' => (int)$summary->four_star,  'percentage' => $total > 0 ? round(($summary->four_star  / $total) * 100, 2) : 0],
          3 => ['count' => (int)$summary->three_star, 'percentage' => $total > 0 ? round(($summary->three_star / $total) * 100, 2) : 0],
          2 => ['count' => (int)$summary->two_star,   'percentage' => $total > 0 ? round(($summary->two_star   / $total) * 100, 2) : 0],
          1 => ['count' => (int)$summary->one_star,   'percentage' => $total > 0 ? round(($summary->one_star   / $total) * 100, 2) : 0],
        ],
        'trend'        => $trend->map(fn($r) => [
          'label' => $r->label,
          'count' => (int)   $r->count,
          'avg'   => (float) $r->avg,
        ])->toArray(),
      ],
      'project_ratings' => [
        'total'      => (int)   ($projectRatings->total      ?? 0),
        'avg_rating' => (float) ($projectRatings->avg_rating ?? 0),
      ],
    ];
  }

  // =========================================================
  // Helpers
  // =========================================================

  public function resolveGroupBy(string $period): string
  {
    return match ($period) {
      'weekly'  => "DATE_FORMAT(created_at, '%x-W%v')",
      'monthly' => "DATE_FORMAT(created_at, '%Y-%m')",
      default   => "DATE(created_at)",
    };
  }

  public function emptyRatingsReport(AnalyticsFilterDTO $dto): array
  {
    return [
      'period'          => ['from' => $dto->from, 'to' => $dto->to],
      'content_ratings' => [
        'total'        => 0,
        'avg_rating'   => 0,
        'with_review'  => 0,
        'distribution' => [5 => ['count' => 0, 'percentage' => 0], 4 => ['count' => 0, 'percentage' => 0], 3 => ['count' => 0, 'percentage' => 0], 2 => ['count' => 0, 'percentage' => 0], 1 => ['count' => 0, 'percentage' => 0]],
        'trend'        => [],
      ],
      'project_ratings' => ['total' => 0, 'avg_rating' => 0],
    ];
  }
}
