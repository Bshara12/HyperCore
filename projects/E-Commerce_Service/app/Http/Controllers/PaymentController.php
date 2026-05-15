<?php

namespace App\Http\Controllers;

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Domains\Payment\Requests\ProcessPaymentRequest;
use App\Domains\Payment\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $service,
    ) {}

    // ─── POST /payments ───────────────────────────────────────────────────────
    public function charge(ProcessPaymentRequest $request)
    {
        try {
            $dto = PaymentDTO::fromRequest($request);
            $result = $this->service->processPayment($dto);

            $expectedStatus = $dto->paymentType === 'installment' ? 'pending' : 'paid';
            if ($result['status'] !== $expectedStatus) {
                return response()->json([
                    'message' => 'Payment failed. Please try again.',
                    'status' => $result['status'],
                ], 422);
            }

            return response()->json(
                $result,
                201
            );
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

            if ($result['message'] != 'Installment paid successfully.') {
                return response()->json([
                    'message' => 'Installment payment failed.',
                    'status' => $result['status'] ?? 'failed',
                ], 422);
            }

            return response()->json(
                $result,
                201
            );
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
