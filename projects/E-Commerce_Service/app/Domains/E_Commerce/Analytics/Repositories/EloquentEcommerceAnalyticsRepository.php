<?php

namespace App\Domains\E_Commerce\Analytics\Repositories;

use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use Illuminate\Support\Facades\DB;

class EloquentEcommerceAnalyticsRepository implements AnalyticsRepositoryInterface
{
  public function getSalesSummary(AnalyticsFilterDTO $dto): array
  {
    $from = $dto->from . ' 00:00:00';
    $to   = $dto->to   . ' 23:59:59';

    // ملخص الطلبات
    $orderStats = DB::table('orders')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->selectRaw("
                COUNT(*)                                                      as total_orders,
                COALESCE(SUM(total_price), 0)                                as total_revenue,
                COALESCE(ROUND(AVG(total_price), 2), 0)                      as avg_order_value,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END)        as pending,
                SUM(CASE WHEN status = 'paid'      THEN 1 ELSE 0 END)        as paid,
                SUM(CASE WHEN status = 'shipped'   THEN 1 ELSE 0 END)        as shipped,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END)        as delivered,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)        as cancelled,
                SUM(CASE WHEN status = 'returned' OR status = 'partially_returned' THEN 1 ELSE 0 END) as returned
            ")
      ->first();

    // إجمالي العناصر المباعة
    $itemsStats = DB::table('order_items as oi')
      ->join('orders as o', 'o.id', '=', 'oi.order_id')
      ->where('o.project_id', $dto->projectId)
      ->whereBetween('o.created_at', [$from, $to])
      ->whereNotIn('o.status', ['cancelled'])
      ->selectRaw("
                COALESCE(SUM(oi.quantity), 0) as total_items_sold,
                COUNT(DISTINCT oi.product_id) as unique_products_sold
            ")
      ->first();

    // طلبات الإرجاع
    $returnStats = DB::table('return_requests')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->selectRaw("
                COUNT(*) as total_return_requests,
                SUM(CASE WHEN status = 'approved'  THEN 1 ELSE 0 END) as approved_returns,
                SUM(CASE WHEN status = 'pending'   THEN 1 ELSE 0 END) as pending_returns,
                SUM(CASE WHEN status = 'rejected'  THEN 1 ELSE 0 END) as rejected_returns
            ")
      ->first();

    $totalOrders = (int) $orderStats->total_orders;

    return [
      'period' => ['from' => $dto->from, 'to' => $dto->to],
      'orders' => [
        'total'             => $totalOrders,
        'total_revenue'     => (float) $orderStats->total_revenue,
        'avg_order_value'   => (float) $orderStats->avg_order_value,
        'by_status'         => [
          'pending'   => (int) $orderStats->pending,
          'paid'      => (int) $orderStats->paid,
          'shipped'   => (int) $orderStats->shipped,
          'delivered' => (int) $orderStats->delivered,
          'cancelled' => (int) $orderStats->cancelled,
          'returned'  => (int) $orderStats->returned,
        ],
        'cancellation_rate' => $totalOrders > 0
          ? round(($orderStats->cancelled / $totalOrders) * 100, 2)
          : 0,
        'return_rate'       => $totalOrders > 0
          ? round(($orderStats->returned / $totalOrders) * 100, 2)
          : 0,
      ],
      'items' => [
        'total_sold'      => (int) $itemsStats->total_items_sold,
        'unique_products' => (int) $itemsStats->unique_products_sold,
      ],
      'returns' => [
        'total'    => (int) $returnStats->total_return_requests,
        'approved' => (int) $returnStats->approved_returns,
        'pending'  => (int) $returnStats->pending_returns,
        'rejected' => (int) $returnStats->rejected_returns,
      ],
    ];
  }

  public function getSalesTrend(AnalyticsFilterDTO $dto): array
  {
    $from    = $dto->from . ' 00:00:00';
    $to      = $dto->to   . ' 23:59:59';
    $groupBy = $this->resolveGroupBy($dto->period, 'o.created_at');

    $rows = DB::table('orders as o')
      ->where('o.project_id', $dto->projectId)
      ->whereBetween('o.created_at', [$from, $to])
      ->whereNotIn('o.status', ['cancelled'])
      ->selectRaw("
                {$groupBy}                          as label,
                COUNT(*)                            as orders_count,
                COALESCE(SUM(o.total_price), 0)     as revenue,
                COALESCE(ROUND(AVG(o.total_price), 2), 0) as avg_order_value
            ")
      ->groupByRaw($groupBy)
      ->orderBy('label')
      ->get();

    return [
      'period' => $dto->period,
      'from'   => $dto->from,
      'to'     => $dto->to,
      'data'   => $rows->map(fn($r) => [
        'label'           => $r->label,
        'orders_count'    => (int)   $r->orders_count,
        'revenue'         => (float) $r->revenue,
        'avg_order_value' => (float) $r->avg_order_value,
      ])->toArray(),
    ];
  }

  public function getTopProducts(AnalyticsFilterDTO $dto): array
  {
    $from = $dto->from . ' 00:00:00';
    $to   = $dto->to   . ' 23:59:59';

    // أكثر المنتجات مبيعاً
    $topBySales = DB::table('order_items as oi')
      ->join('orders as o', 'o.id', '=', 'oi.order_id')
      ->where('o.project_id', $dto->projectId)
      ->whereBetween('o.created_at', [$from, $to])
      ->whereNotIn('o.status', ['cancelled'])
      ->selectRaw("
                oi.product_id,
                SUM(oi.quantity)         as total_quantity,
                COALESCE(SUM(oi.total), 0)  as total_revenue,
                COUNT(DISTINCT o.id)     as order_count,
                ROUND(AVG(oi.price), 2)  as avg_price
            ")
      ->groupBy('oi.product_id')
      ->orderByRaw('total_quantity DESC')
      ->limit($dto->limit)
      ->get();

    // نضيف بيانات الإرجاع لكل منتج
    $productIds = $topBySales->pluck('product_id')->toArray();

    $returnCounts = DB::table('return_requests as rr')
      ->join('order_items as oi', 'oi.id', '=', 'rr.order_item_id')
      ->where('rr.project_id', $dto->projectId)
      ->whereIn('oi.product_id', $productIds)
      ->where('rr.status', 'approved')
      ->selectRaw("oi.product_id, COUNT(*) as return_count")
      ->groupBy('oi.product_id')
      ->pluck('return_count', 'product_id');

    // المنتجات التي لم تُباع (لو الـ limit أكبر من المنتجات الموجودة)
    $leastSold = DB::table('order_items as oi')
      ->join('orders as o', 'o.id', '=', 'oi.order_id')
      ->where('o.project_id', $dto->projectId)
      ->whereBetween('o.created_at', [$from, $to])
      ->whereNotIn('o.status', ['cancelled'])
      ->selectRaw("
                oi.product_id,
                SUM(oi.quantity)        as total_quantity,
                COALESCE(SUM(oi.total), 0) as total_revenue,
                COUNT(DISTINCT o.id)    as order_count
            ")
      ->groupBy('oi.product_id')
      ->orderByRaw('total_quantity ASC')
      ->limit($dto->limit)
      ->get();

    return [
      'period'     => ['from' => $dto->from, 'to' => $dto->to],
      'limit'      => $dto->limit,
      'top_by_quantity' => $topBySales->map(fn($r) => [
        'product_id'      => $r->product_id,
        'total_quantity'  => (int)   $r->total_quantity,
        'total_revenue'   => (float) $r->total_revenue,
        'order_count'     => (int)   $r->order_count,
        'avg_price'       => (float) $r->avg_price,
        'return_count'    => (int)  ($returnCounts[$r->product_id] ?? 0),
        'return_rate'     => $r->total_quantity > 0
          ? round((($returnCounts[$r->product_id] ?? 0) / $r->total_quantity) * 100, 2)
          : 0,
      ])->toArray(),
      'least_sold' => $leastSold->map(fn($r) => [
        'product_id'     => $r->product_id,
        'total_quantity' => (int)   $r->total_quantity,
        'total_revenue'  => (float) $r->total_revenue,
        'order_count'    => (int)   $r->order_count,
      ])->toArray(),
    ];
  }

  public function getOffersAnalytics(AnalyticsFilterDTO $dto): array
  {
    $from = $dto->from . ' 00:00:00';
    $to   = $dto->to   . ' 23:59:59';

    // ملخص العروض
    $offersSummary = DB::table('offers')
      ->where('project_id', $dto->projectId)
      ->whereNull('deleted_at')
      ->selectRaw("
                COUNT(*)                                             as total_offers,
                SUM(CASE WHEN is_active = 1    THEN 1 ELSE 0 END)  as active_offers,
                SUM(CASE WHEN is_code_offer = 1 THEN 1 ELSE 0 END) as code_offers,
                SUM(CASE WHEN is_code_offer = 0 THEN 1 ELSE 0 END) as automatic_offers
            ")
      ->first();

    // أداء كل عرض — كم وفّر من خصم وكم طُبِّق
    $offersPerformance = DB::table('offer_prices as op')
      ->join('offers as o', 'o.id', '=', 'op.applied_offer_id')
      ->where('o.project_id', $dto->projectId)
      ->where('op.is_applied', true)
      ->whereBetween('op.created_at', [$from, $to])
      ->selectRaw("
                o.id                                                     as offer_id,
                o.benefit_type,
                o.is_code_offer,
                o.code,
                o.is_active,
                COUNT(op.id)                                             as times_applied,
                COALESCE(SUM(op.original_price - op.final_price), 0)    as total_discount_given,
                COALESCE(SUM(op.final_price), 0)                         as total_revenue_after_discount,
                COALESCE(ROUND(AVG(op.original_price - op.final_price), 2), 0) as avg_discount
            ")
      ->groupBy('o.id', 'o.benefit_type', 'o.is_code_offer', 'o.code', 'o.is_active')
      ->orderByRaw('times_applied DESC')
      ->get();

    // إجمالي الخصم الممنوح
    $totalDiscount = $offersPerformance->sum('total_discount_given');

    // المشتركون في العروض بالكود
    $codeOffersSubscribers = DB::table('user_offers as uo')
      ->join('offers as o', 'o.id', '=', 'uo.offer_id')
      ->where('o.project_id', $dto->projectId)
      ->whereBetween('uo.created_at', [$from, $to])
      ->selectRaw("
                o.id       as offer_id,
                o.code,
                COUNT(uo.id) as subscribers_count
            ")
      ->groupBy('o.id', 'o.code')
      ->orderByRaw('subscribers_count DESC')
      ->get();

    return [
      'period'  => ['from' => $dto->from, 'to' => $dto->to],
      'summary' => [
        'total_offers'     => (int)   $offersSummary->total_offers,
        'active_offers'    => (int)   $offersSummary->active_offers,
        'code_offers'      => (int)   $offersSummary->code_offers,
        'automatic_offers' => (int)   $offersSummary->automatic_offers,
        'total_discount_given' => (float) $totalDiscount,
      ],
      'offers_performance' => $offersPerformance->map(fn($r) => [
        'offer_id'                  => $r->offer_id,
        'benefit_type'              => $r->benefit_type,
        'is_code_offer'             => (bool)  $r->is_code_offer,
        'code'                      => $r->code,
        'is_active'                 => (bool)  $r->is_active,
        'times_applied'             => (int)   $r->times_applied,
        'total_discount_given'      => (float) $r->total_discount_given,
        'total_revenue_after_discount' => (float) $r->total_revenue_after_discount,
        'avg_discount'              => (float) $r->avg_discount,
      ])->toArray(),
      'code_offers_subscribers' => $codeOffersSubscribers->map(fn($r) => [
        'offer_id'          => $r->offer_id,
        'code'              => $r->code,
        'subscribers_count' => (int) $r->subscribers_count,
      ])->toArray(),
    ];
  }

  public function getTopCustomers(AnalyticsFilterDTO $dto): array
  {
    $from = $dto->from . ' 00:00:00';
    $to   = $dto->to   . ' 23:59:59';

    // أكثر العملاء شراءً
    $topCustomers = DB::table('orders')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->whereNotIn('status', ['cancelled'])
      ->selectRaw("
                user_id,
                COUNT(*)                         as total_orders,
                COALESCE(SUM(total_price), 0)    as total_spent,
                COALESCE(ROUND(AVG(total_price), 2), 0) as avg_order_value,
                MIN(created_at)                  as first_order_at,
                MAX(created_at)                  as last_order_at
            ")
      ->groupBy('user_id')
      ->orderByRaw('total_spent DESC')
      ->limit($dto->limit)
      ->get();

    // إجمالي العملاء الفريدين
    $uniqueCustomers = DB::table('orders')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->distinct('user_id')
      ->count('user_id');

    // العملاء الجدد (أول طلب لهم في الفترة)
    $newCustomers = DB::table('orders as o1')
      ->where('o1.project_id', $dto->projectId)
      ->whereBetween('o1.created_at', [$from, $to])
      ->whereNotExists(function ($q) use ($dto, $from) {
        $q->from('orders as o2')
          ->whereColumn('o2.user_id', 'o1.user_id')
          ->where('o2.project_id', $dto->projectId)
          ->where('o2.created_at', '<', $from);
      })
      ->distinct('o1.user_id')
      ->count('o1.user_id');

    // العملاء المرتجعون
    $returningCustomers = $uniqueCustomers - $newCustomers;

    return [
      'period'   => ['from' => $dto->from, 'to' => $dto->to],
      'summary'  => [
        'unique_customers'    => $uniqueCustomers,
        'new_customers'       => $newCustomers,
        'returning_customers' => max(0, $returningCustomers),
        'new_customer_rate'   => $uniqueCustomers > 0
          ? round(($newCustomers / $uniqueCustomers) * 100, 2)
          : 0,
      ],
      'top_customers' => $topCustomers->map(fn($r) => [
        'user_id'         => $r->user_id,
        'total_orders'    => (int)   $r->total_orders,
        'total_spent'     => (float) $r->total_spent,
        'avg_order_value' => (float) $r->avg_order_value,
        'first_order_at'  => $r->first_order_at,
        'last_order_at'   => $r->last_order_at,
      ])->toArray(),
    ];
  }


  public function getReturnsAnalytics(AnalyticsFilterDTO $dto): array
  {
    $from = $dto->from . ' 00:00:00';
    $to   = $dto->to   . ' 23:59:59';

    // ملخص الإرجاعات
    $returnsSummary = DB::table('return_requests')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->selectRaw("
                COUNT(*)                                              as total,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                COALESCE(SUM(quantity), 0)                            as total_items_returned
            ")
      ->first();

    // إجمالي الطلبات في نفس الفترة لحساب معدل الإرجاع
    $totalOrders = DB::table('orders')
      ->where('project_id', $dto->projectId)
      ->whereBetween('created_at', [$from, $to])
      ->count();

    // أكثر المنتجات إرجاعاً
    $mostReturnedProducts = DB::table('return_requests as rr')
      ->join('order_items as oi', 'oi.id', '=', 'rr.order_item_id')
      ->where('rr.project_id', $dto->projectId)
      ->whereBetween('rr.created_at', [$from, $to])
      ->selectRaw("
                oi.product_id,
                COUNT(rr.id)          as return_requests,
                COALESCE(SUM(rr.quantity), 0) as total_returned_qty,
                SUM(CASE WHEN rr.status = 'approved' THEN 1 ELSE 0 END) as approved_count
            ")
      ->groupBy('oi.product_id')
      ->orderByRaw('return_requests DESC')
      ->limit($dto->limit)
      ->get();

    // توزيع الإرجاع عبر الزمن
    $groupBy = $this->resolveGroupBy($dto->period, 'rr.created_at');
    $returnsTrend = DB::table('return_requests as rr')
      ->where('rr.project_id', $dto->projectId)
      ->whereBetween('rr.created_at', [$from, $to])
      ->selectRaw("{$groupBy} as label, COUNT(*) as count")
      ->groupByRaw($groupBy)
      ->orderBy('label')
      ->get();

    $total = (int) $returnsSummary->total;

    return [
      'period'  => ['from' => $dto->from, 'to' => $dto->to],
      'summary' => [
        'total'               => $total,
        'approved'            => (int) $returnsSummary->approved,
        'pending'             => (int) $returnsSummary->pending,
        'rejected'            => (int) $returnsSummary->rejected,
        'total_items_returned' => (int) $returnsSummary->total_items_returned,
        'approval_rate'       => $total > 0
          ? round(($returnsSummary->approved / $total) * 100, 2)
          : 0,
        'return_vs_orders_rate' => $totalOrders > 0
          ? round(($total / $totalOrders) * 100, 2)
          : 0,
      ],
      'most_returned_products' => $mostReturnedProducts->map(fn($r) => [
        'product_id'          => $r->product_id,
        'return_requests'     => (int) $r->return_requests,
        'total_returned_qty'  => (int) $r->total_returned_qty,
        'approved_count'      => (int) $r->approved_count,
      ])->toArray(),
      'trend' => $returnsTrend->map(fn($r) => [
        'label' => $r->label,
        'count' => (int) $r->count,
      ])->toArray(),
    ];
  }

  public function resolveGroupBy(string $period, string $column): string
  {
    return match ($period) {
      'weekly'  => "DATE_FORMAT({$column}, '%x-W%v')",
      'monthly' => "DATE_FORMAT({$column}, '%Y-%m')",
      default   => "DATE({$column})",
    };
  }
}
