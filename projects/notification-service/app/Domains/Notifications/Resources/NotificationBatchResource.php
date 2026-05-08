<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'created_by_type' => $this->created_by_type,
            'created_by_id' => $this->created_by_id,
            'source_service' => $this->source_service,
            'source_event_type' => $this->source_event_type,
            'audience_type' => $this->audience_type,
            'audience_query' => $this->audience_query,
            'status' => $this->status,
            'dedupe_key' => $this->dedupe_key,
            'total_targets' => $this->total_targets,
            'processed_targets' => $this->processed_targets,
            'scheduled_at' => optional($this->scheduled_at)?->toISOString(),
            'started_at' => optional($this->started_at)?->toISOString(),
            'completed_at' => optional($this->completed_at)?->toISOString(),
            'created_at' => optional($this->created_at)?->toISOString(),
            'updated_at' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
