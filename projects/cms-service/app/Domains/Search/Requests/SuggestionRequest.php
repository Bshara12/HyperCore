<?php

namespace App\Domains\Search\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SuggestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'    => ['required', 'string', 'min:1', 'max:100'],
            'lang' => ['sometimes', 'string', 'size:2'],
            'limit'=> ['sometimes', 'integer', 'min:1', 'max:15'],
        ];
    }

    public function prefix(): string
    {
        return trim($this->input('q', ''));
    }

    public function language(): string
    {
        return $this->input('lang', 'en');
    }

    public function limit(): int
    {
        return (int) $this->input('limit', 8);
    }
}