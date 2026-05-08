<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'min:1'],
            'preferences.*.topic_key' => ['nullable', 'string', 'max:255'],
            'preferences.*.channel' => ['required', 'string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.mute_until' => ['nullable', 'date'],
            'preferences.*.quiet_hours' => ['nullable', 'array'],
            'preferences.*.delivery_mode' => ['nullable', 'string', Rule::in(['instant', 'digest', 'muted'])],
            'preferences.*.locale' => ['nullable', 'string', 'max:20'],
            'preferences.*.metadata' => ['nullable', 'array'],
        ];
    }
}
