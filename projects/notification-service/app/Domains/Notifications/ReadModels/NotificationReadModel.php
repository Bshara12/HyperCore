<?php

namespace App\Domains\Notifications\ReadModels;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use JsonSerializable;

final class NotificationReadModel implements JsonSerializable
{
  public function __construct(
    public readonly string $id,
    public readonly ?string $projectId,
    public readonly string $recipientType,
    public readonly string|int $recipientId,
    public readonly string $title,
    public readonly ?string $body,
    public readonly string $status,
    public readonly int $priority,
    public readonly ?string $topicKey,
    public readonly array $data,
    public readonly array $metadata,
    public readonly ?array $source,
    public readonly ?string $readAt,
    public readonly ?string $createdAt,
    public readonly ?string $updatedAt,
  ) {}

  public static function fromNotification(Notification $notification): self
  {
    // $status = $notification->status instanceof NotificationStatus
    //     ? $notification->status->value
    //     : (string) $notification->status;
    $status = (string) $notification->status->value;

    return new self(
      id: (string) $notification->id,
      projectId: $notification->project_id ? (string) $notification->project_id : null,
      recipientType: (string) $notification->recipient_type,
      recipientId: $notification->recipient_id,
      title: (string) $notification->title,
      body: $notification->body,
      status: $status,
      priority: (int) $notification->priority,
      topicKey: $notification->topic_key,
      data: $notification->data ?? [],
      metadata: $notification->metadata ?? [],
      source: [
        'type' => $notification->source_type,
        'service' => $notification->source_service,
        'id' => $notification->source_id,
      ],
      readAt: $notification->read_at?->toISOString(),
      createdAt: $notification->created_at?->toISOString(),
      updatedAt: $notification->updated_at?->toISOString(),
    );
  }

  public function toArray(): array
  {
    return [
      'id' => $this->id,
      'project_id' => $this->projectId,
      'recipient_type' => $this->recipientType,
      'recipient_id' => $this->recipientId,
      'title' => $this->title,
      'body' => $this->body,
      'status' => $this->status,
      'priority' => $this->priority,
      'topic_key' => $this->topicKey,
      'data' => $this->data,
      'metadata' => $this->metadata,
      'source' => $this->source,
      'read_at' => $this->readAt,
      'created_at' => $this->createdAt,
      'updated_at' => $this->updatedAt,
    ];
  }

  public function jsonSerialize(): array
  {
    return $this->toArray();
  }
}
