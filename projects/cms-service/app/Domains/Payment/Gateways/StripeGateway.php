<?php

namespace App\Domains\Payment\Gateways;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use Illuminate\Support\Facades\Log;
use Stripe\Balance;
use Stripe\Charge;
use Stripe\Refund;
use Stripe\Stripe;

class StripeGateway implements PaymentGatewayInterface
{
  public function __construct()
  {
    Stripe::setApiKey(config('payment.gateways.stripe.secret_key'));
  }

  // ─── Charge (المبلغ الكامل) ───────────────────────────────────────────────

  public function charge(PaymentDTO $dto): array
  {
    return $this->chargeAmount($dto, $dto->amount);
  }

  // ─── Charge Amount (مبلغ محدد — للتقسيط والدفعة الأولى) ─────────────────

  public function chargeAmount(PaymentDTO $dto, float $amount): array
  {
    try {
      $charge = Charge::create([
        'amount'      => (int) round($amount * 100),
        'currency'    => strtolower($dto->currency),
        'source'      => $dto->gatewayToken ?? 'tok_visa',
        'description' => $dto->description ?? "Project #{$dto->projectId}",
        'metadata'    => [
          'customer_name' => $dto->userName,
          'project_id'    => $dto->projectId,
        ],
      ]);

      return [
        'success'        => $charge->status === 'succeeded',
        'transaction_id' => $charge->id ?? '',
        'amount'         => $amount,
        'status'         => $charge->status,
        'raw'            => $charge->toArray(),
      ];
    } catch (\Exception $e) {
      Log::error('Stripe chargeAmount exception', ['error' => $e->getMessage()]);
      return [
        'success'        => false,
        'transaction_id' => '',
        'amount'         => $amount,
        'status'         => 'failed',
        'raw'            => ['error' => $e->getMessage()],
      ];
    }
  }

  // ─── Refund ───────────────────────────────────────────────────────────────

  public function refund(RefundDTO $dto): array
  {
    try {
      $refund = Refund::create([
        'charge'   => $dto->transactionId,
        'amount'   => (int) round($dto->amount * 100),
        'reason'   => $this->mapRefundReason($dto->reason),
        'metadata' => $dto->metadata ?? [],
      ]);

      return [
        'success'   => $refund->status === 'succeeded',
        'refund_id' => $refund->id,
        'status'    => $refund->status,
        'raw'       => $refund->toArray(),
      ];
    } catch (\Exception $e) {
      Log::error('Stripe refund exception', ['error' => $e->getMessage()]);
      return ['success' => false, 'refund_id' => '', 'status' => 'failed', 'raw' => ['error' => $e->getMessage()]];
    }
  }

  // ─── Status ───────────────────────────────────────────────────────────────

  public function status(string $transactionId): array
  {
    try {
      $charge = Charge::retrieve($transactionId);
      return ['status' => $charge->status, 'raw' => $charge->toArray()];
    } catch (\Exception $e) {
      return ['status' => 'unknown', 'raw' => ['error' => $e->getMessage()]];
    }
  }

  // ─── Balance ──────────────────────────────────────────────────────────────

  public function getBalance(): array
  {
    $balance = Balance::retrieve();
    return ['success' => true, 'balance' => $balance->available[0]->amount / 100];
  }

  private function mapRefundReason(?string $reason): string
  {
    return match (true) {
      str_contains((string) $reason, 'duplicate')  => 'duplicate',
      str_contains((string) $reason, 'fraudulent') => 'fraudulent',
      default                                       => 'requested_by_customer',
    };
  }
}
