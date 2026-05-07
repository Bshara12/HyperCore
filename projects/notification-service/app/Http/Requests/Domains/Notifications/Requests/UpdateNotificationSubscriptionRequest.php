<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'topic_key' => ['sometimes', 'string', 'max:255'],
            'channel_mask' => ['sometimes', 'nullable', 'array'],
            'channel_mask.*' => ['string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'filters' => ['sometimes', 'nullable', 'array'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
