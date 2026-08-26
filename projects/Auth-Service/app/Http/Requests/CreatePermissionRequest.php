<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePermissionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'permession' => 'required|string|max:255',
            'project_id' => 'nullable|integer',
        ];
    }
}
