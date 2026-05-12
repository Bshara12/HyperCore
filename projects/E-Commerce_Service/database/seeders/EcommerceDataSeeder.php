<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\OfferPrice;
use App\Models\Order;
use App\Models\User;
use App\Models\UserOffer;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EcommerceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $now = now();

            $projectId = (int) env('CMS_PROJECT_ID', 1);
            $featuredCollectionId = (int) env('CMS_FEATURED_COLLECTION_ID', 1);
            $saleCollectionId = (int) env('CMS_SALE_COLLECTION_ID', 2);

            $productEntryIds = array_values(array_filter(
                array_map('intval', explode(',', (string) env('CMS_PRODUCT_ENTRY_IDS', '1,2,3,4,5,6')))
            ));

            // ─── Offers ───────────────────────────────────────────────────────

            $percentageOffer = Offer::query()->firstOrCreate(
                [
                    'project_id' => $projectId,
                    'collection_id' => $saleCollectionId,
                    'benefit_type' => 'percentage',
                    'is_code_offer' => false,
                ],
                [
                    'benefit_config' => ['percentage' => 10],
                    'start_at' => $now->copy()->subDay(),
                    'end_at' => $now->copy()->addDays(30),
                    'is_active' => true,
                ]
            );

            $codeOffer = Offer::query()->firstOrCreate(
                [
                    'project_id' => $projectId,
                    'collection_id' => $featuredCollectionId,
                    'benefit_type' => 'fixed_amount',
                    'code' => 'WELCOME10',
                    'is_code_offer' => true,
                ],
                [
                    'offer_duration' => 7,
                    'benefit_config' => ['fixed_amount' => 10],
                    'start_at' => $now->copy(),
                    'end_at' => $now->copy()->addDays(7),
                    'is_active' => true,
                ]
            );

            // ─── User Offer ───────────────────────────────────────────────────

            UserOffer::query()->firstOrCreate(
                [
                    'offer_id' => $codeOffer->id,
                    'user_id' => 1,
                    'project_id' => $projectId,
                ],
                [
                    'start_at' => $now,
                    'end_at' => $now->copy()->addDays(7),
                ]
            );

            // ─── Offer Prices ─────────────────────────────────────────────────

            foreach ($productEntryIds as $entryId) {
                $original = (float) (100 + ($entryId * 10));

                OfferPrice::query()->updateOrCreate(
                    ['entry_id' => $entryId, 'applied_offer_id' => $percentageOffer->id],
                    [
                        'original_price' => $original,
                        'final_price' => max(0.0, $original - ($original * (10 / 100.0))),
                        'is_applied' => true,
                        'is_code_price' => false,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );

                OfferPrice::query()->updateOrCreate(
                    ['entry_id' => $entryId, 'applied_offer_id' => $codeOffer->id],
                    [
                        'original_price' => $original,
                        'final_price' => max(0.0, $original - 10),
                        'is_applied' => true,
                        'is_code_price' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            // ─── Cart ─────────────────────────────────────────────────────────

            $cart = Cart::query()->firstOrCreate(
                ['user_id' => 1, 'project_id' => $projectId]);

            CartItem::query()->where('cart_id', $cart->id)->delete();

            // السعر محذوف — يُجلب من CMS عند عرض السلة
            foreach (array_slice($productEntryIds, 0, 3) as $idx => $productId) {
                CartItem::query()->create([
                    'cart_id' => $cart->id,
                    'item_id' => $productId,
                    'quantity' => $idx + 1,    // 1, 2, 3
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            //   $wishlist = Wishlist::query()->firstOrCreate(['user_id' => 1]);
            //   WishlistItem::query()->where('wishlist_id', $wishlist->id)->delete();
            //   foreach (array_slice($productEntryIds, 0, 2) as $productId) {
            //     WishlistItem::query()->create([
            //       'wishlist_id' => $wishlist->id,
            //       'product_id' => $productId,
            //       'created_at' => $now,
            //       'updated_at' => $now,
            //     ]);
            //   }

            $wishlist = Wishlist::query()->firstOrCreate(
                [
                    'user_id' => 1,
                    'name' => 'My Wishlist', // مطلوب بسبب unique constraint
                ],
                [
                    'guest_token' => null,
                    'is_default' => true,
                    'visibility' => 'private',
                    'is_shareable' => false,
                    'share_token' => null,
                ]
            );

            // حذف العناصر القديمة
            $wishlist->items()->delete();

            // إضافة عناصر جديدة
            foreach (array_slice($productEntryIds, 0, 2) as $index => $productId) {
                $wishlist->items()->create([
                    'product_id' => $productId,
                    'variant_id' => null,
                    'sort_order' => $index,
                    'added_from_cart' => false,
                    'product_snapshot' => null,
                    'price_when_added' => null,
                    'notify_on_price_drop' => false,
                    'notify_on_back_in_stock' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // $order = Order::query()->create([
            //   'user_id' => 1,
            //   'status' => 'paid',
            //   'project_id' => 5,
            //   'total_price' => $cartTotal,
            //   'currency' => 'USD',
            //   'address' => json_encode([
            //     'country' => 'SY',
            //     'city' => 'Damascus',
            //     'street' => 'Main St',
            //   ]),
            //   'created_at' => $now,
            //   'updated_at' => $now,
            // ]);

            // foreach (CartItem::query()->where('cart_id', $cart->id)->get() as $item) {
            //   OrderItem::query()->create([
            //     'order_id' => $order->id,
            //     'product_id' => $item->item_id,
            //     'price' => $item->price,
            //     'quantity' => $item->quantity,
            //     'total' => $item->subtotal,
            //     'created_at' => $now,
            //     'updated_at' => $now,
            //   ]);
            // }

            // ─── Wishlist ─────────────────────────────────────────────────────

            $wishlist = Wishlist::query()->firstOrCreate(['user_id' => 1]);

            WishlistItem::query()->where('wishlist_id', $wishlist->id)->delete();

            foreach (array_slice($productEntryIds, 0, 2) as $productId) {
                WishlistItem::query()->create([
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $productId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Payment::query()->create([
            //   'order_id' => $order->id,
            //   'method' => 'card',
            //   'status' => 'paid',
            //   'transaction_id' => 'TX-' . $order->id,
            //   'amount' => $cartTotal,
            //   'paid_at' => $now,
            //   'created_at' => $now,
            //   'updated_at' => $now,
            // ]);
        });
    }
}
