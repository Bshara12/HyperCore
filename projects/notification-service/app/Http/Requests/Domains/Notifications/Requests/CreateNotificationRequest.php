<?php

namespace App\Http\Requests\Domains\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateNotificationRequest extends FormRequest
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

            'recipient' => ['required', 'array'],
            'recipient.type' => ['required', 'string', Rule::in(['user', 'team', 'project', 'admin'])],
            'recipient.id' => ['required'],

            'source' => ['required', 'array'],
            'source.service' => ['required', 'string', 'max:100'],
            'source.type' => ['required', 'string', 'max:100'],
            'source.id' => ['nullable', 'string', 'max:255'],

            'template_key' => ['nullable', 'string', 'max:255'],

            'title' => ['required_without:template_key', 'nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],

            'data' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],

            'priority' => ['nullable', 'integer', 'min:0', 'max:255'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],

            'channel' => ['required', 'array', 'min:1'],
            'channel.*' => ['string', Rule::in(['database', 'broadcast', 'email', 'webhook'])],

            'dedupe_key' => ['nullable', 'string', 'max:255'],
            'topic_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
