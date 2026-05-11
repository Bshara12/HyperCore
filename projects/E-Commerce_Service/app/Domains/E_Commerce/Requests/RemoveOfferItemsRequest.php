<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveOfferItemsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'items' => 'required|array',
            'items.*' => [
                'required',
                'integer',
                'distinct',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'The items field is required.',
            'items.array' => 'The items field must be an array.',

            'items.*.required' => 'Each item must have an item_id.',
            'items.*.integer' => 'The item_id must be a valid integer.',
            'items.*.distinct' => 'Duplicate item_id values are not allowed.',
        ];
    }
}
