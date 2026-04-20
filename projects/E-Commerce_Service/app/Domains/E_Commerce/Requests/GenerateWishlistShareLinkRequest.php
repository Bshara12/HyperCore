<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateWishlistShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
