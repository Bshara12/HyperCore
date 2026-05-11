<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function rules()
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ];
    }
}
