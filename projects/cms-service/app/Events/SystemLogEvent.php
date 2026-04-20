<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SystemLogEvent
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  public function __construct(
    public string $module,
    public string $eventType,
    public ?int $userId = null,
    public ?string $entityType = null,
    public ?int $entityId = null,
    public ?array $oldValues = null,
    public ?array $newValues = null
  ) {}

  /**
   * Get the channels the event should broadcast on.
   *
   * @return array<int, \Illuminate\Broadcasting\Channel>
   */
  public function broadcastOn(): array
  {
    return [
      new PrivateChannel('channel-name'),
    ];
  }
}
