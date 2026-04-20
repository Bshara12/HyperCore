<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Read\Services\EntryReadService;
use Illuminate\Http\Request;

class DataTypeEntriesController extends Controller
{
    public function __construct(
        private EntryReadService $service
    ) {}

    public function index(Request $request, int $projectId, string $slug)
    {
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
