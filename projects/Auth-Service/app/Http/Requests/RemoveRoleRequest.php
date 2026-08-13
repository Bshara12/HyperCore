<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RemoveRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return ['user_id' => 'required|integer|exists:users,id'];
    }
}
