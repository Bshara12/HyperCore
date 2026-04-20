<?php

namespace App\Domains\Payment\Gateways;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use Braintree\Gateway;
use Braintree\Transaction;
use Braintree\TransactionSearch;
use Illuminate\Support\Facades\Log;

class PaypalGateway implements PaymentGatewayInterface
{
  private Gateway $gateway;

  public function __construct()
  {
    $this->gateway = new Gateway([
      'environment' => config('payment.gateways.paypal.environment'),
      'merchantId'  => config('payment.gateways.paypal.merchant_id'),
      'publicKey'   => config('payment.gateways.paypal.public_key'),
      'privateKey'  => config('payment.gateways.paypal.private_key'),
    ]);
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
      $result = $this->gateway->transaction()->sale([
        'amount'             => number_format($amount, 2, '.', ''),
        'paymentMethodNonce' => 'fake-valid-nonce',
        'customer'           => ['firstName' => $dto->userName],
        'options'            => ['submitForSettlement' => true],
      ]);

      if ($result->success) {
        return [
          'success'        => true,
          'transaction_id' => $result->transaction->id,
          'amount'         => $amount,
          'status'         => $result->transaction->status,
          'raw'            => (array) $result->transaction,
        ];
      }

      Log::warning('Braintree chargeAmount failed', ['message' => $result->message]);
      return [
        'success'        => false,
        'transaction_id' => '',
        'amount'         => $amount,
        'status'         => 'failed',
        'raw'            => ['error' => $result->message],
      ];
    } catch (\Exception $e) {
      Log::error('Braintree chargeAmount exception', ['error' => $e->getMessage()]);
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
      $result = $this->gateway->transaction()->refund(
        $dto->transactionId,
        number_format($dto->amount, 2, '.', ''),
      );

      if ($result->success) {
        return [
          'success'   => true,
          'refund_id' => $result->transaction->id,
          'status'    => $result->transaction->status,
          'raw'       => (array) $result->transaction,
        ];
      }

      return ['success' => false, 'refund_id' => '', 'status' => 'failed', 'raw' => ['error' => $result->message ?? 'Refund failed']];
    } catch (\Exception $e) {
      Log::error('Braintree refund exception', ['error' => $e->getMessage()]);
      return ['success' => false, 'refund_id' => '', 'status' => 'failed', 'raw' => ['error' => $e->getMessage()]];
    }
  }

  // ─── Status ───────────────────────────────────────────────────────────────

  public function status(string $transactionId): array
  {
    try {
      $transaction = $this->gateway->transaction()->find($transactionId);
      return ['status' => $transaction->status, 'raw' => (array) $transaction];
    } catch (\Exception $e) {
      return ['status' => 'unknown', 'raw' => ['error' => $e->getMessage()]];
    }
  }

  // ─── Balance ──────────────────────────────────────────────────────────────

  public function getBalance(): array
  {
    $collection = $this->gateway->transaction()->search([
      TransactionSearch::status()->is(Transaction::SETTLED),
    ]);

    $total = 0.0;
    foreach ($collection as $transaction) {
      $total += floatval($transaction->amount);
    }

    return ['success' => true, 'settled_balance_usd' => number_format($total, 2, '.', '')];
  }
}
