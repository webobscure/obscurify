<?php

namespace App\Domain\Builder\Http\Controllers;

use App\Domain\Builder\Application\FindOrCreatePageLayout;
use App\Domain\Builder\Application\RedoBuilderLayout;
use App\Domain\Builder\Application\SaveBuilderLayout;
use App\Domain\Builder\Application\UndoBuilderLayout;
use App\Domain\Builder\Http\Requests\UpdateBuilderLayoutRequest;
use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Cms\Application\DuplicatePage;
use App\Domain\Cms\Application\PublishPageVersion;
use App\Domain\Cms\Application\RollbackPage;
use App\Domain\Cms\Http\Resources\PageResource;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\PageVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The visual Builder's page endpoint (spec section 13). Reuses Cms's
 * own draft/publish/rollback/duplicate actions untouched — this
 * controller's only original logic is resolving/bootstrapping a
 * PageLayout (FindOrCreatePageLayout) and persisting drag-and-drop
 * edits through it (SaveBuilderLayout); publish/rollback/duplicate are
 * thin passthroughs to `App\Domain\Cms\Application`, not
 * reimplementations, per the milestone's "reuse the CMS architecture
 * already implemented" instruction.
 */
final class BuilderPageController extends Controller
{
    public function __construct(private readonly FindOrCreatePageLayout $findOrCreatePageLayout) {}

    public function show(Page $page): JsonResponse
    {
        $draft = $this->draftVersion($page);
        $layout = $this->findOrCreatePageLayout->handle($draft);

        return response()->json(['data' => $this->present($page, $draft, $layout)]);
    }

    public function update(UpdateBuilderLayoutRequest $request, Page $page, SaveBuilderLayout $action): JsonResponse
    {
        $draft = $this->draftVersion($page);
        $layout = $this->findOrCreatePageLayout->handle($draft);

        $action->handle($layout, $request->validated('sections'));

        return response()->json(['data' => $this->present($page, $draft->fresh(), $layout)]);
    }

    public function publish(Page $page, PublishPageVersion $action): JsonResponse
    {
        $draft = $this->draftVersion($page);
        $action->handle($draft);

        return (new PageResource($page->fresh(['versions', 'activePointer'])))->response();
    }

    public function duplicate(Page $page, DuplicatePage $action): JsonResponse
    {
        $copy = $action->handle($page);

        return (new PageResource($copy->load(['versions', 'activePointer'])))->response()->setStatusCode(201);
    }

    /**
     * Takes a page id (spec section 13's literal route shape) plus the
     * target published version in the body, unlike Cms's own
     * `POST /page-versions/{pageVersion}/rollback` (which the version id
     * alone already scopes) — resolved and validated to actually belong
     * to this page before delegating to the same RollbackPage action.
     */
    public function rollback(Request $request, Page $page, RollbackPage $action): JsonResponse
    {
        $data = $request->validate(['page_version_id' => ['required', 'string']]);

        $version = $page->versions()->where('id', $data['page_version_id'])->firstOrFail();
        $action->handle($version);

        return (new PageResource($page->fresh(['versions', 'activePointer'])))->response();
    }

    public function undo(Page $page, UndoBuilderLayout $action): JsonResponse
    {
        $draft = $this->draftVersion($page);
        $layout = $this->findOrCreatePageLayout->handle($draft);
        $action->handle($layout);

        return response()->json(['data' => $this->present($page, $draft->fresh(), $layout)]);
    }

    public function redo(Page $page, RedoBuilderLayout $action): JsonResponse
    {
        $draft = $this->draftVersion($page);
        $layout = $this->findOrCreatePageLayout->handle($draft);
        $action->handle($layout);

        return response()->json(['data' => $this->present($page, $draft->fresh(), $layout)]);
    }

    private function draftVersion(Page $page): PageVersion
    {
        return $page->versions()->where('status', 'draft')->latest('version_number')->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Page $page, PageVersion $draft, PageLayout $layout): array
    {
        $history = BuilderHistory::query()->where('page_layout_id', $layout->id)->first();
        $currentSequence = $history?->currentRevision->sequence ?? 0;

        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'status' => $page->status->value,
            'draft_version_id' => $draft->id,
            'sections' => $draft->sections,
            'can_undo' => BuilderRevision::query()->where('page_layout_id', $layout->id)->where('sequence', '<', $currentSequence)->exists(),
            'can_redo' => BuilderRevision::query()->where('page_layout_id', $layout->id)->where('sequence', '>', $currentSequence)->exists(),
        ];
    }
}
