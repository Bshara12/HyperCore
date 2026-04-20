<?php

namespace App\Domains\CMS\Read\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EntryVersionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'with_snapshot' => ['nullable', 'boolean'],
        ];
    }

    public function page(): int
    {
        return (int) $this->query('page', 1);
    }

    public function perPage(): int
    {
        return (int) $this->query('per_page', 20);
    }

    public function withSnapshot(): bool
    {
        return $this->boolean('with_snapshot', false);
    }
}
