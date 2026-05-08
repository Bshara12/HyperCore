<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNotificationTemplateRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],
            'locale' => ['nullable', 'string', 'max:20'],
            'version' => ['nullable', 'integer', 'min:1'],
            'subject_template' => ['nullable', 'string', 'max:255'],
            'body_template' => ['required', 'string'],
            'variables_schema' => ['nullable', 'array'],
            'defaults' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
