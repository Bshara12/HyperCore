<?php

namespace App\Domains\Platform\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAllProjectsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route carries the `hypercore` middleware; nothing here is
        // per-record, so there is no second authorisation decision to make.
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:50'],
            'owner_id' => ['nullable', 'integer'],
            'include_trashed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
