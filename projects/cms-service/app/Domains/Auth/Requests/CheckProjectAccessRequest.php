<?php

namespace App\Domains\Auth\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckProjectAccessRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer'],
            // 'project_key' => ['required', 'string'],
        ];
    }
}