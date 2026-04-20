<?php

namespace App\Events;

use App\Models\DataEntry;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EntryChanged
{
  use Dispatchable, InteractsWithSockets, SerializesModels;

  /**
   * Create a new event instance.
   */
  public function __construct(
    public DataEntry $entry,
    public ?int $userId = null
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
