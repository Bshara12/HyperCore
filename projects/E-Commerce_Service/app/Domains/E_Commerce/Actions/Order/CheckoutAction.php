<?php

namespace App\Domains\E_Commerce\Actions\Order;

use App\Domains\E_Commerce\Actions\Stock\UpdateStockInCMSAction;
use App\Domains\E_Commerce\DTOs\Order\CheckoutDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\Payment\Actions\ProcessPaymentAction;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Services\PaymentService;
use Illuminate\Support\Facades\DB;

class CheckoutAction
{
  public function __construct(
    protected CartRepositoryInterface $cartRepo,
    protected CalculateCartPricingAction $pricingAction,
    protected OrderRepositoryInterface $orderRepo,
    protected OrderItemRepositoryInterface $orderItemRepo,
    protected PaymentService $paymentService,
    protected UpdateStockInCMSAction $updateStockAction,
  ) {}

  // public function execute(CheckoutDTO $dto)
  // {
  //   return DB::transaction(function () use ($dto) {

  //     // 1. cart
  //     $cart = $this->cartRepo->loadItems(
  //       $this->cartRepo->findById($dto->cart_id)
  //     );

  //     if (!$cart || $cart->items->isEmpty()) {
  //       throw new \Exception('Cart is empty');
  //     }

  //     if ($cart->user_id !== $dto->user_id) {
  //       throw new \Exception('Unauthorized cart');
  //     }

  //     // 2. pricing
  //     $pricing = $this->pricingAction->execute($cart);

  //     // 3. payment
  //     $paymentStatus = 'pending';

  //     if ($dto->payment_method === 'online') {

  //       $payment = $this->paymentService->processPayment(
  //         new PaymentDTO(
  //           userId: $dto->user_id,
  //           userName: $dto->user_name,
  //           projectId: $dto->project_id,
  //           amount: $pricing['total'],
  //           currency: 'USD',
  //           gateway: $dto->gateway,
  //           paymentType: $dto->payment_type
  //         )
  //       );

  //       if (!in_array($payment['status'], ['paid', 'pending'])) {
  //         throw new \Exception('Payment failed');
  //       }

  //       $paymentStatus = $payment['status'];
  //     }

  //     // 4. create order
  //     $order = $this->orderRepo->create([
  //       'project_id' => $dto->project_id,
  //       'user_id' => $dto->user_id,
  //       'total_price' => $pricing['total'],
  //       'status' => $dto->payment_method === 'cod' ? 'pending' : $paymentStatus,
  //       'address' => $dto->address,
  //     ]);

  //     // 5. items
  //     foreach ($pricing['items'] as $item) {
  //       $this->orderItemRepo->create([
  //         'order_id' => $order->id,
  //         'product_id' => $item['product_id'],
  //         'quantity' => $item['quantity'],
  //         'price' => $item['price'],
  //         'total' => $item['total'],
  //         'status' => 'pending',
  //       ]);
  //     }

  //     // 6. delete cart
  //     $this->cartRepo->delete($cart->id);

  //     return $this->orderRepo->loadItems($order);
  //   });
  // }
  public function execute(CheckoutDTO $dto)
  {
    return DB::transaction(function () use ($dto) {

      // 1. cart
      $cart = $this->cartRepo->loadItems(
        $this->cartRepo->findById($dto->cart_id)
      );

      if (!$cart || $cart->items->isEmpty()) {
        throw new \Exception('Cart is empty');
      }

      if ($cart->user_id !== $dto->user_id) {
        throw new \Exception('Unauthorized cart');
      }

      // 2. pricing (هون لازم يكون فيه entries من CMS)
      $pricing = $this->pricingAction->execute($cart);

      // ✅ 3. تحقق من stock قبل الدفع
      foreach ($pricing['items'] as $item) {

        if (isset($item['count'])) {

          if ($item['quantity'] > $item['count']) {
            throw new \Exception(
              "Product {$item['title']} only has {$item['count']} left"
            );
          }
        }
      }

      // 4. payment
      $paymentStatus = 'pending';

      if ($dto->payment_method === 'online') {

        $payment = $this->paymentService->processPayment(
          new PaymentDTO(
            userId: $dto->user_id,
            userName: $dto->user_name,
            projectId: $dto->project_id,
            amount: $pricing['total'],
            currency: 'USD',
            gateway: $dto->gateway,
            paymentType: $dto->payment_type
          )
        );

        if (!in_array($payment['status'], ['paid', 'pending'])) {
          throw new \Exception('Payment failed');
        }

        $paymentStatus = $payment['status'];
      }

      // ✅ 5. إعادة التحقق + خصم الكمية (CRITICAL 🔥)
      $this->updateStockAction->execute($pricing['items']);

      // 6. create order
      $order = $this->orderRepo->create([
        'project_id' => $dto->project_id,
        'user_id' => $dto->user_id,
        'total_price' => $pricing['total'],
        'status' => $dto->payment_method === 'cod' ? 'pending' : $paymentStatus,
        'address' => $dto->address,
      ]);

      // 7. items
      foreach ($pricing['items'] as $item) {
        $this->orderItemRepo->create([
          'order_id' => $order->id,
          'product_id' => $item['product_id'],
          'quantity' => $item['quantity'],
          'price' => $item['price'],
          'total' => $item['total'],
          'status' => 'pending',
        ]);
      }

      // 8. delete cart
      $this->cartRepo->delete($cart->id);

      return $this->orderRepo->loadItems($order);
    });
  }
}
