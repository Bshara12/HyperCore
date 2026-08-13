<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RemovePermissionFromRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'permession_id' => 'required|integer|exists:permessions,id',
            'role_id' => 'required|integer|exists:roles,id',
        ];
    }
}
