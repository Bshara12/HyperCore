<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookDeliveryCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_id' => ['required', 'string'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['sent', 'delivered', 'failed', 'skipped', 'received', 'processed'])],
            'error_code' => ['nullable', 'string', 'max:255'],
            'error_message' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ];
    }
}
