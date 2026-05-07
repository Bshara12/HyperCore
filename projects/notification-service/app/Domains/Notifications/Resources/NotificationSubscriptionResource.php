<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'subscriber_type' => $this->subscriber_type,
            'subscriber_id' => $this->subscriber_id,
            'topic_key' => $this->topic_key,
            'channel_mask' => $this->channel_mask,
            'filters' => $this->filters,
            'active' => $this->active,
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
