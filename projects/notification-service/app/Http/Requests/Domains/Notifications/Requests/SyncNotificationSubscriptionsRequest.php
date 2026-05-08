<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncNotificationSubscriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscriptions' => ['required', 'array', 'min:1'],
            'subscriptions.*.subscriber_type' => ['required', 'string', Rule::in(['user', 'team', 'project', 'admin'])],
            'subscriptions.*.subscriber_id' => ['required'],
            'subscriptions.*.topic_key' => ['required', 'string', 'max:255'],
            'subscriptions.*.channel_mask' => ['nullable', 'array'],
            'subscriptions.*.channel_mask.*' => ['string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'subscriptions.*.filters' => ['nullable', 'array'],
            'subscriptions.*.active' => ['nullable', 'boolean'],
        ];
    }
}
