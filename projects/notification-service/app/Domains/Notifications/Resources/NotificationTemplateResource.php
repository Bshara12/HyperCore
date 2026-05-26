<?php

namespace App\Domains\Notifications\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Domains\Notifications\Models\NotificationTemplate;

/**
 * @property NotificationTemplate $resource
 */
class NotificationTemplateResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    /** @var NotificationTemplate $batch */
    $batch = $this->resource;

    return [
      'id' =>  $batch->id,
      'project_id' =>  $batch->project_id,
      'key' =>  $batch->key,
      'channel' =>  $batch->channel,
      'locale' =>  $batch->locale,
      'version' =>  $batch->version,
      'subject_template' =>  $batch->subject_template,
      'body_template' =>  $batch->body_template,
      'variables_schema' =>  $batch->variables_schema,
      'defaults' =>  $batch->defaults,
      'is_active' =>  $batch->is_active,
      'created_at' => optional( $batch->created_at)?->toISOString(),
      'updated_at' => optional( $batch->updated_at)?->toISOString(),
    ];
  }
}
