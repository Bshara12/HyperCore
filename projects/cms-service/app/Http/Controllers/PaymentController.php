<?php

namespace App\Http\Controllers;

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\DTOs\TopUpDTO;
use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Domains\Payment\Requests\RefundRequest;
use App\Domains\Payment\Requests\TopUpWalletRequest;
use App\Domains\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
  public function __construct(
    private readonly PaymentService $service,
  ) {}

  // ─── POST /payments ───────────────────────────────────────────────────────

  public function charge(Request $request): JsonResponse
  {
    try {
      $dto    = PaymentDTO::fromRequest($request);
      $result = $this->service->processPayment($dto);

      if (!$result['success']) {
        return response()->json([
          'message' => 'Payment failed. Please try again.',
          'status'  => $result['status'],
        ], 422);
      }

      return response()->json([
        'message'            => 'Payment processed successfully.',
        'payment_id'         => $result['payment_id'],
        'transaction_id'     => $result['transaction_id'],
        'payment_method'     => $result['payment_method'],
        'status'             => $result['status'],
        'installment_number' => $result['installment_number'],
      ], 201);
    } catch (\Exception $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }

  // ─── POST /payments/installment ───────────────────────────────────────────

  public function payInstallment(PayInstallmentRequest $request): JsonResponse
  {
    try {
      $dto = PayInstallmentDTO::fromRequest($request);
      $result = $this->service->payInstallment($dto);

      if (! $result['success']) {
        return response()->json([
          'message' => 'Installment payment failed.',
          'status'  => $result['status'] ?? 'failed',
        ], 422);
      }

      return response()->json([
        'message'            => 'Installment paid successfully.',
        'payment_id'         => $result['payment_id'],
        'transaction_id'     => $result['transaction_id'],
        'payment_method'     => $result['payment_method'],
        'installment_number' => $result['installment_number'],
        'remaining'          => $result['remaining'],
        'plan_status'        => $result['plan_status'],
      ]);
    } catch (\Exception $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }

  // ─── POST /api/wallet/topup ───────────────────────────────────────────────

  public function topUp(TopUpWalletRequest $request): JsonResponse
  {
    try {
      $dto = TopUpDTO::fromRequest($request);
      $result = $this->service->topUp($dto);

      return response()->json([
        'message'        => 'Wallet topped up successfully.',
        'wallet_id'      => $result['wallet_id'],
        'amount_added'   => $result['amount_added'],
        'new_balance'    => $result['new_balance'],
        'transaction_id' => $result['transaction_id'],
      ], 201);
    } catch (\Exception $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }

  // ─── POST /payments/{payment}/refund ──────────────────────────────────────

  public function refund(RefundRequest $request): JsonResponse
  {
    try {
      $dto    = RefundDTO::fromRequest($request);
      $result = $this->service->processRefund($dto);

      if (! $result['success']) {
        return response()->json([
          'message' => 'Refund failed. Please try again.',
          'status'  => $result['status'],
        ], 422);
      }

      return response()->json([
        'message'        => 'Refund processed successfully.',
        'payment_id'     => $result['payment_id'],
        'refund_id'      => $result['refund_id'],
        'payment_method' => $result['payment_method'],
        'status'         => $result['status'],
      ]);
    } catch (\Exception $e) {
      return response()->json(['message' => $e->getMessage()], 422);
    }
  }
}
