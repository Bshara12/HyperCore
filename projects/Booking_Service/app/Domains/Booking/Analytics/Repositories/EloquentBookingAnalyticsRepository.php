<?php

namespace App\Domains\Booking\Analytics\Repositories;

use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EloquentBookingAnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function getOverview(AnalyticsFilterDTO $dto): array
    {
        $from = $dto->from.' 00:00:00';
        $to = $dto->to.' 23:59:59';

        // ملخص الحجوزات
        $bookingStats = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*)                                                          as total_bookings,
                COALESCE(SUM(amount), 0)                                          as total_revenue,
                COALESCE(ROUND(AVG(amount), 2), 0)                               as avg_booking_value,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END)            as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END)            as confirmed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)            as cancelled,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END)            as completed,
                SUM(CASE WHEN status = 'no_show'   THEN 1 ELSE 0 END)            as no_show,
                COALESCE(SUM(CASE WHEN status = 'cancelled' THEN refund_amount ELSE 0 END), 0) as total_refunded
            ")
            ->first();

        // ملخص الـ Resources
        $resourceStats = DB::table('resources')
            ->where('project_id', $dto->projectId)
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total_resources,
                SUM(CASE WHEN status = 'active'   THEN 1 ELSE 0 END)  as active_resources,
                SUM(CASE WHEN payment_type = 'paid' THEN 1 ELSE 0 END) as paid_resources,
                SUM(CASE WHEN payment_type = 'free' THEN 1 ELSE 0 END) as free_resources
            ")
            ->first();

        $total = (int) $bookingStats->total_bookings;

        return [
            'period' => ['from' => $dto->from, 'to' => $dto->to],
            'bookings' => [
                'total' => $total,
                'total_revenue' => (float) $bookingStats->total_revenue,
                'avg_booking_value' => (float) $bookingStats->avg_booking_value,
                'total_refunded' => (float) $bookingStats->total_refunded,
                'by_status' => [
                    'pending' => (int) $bookingStats->pending,
                    'confirmed' => (int) $bookingStats->confirmed,
                    'cancelled' => (int) $bookingStats->cancelled,
                    'completed' => (int) $bookingStats->completed,
                    'no_show' => (int) $bookingStats->no_show,
                ],
                'cancellation_rate' => $total > 0
                  ? round(($bookingStats->cancelled / $total) * 100, 2) : 0,
                'no_show_rate' => $total > 0
                  ? round(($bookingStats->no_show / $total) * 100, 2) : 0,
                'completion_rate' => $total > 0
                  ? round(($bookingStats->completed / $total) * 100, 2) : 0,
            ],
            'resources' => [
                'total' => (int) $resourceStats->total_resources,
                'active' => (int) $resourceStats->active_resources,
                'paid_resources' => (int) $resourceStats->paid_resources,
                'free_resources' => (int) $resourceStats->free_resources,
            ],
        ];
    }

    public function getBookingTrend(AnalyticsFilterDTO $dto): array
    {
        $from = $dto->from.' 00:00:00';
        $to = $dto->to.' 23:59:59';
        $groupBy = $this->resolveGroupBy($dto->period, 'created_at');

        $rows = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw("
                {$groupBy}                              as label,
                COUNT(*)                                as bookings_count,
                COALESCE(SUM(amount), 0)                as revenue,
                COALESCE(ROUND(AVG(amount), 2), 0)      as avg_value
            ")
            ->groupByRaw($groupBy)
            ->orderBy('label')
            ->get();

        return [
            'period' => $dto->period,
            'from' => $dto->from,
            'to' => $dto->to,
            'data' => $rows->map(fn ($r) => [
                'label' => $r->label,
                'bookings_count' => (int) $r->bookings_count,
                'revenue' => (float) $r->revenue,
                'avg_value' => (float) $r->avg_value,
            ])->toArray(),
        ];
    }

    public function getResourcePerformance(AnalyticsFilterDTO $dto): array
    {
        $from = $dto->from.' 00:00:00';
        $to = $dto->to.' 23:59:59';

        $resources = DB::table('resources as r')
            ->leftJoin('bookings as b', function ($join) use ($from, $to) {
                $join->on('b.resource_id', '=', 'r.id')
                    ->whereBetween('b.created_at', [$from, $to])
                    ->whereNull('b.deleted_at');
            })
            ->where('r.project_id', $dto->projectId)
            ->whereNull('r.deleted_at')
            ->selectRaw("
                r.id as resource_id,
                r.name,
                r.type,
                r.capacity,
                r.payment_type,
                r.price,
                COUNT(b.id) as total_bookings,
                SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN b.status = 'no_show'   THEN 1 ELSE 0 END) as no_show,
                COALESCE(SUM(CASE WHEN b.status NOT IN ('cancelled') THEN b.amount ELSE 0 END), 0) as total_revenue,
                COALESCE(SUM(CASE WHEN b.status = 'cancelled' THEN b.refund_amount ELSE 0 END), 0) as total_refunded,
                COALESCE(ROUND(AVG(CASE WHEN b.status NOT IN ('cancelled')
                    THEN TIMESTAMPDIFF(MINUTE, b.start_at, b.end_at) END), 2), 0) as avg_duration_minutes
            ")
            ->groupBy('r.id', 'r.name', 'r.type', 'r.capacity', 'r.payment_type', 'r.price')
            ->orderByRaw('total_bookings DESC')
            ->get();

        // حساب الـ Occupancy Rate لكل resource
        // عدد ساعات متاحة × عدد الأيام في الفترة
        $totalDays = Carbon::parse($dto->from)
            ->diffInDays(Carbon::parse($dto->to)) + 1;

        // جيب الـ availabilities لكل resource
        $availabilities = DB::table('resource_availabilities')
            ->whereIn('resource_id', $resources->pluck('resource_id'))
            ->where('is_active', true)
            ->selectRaw('
                resource_id,
                SUM(TIMESTAMPDIFF(HOUR, start_time, end_time)) as daily_hours
            ')
            ->groupBy('resource_id')
            ->pluck('daily_hours', 'resource_id');

        // جيب الساعات الفعلية المحجوزة لكل resource
        $bookedHours = DB::table('bookings')
            ->whereIn('resource_id', $resources->pluck('resource_id'))
            ->where('project_id', $dto->projectId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->selectRaw('
                resource_id,
                COALESCE(SUM(TIMESTAMPDIFF(HOUR, start_at, end_at)), 0) as booked_hours
            ')
            ->groupBy('resource_id')
            ->pluck('booked_hours', 'resource_id');

        return [
            'period' => ['from' => $dto->from, 'to' => $dto->to],
            'resources' => $resources->map(function ($r) use ($totalDays, $availabilities, $bookedHours) {

                $dailyAvailableHours = (float) ($availabilities[$r->resource_id] ?? 0);
                $totalAvailableHours = $dailyAvailableHours * $totalDays * $r->capacity;
                $totalBookedHours = (float) ($bookedHours[$r->resource_id] ?? 0);

                $occupancyRate = $totalAvailableHours > 0
                  ? round(($totalBookedHours / $totalAvailableHours) * 100, 2)
                  : 0;

                $totalBookings = (int) $r->total_bookings;

                return [
                    'resource_id' => $r->resource_id,
                    'name' => $r->name,
                    'type' => $r->type,
                    'capacity' => $r->capacity,
                    'payment_type' => $r->payment_type,
                    'price' => (float) $r->price,
                    'total_bookings' => $totalBookings,
                    'confirmed' => (int) $r->confirmed,
                    'completed' => (int) $r->completed,
                    'cancelled' => (int) $r->cancelled,
                    'no_show' => (int) $r->no_show,
                    'total_revenue' => (float) $r->total_revenue,
                    'total_refunded' => (float) $r->total_refunded,
                    'avg_duration_minutes' => (float) $r->avg_duration_minutes,
                    'cancellation_rate' => $totalBookings > 0
                      ? round(($r->cancelled / $totalBookings) * 100, 2) : 0,
                    'occupancy_rate' => $occupancyRate,
                    'total_available_hours' => $totalAvailableHours,
                    'total_booked_hours' => $totalBookedHours,
                ];
            })->toArray(),
        ];
    }

    public function getCancellationReport(AnalyticsFilterDTO $dto): array
    {
        $from = $dto->from.' 00:00:00';
        $to = $dto->to.' 23:59:59';

        // ملخص الإلغاءات
        $summary = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->where('status', 'cancelled')
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*)                                    as total_cancellations,
                COALESCE(SUM(amount), 0)                    as total_amount_cancelled,
                COALESCE(SUM(refund_amount), 0)             as total_refunded,
                COALESCE(ROUND(AVG(refund_amount), 2), 0)   as avg_refund
            ')
            ->first();

        // إجمالي الحجوزات لحساب معدل الإلغاء
        $totalBookings = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->count();

        // الإلغاءات لكل resource
        $byResource = DB::table('bookings as b')
            ->join('resources as r', 'r.id', '=', 'b.resource_id')
            ->where('b.project_id', $dto->projectId)
            ->where('b.status', 'cancelled')
            ->whereBetween('b.created_at', [$from, $to])
            ->whereNull('b.deleted_at')
            ->selectRaw('
                r.id                              as resource_id,
                r.name                            as resource_name,
                r.type                            as resource_type,
                COUNT(b.id)                       as cancellations,
                COALESCE(SUM(b.refund_amount), 0) as total_refunded
            ')
            ->groupBy('r.id', 'r.name', 'r.type')
            ->orderByRaw('cancellations DESC')
            ->get();

        // توزيع الإلغاء عبر الزمن
        $groupBy = $this->resolveGroupBy($dto->period, 'b.created_at');
        $trend = DB::table('bookings as b')
            ->where('b.project_id', $dto->projectId)
            ->where('b.status', 'cancelled')
            ->whereBetween('b.created_at', [$from, $to])
            ->whereNull('b.deleted_at')
            ->selectRaw("
                {$groupBy}                              as label,
                COUNT(*)                                as count,
                COALESCE(SUM(b.refund_amount), 0)       as refunded
            ")
            ->groupByRaw($groupBy)
            ->orderBy('label')
            ->get();

        // no_show تقرير
        $noShowStats = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->where('status', 'no_show')
            ->whereBetween('created_at', [$from, $to])
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*)                   as total,
                COALESCE(SUM(amount), 0)   as revenue_lost
            ')
            ->first();

        $totalCancellations = (int) $summary->total_cancellations;

        return [
            'period' => ['from' => $dto->from, 'to' => $dto->to],
            'summary' => [
                'total_cancellations' => $totalCancellations,
                'total_amount_cancelled' => (float) $summary->total_amount_cancelled,
                'total_refunded' => (float) $summary->total_refunded,
                'avg_refund' => (float) $summary->avg_refund,
                'cancellation_rate' => $totalBookings > 0
                  ? round(($totalCancellations / $totalBookings) * 100, 2) : 0,
                'refund_rate' => $summary->total_amount_cancelled > 0
                  ? round(($summary->total_refunded / $summary->total_amount_cancelled) * 100, 2) : 0,
            ],
            'no_show' => [
                'total' => (int) $noShowStats->total,
                'revenue_lost' => (float) $noShowStats->revenue_lost,
                'no_show_rate' => $totalBookings > 0
                  ? round(($noShowStats->total / $totalBookings) * 100, 2) : 0,
            ],
            'by_resource' => $byResource->map(fn ($r) => [
                'resource_id' => $r->resource_id,
                'resource_name' => $r->resource_name,
                'resource_type' => $r->resource_type,
                'cancellations' => (int) $r->cancellations,
                'total_refunded' => (float) $r->total_refunded,
            ])->toArray(),
            'trend' => $trend->map(fn ($r) => [
                'label' => $r->label,
                'count' => (int) $r->count,
                'refunded' => (float) $r->refunded,
            ])->toArray(),
        ];
    }

    public function getPeakTimes(AnalyticsFilterDTO $dto): array
    {
        $from = $dto->from.' 00:00:00';
        $to = $dto->to.' 23:59:59';

        // أكثر الأيام حجزاً (0=Sunday...6=Saturday)
        $byDayOfWeek = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->selectRaw('
        (DAYOFWEEK(start_at) - 1)     as day_of_week,
        DAYNAME(start_at)             as day_name,
        COUNT(*)                      as bookings_count,
        COALESCE(SUM(amount), 0)      as revenue
    ')
            ->groupByRaw('(DAYOFWEEK(start_at) - 1), DAYNAME(start_at)')
            ->orderByRaw('bookings_count DESC')
            ->get();

        // أكثر الساعات حجزاً
        $byHour = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->selectRaw('
                HOUR(start_at)             as hour,
                COUNT(*)                   as bookings_count,
                COALESCE(SUM(amount), 0)   as revenue
            ')
            ->groupByRaw('HOUR(start_at)')
            ->orderByRaw('bookings_count DESC')
            ->get();

        // أكثر الأشهر حجزاً
        $byMonth = DB::table('bookings')
            ->where('project_id', $dto->projectId)
            ->whereBetween('start_at', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->whereNull('deleted_at')
            ->selectRaw("
                DATE_FORMAT(start_at, '%Y-%m') as month,
                COUNT(*)                        as bookings_count,
                COALESCE(SUM(amount), 0)        as revenue
            ")
            ->groupByRaw("DATE_FORMAT(start_at, '%Y-%m')")
            ->orderByRaw('bookings_count DESC')
            ->get();

        // أسرع resource تُحجز (أقل وقت بين الإنشاء والبدء)
        $avgLeadTime = DB::table('bookings as b')
            ->join('resources as r', 'r.id', '=', 'b.resource_id')
            ->where('b.project_id', $dto->projectId)
            ->whereBetween('b.start_at', [$from, $to])
            ->whereNotIn('b.status', ['cancelled'])
            ->whereNull('b.deleted_at')
            ->selectRaw('
                r.id                                                            as resource_id,
                r.name,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, b.created_at, b.start_at)), 2)   as avg_lead_time_hours
            ')
            ->groupBy('r.id', 'r.name')
            ->orderByRaw('avg_lead_time_hours ASC')
            ->limit($dto->limit)
            ->get();

        return [
            'period' => ['from' => $dto->from, 'to' => $dto->to],
            'by_day_of_week' => $byDayOfWeek->map(fn ($r) => [
                'day_of_week' => (int) $r->day_of_week,
                'day_name' => $r->day_name,
                'bookings_count' => (int) $r->bookings_count,
                'revenue' => (float) $r->revenue,
            ])->toArray(),
            'by_hour' => $byHour->map(fn ($r) => [
                'hour' => (int) $r->hour,
                'hour_label' => str_pad($r->hour, 2, '0', STR_PAD_LEFT).':00',
                'bookings_count' => (int) $r->bookings_count,
                'revenue' => (float) $r->revenue,
            ])->sortBy('hour')->values()->toArray(),
            'by_month' => $byMonth->map(fn ($r) => [
                'month' => $r->month,
                'bookings_count' => (int) $r->bookings_count,
                'revenue' => (float) $r->revenue,
            ])->toArray(),
            'avg_lead_time' => $avgLeadTime->map(fn ($r) => [
                'resource_id' => $r->resource_id,
                'name' => $r->name,
                'avg_lead_time_hours' => (float) $r->avg_lead_time_hours,
            ])->toArray(),
        ];
    }

    // =========================================================
    // Helper
    // =========================================================

    public function resolveGroupBy(string $period, string $column): string
    {
        return match ($period) {
            'weekly' => "DATE_FORMAT({$column}, '%x-W%v')",
            'monthly' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }
}
