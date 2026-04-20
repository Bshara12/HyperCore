<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderWishlistItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.id' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Items array is required.',
            'items.array' => 'Items must be an array.',
            'items.*.id.required' => 'Each item must contain an id.',
            'items.*.sort_order.required' => 'Each item must contain sort_order.',
        ];
    }
}
