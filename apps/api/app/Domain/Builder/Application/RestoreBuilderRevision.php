<?php

namespace App\Domain\Builder\Application;

use App\Domain\Builder\Models\BuilderHistory;
use App\Domain\Builder\Models\BuilderRevision;
use App\Domain\Builder\Models\PageLayout;
use App\Domain\Builder\Support\ReplaceSectionInstancesFromArray;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Restores a PageLayout to an existing BuilderRevision's snapshot —
 * shared by the revision-timeline "restore" action and by
 * UndoBuilderLayout/RedoBuilderLayout, which just resolve which
 * revision is adjacent and delegate here.
 */
final class RestoreBuilderRevision
{
    public function __construct(private readonly ReplaceSectionInstancesFromArray $replaceSectionInstancesFromArray) {}

    public function handle(PageLayout $layout, BuilderRevision $revision): PageLayout
    {
        if ($revision->page_layout_id !== $layout->id) {
            throw ValidationException::withMessages(['revision' => 'That revision does not belong to this page layout.']);
        }

        $layout->pageVersion->assertEditable();

        DB::transaction(function () use ($layout, $revision) {
            $this->replaceSectionInstancesFromArray->handle($layout, $revision->sections);
            $layout->pageVersion->update(['sections' => $revision->sections]);

            BuilderHistory::query()->updateOrCreate(
                ['page_layout_id' => $layout->id],
                ['current_revision_id' => $revision->id],
            );
        });

        return $layout;
    }
}
