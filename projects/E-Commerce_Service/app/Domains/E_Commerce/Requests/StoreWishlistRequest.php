<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWishlistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'visibility' => [
                'nullable',
                Rule::in(['private', 'public']),
            ],

            'is_default' => [
                'nullable',
                'boolean',
            ],

            'is_shareable' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Wishlist name is required.',
            'name.max' => 'Wishlist name may not be greater than 255 characters.',
            'visibility.in' => 'Visibility must be either private or public.',
        ];
    }
}
