<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,
            'topic_key' => $this->topic_key,
            'channel' => $this->channel,
            'enabled' => $this->enabled,
            'mute_until' => optional($this->mute_until)?->toISOString(),
            'quiet_hours' => $this->quiet_hours,
            'delivery_mode' => $this->delivery_mode,
            'locale' => $this->locale,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
