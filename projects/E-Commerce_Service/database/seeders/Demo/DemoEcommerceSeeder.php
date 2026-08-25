<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * E-Commerce data for the comprehensive project (Nova Marketplace).
 *
 * The product ids written into order_items and cart_items are CMS data entry
 * ids — this service has no products table of its own, so those values only
 * line up because both seeders use the ids fixed in DemoIds.
 *
 * Orders are spread over the last ten weeks and across every status so the
 * sales trend, top products, top customers and returns reports all have
 * something to aggregate rather than a single flat bucket.
 *
 * Run: php artisan db:seed --class="Database\Seeders\Demo\DemoEcommerceSeeder"
 *
 * Safe to re-run — the project's rows are cleared first.
 */
class DemoEcommerceSeeder extends Seeder
{
    private const CURRENCY = 'USD';

    public function run(): void
    {
        $projectId = DemoIds::OWNER_PROJECT_MARKETPLACE;

        DB::transaction(function () use ($projectId) {
            $this->purge($projectId);
            $this->seedOffer($projectId);
            $this->seedCarts($projectId);
            $this->seedOrders($projectId);
        });

        // Written with DB::table(), so the actions that normally invalidate the
        // analytics, offer and cart caches never ran.
        try {
            Cache::flush();
        } catch (\Throwable $e) {
            $this->command?->warn('Could not flush the cache: '.$e->getMessage());
        }

        $counts = [
            ['orders', DB::table('orders')->where('project_id', $projectId)->count()],
            ['order items', DB::table('order_items')->whereIn('order_id', DB::table('orders')->where('project_id', $projectId)->pluck('id'))->count()],
            ['carts', DB::table('carts')->where('project_id', $projectId)->count()],
            ['offers', DB::table('offers')->where('project_id', $projectId)->count()],
        ];

        $this->command?->info("E-Commerce demo data seeded for project {$projectId} (Nova Marketplace):");
        $this->command?->table(['table', 'rows'], $counts);
    }

    // ─────────────────────────────────────────────────────────
    private function purge(int $projectId): void
    {
        $orderIds = DB::table('orders')->where('project_id', $projectId)->pluck('id');

        if ($orderIds->isNotEmpty()) {
            DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        }

        DB::table('orders')->where('project_id', $projectId)->delete();

        $cartIds = DB::table('carts')->where('project_id', $projectId)->pluck('id');

        if ($cartIds->isNotEmpty()) {
            DB::table('cart_items')->whereIn('cart_id', $cartIds)->delete();
        }

        DB::table('carts')->where('project_id', $projectId)->delete();
        DB::table('offers')->where('project_id', $projectId)->delete();
    }

