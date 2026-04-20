<?php

namespace App\Traits;

use Illuminate\Support\Facades\App;

trait BelongsToProject
{
  protected static function booted()
  {
    static::creating(function ($model) {

      // إذا الموديل نفسه Project → تجاهل
      if ($model instanceof \App\Models\Project) {
        return;
      }

      // إذا ما في currentProject → تجاهل
      if (! App::bound('currentProject')) {
        return;
      }

      if (empty($model->project_id)) {
        $model->project_id = App::make('currentProject')->id;
      }
    });
  }
}
