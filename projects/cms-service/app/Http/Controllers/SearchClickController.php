<?php

namespace App\Http\Controllers;

use App\Domains\Search\Actions\LogClickAction;
use App\Domains\Search\DTOs\LogClickDTO;
use App\Domains\Search\Repositories\Eloquent\EloquentSearchRepository;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchClickController extends Controller
{
    public function __construct(
        private LogClickAction $logClickAction,
        private EloquentSearchRepository $searchRepository,  // ← إضافة
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'entry_id' => ['required', 'integer'],
            'data_type_id' => ['required', 'integer'],
            'result_position' => ['required', 'integer', 'min:1'],
            'search_log_id' => ['nullable', 'integer'],
            'language' => ['sometimes', 'string', 'size:2'],
        ]);

        $projectId = CurrentProject::id();
        $user = $request->attributes->get('auth_user');
        $language = $request->input('language', 'en');

        // ─── 1. تسجيل في user_click_logs (الموجود) ───────────────────
        $this->logClickAction->execute(new LogClickDTO(
            projectId: $projectId,
            entryId: $request->integer('entry_id'),
            dataTypeId: $request->integer('data_type_id'),
            resultPosition: $request->integer('result_position'),
            userId: $user['id'] ?? null,
            searchLogId: $request->integer('search_log_id') ?: null,
            sessionId: $user['sessions'][0]['id'] ?? null,
        ));

        // ─── 2. تحديث click_count في search_indices ──────────────────
        $this->searchRepository->incrementClickCount(
            entryId: $request->integer('entry_id'),
            language: $language,
        );

        return response()->json(['message' => 'Click logged.']);
    }
}
