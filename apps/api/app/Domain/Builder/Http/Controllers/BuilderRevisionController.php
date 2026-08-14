<?php

namespace App\Domain\Builder\Http\Controllers;

use App\Domain\Builder\Application\FindOrCreatePageLayout;
use App\Domain\Builder\Application\RestoreBuilderRevision;
use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Cms\Models\Page;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * The revision timeline (spec sections 9/15) — every autosave/manual
 * save appends one BuilderRevision (see SaveBuilderLayout); this lists
 * them and lets the merchant jump straight to any one, not just the
 * adjacent step undo/redo moves.
 */
final class BuilderRevisionController extends Controller
{
    public function __construct(private readonly FindOrCreatePageLayout $findOrCreatePageLayout) {}

    public function index(Page $page): JsonResponse
    {
        $draft = $page->versions()->where('status', 'draft')->latest('version_number')->firstOrFail();
        $layout = $this->findOrCreatePageLayout->handle($draft);
        $currentId = BuilderHistory::query()->where('page_layout_id', $layout->id)->value('current_revision_id');

        $revisions = BuilderRevision::query()->where('page_layout_id', $layout->id)->orderByDesc('sequence')->get();

        return response()->json(['data' => $revisions->map(fn (BuilderRevision $revision) => [
            'id' => $revision->id,
            'sequence' => $revision->sequence,
            'is_current' => $revision->id === $currentId,
            'created_at' => $revision->created_at,
        ])->values()]);
    }

    public function restore(Page $page, BuilderRevision $revision, RestoreBuilderRevision $action): JsonResponse
    {
        $draft = $page->versions()->where('status', 'draft')->latest('version_number')->firstOrFail();
        $layout = $this->findOrCreatePageLayout->handle($draft);

        $action->handle($layout, $revision);

        return response()->json(['data' => ['draft_version_id' => $draft->id, 'sections' => $draft->fresh()->sections]]);
    }
}
