<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Requests\Plan\CreatePlanRequest;
use App\Domains\Subscription\Services\PlanService;

class PlanController extends Controller
{
    public function __construct(
        private PlanService $service
    ) {}

    public function store(
        CreatePlanRequest $request
    ) {

        $dto = CreatePlanDTO::fromRequest($request);

        $plan = $this->service->create($dto);

        return response()->json([
            'data' => $plan,
        ], 201);
    }
}
