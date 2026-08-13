<?php

namespace App\Domains\Subscription\Requests\Subscription;

use App\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            // ملاحظة: ما بنتحقق من user_id هون عن قصد —
            // الـ userId دايمًا ماخوذ من auth_user وليس من query string (أمان).

            'project_id' => [
                'nullable',
                'integer',
            ],

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
        ];
    }
}