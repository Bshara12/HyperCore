<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// class CreateOrderFromCartAction
// {
//   public function __construct(
//     protected OrderRepositoryInterface $orderRepo,
//     protected OrderItemRepositoryInterface $orderItemRepo,
//     protected CartRepositoryInterface $cartRepo
//   ) {}

//   public function execute(CreateOrderDTO $dto)
//   {
//     $cart = $this->cartRepo->loadItems($this->cartRepo->findById($dto->cart_id));
//     if (!$cart || $cart->items->isEmpty()) {
//       throw new \Exception('Cart is empty or not found');
//     }

//     // $order = $this->orderRepo->create([
//     //   'project_id' => $dto->project_id,
//     //   'user_id' => $dto->user_id,
//     //   'total' => $cart->items->sum('subtotal'),
//     //   'status' => 'pending',
//     // ]);
//     $order = $this->orderRepo->create([
//   'project_id' => $dto->project_id,
//   'user_id' => $dto->user_id,
//   'total_price' => $cart->items->sum('subtotal'),
//   'status' => 'pending',
// ]);

//     // foreach ($cart->items as $item) {
//     //   $this->orderItemRepo->create([
//     //     'order_id' => $order->id,
//     //     'item_id' => $item->item_id,
//     //     'quantity' => $item->quantity,
//     //     'price' => $item->price,
//     //     'subtotal' => $item->subtotal,
//     //     'status' => 'pending',
//     //   ]);
//     // }
//     foreach ($cart->items as $item) {
//   $this->orderItemRepo->create([
//     'order_id' => $order->id,
//     'product_id' => $item->item_id,
//     'product_name' => 'TODO', // لاحقاً من CMS
//     'quantity' => $item->quantity,
//     'price' => $item->price,
//     'total' => $item->subtotal,
//     'status' => 'pending',
//   ]);
// }

//     // Optionally clear cart after order creation
//     // $this->cartRepo->clear($cart->id);

//     return $this->orderRepo->loadItems($order);
//   }
// }

class CreateOrderFromCartAction
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected OrderItemRepositoryInterface $orderItemRepo,
        protected CartRepositoryInterface $cartRepo,
        // new for cart edit
        protected FetchEntriesByIdsAction $fetchEntries,
        protected EnrichEntriesWithPricesAction $enrichPrices,
    ) {}

    public function execute(CreateOrderDTO $dto)
    {
        // return DB::transaction(function () use ($dto) {

        //   $cart = $this->cartRepo->loadItems(
        //     $this->cartRepo->findById($dto->cart_id)
        //   );

        //   if (!$cart || $cart->items->isEmpty()) {
        //     throw new \Exception('Cart is empty or not found');
        //   }

        //   if ($cart->user_id !== $dto->user_id) {
        //     throw new \Exception('Unauthorized cart');
        //   }

        //   $order = $this->orderRepo->create([
        //     'project_id' => $dto->project_id,
        //     'user_id' => $dto->user_id,
        //     'total_price' => $cart->items->sum('subtotal'),
        //     'status' => 'pending',
        //     'address' => $dto->address,
        //   ]);

        //   foreach ($cart->items as $item) {
        //     $this->orderItemRepo->create([
        //       'order_id' => $order->id,
        //       'product_id' => $item->item_id,
        //       // 'product_name' => 'TODO',
        //       'quantity' => $item->quantity,
        //       'price' => $item->price,
        //       'total' => $item->subtotal,
        //       'status' => 'pending',
        //     ]);
        //   }

        //   // ✅ هون الإضافة الجديدة
        //   $this->cartRepo->delete($cart->id);

        //   // event(new \App\Events\SystemLogEvent(
        //   //   module: 'ecommerce',
        //   //   eventType: 'order_created',
        //   //   userId: $dto->user_id,
        //   //   entityType: 'order',
        //   //   entityId: $order->id,
        //   //   newValues: $order->toArray()
        //   // ));

        //   return $this->orderRepo->loadItems($order);
        // });
        return DB::transaction(function () use ($dto) {

            $cart = $this->cartRepo->loadItems(
                $this->cartRepo->findById($dto->cart_id)
            );

            if (! $cart || $cart->items->isEmpty()) {
                throw new \Exception('Cart is empty or not found');
            }

            if ($cart->user_id !== $dto->user_id) {
                throw new \Exception('Unauthorized cart');
            }

            // 🟢 1. جيب item ids
            $itemIds = $cart->items->pluck('item_id')->toArray();

            // 🟢 2. جيب entries من CMS
            $entries = $this->fetchEntries->execute($itemIds);

            // 🟢 3. طبق pricing
            $enrichedEntries = $this->enrichPrices->execute($entries);

            // 🟢 4. map
            $entriesMap = collect($enrichedEntries)->keyBy('id');

            // 🟢 5. احسب total
            $total = 0;
            foreach ($cart->items as $item) {
                $entry = $entriesMap[$item->item_id] ?? null;

                if (! $entry) {
                    throw new \Exception("Item not found in CMS: {$item->item_id}");
                }
                // ✅ تحقق إذا عنده stock
                if (isset($entry['count']) || isset($entry['3'])) {

                    $available = (int) $entry['3'];
                    $requested = (int) $item->quantity;

                    if ($requested > $available) {
                        throw new \Exception(
                            "Product '{$entry['0']}' only has {$available} items available, you requested {$requested}"
                        );
                    }
                }

                $price = $entry['final_price'];
                $subtotal = $price * $item->quantity;

                $total += $subtotal;
            }

            // 🟢 6. create order
            $order = $this->orderRepo->create([
                'project_id' => $dto->project_id,
                'user_id' => $dto->user_id,
                'total_price' => $total,
                'status' => 'pending',
                'address' => $dto->address,
            ]);

            // 🟢 7. create order items
            foreach ($cart->items as $item) {

                $entry = $entriesMap[$item->item_id];

                $price = $entry['final_price'];
                $subtotal = $price * $item->quantity;

                $this->orderItemRepo->create([
                    'order_id' => $order->id,
                    'product_id' => $item->item_id,
                    'quantity' => $item->quantity,
                    'price' => $price,
                    'total' => $subtotal,
                    'status' => 'pending',
                ]);
            }

            // 🟢 8. حذف الكارت
            $this->cartRepo->delete($cart->id);
            Cache::forget(CacheKeys::cart($dto->user_id, $dto->project_id));
            Cache::forget(CacheKeys::userOrders($dto->user_id, $dto->project_id));
            Cache::tags(['admin_orders'])->flush();

            return $this->orderRepo->loadItems($order);
        });
    }
}
