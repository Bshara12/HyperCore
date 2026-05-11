<?php

namespace App\Http\Controllers;

use App\Domains\E_Commerce\Actions\Order\CheckoutAction;
use App\Domains\E_Commerce\DTOs\Order\CheckoutDTO;
use App\Domains\E_Commerce\Requests\CheckoutRequest;

class CheckoutController extends Controller
{
    public function __construct(
        protected CheckoutAction $checkout
    ) {}

    public function store(CheckoutRequest $request)
    {
        $dto = CheckoutDTO::fromRequest($request);

        $order = $this->checkout->execute($dto);

        return response()->json([
            'message' => 'Checkout completed successfully',
            'data' => $order,
        ]);
    }
}
