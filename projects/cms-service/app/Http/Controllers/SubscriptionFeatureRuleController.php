<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\Services\SubscriptionFeatureRuleService;
use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Domains\Subscription\Requests\Rule\CreateFeatureRuleRequest;

class SubscriptionFeatureRuleController
extends Controller
{
    public function __construct(
        private SubscriptionFeatureRuleService $service
    ) {}

    public function store(
        CreateFeatureRuleRequest $request
    ) {

        $dto = CreateFeatureRuleDTO
            ::fromRequest($request);

        $rule = $this->service
            ->create($dto);

        return response()->json([
            'data' => $rule
        ], 201);
    }
}