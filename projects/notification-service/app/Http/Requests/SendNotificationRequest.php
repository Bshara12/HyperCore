<?php

namespace App\Http\Requests;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendNotificationRequest extends FormRequest
{
    // الـ Authorization تعالجه الـ Middleware، فالطلب هنا مسموح به دائماً
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // معرّف المستخدم المراد إرسال الإشعار له
            'user_id'    => ['required', 'string'],

            // الإيميل: مطلوب فقط إذا كانت القناة هي email
            'user_email' => [
                'nullable',
                'email',
                Rule::requiredIf(fn () => $this->input('channel') === NotificationChannel::EMAIL->value),
            ],

            // القناة: يجب أن تكون إحدى القيم المعرَّفة في الـ Enum
            'channel' => ['required', 'string', Rule::enum(NotificationChannel::class)],

            'title' => ['required', 'string', 'max:255'],
            'body'  => ['required', 'string'],

            // بيانات إضافية اختيارية (لا حد لمحتواها طالما هي JSON)
            'data' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required'    => 'The target user ID is required.',
            'user_email.required' => 'Email address is required when channel is email.',
            'user_email.email'    => 'Please provide a valid email address.',
            'channel.required'    => 'Notification channel is required.',
            'channel.enum'        => 'Channel must be one of: ' . implode(', ', NotificationChannel::values()),
            'title.required'      => 'Notification title is required.',
            'body.required'       => 'Notification body is required.',
        ];
    }
}
