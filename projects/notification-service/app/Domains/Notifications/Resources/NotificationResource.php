<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'recipient_type' => $this->recipient_type,
            'recipient_id' => $this->recipient_id,

            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'priority' => $this->priority,

            'topic_key' => $this->topic_key,
            'data' => $this->data,
            'metadata' => $this->metadata,

            'source' => [
                'type' => $this->source_type,
                'service' => $this->source_service,
                'id' => $this->source_id,
            ],

            'read_at' => optional($this->read_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