    // ─────────────────────────────────────────────────────────
    private function seedOffer(int $projectId): void
    {
        // Attached to the "Under 100" collection in CMS: a percentage benefit
        // that is currently running, so the offers report is not empty.
        DB::table('offers')->insert([
            'project_id' => $projectId,
            'collection_id' => DemoIds::MARKETPLACE_COLLECTION_AFFORDABLE,
            'is_code_offer' => false,
            'offer_duration' => 30,
            'code' => null,
            'benefit_type' => 'percentage',
            'benefit_config' => json_encode(['percentage' => 15]),
            'start_at' => now()->subDays(10),
            'end_at' => now()->addDays(20),
            'is_active' => true,
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        // A code-based offer that has already expired, so the report has both
        // an active and an inactive row to distinguish.
        DB::table('offers')->insert([
            'project_id' => $projectId,
            'collection_id' => DemoIds::MARKETPLACE_COLLECTION_FEATURED,
            'is_code_offer' => true,
            'offer_duration' => 14,
            'code' => 'NOVA-LAUNCH',
            'benefit_type' => 'fixed_amount',
            'benefit_config' => json_encode(['amount' => 25]),
            'start_at' => now()->subDays(60),
            'end_at' => now()->subDays(46),
            'is_active' => false,
            'created_at' => now()->subDays(60),
            'updated_at' => now()->subDays(46),
        ]);
    }

    // ─────────────────────────────────────────────────────────
    private function seedCarts(int $projectId): void
    {
        // Two customers mid-checkout — abandoned carts the reports can see.
        $carts = [
            [DemoIds::CUSTOMER_ONE_ID, [[9412, 2], [9417, 1]]],
            [DemoIds::CUSTOMER_THREE_ID, [[9414, 1]]],
        ];

        foreach ($carts as [$userId, $items]) {
            $cartId = DB::table('carts')->insertGetId([
                'project_id' => $projectId,
                'user_id' => $userId,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(1),
            ]);

            foreach ($items as [$entryId, $quantity]) {
                DB::table('cart_items')->insert([
                    'cart_id' => $cartId,
                    'item_id' => $entryId,
                    'quantity' => $quantity,
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(1),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    private function seedOrders(int $projectId): void
    {
        // Prices mirror the CMS product entries so revenue figures are coherent.
        $unitPrice = [
            9411 => 249.00, 9412 => 89.00, 9413 => 59.50, 9414 => 42.00,
            9415 => 640.00, 9416 => 310.00, 9417 => 26.00, 9418 => 135.00,
        ];

        /*
         | [days ago, customer, status, [[product, qty], ...]]
         |
         | Statuses cover the whole lifecycle, including one cancelled and one
         | returned order, so cancellation_rate and return_rate are non-zero.
         */
        $orders = [
            [68, DemoIds::CUSTOMER_ONE_ID,   'delivered', [[9411, 1], [9417, 2]]],
            [61, DemoIds::CUSTOMER_TWO_ID,   'delivered', [[9415, 1]]],
            [54, DemoIds::CUSTOMER_THREE_ID, 'delivered', [[9412, 2], [9414, 1]]],
            [47, DemoIds::CUSTOMER_ONE_ID,   'returned',  [[9416, 1]]],
            [40, DemoIds::CUSTOMER_TWO_ID,   'delivered', [[9418, 1], [9413, 1]]],
            [33, DemoIds::CUSTOMER_THREE_ID, 'cancelled', [[9411, 1]]],
            [26, DemoIds::CUSTOMER_ONE_ID,   'delivered', [[9412, 1], [9417, 3]]],
            [19, DemoIds::CUSTOMER_TWO_ID,   'shipped',   [[9418, 2]]],
            [12, DemoIds::CUSTOMER_THREE_ID, 'paid',      [[9414, 2], [9413, 1]]],
            [5,  DemoIds::CUSTOMER_ONE_ID,   'paid',      [[9411, 1]]],
            [2,  DemoIds::CUSTOMER_TWO_ID,   'pending',   [[9417, 4]]],
        ];

        $address = [
            'full_address' => '14 Nova Street, Building B',
            'city' => 'Damascus',
            'street' => 'Nova Street',
            'phone' => '+963900000000',
        ];

        foreach ($orders as [$daysAgo, $userId, $status, $items]) {
            $placedAt = now()->subDays($daysAgo);

            $total = 0.0;

            foreach ($items as [$entryId, $quantity]) {
                $total += $unitPrice[$entryId] * $quantity;
            }

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $userId,
                'project_id' => $projectId,
                'status' => $status,
                'total_price' => round($total, 2),
                'currency' => self::CURRENCY,
                'address' => json_encode($address),
                'created_at' => $placedAt,
                'updated_at' => $placedAt,
            ]);

            foreach ($items as [$entryId, $quantity]) {
                DB::table('order_items')->insert([
                    'order_id' => $orderId,
                    'product_id' => $entryId,
                    'status' => $status === 'cancelled' ? 'cancelled' : 'confirmed',
                    'price' => $unitPrice[$entryId],
                    'quantity' => $quantity,
                    'total' => round($unitPrice[$entryId] * $quantity, 2),
                    'created_at' => $placedAt,
                    'updated_at' => $placedAt,
                ]);
            }
        }
    }
}
