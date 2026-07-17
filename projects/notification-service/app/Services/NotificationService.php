<?php

namespace App\Services;

use App\DTOs\NotificationPayloadDTO;
use App\Enums\NotificationStatus;
use App\Exceptions\UserNotFoundException;
use App\Jobs\ProcessNotificationJob;
use App\Models\Notification;
use App\Services\Auth\AuthApiClient;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function __construct(
        private readonly AuthApiClient $authClient
    ) {}

    /**
     * يُستدعى من HTTP Controllers (خدمات E-commerce, Booking...)
     * يتحقق من الـ Service Token عبر Middleware
     */
    public function create(array $data, array $service): Notification
    {
        $user = $this->authClient->getUserById($data['user_id']);

        if (empty($user)) {
            throw new UserNotFoundException($data['user_id']);
        }

        return $this->store(
            userId:        $data['user_id'],
            userEmail:     $data['user_email'] ?? $user['email'] ?? null,
            channel:       $data['channel'],
            title:         $data['title'],
            body:          $data['body'],
            data:          $data['data'] ?? null,
            sourceService: $service['name'] ?? 'unknown',
        );
    }

    /**
     * يُستدعى من RabbitMQ Consumer مباشرة
     * لا يحتاج HTTP Middleware ولا Service Token لأن البيانات قادمة من RabbitMQ
     * لكنه يتحقق من وجود المستخدم في Auth Service لضمان سلامة البيانات
     */
    public function createFromConsumer(
        NotificationPayloadDTO $dto,
        array $service,
        bool $skipUserVerification = false // ← Auth events تضع true
    ): Notification {

        if (!$skipUserVerification) {
            $user = $this->authClient->getUserById($dto->userId);

            if (empty($user)) {
                throw new UserNotFoundException($dto->userId);
            }

            $resolvedEmail = $dto->userEmail ?? $user['email'] ?? null;
        } else {
            // نثق بالبيانات القادمة من Auth Service مباشرة
            $resolvedEmail = $dto->userEmail;
        }

        return $this->store(
            userId:        $dto->userId,
            userEmail:     $resolvedEmail,
            channel:       $dto->channel,
            title:         $dto->title,
            body:          $dto->body,
            data:          $dto->data ?: null,
            sourceService: $service['name'] ?? 'unknown',
        );
    }

    /**
     * المنطق المشترك بين create() وcreateFromConsumer()
     * مركزنا إنشاء الـ Notification وإرساله للـ Queue في مكان واحد
     */
    private function store(
        string  $userId,
        ?string $userEmail,
        string  $channel,
        string  $title,
        string  $body,
        ?array  $data,
        string  $sourceService,
    ): Notification {

        $notification = Notification::create([
            'user_id'        => $userId,
            'user_email'     => $userEmail,
            'channel'        => $channel,
            'title'          => $title,
            'body'           => $body,
            'data'           => $data,
            'source_service' => $sourceService,
            'status'         => NotificationStatus::PENDING,
        ]);

        ProcessNotificationJob::dispatch($notification)
            ->onQueue('notifications');

        Log::info('[NotificationService] Notification queued', [
            'id'             => $notification->id,
            'channel'        => $notification->channel->value,
            'user_id'        => $notification->user_id,
            'source_service' => $notification->source_service,
        ]);

        return $notification;
    }

    /**
     * Bulk للـ HTTP Controllers
     */
    public function createBulk(array $notifications, array $service): array
    {
        return array_map(
            fn (array $data) => $this->create($data, $service),
            $notifications
        );
    }
}
