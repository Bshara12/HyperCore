<?php

namespace App\Domains\Payment\Services;

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Services\CMS\CMSApiClient;

class PaymentService
{
    public function __construct(
        private readonly CMSApiClient $cms
    ) {}

    // ─── Process Payment ──────────────────────────────────────────────────────

    public function processPayment(PaymentDTO $dto): array
    {
        return $this->cms->processPayment($dto);
    }

    public function payInstallment(PayInstallmentDTO $dto): array
    {
        return $this->cms->payInstallment($dto);
    }

    // ─── Process Refund ───────────────────────────────────────────────────────

    public function processRefund(RefundDTO $dto)
    {
        // return $this->refundAction->execute($dto);
    }
}
