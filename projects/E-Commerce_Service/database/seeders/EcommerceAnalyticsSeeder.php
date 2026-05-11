<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EcommerceAnalyticsSeeder extends Seeder
{
    // =========================================================
    // هذا الـ Seeder يشتغل على DB خاصة بـ E-Commerce Service
    // الـ project_id و user_ids جايين من CMS Service
    // تأكد من تحديث config/seed_config.php أولاً
    // =========================================================

    public function run(): void
    {
        $this->command->info('🌱 [E-Commerce] Starting Seeder...');

        // ─── قراءة الـ IDs من الـ Config ───────────────────────────
        $projectId = config('seed_config.project_id', 1);
        $userIds = config('seed_config.user_ids', range(1, 10));
        $productIds = range(1, 30); // IDs المنتجات من CMS entries

        $this->command->info("📌 Using Project ID: {$projectId}");
        $this->command->info('📌 Using User IDs: '.implode(', ', $userIds));

        // ─── 1. Offers ─────────────────────────────────────────────
        $offersData = [
            [
                'collection_id' => config('seed_config.collection_id', 1),
                'benefit_type' => 'percentage',
                'benefit_config' => json_encode(['percentage' => 20]),
                'is_code_offer' => false,
                'code' => null,
                'offer_duration' => null,
                'is_active' => true,
                'start_at' => now()->subMonths(3),
                'end_at' => now()->addMonths(3),
            ],
            [
                'collection_id' => config('seed_config.collection_id', 1) + 1,
                'benefit_type' => 'fixed',
                'benefit_config' => json_encode(['amount' => 15]),
                'is_code_offer' => true,
                'code' => 'SAVE15',
                'offer_duration' => 30,
                'is_active' => true,
                'start_at' => now()->subMonths(2),
                'end_at' => now()->addMonths(1),
            ],
            [
                'collection_id' => config('seed_config.collection_id', 1) + 2,
                'benefit_type' => 'percentage',
                'benefit_config' => json_encode(['percentage' => 30]),
                'is_code_offer' => true,
                'code' => 'VIP30',
                'offer_duration' => 60,
                'is_active' => false,
                'start_at' => now()->subMonths(5),
                'end_at' => now()->subMonths(2),
            ],
            [
                'collection_id' => config('seed_config.collection_id', 1) + 3,
                'benefit_type' => 'fixed',
                'benefit_config' => json_encode(['amount' => 5]),
                'is_code_offer' => false,
                'code' => null,
                'offer_duration' => null,
                'is_active' => true,
                'start_at' => now()->subMonth(),
                'end_at' => now()->addMonths(2),
            ],
        ];

        $offerIds = [];
        foreach ($offersData as $offer) {
            $offerIds[] = DB::table('offers')->insertGetId([
                'project_id' => $projectId,
                'collection_id' => $offer['collection_id'],
                'benefit_type' => $offer['benefit_type'],
                'benefit_config' => $offer['benefit_config'],
                'is_code_offer' => $offer['is_code_offer'],
                'code' => $offer['code'],
                'offer_duration' => $offer['offer_duration'],
                'is_active' => $offer['is_active'],
                'start_at' => $offer['start_at'],
                'end_at' => $offer['end_at'],
                'created_at' => $offer['start_at'],
                'updated_at' => now(),
            ]);
        }
        $this->command->info('✅ Offers created: '.count($offerIds));

        // ─── 2. Offer Prices ────────────────────────────────────────
        foreach ($productIds as $productId) {
            $originalPrice = rand(50, 500);

            foreach ($offerIds as $index => $offerId) {
                $discountRate = match ($index) {
                    0 => 0.20,
                    1 => 15 / $originalPrice,
                    2 => 0.30,
                    3 => 5 / $originalPrice,
                    default => 0.10,
                };

                $finalPrice = max(1, round($originalPrice * (1 - $discountRate), 2));

                DB::table('offer_prices')->insertOrIgnore([
                    'entry_id' => $productId,
                    'applied_offer_id' => $offerId,
                    'original_price' => $originalPrice,
                    'final_price' => $finalPrice,
                    'is_applied' => $index === 0, // فقط أول عرض مطبق
                    'is_code_price' => (bool) $offersData[$index]['is_code_offer'],
                    'created_at' => now()->subDays(rand(1, 90)),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->command->info('✅ Offer Prices created');

        // ─── 3. User Offers (Code Subscriptions) ───────────────────
        $codeOfferIds = [$offerIds[1], $offerIds[2]]; // SAVE15 و VIP30

        foreach (array_slice($userIds, 0, 8) as $uid) {
            foreach ($codeOfferIds as $codeOfferId) {
                DB::table('user_offers')->insertOrIgnore([
                    'offer_id' => $codeOfferId,
                    'user_id' => $uid,
                    'project_id' => $projectId,
                    'start_at' => now()->subDays(rand(1, 30)),
                    'end_at' => now()->addDays(rand(10, 60)),
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now(),
                ]);
            }
        }
        $this->command->info('✅ User Offers subscriptions created');

        // ─── 4. Orders ─────────────────────────────────────────────
        $orderStatuses = [
            'pending',
            'paid',
            'shipped',
            'delivered',
            'delivered',
            'delivered',
            'delivered',
            'cancelled',
            'returned',
            'partially_returned',
        ];

        $orderIds = [];

        for ($o = 1; $o <= 120; $o++) {
            $userId = $userIds[array_rand($userIds)];
            $status = $orderStatuses[array_rand($orderStatuses)];
            $createdAt = now()->subDays(rand(1, 180));

            $orderId = DB::table('orders')->insertGetId([
                'user_id' => $userId,
                'project_id' => $projectId,
                'status' => $status,
                'total_price' => 0,
                'currency' => 'USD',
                'address' => json_encode([
                    'street' => rand(1, 999).' Main St',
                    'city' => 'New York',
                    'country' => 'US',
                    'zip' => '10001',
                ]),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);

            $orderIds[] = [
                'id' => $orderId,
                'user_id' => $userId,
                'status' => $status,
                'created_at' => $createdAt,
            ];
        }

        // ─── 5. Order Items ─────────────────────────────────────────
        $itemIds = []; // لتخزين order_item IDs للـ return requests

        foreach ($orderIds as $order) {
            $itemsCount = rand(1, 4);
            $orderTotal = 0;
            $usedProducts = [];

            for ($i = 0; $i < $itemsCount; $i++) {
                $productId = $productIds[array_rand($productIds)];
                if (in_array($productId, $usedProducts)) {
                    continue;
                }
                $usedProducts[] = $productId;

                $price = rand(20, 300);
                $quantity = rand(1, 5);
                $total = $price * $quantity;
                $orderTotal += $total;

                $itemId = DB::table('order_items')->insertGetId([
                    'order_id' => $order['id'],
                    'product_id' => $productId,
                    'status' => $order['status'],
                    'price' => $price,
                    'quantity' => $quantity,
                    'total' => $total,
                    'created_at' => $order['created_at'],
                    'updated_at' => $order['created_at'],
                ]);

                $itemIds[] = [
                    'id' => $itemId,
                    'order_id' => $order['id'],
                    'user_id' => $order['user_id'],
                    'status' => $order['status'],
                    'quantity' => $quantity,
                    'created_at' => $order['created_at'],
                ];
            }

            DB::table('orders')
                ->where('id', $order['id'])
                ->update(['total_price' => $orderTotal]);
        }
        $this->command->info('✅ Orders and Order Items created: '.count($orderIds));

        // ─── 6. Return Requests ─────────────────────────────────────
        // فقط من الطلبات الـ delivered
        $deliveredItems = collect($itemIds)
            ->filter(fn ($i) => $i['status'] === 'delivered')
            ->shuffle()
            ->take(20);

        $returnStatuses = ['pending', 'approved', 'approved', 'rejected'];

        foreach ($deliveredItems as $item) {
            $returnStatus = $returnStatuses[array_rand($returnStatuses)];

            DB::table('return_requests')->insert([
                'user_id' => $item['user_id'],
                'order_id' => $item['order_id'],
                'order_item_id' => $item['id'],
                'description' => 'Product not as described',
                'quantity' => rand(1, $item['quantity']),
                'project_id' => $projectId,
                'status' => $returnStatus,
                'created_at' => Carbon::parse($item['created_at'])->addDays(rand(1, 14)),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('✅ Return Requests created: '.$deliveredItems->count());

        // ─── طباعة النتائج ──────────────────────────────────────────
        $this->command->info('🎉 [E-Commerce] Seeder completed!');
        $this->command->table(
            ['Entity', 'Count'],
            [
                ['Offers',          count($offerIds)],
                ['Offer Prices',    count($productIds) * count($offerIds)],
                ['User Offers',     8 * count($codeOfferIds)],
                ['Orders',          count($orderIds)],
                ['Return Requests', $deliveredItems->count()],
            ]
        );
    }
}
