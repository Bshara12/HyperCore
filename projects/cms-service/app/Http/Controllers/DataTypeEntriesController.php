<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Read\Services\EntryReadService;
use App\Models\Project;
use Illuminate\Http\Request;

class DataTypeEntriesController extends Controller
{
    public function __construct(
        private EntryReadService $service
    ) {}

    /**
     * The {project} route parameter is substituted with a Project instance by
     * route binding, so accept both shapes instead of type-erroring on it.
     */
    public function index(Request $request, Project|int|string $project, string $slug)
    {
        $projectId = $project instanceof Project ? (int) $project->id : (int) $project;

        return response()->json(
            $this->service->getEntriesByDataTypeSlug(
                projectId: $projectId,
                slug: $slug,
                filters: [
                    'lang' => $request->query('lang'),
                    'page' => $request->query('page', 1),
                    'per_page' => $request->query('per_page', 20),
                    'search' => $request->query('search'),
                    'field_id' => $request->query('field_id'),
                    'date_from' => $request->query('date_from'),
                    'date_to' => $request->query('date_to'),
                ]
            )
        );
    }
}
