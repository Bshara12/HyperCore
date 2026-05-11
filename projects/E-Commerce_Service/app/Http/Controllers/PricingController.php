<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\Requests\CalculatePricingRequest;
use App\Domains\E_Commerce\Services\PricingService;

class PricingController extends Controller
{
    public function __construct(private PricingService $service) {}

    // public function calculate(Request $request)
    // {
    //     return $this->service->calculate(
    //         $request->entry_ids,
    //         $request->code
    //     );
    // }
    public function calculate(CalculatePricingRequest $request)
    {
        return $this->service->calculate(
            $request->entry_ids,
        );
    }
}
