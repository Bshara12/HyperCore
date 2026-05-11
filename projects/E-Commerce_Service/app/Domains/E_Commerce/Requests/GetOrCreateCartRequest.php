<?php

namespace App\Domains\E_Commerce\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class GetOrCreateCartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [];
    }
}
