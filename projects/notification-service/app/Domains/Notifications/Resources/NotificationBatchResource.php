<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\NotificationBatch;

/**
 * @property NotificationBatch $resource
 */
class NotificationBatchResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    /** @var NotificationBatch $batch */
    $batch = $this->resource;
    return [
      'id' =>  $batch->id,
      'project_id' =>  $batch->project_id,
      'created_by_type' =>  $batch->created_by_type,
      'created_by_id' =>  $batch->created_by_id,
      'source_service' =>  $batch->source_service,
      'source_event_type' =>  $batch->source_event_type,
      'audience_type' =>  $batch->audience_type,
      'audience_query' =>  $batch->audience_query,
      'status' =>  $batch->status,
      'dedupe_key' =>  $batch->dedupe_key,
      'total_targets' =>  $batch->total_targets,
      'processed_targets' =>  $batch->processed_targets,
      'scheduled_at' => optional($batch->scheduled_at)?->toISOString(),
      'started_at' => optional($batch->started_at)?->toISOString(),
      'completed_at' => optional($batch->completed_at)?->toISOString(),
      'created_at' => optional($batch->created_at)?->toISOString(),
      'updated_at' => optional($batch->updated_at)?->toISOString(),
    ];
  }
}
