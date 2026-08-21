<?php

namespace App\Support;

use App\Models\Project;
use Illuminate\Support\Facades\App;

class CurrentProject
{
    public static function get(): Project
    {
        $project = self::resolve();

        if (! $project) {
            abort(500, 'Current project is not resolved');
        }

        return $project;
    }

    public static function id(): int
    {
        return self::get()->id;
    }

    /**
     * Resolve the current project.
     *
     * Falls back to the request headers when the container binding is not
     * available yet: route model binding (SubstituteBindings) runs inside the
     * "api" middleware group, i.e. before the ResolveProject middleware, so
     * resolveRouteBinding() cannot rely on the binding alone.
     */
    public static function resolve(): ?Project
    {
        if (App::bound('currentProject')) {
            return App::make('currentProject');
        }

        $project = self::fromRequest();

        if ($project) {
            App::instance('currentProject', $project);
        }

        return $project;
    }

    public static function fromRequest(): ?Project
    {
        $request = request();

        if (! $request) {
            return null;
        }

        $identifier = $request->header('X-Project-Key')
            ?: $request->header('X-Project-Id');

        if (! $identifier) {
            return null;
        }

        if (is_numeric($identifier)) {
            return Project::find((int) $identifier);
        }

        return Project::where('public_id', $identifier)
            ->orWhere('slug', $identifier)
            ->first();
    }
}
