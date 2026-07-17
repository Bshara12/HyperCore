<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendBulkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // مصفوفة من الإشعارات (الحد الأقصى 100 إشعار في كل طلب)
            'notifications'              => ['required', 'array', 'min:1', 'max:100'],
            'notifications.*.user_id'    => ['required', 'string'],
            'notifications.*.user_email' => ['nullable', 'email'],
            'notifications.*.channel'    => ['required', 'string', Rule::enum(NotificationChannel::class)],
            'notifications.*.title'      => ['required', 'string', 'max:255'],
            'notifications.*.body'       => ['required', 'string'],
            'notifications.*.data'       => ['nullable', 'array'],
        ];
    }
}
