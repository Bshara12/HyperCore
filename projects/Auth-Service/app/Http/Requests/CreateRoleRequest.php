<?php

namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'project_id' => 'nullable|integer', // جدول المشاريع بخدمة CMS، لا يوجد exists محلي ممكن
        ];
    }
}
