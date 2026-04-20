<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Read\Requests\EntryVersionsRequest;
use App\Domains\CMS\Read\Services\EntryVersionReadService;
use App\Support\CurrentProject;

class EntryVersionController extends Controller
{
    public function __construct(
        private EntryVersionReadService $service,
    ) {}

    public function index(EntryVersionsRequest $request, string $entrySlug)
    {
        $dto = $this->service->listForEntrySlug(
            projectId: CurrentProject::id(),
            entrySlug: $entrySlug,
            page: $request->page(),
            perPage: $request->perPage(),
            withSnapshot: $request->withSnapshot(),
        );

        return response()->json($dto);
    }
}
