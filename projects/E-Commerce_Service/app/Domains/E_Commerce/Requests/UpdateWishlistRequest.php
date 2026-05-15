<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWishlistRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'visibility' => ['sometimes', 'in:private,public'],
            'is_default' => ['sometimes', 'boolean'],
            'is_shareable' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'Wishlist name may not be greater than 255 characters.',
            'visibility.in' => 'Visibility must be either private or public.',
        ];
    }
}
