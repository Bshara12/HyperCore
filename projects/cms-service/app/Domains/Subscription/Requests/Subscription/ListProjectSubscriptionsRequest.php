<?php

namespace App\Domains\Subscription\Requests\Subscription;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            /*
            |------------------------------------------------------------------
            | No project_id here on purpose: the project comes from the
            | resolve.project middleware (X-Project-Key / X-Project-Id), so a
            | client cannot ask for another project's subscriber list.
            |------------------------------------------------------------------
            */

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    Subscription::STATUS_PENDING,
                    Subscription::STATUS_ACTIVE,
                    Subscription::STATUS_EXPIRED,
                    Subscription::STATUS_CANCELLED,
                    Subscription::STATUS_GRACE_PERIOD,
                ]),
            ],

            'plan_id' => [
                'nullable',
                'integer',
            ],

            'user_id' => [
                'nullable',
                'integer',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ];
    }
}
