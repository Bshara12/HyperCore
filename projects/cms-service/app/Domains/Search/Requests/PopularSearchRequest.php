<?php

namespace App\Domains\Search\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PopularSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lang'   => ['sometimes', 'string', 'size:2'],
            'window' => ['sometimes', 'string', 'in:24h,7d,30d,all'],
            'type'   => ['sometimes', 'string', 'in:trending,popular,both'],
            'limit'  => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function language(): string { return $this->input('lang', 'en'); }
    public function window(): string   { return $this->input('window', '7d'); }
    public function type(): string     { return $this->input('type', 'both'); }
    public function limit(): int       { return (int) $this->input('limit', 10); }
}