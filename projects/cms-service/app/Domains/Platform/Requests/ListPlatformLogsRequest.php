<?php

namespace App\Domains\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListPlatformLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module' => ['nullable', 'string', 'max:50'],
            'event_type' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Only the keys the Logging Service actually understands get forwarded, so
     * an unexpected query param cannot ride along to another service.
     *
     * @return array<string, mixed>
     */
    public function forwardableFilters(): array
    {
        return $this->only([
            'module',
            'event_type',
            'user_id',
            'from',
            'to',
            'page',
        ]);
    }
}
