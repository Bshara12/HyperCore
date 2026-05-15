<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWishlistItemRequest extends FormRequest
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
            'added_from_cart' => [
                'nullable',
                'boolean',
            ],
            'notify_on_price_drop' => [
                'nullable',
                'boolean',
            ],
            'notify_on_back_in_stock' => [
                'nullable',
                'boolean',
            ],
        ];
    }
}
