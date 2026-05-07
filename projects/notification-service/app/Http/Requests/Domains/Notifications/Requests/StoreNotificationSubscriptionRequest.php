<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'project_id' => $this->header('X-Project-Id') ?? $this->input('project_id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'string'],
            'topic_key' => ['required', 'string', 'max:255'],
            'channel_mask' => ['nullable', 'array'],
            'channel_mask.*' => ['string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'filters' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
