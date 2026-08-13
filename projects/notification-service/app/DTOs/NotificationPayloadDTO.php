<?php

namespace App\DTOs;

/**
 * Data Transfer Object لنقل بيانات الإشعار داخل Notification Service
 *
 * يُستخدم في حالتين:
 * 1. ConsumeNotificationEventsCommand → يبني الـ DTO من بيانات RabbitMQ
 * 2. NotificationService::createFromConsumer() → يستقبل الـ DTO ويعالجه
 *
 * الفائدة: بدلاً من تمرير arrays خام بين الكلاسات، نضمن أن البيانات
 * منظمة ومكتملة قبل وصولها لـ NotificationService
 */
readonly class NotificationPayloadDTO
{
    public function __construct(
        public string  $userId,
        public string  $channel,    // email | in_app | real_time
        public string  $title,
        public string  $body,
        public ?string $userEmail = null,
        public array   $data      = [],
    ) {}
}
