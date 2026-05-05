<?php

namespace App\Domains\Search\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'lang' => ['sometimes', 'string', 'max:10'],
            'data_type_slug' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Search keyword is required.',
            'q.min' => 'Search keyword must be at least 2 characters.',
        ];
    }

    // ─── Helper Methods ──────────────────────────────────────────────

    public function keyword(): string
    {
        return trim($this->input('q'));
    }

    public function language(): string
    {
        return $this->input('lang', 'en');
    }

    public function dataTypeSlug(): ?string
    {
        return $this->input('data_type_slug');
    }

    public function page(): int
    {
        return (int) $this->input('page', 1);
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 15);
    }
}
