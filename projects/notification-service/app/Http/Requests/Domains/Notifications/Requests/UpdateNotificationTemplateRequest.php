<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'nullable', 'string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'locale' => ['sometimes', 'nullable', 'string', 'max:20'],
            'version' => ['sometimes', 'integer', 'min:1'],
            'subject_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body_template' => ['sometimes', 'string'],
            'variables_schema' => ['sometimes', 'nullable', 'array'],
            'defaults' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
