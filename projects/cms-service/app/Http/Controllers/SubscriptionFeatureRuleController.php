<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\DTOs\Rule\CreateFeatureRuleDTO;
use App\Domains\Subscription\Requests\Rule\CreateFeatureRuleRequest;
use App\Domains\Subscription\Services\SubscriptionFeatureRuleService;
use App\Support\CurrentProject;

class SubscriptionFeatureRuleController extends Controller
{
    public function __construct(
        private SubscriptionFeatureRuleService $service
    ) {}

    public function store(
        CreateFeatureRuleRequest $request
    ) {

        $dto = CreateFeatureRuleDTO::fromRequest($request);

        $rule = $this->service
            ->create($dto);

        return response()->json([
            'data' => $rule,
        ], 201);
    }

    /**
     * Every rule of the current project (resolved from X-Project-Key),
     * for the admin dashboard.
     */
    public function index()
    {

        $rules = $this->service
            ->listForProject(CurrentProject::id());

        return response()->json([
            'data' => $rules,
        ]);
    }
}
