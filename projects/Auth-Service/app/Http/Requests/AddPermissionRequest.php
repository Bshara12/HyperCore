<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPermissionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['permession' => 'required|string|max:255'];
    }
}
