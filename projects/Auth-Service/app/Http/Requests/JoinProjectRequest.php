<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // مطلوب دائماً حتى لو كان المستخدم موجوداً مسبقاً (يُتجاهَل في تلك الحالة)
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
