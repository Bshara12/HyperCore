<?php

namespace App\Traits;

use App\Models\Project;
use Illuminate\Support\Facades\App;

trait BelongsToProject
{
    protected static function booted()
    {
        static::creating(function ($model) {

            // إذا الموديل نفسه Project → تجاهل
            if ($model instanceof Project) {
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
