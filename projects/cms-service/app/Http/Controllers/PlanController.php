<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Domains\Subscription\Services\PlanService;
use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Requests\Plan\CreatePlanRequest;

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
            'data' => $plan
        ], 201);
    }
}