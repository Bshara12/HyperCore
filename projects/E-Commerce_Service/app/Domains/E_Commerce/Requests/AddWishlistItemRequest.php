<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddWishlistItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'variant_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product id is required.',
            'product_id.integer' => 'Product id must be an integer.',
        ];
    }
}
