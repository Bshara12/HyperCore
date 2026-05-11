<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveCartItemsRequest extends FormRequest
{
    public function rules()
    {
        return [
            'project_id' => 'required|integer',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
        ];
    }
}
