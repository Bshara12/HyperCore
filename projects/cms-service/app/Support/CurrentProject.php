<?php
namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\App;

class CurrentProject
{
    public static function get(): Project
    {
        if (! App::bound('currentProject')) {
            abort(500, 'Current project is not resolved');
        }

        return App::make('currentProject');
    }

    public static function id(): int
    {
        return self::get()->id;
    }
}
